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

/**
 * Рендер шаблонов уведомлений.
 *
 * Плейсхолдеры: {date} {time} {src} {srcname} {dst} {did} {linkedid}.
 * Неизвестные остаточные плейсхолдеры удаляются, чтобы не мусорить в сообщении.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class BitrixTemplates
{
    public static function render(string $template, array $data): string
    {
        $date = '';
        $time = '';
        $ts   = strtotime((string)($data['start'] ?? ''));
        if ($ts !== false) {
            $date = date('d.m.Y', $ts);
            $time = date('H:i', $ts);
        }

        $src     = (string)($data['src_num'] ?? '');
        $srcName = trim((string)($data['src_name'] ?? ''));
        $dst     = (string)($data['dst_num'] ?? '');
        $did     = (string)($data['did'] ?? '');
        if ($dst === '' && $did !== '') {
            $dst = $did;
        }
        $srcPart = $src !== '' && $srcName !== '' ? "$src ($srcName)" : $src;

        $replace = [
            '{date}'     => $date,
            '{time}'     => $time,
            '{src}'      => $src,
            '{srcname}'  => $srcName !== '' ? " ($srcName)" : '',
            '{dst}'      => $dst,
            '{did}'      => $did,
            '{linkedid}' => (string)($data['linkedid'] ?? ''),
        ];
        $out = strtr($template, $replace);

        return trim((string)preg_replace('/\{[a-z_]+\}/', '', $out));
    }
}
