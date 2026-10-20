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
return [
    'BreadcrumbModuleBitrixNotify'           => 'Уведомления в Битрикс24',
    'SubHeaderModuleBitrixNotify'            => 'Уведомления о пропущенных и входящих вызовах в чат Bitrix24',
    'AdditionalMenuItemModuleBitrixNotify'   => 'Уведомления в Битрикс24',
    'module_bitrix_notify_InfoTitle'         => 'Уведомления MikoPBX в чат Битрикс24',
    'module_bitrix_notify_InfoDescription'   => 'Пропущенные и входящие звонки приходят пользователю Битрикс24 через входящий вебхук (im.notify).',

    // Подключение
    'module_bitrix_notify_ConnectTitle'      => 'Подключение к Битрикс24',
    'module_bitrix_notify_PortalUrl'         => 'Вебхук Bitrix24 (полный URL)',
    'module_bitrix_notify_WebhookHint'       => 'Как получить: в Битрикс24 — «Разработка → Интеграции → Входящий вебхук», метод im.notify. Вставьте полный адрес вида https://portal/rest/индекс/токен/ — индекс и токен распознаются автоматически.',
    'module_bitrix_notify_WebhookPlaceholder' => 'https://company.bitrix24.ru/rest/1711/abcdef1234567890/',
    'module_bitrix_notify_Token'             => 'Токен входящего вебхука',
    'module_bitrix_notify_TokenEnterPlaceholder' => 'Введите токен (например, abcdef123456)',
    'module_bitrix_notify_TokenSavedPlaceholder' => 'Сохранён — введите новый, чтобы заменить',
    'module_bitrix_notify_TokenPriorityHint' => 'Приоритет источника токена: это поле → токен из URL вебхука → переменная BX_WEBHOOK_TOKEN → файл /etc/bitrix-webhook-token (600, root). Пустое поле не затирает сохранённый токен.',
    'module_bitrix_notify_Recipients'        => 'Получатели по умолчанию (ID пользователей Битрикс24, через запятую)',

    // События
    'module_bitrix_notify_EventsTitle'       => 'События и шаблоны',
    'module_bitrix_notify_NotifyMissed'      => 'Пропущенные вызовы',
    'module_bitrix_notify_MissedTooltip'     => 'Уведомлять о пропущенных вызовах извне на внутренние номера. Внутренние пропущенные между сотрудниками не отправляются.',
    'module_bitrix_notify_NotifyRing'        => 'Входящие звонки (реальное время)',
    'module_bitrix_notify_RingTooltip'       => 'Уведомление приходит в момент звонка (до ответа). Работает только по карте персональных получателей.',
    'module_bitrix_notify_RingMapHint'       => 'Нужно заполнять карту персональных получателей ниже — иначе уведомления о звонках не отправляются.',
    'module_bitrix_notify_NumbersMap'        => 'Карта персональных получателей',
    'module_bitrix_notify_NumbersMapHelp'    => "Внутренний номер PBX = ID пользователя Битрикс24, по одной паре в строке:\n201 = 1711\n205 = 43\nПропущенный или входящий на 201 уйдёт пользователю 1711 вместо списка «по умолчанию».",
    'module_bitrix_notify_TemplateMissed'    => 'Шаблон: пропущенный вызов',
    'module_bitrix_notify_TemplateHelp'      => 'Плейсхолдеры: {date} {time} {src} {srcname} {dst} {did} {linkedid}. Неизвестные плейсхолдеры удаляются.',
    'module_bitrix_notify_TemplateRing'      => 'Шаблон: входящий звонок',
    'module_bitrix_notify_Throttle'          => 'Антифлуд, сек.',
    'module_bitrix_notify_ThrottleHelp'      => 'Минимальный интервал между уведомлениями одному получателю. 0 — выключено. При событии внутри интервала уведомление не повторяется.',

    // Публичный API
    'module_bitrix_notify_PublicApiTitle'    => 'Публичный endpoint (для автоматизаций)',
    'module_bitrix_notify_PublicApiToggle'   => 'Разрешить отправку без авторизации (по секрету)',
    'module_bitrix_notify_PublicApiTooltip'  => 'POST endpoint для внешних систем (Zabbix, скрипты, relay). Bearer-токен или JWT не нужны, запрос авторизуется секретом.',
    'module_bitrix_notify_PublicApiUrlLabel' => 'Адрес запроса',
    'module_bitrix_notify_ApiSecret'         => 'Секрет запроса',
    'module_bitrix_notify_SecretEnterPlaceholder' => 'Введите секрет или сгенерируйте',
    'module_bitrix_notify_SecretSavedPlaceholder' => 'Сохранён — введите новый или сгенерируйте',
    'module_bitrix_notify_GenerateSecret'    => 'Сгенерировать',
    'module_bitrix_notify_SecretRequired'    => 'Включён публичный endpoint, но секрет не задан: задайте секрет или отключите тумблер.',

    // Диагностика
    'module_bitrix_notify_DiagTitle'         => 'Диагностика',
    'module_bitrix_notify_DiagCheck'         => 'Проверить подключение',
    'module_bitrix_notify_DiagMode'          => 'Режим воркера',
    'module_bitrix_notify_DiagPortal'        => 'Портал',
    'module_bitrix_notify_DiagToken'         => 'Источник токена',
    'module_bitrix_notify_DiagTokenNone'     => 'не задан',
    'module_bitrix_notify_DiagRecipients'    => 'Получатели по умолчанию',
    'module_bitrix_notify_DiagMap'           => 'Пар в карте номеров',
    'module_bitrix_notify_DiagPublic'        => 'Публичный endpoint',
    'module_bitrix_notify_DiagFail'          => 'Проверка не прошла: исправьте настройки и сохраните форму.',

    // Тесты
    'module_bitrix_notify_TestTitle'         => 'Тестовая отправка',
    'module_bitrix_notify_TestMissed'        => 'Тест пропущенного (имитация)',
    'module_bitrix_notify_TestPing'          => 'Тестовое сообщение',
    'module_bitrix_notify_TestSentTo'        => 'Доставлено:',
    'module_bitrix_notify_TestFail'          => 'Ошибка',

    // Справка
    'module_bitrix_notify_HelpTitle'         => 'Как это работает',
    'module_bitrix_notify_HelpPlaceholders'  => 'Плейсхолдеры подставляются автоматически, лишние удаляются.',
    'module_bitrix_notify_HelpPlaceholdersList' => '{date} — дата, {time} — время, {src}/{srcname} — звонящий и имя, {dst} — внутренний номер, {did} — внешняя линия, {linkedid} — ID связки звонков.',
    'module_bitrix_notify_HelpRouting'       => 'Получатель: если внутренний номер есть в карте — уведомление уходит только ему, иначе — списку «по умолчанию».',
    'module_bitrix_notify_HelpModes'         => 'MikoPBX от 2026.4: пропущенные приходят мгновенно. На более старых ядрах — опрос раз в 60 секунд, уведомление может прийти с задержкой до минуты.',
];
