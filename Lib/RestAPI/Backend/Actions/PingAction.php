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
use Modules\ModuleBitrixNotify\Lib\BitrixRestApi;
use Modules\ModuleBitrixNotify\Lib\BitrixNotifySettings;

/**
 * Тестовое сообщение (защищённый эндпоинт, админкой из JS).
 *
 * data params: text (необязателен), uid (необязателен — иначе список по умолчанию).
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions
 */
class PingAction
{
    public static function main(array $data): PBXApiResult
    {
        $result            = new PBXApiResult();
        $result->processor = __METHOD__;

        $settings = BitrixNotifySettings::load();
        $api      = new BitrixRestApi($settings);
        if (!$api->initialized) {
            $result->success    = false;
            $result->messages[] = $api->getLastError();

            return $result;
        }

        $text = trim((string)($data['text'] ?? ''));
        $text = $text !== '' ? $text : 'Тестовое уведомление от MikoPBX (ModuleBitrixNotify).';
        $uid  = trim((string)($data['uid'] ?? ''));

        $results = $uid !== ''
            ? [$uid => $api->sendToUser($uid, $text)]
            : $api->sendToAll($text);

        $me          = $api->getMe();
        $anyDelivered = false;
        foreach ($results as $r) {
            if (($r['ok'] ?? false)) {
                $anyDelivered = true;
                break;
            }
        }

        $result->success = $anyDelivered;
        $result->data    = [
            'delivery'     => $results,
            'webhookOwner' => $me,
        ];

        return $result;
    }
}
