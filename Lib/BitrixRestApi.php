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

use MikoPBX\Core\System\Util;

/**
 * Отправка уведомлений в Битрикс24 через входящий вебхук (im.notify).
 *
 * Проверенный приём: только form-urlencoded, по одному вызову на каждого получателя
 * (JSON-тела Битрикс24 в im.notify игнорирует).
 *
 * Значение токена никогда не попадает в тексты ошибок и логи.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class BitrixRestApi
{
    public bool $initialized = false;

    private BitrixNotifySettings $settings;
    private string $lastError = '';
    private const HTTP_TIMEOUT = 20;

    public function __construct(?BitrixNotifySettings $settings = null)
    {
        $this->settings = $settings ?? BitrixNotifySettings::load();
        $this->initialized = $this->settings->initialized;
        if (!$this->initialized) {
            $this->lastError = $this->settings->lastError;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Проверка вебхука: находит владельца токена (user.get на себя).
     */
    public function getMe(): array
    {
        $result = $this->exec('user.get');
        if (($result['ok'] ?? false) && isset($result['item']['ID'])) {
            return [
                'ok'      => true,
                'user_id' => (string)$result['item']['ID'],
                'name'    => (string)($result['item']['NAME'] ?? ''),
            ];
        }

        return ['ok' => false, 'error' => $result['error'] ?? 'unknown'];
    }

    /**
     * Отправка уведомления получателям по умолчанию.
     *
     * @return array uid => ['ok' => bool, 'error' => string]
     */
    public function sendToAll(string $message, ?string $tagSuffix = null): array
    {
        $results = [];
        foreach ($this->settings->defaultRecipients as $uid) {
            $results[$uid] = $this->sendToUser($uid, $message, $tagSuffix);
        }

        return $results;
    }

    /**
     * im.notify на одного пользователя.
     *
     * @return array{ok: bool, error: string}
     */
    public function sendToUser(string $uid, string $message, ?string $tagSuffix = null): array
    {
        $params = [
            'user_id'    => (string)$uid,
            'message'    => $message,
            'type'       => 'user',
            'importance' => 'important',
        ];
        if ($tagSuffix !== null) {
            $params['tag'] = 'module_bitrixnotify|' . $tagSuffix . '|' . $uid;
        }
        $res = $this->exec('im.notify', $params);

        return [
            'ok'    => (bool)($res['ok'] ?? false),
            'error' => (string)($res['error'] ?? ''),
        ];
    }

    /**
     * REST вызов на портал. Нормализует ответ: ['ok'=>bool, 'error'=>..., 'item'=>mixed].
     * Токен в строки ошибок не подставляется.
     */
    private function exec(string $method, array $params = []): array
    {
        // Канонический REST-путь Б24: <portal>/rest/<индекс>/<токен>/<метод>.json
        // (без индекса Б24 отвечает ERROR_METHOD_NOT_FOUND)
        $url = $this->settings->portalOrigin . '/rest/' . $this->settings->webhookIndex
            . '/' . $this->settings->token . '/' . $method . '.json';
        $contents = $this->curlPost($url, $params);
        $decoded  = json_decode((string)$contents, true);
        if (!is_array($decoded)) {
            $this->lastError = "$method: non-json response from portal (code " . strlen((string)$contents) . ' bytes)';
            Util::sysLogMsg('ModuleBitrixNotify', $this->lastError, LOG_WARNING);

            return ['ok' => false, 'error' => 'non-json response'];
        }
        if (isset($decoded['error'])) {
            $this->lastError = "$method: {$decoded['error']}: " . ($decoded['error_description'] ?? '');
            Util::sysLogMsg('ModuleBitrixNotify', $this->lastError, LOG_WARNING);

            return ['ok' => false, 'error' => (string)$decoded['error'] . ': ' . (string)($decoded['error_description'] ?? '')];
        }

        // im.notify возвращает int (ID уведомления); user.get — массив записей в result
        $item    = null;
        $rawItem = $decoded['result'] ?? null;
        if (is_array($rawItem)) {
            $values = array_values($rawItem);
            $item   = ($values !== []) ? $values[0] : null;
        } elseif ($rawItem !== null) {
            $item = $rawItem;
        }

        return ['ok' => true, 'error' => '', 'item' => $item];
    }

    /**
     * POST x-www-form-urlencoded через cURL.
     */
    private function curlPost(string $url, array $params): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        // A warning from cURL would become an exception in MikoPBX and
        // would kill the worker, so the call is protected.
        try {
            $result = curl_exec($ch);
        } catch (\Throwable $e) {
            $result = false;
            $this->lastError = 'curl error: ' . $e->getMessage();
        }
        if ($result === false) {
            $this->lastError = 'curl error: ' . curl_errno($ch) . ' ' . curl_error($ch);
            Util::sysLogMsg('ModuleBitrixNotify', $this->lastError, LOG_WARNING);
        }
        curl_close($ch);

        return (string)$result;
    }
}
