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

/**
 * Публичный health-check (NoAuth).
 *
 * GET /pbxcore/api/modules/ModuleBitrixNotify/public/status
 *
 * Возвращается минимум информации: версия и режим CDR-воркера.
 * Портала, токена и прочего чувствительного здесь нет.
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions
 */
class PublicStatusAction
{
    public static function main(array $data): PBXApiResult
    {
        $result            = new PBXApiResult();
        $result->processor = __METHOD__;
        $result->success   = true;
        $result->data      = [
            'status'    => 'ok',
            'module'    => 'ModuleBitrixNotify',
            'version'   => '2.0.9',
            'workerMode' => CheckAction::workerMode(),
            'timestamp' => date('c'),
        ];

        return $result;
    }
}
