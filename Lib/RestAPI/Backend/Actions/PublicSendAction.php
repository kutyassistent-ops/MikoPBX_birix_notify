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

declare(strict_types=1);

namespace Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions;

use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use MikoPBX\Core\System\Util;
use Modules\ModuleBitrixNotify\Lib\BitrixRestApi;
use Modules\ModuleBitrixNotify\Lib\BitrixNotifyState;
use Modules\ModuleBitrixNotify\Lib\BitrixNotifySettings;

/**
 * Публичная отправка (NoAuth) с секретом.
 *
 * POST /pbxcore/api/modules/ModuleBitrixNotify/public/send
 * params: secret (обязателен), text (обязателен), uid (необязателен).
 *
 * Включается в настройках модуля ("Разрешить публичный endpoint") + secret.
 * При выключенном флаге или неверном секрете — отказ без деталей.
 * Антифлуд из настроек применяется (throttle_sec).
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions
 */
class PublicSendAction
{
    public static function main(array $data): PBXApiResult
    {
        $result            = new PBXApiResult();
        $result->processor = __METHOD__;

        $settings = BitrixNotifySettings::load();
        if (!$settings->publicApiEnabled || $settings->apiSecret === '') {
            Util::sysLogMsg(__CLASS__, 'public send rejected: public api disabled', LOG_WARNING);
            $result->success    = false;
            $result->messages[] = 'Forbidden';

            return $result;
        }
        $secret = (string)($data['secret'] ?? '');
        if ($secret === '' || !hash_equals($settings->apiSecret, $secret)) {
            Util::sysLogMsg(__CLASS__, 'public send rejected: bad secret (length ' . strlen($secret) . ')', LOG_WARNING);
            $result->success    = false;
            $result->messages[] = 'Forbidden';

            return $result;
        }

        $api = new BitrixRestApi($settings);
        if (!$api->initialized) {
            $result->success    = false;
            $result->messages[] = 'Module not initialized';

            return $result;
        }

        $text = trim((string)($data['text'] ?? ''));
        if ($text === '') {
            $result->success    = false;
            $result->messages[] = 'Parameter text is required';

            return $result;
        }

        $uid = preg_match('/^\d{1,10}$/', (string)($data['uid'] ?? ''))
            ? (string)$data['uid']
            : ($settings->defaultRecipients[0] ?? '');
        if ($uid === '') {
            $result->success    = false;
            $result->messages[] = 'No default recipient configured';

            return $result;
        }

        if (BitrixNotifyState::isThrottled('public|' . $uid, $settings->throttleSec)) {
            $result->success    = false;
            $result->messages[] = 'Throttled';

            return $result;
        }

        $out = $api->sendToUser($uid, $text, 'public');
        $result->success = (bool)$out['ok'];
        $result->data    = [
            'delivery' => [$uid => $out],
        ];
        if ($out['ok']) {
            BitrixNotifyState::mark('public|' . $uid);
        }

        return $result;
    }
}
