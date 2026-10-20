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
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\Asterisk\AsteriskManager;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerBase;

require_once 'Globals.php';

/**
 * Воркер уведомлений о входящих звонках в реальном времени.
 *
 * Слушает UserEvent CdrConnector (события dial/transfer_dial) через AMI,
 * как официальный ModuleTelegramNotify. Уведомления отправляются только
 * на внутренние номера из карты настроек (numbers_map: "201=1711").
 *
 * Паттерн проверен по исходникам официального модуля: Util::getAstManager,
 * Filter UserEvent: {pingTube} + UserEvent: CdrConnector, waitUserEvent(true),
 * replyOnPingRequest, декодирование AgiData.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class WorkerBitrixNotifyAMI extends WorkerBase
{
    protected AsteriskManager $am;

    private array $numbers       = [];
    private string $ringTemplate  = '';
    private int   $throttleSec   = 60;
    private int   $counter       = 0;

    public function start($argv): void
    {
        $settings = BitrixNotifySettings::load();
        if (!$settings->initialized) {
            Util::sysLogMsg(__CLASS__, 'Not initialized: ' . $settings->lastError, LOG_WARNING);

            return;
        }
        if (!$settings->notifyRing) {
            Util::sysLogMsg(__CLASS__, 'notify_ring is off, AMI worker stopped', LOG_WARNING);

            return;
        }
        $this->ringTemplate = $settings->templateRing;
        $this->throttleSec  = $settings->throttleSec;

        $this->am = Util::getAstManager();
        $this->setFilter();
        $this->updateNumbers();
        $this->am->addEventHandler('userevent', [$this, 'callback']);

        while (true) {
            $result = $this->am->waitUserEvent(true);
            if ($result === []) {
                // Реконнект
                usleep(100000);
                $this->am = Util::getAstManager();
                $this->setFilter();
            }
        }
    }

    /**
     * Установка фильтров AMI: пинг SafeScripts + события CDR.
     */
    private function setFilter(): array
    {
        $pingTube               = $this->makePingTubeName(self::class);
        $params                 = ['Operation' => 'Add', 'Filter' => 'UserEvent: ' . $pingTube];
        $this->am->sendRequestTimeout('Filter', $params);

        $params = ['Operation' => 'Add', 'Filter' => 'UserEvent: CdrConnector'];

        return $this->am->sendRequestTimeout('Filter', $params);
    }

    /**
     * Карта отслеживаемых номеров: хвост внутреннего номера => bitrix uid.
     */
    private function updateNumbers(): void
    {
        $this->numbers = [];
        $settings      = BitrixNotifySettings::load();
        foreach ($settings->numbersMap as $ext => $uid) {
            $this->numbers[$ext] = $uid;
        }
    }

    /**
     * Обработка AMI событий.
     */
    public function callback($parameters): void
    {
        if (is_array($parameters) && count($parameters) === 1 && isset($parameters[0])) {
            // Пинг приходит вложенным массивом
            $parameters = $parameters[0];
        }
        if ($this->replyOnPingRequest($parameters)) {
            $this->counter++;
            if ($this->counter > 5) {
                // Пинг раз в минуту: обновляем карту каждые ~5 минут,
                // свежие настройки также прилетают через modelsEventChangeData (рестарт воркера)
                $settings          = BitrixNotifySettings::load();
                $this->ringTemplate = $settings->templateRing;
                $this->throttleSec  = $settings->throttleSec;
                $this->updateNumbers();
                $this->counter = 0;
            }

            return;
        }

        if (is_array($parameters) && !isset($parameters['AgiData'])) {
            return;
        }
        $data = json_decode(base64_decode((string)$parameters['AgiData']), true);
        if (!is_array($data)) {
            return;
        }
        if (($data['action'] ?? '') === 'dial' || ($data['action'] ?? '') === 'transfer_dial') {
            $this->actionDial($data);
        }
    }

    /**
     * Входящий звонок на внутренний номер из карты -> уведомление.
     */
    private function actionDial(array $data): void
    {
        $dst = substr((string)($data['dst_num'] ?? ''), -10);
        if ($dst === '' || !isset($this->numbers[$dst])) {
            return;
        }
        if (BitrixNotifyState::isThrottled('ring|' . $this->numbers[$dst], $this->throttleSec)) {
            return;
        }
        $message = BitrixTemplates::render((string)$this->ringTemplate, $data);
        if ($message === '') {
            return;
        }
        $settings = BitrixNotifySettings::load();
        $api      = new BitrixRestApi($settings);
        if (!$api->initialized) {
            Util::sysLogMsg(__CLASS__, 'Bitrix api not initialized: ' . $api->getLastError(), LOG_WARNING);

            return;
        }
        $out = $api->sendToUser($this->numbers[$dst], $message, 'ring-' . substr((string)($data['linkedid'] ?? ''), -20));
        if ($out['ok']) {
            BitrixNotifyState::mark('ring|' . $this->numbers[$dst]);
        }
    }
}

// Start worker process
$workerClassname = WorkerBitrixNotifyAMI::class;
if (isset($argv) && count($argv) > 1) {
    cli_set_process_title($workerClassname);
    try {
        $worker = new $workerClassname();
        $worker->start($argv);
    } catch (\Throwable $e) {
        CriticalErrorsHandler::handleExceptionWithSyslog($e);
    }
}
