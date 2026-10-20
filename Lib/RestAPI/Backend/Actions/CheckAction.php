<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright Р’В© 2017-2023 Alexey Portnov and Nikolay Beketov
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
use Modules\ModuleBitrixNotify\Lib\BitrixNotifySettings;
use Modules\ModuleBitrixNotify\Lib\WorkerBitrixNotifyCdr;

/**
 * Status Р СР С•Р Т‘РЎС“Р В»РЎРЏ: Р Р…Р В°РЎРѓРЎвЂљРЎР‚Р С•Р в„–Р С”Р С‘, Р Р†Р С‘Р Т‘ Р Р†Р С•РЎР‚Р С”Р ВµРЎР‚Р В°, Р Р†Р ВµРЎР‚РЎРѓР С‘РЎРЏ.
 *
 * Р вЂ”Р В°Р С—РЎС“РЎРѓР С”Р В°Р ВµРЎвЂљРЎРѓРЎРЏ Р Р† РЎвЂћР С•Р Р…Р Вµ (backend worker РЎвЂЎР ВµРЎР‚Р ВµР В· WorkerApiCommands) Р С‘Р В»Р С‘
 * Р Р…Р В°Р С—РЎР‚РЎРЏР СРЎС“РЎР‹ Р С‘Р В· BitrixNotifyConf::moduleRestAPICallback (Р В»Р ВµР С–Р В°РЎРѓР С‘-РЎР‚Р С•РЎС“РЎвЂљ).
 *
 * @package Modules\ModuleBitrixNotify\Lib\RestAPI\Backend\Actions
 */
class CheckAction
{
    public static function main(array $data): PBXApiResult
    {
        $result           = new PBXApiResult();
        $result->processor = __METHOD__;

        $settings = BitrixNotifySettings::load();
        $status   = $settings->getStatusData();
        $status['workerMode'] = self::workerMode();
        $status['version']    = '2.0.10';
        $status['recipientsMode'] = $settings->defaultRecipients !== [] || $settings->numbersMap !== [];

        $result->success = $status['initialized'] && $status['recipientsMode'];
        $result->data    = $status;

        return $result;
    }

    /**
     * Р С™Р В°Р С”Р С•Р в„– РЎР‚Р ВµР В¶Р С‘Р С CDR-Р Р†Р С•РЎР‚Р С”Р ВµРЎР‚Р В° Р Т‘Р С•РЎРѓРЎвЂљРЎС“Р С—Р ВµР Р… Р Р…Р В° РЎРЊРЎвЂљР С•Р С РЎРЏР Т‘РЎР‚Р Вµ (finalize / polling).
     */
    public static function workerMode(): string
    {
        return class_exists(WorkerBitrixNotifyCdr::class)
            && defined('MikoPBX\\Core\\Workers\\WorkerCdr::FINALIZE_CDR_TUBE') ? 'finalize' : 'polling';
    }
}


