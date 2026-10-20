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

namespace Modules\ModuleBitrixNotify\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

/**
 * Модель настроек модуля ModuleBitrixNotify (таблица m_ModuleBitrixNotify).
 *
 * Колонки, добавленные после v1.x, создаются установщиком автоматически
 * (createSettingsTableByModelsAnnotations -> UpdateDatabase по аннотациям).
 *
 * @package Modules\ModuleBitrixNotify\Models
 */
class ModuleBitrixNotify extends ModulesModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Bitrix24 portal URL, i.e. https://company.bitrix24.ru
     *
     * @Column(type="string", nullable=true)
     */
    public $portal_url;

    /**
     * Bitrix24 incoming webhook token.
     * Rendered as password field, never prefilled in UI, never sent back in responses.
     *
     * @Column(type="string", nullable=true)
     */
    public $webhook_token;

    /**
     * Получатели по умолчанию: Bitrix24 user ids через запятую ("1711, 205").
     *
     * @Column(type="string", nullable=true)
     */
    public $recipients;

    /**
     * Персональные уведомления: строки вида "201=1711".
     * Внутренний номер PBX -> Bitrix24 user id.
     * Пропущенный/входящий на 201 уйдёт пользователю 1711 вместо списка по умолчанию.
     *
     * @Column(type="text", nullable=true)
     */
    public $numbers_map;

    /**
     * Включить уведомления о пропущенных вызовах.
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $notify_missed;

    /**
     * Включить уведомления о входящих звонках (реальное время, AMI-воркер).
     *
     * @Column(type="integer", default="0", nullable=true)
     */
    public $notify_ring;

    /**
     * Шаблон уведомления о пропущенном вызове.
     * Плейсхолдеры: {date} {time} {src} {srcname} {dst} {did} {linkedid}
     *
     * @Column(type="text", nullable=true)
     */
    public $template_missed;

    /**
     * Шаблон уведомления о входящем звонке.
     * Плейсхолдеры: {date} {time} {src} {srcname} {dst} {did} {linkedid}
     *
     * @Column(type="text", nullable=true)
     */
    public $template_ring;

    /**
     * Антифлуд: минимальный интервал между уведомлениями одному получателю, сек. 0 = выключено.
     *
     * @Column(type="integer", default="60", nullable=true)
     */
    public $throttle_sec;

    /**
     * Разрешить публичный endpoint /pbxcore/api/modules/ModuleBitrixNotify/public/... (без JWT).
     *
     * @Column(type="integer", default="0", nullable=true)
     */
    public $public_api_enabled;

    /**
     * Секрет для публичного endpoint. Хранится без изменений, в API не возвращается.
     *
     * @Column(type="string", nullable=true)
     */
    public $api_secret;

    public function initialize(): void
    {
        $this->setSource('m_ModuleBitrixNotify');
        parent::initialize();
    }
}
