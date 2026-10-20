<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleBitrixNotify\Lib;

use MikoPBX\Common\Handlers\CriticalErrorsHandler;
use MikoPBX\Common\Models\CallDetailRecords;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;
use MikoPBX\Core\Workers\WorkerCdr;

require_once 'Globals.php';

/**
 * Воркер пропущенных вызовов ModuleBitrixNotify.
 *
 * Два режима (по возможностям ядра):
 *  - ядро >= 2026.4: подписка на WorkerCdr::FINALIZE_CDR_TUBE — мгновенно
 *    по завершению связки звонков (linkedid);
 *  - ядро 2024.x - 2026.3: опрос завершённых CDR через SELECT_CDR_TUBE
 *    раз в минуту по пингу SafeScripts (FINALIZE_CDR_TUBE в этих ядрах нет).
 *
 * Правила пропущенного (в обоих режимах):
 *  - disposition NOANSWER, и ни одна ветка linkedid не отвечена (billsec > 0);
 *  - входящий внешний: did не пусто, src_num не из списка внутренних номеров;
 *  - is_app пусто: очереди/IVR/приложения отсечены (на ядрах без is_app проверка пропускается).
 *
 * Не отправляем в Битрикс значения токена, печатаем только итог.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class WorkerBitrixNotifyCdr extends WorkerBase
{
    private const WINDOW_OLD_ROWS_SEC = 300;
    private const SELECTED_COLS_BASE  = ['id', 'start', 'src_num', 'dst_num', 'endtime', 'linkedid', 'did', 'disposition', 'billsec', 'is_app'];

    private bool   $supportsFinalize   = false;
    private bool   $notifyMissed       = false;
    private int    $throttleSec        = 60;
    private array  $internalNumbers    = [];
    private array  $processedLinkedIds = [];
    private int    $processedCount     = 0;
    private int    $lastSeenRowId      = 0;
    private int    $pollCounter        = 0;

    public function start(array $argv): void
    {
        $this->supportsFinalize = class_exists(WorkerCdr::class)
            && defined('MikoPBX\\Core\\Workers\\WorkerCdr::FINALIZE_CDR_TUBE');

        $settings = BitrixNotifySettings::load();
        if (!$settings->initialized) {
            Util::sysLogMsg(__CLASS__, 'Not initialized: ' . $settings->lastError, LOG_WARNING);

            return;
        }
        if (!$settings->notifyMissed) {
            Util::sysLogMsg(__CLASS__, 'notify_missed is off, worker stopped', LOG_WARNING);

            return;
        }
        $this->notifyMissed = true;
        $this->throttleSec  = $settings->throttleSec;
        $this->loadInternalNumbers();

        $client = new BeanstalkClient();
        if ($this->supportsFinalize) {
            $client->subscribe(WorkerCdr::FINALIZE_CDR_TUBE, [$this, 'finalizeCdr']);
            Util::sysLogMsg(__CLASS__, 'Finalize mode: subscribed to FINALIZE_CDR_TUBE', LOG_INFO);
        } else {
            // Poll-режим поведён на пинг-канале SafeScripts: пинг приходит раз в минуту
            Util::sysLogMsg(__CLASS__, 'Polling mode: no FINALIZE_CDR_TUBE in this core', LOG_INFO);
        }
        $client->subscribe($this->makePingTubeName(self::class), [$this, 'onPing']);

        while ($this->needRestart === false) {
            $client->wait(120);
        }
    }

    /**
     * Пинг SafeScripts: отвечаем ядру; в poll-режиме здесь же собираем пропущенные.
     */
    public function onPing(BeanstalkClient $tube): void
    {
        parent::pingCallBack($tube);
        if ($this->supportsFinalize) {
            return;
        }
        $this->pollCounter++;
        if ($this->pollCounter % 5 === 0) {
            // Раз в 5 минут обновляем настройки и список внутренних
            $settings = BitrixNotifySettings::load();
            $this->notifyMissed = $settings->notifyMissed;
            $this->throttleSec  = $settings->throttleSec;
            $this->loadInternalNumbers();
        }
        if (!$this->notifyMissed) {
            return;
        }
        try {
            $this->pollCompletedRows();
        } catch (\Throwable $e) {
            CriticalErrorsHandler::handleExceptionWithSyslog($e);
        }
    }

    /**
     * Обработчик FINALIZE_CDR_TUBE (событие есть только с ядра 2026.4).
     */
    public function finalizeCdr(BeanstalkClient $tube): void
    {
        $data = json_decode($tube->getBody(), true);
        if (!is_array($data) || !isset($data['linkedid'])) {
            $tube->reply(json_encode(false));

            return;
        }
        try {
            $settings = BitrixNotifySettings::load();
            if (!$settings->initialized || !$settings->notifyMissed) {
                $tube->reply(json_encode(true));

                return;
            }
            $this->throttleSec = $settings->throttleSec;
            $this->sendMissedForLinkedId($settings, (string)$data['linkedid']);
            $tube->reply(json_encode(true));
        } catch (\Throwable $e) {
            CriticalErrorsHandler::handleExceptionWithSyslog($e);
            $tube->reply(json_encode(false));
        }
    }

    /**
     * Poll-режим: получить завершённые CDR с id дальше курсора и разобрать.
     */
    private function pollCompletedRows(): void
    {
        $lastId = (int)$this->lastSeenRowId;
        $filter = [
            "work_completed=1 AND id>{$lastId}",
            'columns'     => $this->getCdrColumns(),
            'order'       => 'id',
            'limit'       => 500,
            'miko_tmp_db' => true,
        ];
        $rows = $this->cdrSelect($filter);
        if ($rows === []) {
            return;
        }
        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId > $this->lastSeenRowId) {
                $this->lastSeenRowId = $rowId;
            }
        }

        $byLinkedId = [];
        foreach ($rows as $row) {
            $byLinkedId[(string)$row['linkedid']][] = $row;
        }
        $settings = BitrixNotifySettings::load();
        foreach ($byLinkedId as $linkedId => $linkedRows) {
            $this->analyzeAndNotify($settings, $linkedId, $linkedRows, false);
        }
    }

    /**
     * Finalize-режим: получить строки CDR по linkedid и разобрать.
     */
    private function sendMissedForLinkedId(BitrixNotifySettings $settings, string $linkedId): void
    {
        $linkedId = trim($linkedId);
        if ($linkedId === '') {
            return;
        }
        $filter = [
            'linkedid = {linkedid}',
            'bind'        => ['linkedid' => $linkedId],
            'columns'     => $this->getCdrColumns(),
            'order'       => 'id',
            'miko_tmp_db' => true,
        ];
        $rows = $this->cdrSelect($filter);
        if ($rows === []) {
            return;
        }
        $this->analyzeAndNotify($settings, $linkedId, $rows, true);
    }

    /**
     * Общий разбор связки: пропущенный -> уведомление в Битрикс.
     */
    private function analyzeAndNotify(BitrixNotifySettings $settings, string $linkedId, array $rows, bool $final): void
    {
        $candidate = null;
        $answered  = false;
        foreach ($rows as $row) {
            if ((int)($row['billsec'] ?? 0) > 0) {
                $answered = true;
            }
            if ($candidate === null
                && (string)($row['disposition'] ?? '') === 'NOANSWER'
                && (string)($row['did'] ?? '') !== ''
                && (string)($row['is_app'] ?? '') === ''
                && !$this->isInternalNumber((string)($row['src_num'] ?? ''))) {
                // Входящий внешний пропущенный; очереди/IVR отсечены is_app,
                // внутренние<->внутренние — списком внутренних номеров
                $candidate = $row;
            }
        }
        if ($answered || $candidate === null) {
            return;
        }
        if (isset($this->processedLinkedIds[$linkedId])) {
            return;
        }
        $this->rememberProcessed($linkedId);

        // После перезапуска воркера старые строки не спамим (в poll-режиме)
        if (!$final) {
            $startTs = strtotime((string)($candidate['start'] ?? ''));
            if ($startTs !== false && $startTs < (time() - self::WINDOW_OLD_ROWS_SEC)) {
                return;
            }
        }

        $message = BitrixTemplates::render($settings->templateMissed, $candidate);
        if ($message === '') {
            return;
        }

        $api        = new BitrixRestApi($settings);
        if (!$api->initialized) {
            Util::sysLogMsg(__CLASS__, 'Bitrix api not initialized: ' . $api->getLastError(), LOG_WARNING);

            return;
        }
        $recipients = $settings->resolveRecipients((string)($candidate['dst_num'] ?? ''));
        foreach ($recipients as $uid) {
            if (BitrixNotifyState::isThrottled('missed|' . $uid, $this->throttleSec)) {
                continue;
            }
            $out = $api->sendToUser($uid, $message, substr($linkedId, -20));
            if ($out['ok']) {
                BitrixNotifyState::mark('missed|' . $uid);
            }
        }
    }

    /**
     * SELECT_CDR_TUBE: запрос к воркеру ядра, ответ приходит файлом.
     */
    private function cdrSelect(array $filter): array
    {
        $filter['miko_result_in_file'] = true;
        $client  = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        $message = $client->request(json_encode($filter), 15);
        if ($message === false) {
            Util::sysLogMsg(__CLASS__, "Error get data from queue 'WorkerCdr::SELECT_CDR_TUBE'", LOG_WARNING);

            return [];
        }
        $filename = json_decode($message);
        if (!is_string($filename) || !file_exists($filename)) {
            return [];
        }
        // The queue file may disappear between the check and the read, and a
        // warning would kill the worker in MikoPBX.
        if (!is_readable($filename)) {
            return [];
        }
        try {
            $content = file_get_contents($filename);
        } catch (\Throwable $e) {
            return [];
        }
        if ($content === false) {
            return [];
        }
        $rows = json_decode((string)$content, true) ?: [];
        @unlink($filename);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Колонки выборки CDR с учётом версии ядра:
     * src_name/dst_name появились только в 2026.1 — там их и берём.
     */
    private function getCdrColumns(): string
    {
        $cols = self::SELECTED_COLS_BASE;
        if (property_exists(CallDetailRecords::class, 'src_name')) {
            $cols[] = 'src_name';
        }
        if (property_exists(CallDetailRecords::class, 'dst_name')) {
            $cols[] = 'dst_name';
        }

        return implode(',', $cols);
    }

    /**
     * Список внутренних номеров (SIP + EXTERNAL) для отсечения внутренних звонков.
     */
    private function loadInternalNumbers(): void
    {
        $this->internalNumbers = [];
        try {
            $extensions = Extensions::find(
                [
                    'type IN ({types:array})',
                    'bind'    => ['types' => [Extensions::TYPE_SIP, Extensions::TYPE_EXTERNAL]],
                    'columns' => 'number',
                ]
            );
            foreach ($extensions as $ext) {
                $this->internalNumbers[] = substr((string)$ext->number, -10);
            }
        } catch (\Throwable $e) {
            CriticalErrorsHandler::handleExceptionWithSyslog($e);
        }
    }

    private function isInternalNumber(string $number): bool
    {
        return in_array(substr($number, -10), $this->internalNumbers, true);
    }

    /**
     * Память обработанных связок с ограничением размера.
     */
    private function rememberProcessed(string $linkedId): void
    {
        $this->processedLinkedIds[$linkedId] = true;
        $this->processedCount++;
        if ($this->processedCount > 500) {
            $this->processedLinkedIds = [];
            $this->processedCount     = 0;
        }
    }
}

// Start worker process
$workerClassname = WorkerBitrixNotifyCdr::class;
if (isset($argv) && count($argv) > 1) {
    cli_set_process_title($workerClassname);
    try {
        $worker = new $workerClassname();
        $worker->start($argv);
    } catch (\Throwable $e) {
        CriticalErrorsHandler::handleExceptionWithSyslog($e);
    }
}
