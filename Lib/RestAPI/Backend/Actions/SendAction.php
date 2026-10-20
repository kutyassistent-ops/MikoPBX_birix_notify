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
use Modules\ModuleBitrixNotify\Lib\BitrixTemplates;

/**
 * Отправка произвольного сообщения (защищённые роуты): /send, /test-missed.
 *
 * send:        message (обязателен), uid (необязателен), tag (необязателен)
 * test-missed: src (необязателен), dst (необязателен) — имитация пропущенного
 *
 * Токен никогда не попадает в ответ и логи.
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions
 */
class SendAction
{
    public static function main(array $data): PBXApiResult
    {
        $result            = new PBXApiResult();
        $result->processor = __METHOD__;

        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            $result->success    = false;
            $result->messages[] = 'Parameter message is required';

            return $result;
        }

        $settings = BitrixNotifySettings::load();
        $api      = new BitrixRestApi($settings);
        if (!$api->initialized) {
            $result->success    = false;
            $result->messages[] = $api->getLastError();

            return $result;
        }

        $uid = trim((string)($data['uid'] ?? ''));
        $tag = (string)($data['tag'] ?? '');
        $tag = $tag !== '' ? 'rest-' . substr($tag, 0, 40) : null;

        $results = $uid !== ''
            ? [$uid => $api->sendToUser($uid, $message, $tag)]
            : $api->sendToAll($message, $tag);

        $result->success = true;
        $result->data    = ['delivery' => $results];

        return $result;
    }

    /**
     * Имитация пропущенного вызова: прогоняет реальный шаблон и маршрутизацию получателей.
     */
    public static function mainTestMissed(array $data): PBXApiResult
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

        $src = trim((string)($data['src'] ?? ''));
        $src = $src !== '' ? $src : '+79001234567';
        $dst = trim((string)($data['dst'] ?? ''));
        $dst = $dst !== '' ? $dst : '201';

        $row = [
            'start'     => date('Y-m-d H:i:s'),
            'src_num'   => $src,
            'dst_num'   => $dst,
            'did'       => '',
            'linkedid'  => 'test-' . time(),
        ];
        $message = BitrixTemplates::render($settings->templateMissed, $row);
        if ($message === '') {
            $result->success    = false;
            $result->messages[] = 'Empty message after template render';

            return $result;
        }

        $recipients = $settings->resolveRecipients($dst);
        if ($recipients === []) {
            $result->success    = false;
            $result->messages[] = 'No recipients configured';

            return $result;
        }

        $delivery = [];
        foreach ($recipients as $uid) {
            $delivery[$uid] = $api->sendToUser($uid, $message, 'test-missed');
        }

        $result->success = true;
        $result->data    = [
            'message'    => $message,
            'recipients' => $recipients,
            'delivery'   => $delivery,
        ];

        return $result;
    }
}
