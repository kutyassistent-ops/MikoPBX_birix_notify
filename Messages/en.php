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
    'BreadcrumbModuleBitrixNotify'           => 'Bitrix24 Notifications',
    'SubHeaderModuleBitrixNotify'            => 'Missed and incoming call notifications to Bitrix24 chat',
    'AdditionalMenuItemModuleBitrixNotify'   => 'Bitrix24 Notifications',
    'module_bitrix_notify_InfoTitle'         => 'MikoPBX call notifications to Bitrix24',
    'module_bitrix_notify_InfoDescription'   => 'Missed and incoming calls are delivered to a Bitrix24 user via incoming webhook (im.notify).',

    // Connection
    'module_bitrix_notify_ConnectTitle'      => 'Connection to Bitrix24',
    'module_bitrix_notify_PortalUrl'         => 'Bitrix24 webhook (full URL)',
    'module_bitrix_notify_WebhookHint'       => 'Create in Bitrix24: "Developer section → Integrations → Incoming webhook", method im.notify. Paste the full address https://portal/rest/index/token/ — index and token are recognized automatically.',
    'module_bitrix_notify_WebhookPlaceholder' => 'https://company.bitrix24.ru/rest/1711/abcdef1234567890/',
    'module_bitrix_notify_Token'             => 'Incoming webhook token',
    'module_bitrix_notify_TokenEnterPlaceholder' => 'Enter token (e.g. abcdef123456)',
    'module_bitrix_notify_TokenSavedPlaceholder' => 'Saved — type a new one to replace it',
    'module_bitrix_notify_TokenPriorityHint' => 'Token priority: this field → token from the webhook URL → BX_WEBHOOK_TOKEN env variable → /etc/bitrix-webhook-token file (600, root). An empty field keeps the stored token.',
    'module_bitrix_notify_Recipients'        => 'Default recipients (Bitrix24 user IDs, comma separated)',

    // Events
    'module_bitrix_notify_EventsTitle'       => 'Events and templates',
    'module_bitrix_notify_NotifyMissed'      => 'Missed calls',
    'module_bitrix_notify_MissedTooltip'     => 'Notify about external missed calls on internal numbers. Internal-to-internal missed calls are not sent.',
    'module_bitrix_notify_NotifyRing'        => 'Incoming calls (real time)',
    'module_bitrix_notify_RingTooltip'       => 'Notification is sent at the moment the call rings (before answer). Works only for numbers listed in the personal map below.',
    'module_bitrix_notify_RingMapHint'       => 'Fill in the personal recipients map below — otherwise ring notifications are not sent.',
    'module_bitrix_notify_NumbersMap'        => 'Personal recipients map',
    'module_bitrix_notify_NumbersMapHelp'    => "PBX internal number = Bitrix24 user ID, one pair per line:\n201 = 1711\n205 = 43\nA missed or incoming call to 201 goes to user 1711 instead of the default list.",
    'module_bitrix_notify_TemplateMissed'    => 'Template: missed call',
    'module_bitrix_notify_TemplateHelp'      => 'Placeholders: {date} {time} {src} {srcname} {dst} {did} {linkedid}. Unknown placeholders are removed.',
    'module_bitrix_notify_TemplateRing'      => 'Template: incoming call',
    'module_bitrix_notify_Throttle'          => 'Anti-flood, sec',
    'module_bitrix_notify_ThrottleHelp'      => 'Minimal interval between notifications to one recipient. 0 — disabled. Notifications inside the interval are skipped.',

    // Public API
    'module_bitrix_notify_PublicApiTitle'    => 'Public endpoint (for automations)',
    'module_bitrix_notify_PublicApiToggle'   => 'Allow unauthenticated sending (secret-based)',
    'module_bitrix_notify_PublicApiTooltip'  => 'POST endpoint for external systems (Zabbix, scripts, relays). No Bearer/JWT needed, the request is authorized by the secret.',
    'module_bitrix_notify_PublicApiUrlLabel' => 'Request URL',
    'module_bitrix_notify_ApiSecret'         => 'Request secret',
    'module_bitrix_notify_SecretEnterPlaceholder' => 'Type a secret or generate one',
    'module_bitrix_notify_SecretSavedPlaceholder' => 'Saved — type or generate a new one',
    'module_bitrix_notify_GenerateSecret'    => 'Generate',
    'module_bitrix_notify_SecretRequired'    => 'Public endpoint is enabled but no secret set: set a secret or disable the toggle.',

    // Diagnostics
    'module_bitrix_notify_DiagTitle'         => 'Diagnostics',
    'module_bitrix_notify_DiagCheck'         => 'Check connection',
    'module_bitrix_notify_DiagMode'          => 'Worker mode',
    'module_bitrix_notify_DiagPortal'        => 'Portal',
    'module_bitrix_notify_DiagToken'         => 'Token source',
    'module_bitrix_notify_DiagTokenNone'     => 'not set',
    'module_bitrix_notify_DiagRecipients'    => 'Default recipients',
    'module_bitrix_notify_DiagMap'           => 'Map entries',
    'module_bitrix_notify_DiagPublic'        => 'Public endpoint',
    'module_bitrix_notify_DiagFail'          => 'Check failed: fix settings and save the form.',

    // Tests
    'module_bitrix_notify_TestTitle'         => 'Test sending',
    'module_bitrix_notify_TestMissed'        => 'Test missed call (simulation)',
    'module_bitrix_notify_TestPing'          => 'Test message',
    'module_bitrix_notify_TestSentTo'        => 'Delivered:',
    'module_bitrix_notify_TestFail'          => 'Error',

    // Help
    'module_bitrix_notify_HelpTitle'         => 'How it works',
    'module_bitrix_notify_HelpPlaceholders'  => 'Placeholders are substituted automatically, leftovers are removed.',
    'module_bitrix_notify_HelpPlaceholdersList' => '{date} — date, {time} — time, {src}/{srcname} — caller and name, {dst} — internal number, {did} — external line, {linkedid} — call chain ID.',
    'module_bitrix_notify_HelpRouting'       => 'Recipient: if the internal number is in the map — notification goes only to that user, otherwise to the default list.',
    'module_bitrix_notify_HelpModes'         => 'MikoPBX from 2026.4: missed calls are delivered instantly. Older cores poll every 60 seconds so notifications may be up to a minute late.',
];
