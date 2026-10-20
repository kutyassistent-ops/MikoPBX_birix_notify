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

namespace Modules\ModuleBitrixNotify\Lib\RestAPI;

use Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions\CheckAction;
use Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions\PingAction;
use Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions\SendAction;
use Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions\PublicSendAction;
use Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions\PublicStatusAction;

/**
 * Процессор REST API модуля (Pattern 2 — расширенный).
 *
 * Роутит запросы от WorkerApiCommands (через контроллеры модуля) и
 * legacy moduleRestAPICallback к Action-классам.
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI
 */
class BitrixNotifyProcessor
{
    public const ACTION_CHECK         = 'check';
    public const ACTION_PING          = 'ping';
    public const ACTION_SEND          = 'send';
    public const ACTION_TEST_MISSED   = 'test-missed';
    public const ACTION_PUBLIC_SEND   = 'publicSend';
    public const ACTION_PUBLIC_STATUS = 'publicStatus';

    /**
     * Единая точка входа: request содержит ключи action и data.
     *
     * @param array $request ['action' => string, 'data' => array]
     */
    public static function callBack(array $request): \MikoPBX\PBXCoreREST\Lib\PBXApiResult
    {
        $action = (string)($request['action'] ?? '');
        $data   = is_array($request['data'] ?? null) ? $request['data'] : [];
        $res    = new \MikoPBX\PBXCoreREST\Lib\PBXApiResult();

        switch ($action) {
            case self::ACTION_CHECK:
                $res = CheckAction::main($data);
                break;
            case self::ACTION_PING:
                $res = PingAction::main($data);
                break;
            case self::ACTION_SEND:
                $res = SendAction::main($data);
                break;
            case self::ACTION_TEST_MISSED:
                $res = SendAction::mainTestMissed($data);
                break;
            case self::ACTION_PUBLIC_SEND:
                $res = PublicSendAction::main($data);
                break;
            case self::ACTION_PUBLIC_STATUS:
                $res = PublicStatusAction::main($data);
                break;
            default:
                $res->success    = false;
                $res->messages[] = "Unknown action: {$action}";
        }

        return $res;
    }
}
