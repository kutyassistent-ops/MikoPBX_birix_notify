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

namespace Modules\ModuleBitrixNotify\Lib;

use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleBitrixNotify\Lib\RestAPI\BitrixNotifyProcessor;
use Modules\ModuleBitrixNotify\Models\ModuleBitrixNotify;

/**
 * Точка входа хуков ядра для модуля ModuleBitrixNotify.
 *
 * Два воркера:
 *  - WorkerBitrixNotifyCdr — пропущенные (finalize-событие на ядрах >=2026.4, иначе poll);
 *  - WorkerBitrixNotifyAMI — входящие звонки в реальном времени (CdrConnector UserEvent).
 *
 * REST (Pattern 2):
 *  - защищённые: /pbxcore/api/modules/ModuleBitrixNotify/{action} — check, ping, send, test-missed;
 *    исполняются через стандартный callActionForModule -> moduleRestAPICallback;
 *  - публичные (NoAuth=true): /pbxcore/api/modules/ModuleBitrixNotify/public/{action} — status (GET), send (POST).
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class BitrixNotifyConf extends ConfigClass
{
    /**
     * После сохранения настроек модуля перезапустить воркеры,
     * чтобы шаблоны/карта/токен применились сразу.
     */
    public function modelsEventChangeData($data): void
    {
        if (($data['model'] ?? '') === ModuleBitrixNotify::class) {
            (new BitrixNotifyMain())->startAllServices(true);
        }
    }

    /**
     * Регистрация воркеров в WorkerSafeScripts.
     */
    public function getModuleWorkers(): array
    {
        return [
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_BEANSTALK,
                'worker' => WorkerBitrixNotifyCdr::class,
            ],
            [
                'type'   => WorkerSafeScriptsCore::CHECK_BY_AMI,
                'worker' => WorkerBitrixNotifyAMI::class,
            ],
        ];
    }

    /**
     * Защищённые action'ы стандартного модульного роута
     * /pbxcore/api/modules/ModuleBitrixNotify/{action}.
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $data   = is_array($request['data'] ?? null) ? $request['data'] : [];
        $action = strtolower((string)($request['action'] ?? ''));

        return match ($action) {
            BitrixNotifyProcessor::ACTION_CHECK,
            BitrixNotifyProcessor::ACTION_PING,
            BitrixNotifyProcessor::ACTION_SEND,
            BitrixNotifyProcessor::ACTION_TEST_MISSED => BitrixNotifyProcessor::callBack(['action' => $action, 'data' => $data]),
            default => $this->createErrorResult("Unknown action: {$action}"),
        };
    }

    /**
     * Публичные NoAuth роуты (без Bearer):
     *  GET  /pbxcore/api/modules/ModuleBitrixNotify/public/status
     *  POST /pbxcore/api/modules/ModuleBitrixNotify/public/send
     */
    public function getPBXCoreRESTAdditionalRoutes(): array
    {
        return [
            ['\Modules\ModuleBitrixNotify\Lib\RestAPI\Controllers\PublicGetController',  'callAction', '/pbxcore/api/modules/ModuleBitrixNotify/public/{actionName}', 'get',  '', true],
            ['\Modules\ModuleBitrixNotify\Lib\RestAPI\Controllers\PublicPostController', 'callAction', '/pbxcore/api/modules/ModuleBitrixNotify/public/{actionName}', 'post', '', true],
        ];
    }

    private function createErrorResult(string $message): PBXApiResult
    {
        $res             = new PBXApiResult();
        $res->success    = false;
        $res->messages[] = $message;

        return $res;
    }

    /**
     * После включения модуля в веб-интерфейсе запустить воркеры.
     */
    public function onAfterModuleEnable(): void
    {
        (new BitrixNotifyMain())->startAllServices(true);
    }
}
