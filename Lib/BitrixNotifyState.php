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
 * Антифлуд: хранит время последней отправки по ключу (например, uid + тип события).
 *
 * Состояние хранится в /tmp (tmpfs): при перезагрузке АТС сбрасывается автоматически.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class BitrixNotifyState
{
    private static function filePath(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/ModuleBitrixNotify-state.json';
    }

    /**
     * Прошло ли меньше $intervalSec с последней отправки по ключу.
     */
    public static function isThrottled(string $key, int $intervalSec): bool
    {
        if ($intervalSec <= 0) {
            return false;
        }
        $state = self::readState();
        $last  = (int)($state[$key] ?? 0);

        return $last > 0 && (time() - $last) < $intervalSec;
    }

    /**
     * Запоминает время отправки по ключу.
     */
    public static function mark(string $key): void
    {
        $state         = self::readState();
        $state[$key]   = time();
        // Не хранить средневековую историю: старше 24 ч чистим
        $state = array_filter($state, static fn($ts) => (time() - (int)$ts) < 86400);
        // MikoPBX turns a warning into an exception, so a failing write is
        // caught instead of killing the worker.
        try {
            $written = file_put_contents(self::filePath(), json_encode($state));
        } catch (\Throwable $e) {
            return;
        }
        if ($written === false) {
            Util::sysLogMsg(__CLASS__, 'Cannot write the notification state file', LOG_WARNING);
        }
    }

    private static function readState(): array
    {
        $path = self::filePath();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        try {
            $raw = file_get_contents($path);
        } catch (\Throwable $e) {
            return [];
        }
        if ($raw === false || $raw === '') {
            return [];
        }
        $state = json_decode($raw, true);

        return is_array($state) ? $state : [];
    }
}
