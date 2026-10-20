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
use Modules\ModuleBitrixNotify\Models\ModuleBitrixNotify;

/**
 * Единая точка доступа к настройкам модуля.
 *
 * Читает модель настроек, распредмечивает (разбирает) токен, карту номеров и шаблоны.
 * Значение токена/секрета не печатается в логи и не попадает в ответы REST API.
 *
 * @package Modules\ModuleBitrixNotify\Lib
 */
class BitrixNotifySettings
{
    /** Токен из поля на странице настроек модуля */
    public const TOKEN_SOURCE_SETTINGS = 'settings';
    /** Токен, встроенный в полный URL вебхука (portal_url) */
    public const TOKEN_SOURCE_WEBHOOK_URL = 'webhook-url';
    /** Токен из переменной окружения BX_WEBHOOK_TOKEN */
    public const TOKEN_SOURCE_ENV      = 'env';
    /** Токен из файла /etc/bitrix-webhook-token (600, root) */
    public const TOKEN_SOURCE_FILE     = 'file';

    public const DEFAULT_TEMPLATE_MISSED
        = 'Пропущенный вызов {date} {time}: {src}{srcname} на {dst} — не отвечено.';
    public const DEFAULT_TEMPLATE_RING
        = 'Входящий вызов {time}: {src}{srcname} на {dst}.';

    public ?ModuleBitrixNotify $record = null;

    public string $portal            = '';
    /** Origin портала (scheme://host) — в диагностике и для сборки REST-пути */
    public string $portalOrigin      = '';
    /** Индекс приложения в пути REST (/rest/<индекс>/<токен>/<метод>.json), по умолчанию 1 */
    public string $webhookIndex      = '1';
    /** Токен был распознан внутри полного URL вебхука */
    public bool   $webhookUrlMode    = false;
    public string $token             = '';
    public string $tokenSource       = '';
    public string $apiSecret         = '';

    /** Получатели по умолчанию (bitrix user ids) */
    public array  $defaultRecipients = [];

    /** Карта "внутренний номер (хвост) => bitrix user id" */
    public array  $numbersMap        = [];

    public string $templateMissed    = self::DEFAULT_TEMPLATE_MISSED;
    public string $templateRing      = self::DEFAULT_TEMPLATE_RING;

    public bool   $notifyMissed      = false;
    public bool   $notifyRing        = false;
    public int    $throttleSec       = 60;
    public bool   $publicApiEnabled  = false;

    public bool   $initialized       = false;
    public string $lastError         = '';

    public static function load(): BitrixNotifySettings
    {
        return new self();
    }

    public function __construct()
    {
        /** @var ModuleBitrixNotify $settings */
        $settings = ModuleBitrixNotify::findFirst();
        $this->record = $settings;
        if ($settings === null) {
            $this->lastError = 'Module settings are empty';

            return;
        }

        $rawPortal             = trim((string)$settings->portal_url);
        $parsed                = $this->parseWebhookUrl($rawPortal);
        $this->portal          = $parsed['origin'];
        $this->portalOrigin    = $parsed['origin'];
        $this->webhookIndex    = $parsed['index'];
        $this->webhookUrlMode  = ($parsed['token'] !== '');
        $this->notifyMissed    = ((string)$settings->notify_missed === '1');
        $this->notifyRing      = ((string)$settings->notify_ring === '1');
        $this->publicApiEnabled = ((string)$settings->public_api_enabled === '1');
        $this->throttleSec     = max(0, (int)$settings->throttle_sec);
        $this->apiSecret       = (string)($settings->api_secret ?? '');
        $this->defaultRecipients = $this->parseRecipients((string)($settings->recipients ?? ''));
        $this->numbersMap      = $this->parseNumbersMap((string)($settings->numbers_map ?? ''));
        $this->templateMissed  = trim((string)($settings->template_missed ?? '')) !== ''
            ? (string)$settings->template_missed : self::DEFAULT_TEMPLATE_MISSED;
        $this->templateRing    = trim((string)($settings->template_ring ?? '')) !== ''
            ? (string)$settings->template_ring : self::DEFAULT_TEMPLATE_RING;

        $tokenInfo             = $this->resolveToken((string)($settings->webhook_token ?? ''), $parsed['token']);
        $this->token           = $tokenInfo['token'];
        $this->tokenSource     = $tokenInfo['source'];

        if ($this->portal === '' || stripos($this->portal, 'http') !== 0) {
            $this->lastError = 'Portal URL is not set or invalid in module settings';
            Util::sysLogMsg('ModuleBitrixNotify', $this->lastError, LOG_WARNING);

            return;
        }
        if ($this->token === '') {
            $this->lastError = 'Token is not set: module settings / BX_WEBHOOK_TOKEN env / /etc/bitrix-webhook-token';
            Util::sysLogMsg('ModuleBitrixNotify', $this->lastError, LOG_WARNING);

            return;
        }
        $this->initialized = true;
    }

    /**
     * Источник токена по приоритету: настройки -> URL вебхука -> env BX_WEBHOOK_TOKEN -> /etc/bitrix-webhook-token.
     *
     * @return array{token: string, source: string}
     */
    private function resolveToken(string $settingsToken, string $urlToken = ''): array
    {
        $token = trim($settingsToken);
        if ($token !== '') {
            return ['token' => $token, 'source' => self::TOKEN_SOURCE_SETTINGS];
        }
        $token = trim($urlToken);
        if ($token !== '') {
            return ['token' => $token, 'source' => self::TOKEN_SOURCE_WEBHOOK_URL];
        }
        $envToken = getenv('BX_WEBHOOK_TOKEN');
        $token    = $envToken === false ? '' : trim((string)$envToken);
        if ($token !== '') {
            return ['token' => $token, 'source' => self::TOKEN_SOURCE_ENV];
        }
        // Файла с токеном обычно нет, а MikoPBX превращает warning в
        // исключение - даже под "@". Поэтому файл проверяется заранее.
        $fileToken = '';
        $tokenFile = '/etc/bitrix-webhook-token';
        if (is_file($tokenFile) && is_readable($tokenFile)) {
            try {
                $read = file_get_contents($tokenFile);
                $fileToken = $read === false ? '' : $read;
            } catch (\Throwable $e) {
                $fileToken = '';
            }
        }
        $token = trim($fileToken);
        if ($token !== '') {
            return ['token' => $token, 'source' => self::TOKEN_SOURCE_FILE];
        }

        return ['token' => '', 'source' => ''];
    }

    /**
     * Разбор поля «Портал/вебхук». Принимает оба варианта:
     *  1) https://portal/rest/<индекс>/<токен>/            — полный вебхук (рекомендуется)
     *  2) https://portal                                   — только портал (токен отдельно)
     *
     * @return array{origin: string, index: string, token: string}
     */
    private function parseWebhookUrl(string $raw): array
    {
        $result = ['origin' => '', 'index' => '1', 'token' => ''];
        $raw    = trim($raw);
        if ($raw === '') {
            return $result;
        }
        // Путь /rest/<индекс>/<токен> в конце URL (хвостовые слэши — необязательны)
        if (preg_match('~^(https?://[^/]+)/rest/([^/?#]+)/([^/?#]+)~i', $raw, $m)) {
            $result['origin'] = rtrim($m[1], '/');
            $result['index']  = trim($m[2]) !== '' ? $m[2] : '1';
            $result['token']  = $m[3];

            return $result;
        }
        // URL без /rest/ — это просто портал
        if (preg_match('~^(https?://[^/#?]+)~i', $raw, $m)) {
            $result['origin'] = rtrim($m[1], '/');

            return $result;
        }
        // Совсем не URL — оставим как есть, валидация ниже отработает
        $result['origin'] = rtrim($raw, '/');

        return $result;
    }

    /**
     * Разбирает список получателей по умолчанию: числа через запятую/пробел.
     */
    private function parseRecipients(string $raw): array
    {
        $arr = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $arr = array_filter($arr, static function ($v) {
            return ctype_digit((string)$v) && (int)$v > 0;
        });

        return array_values(array_unique($arr));
    }

    /**
     * Разбирает карту персональных получателей: "201=1711", разделители ; или перевод строки.
     *
     * @return array<string,string> хвост номера (10 симв.) => bitrix uid
     */
    private function parseNumbersMap(string $raw): array
    {
        $map = [];
        $lines = preg_split('/[\r\n;]+/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, '=') === false) {
                continue;
            }
            [$ext, $uid] = array_map('trim', explode('=', $line, 2));
            if (!ctype_digit($ext) || !ctype_digit($uid)) {
                continue;
            }
            $map[substr($ext, -10)] = $uid;
        }

        return $map;
    }

    /**
     * Кому отправлять уведомление: если внутренний номер найден в карте —
     * только персональный получатель, иначе — список по умолчанию.
     *
     * @return string[] bitrix user ids
     */
    public function resolveRecipients(string $internalNumber): array
    {
        $tail = substr(trim($internalNumber), -10);
        if ($tail !== '' && isset($this->numbersMap[$tail]) && $this->numbersMap[$tail] !== '') {
            return [$this->numbersMap[$tail]];
        }

        return $this->defaultRecipients;
    }

    /**
     * Лейбл источника токена для REST ответов (только имя источника, не значение).
     */
    public function getStatusData(): array
    {
        return [
            'initialized'         => $this->initialized,
            'error'               => $this->lastError,
            'portal'              => $this->portal,
            'webhookUrlMode'      => $this->webhookUrlMode,
            'tokenSource'         => $this->tokenSource,
            'defaultRecipients'   => $this->defaultRecipients,
            'numbersMapCount'     => count($this->numbersMap),
            'notifyMissed'        => $this->notifyMissed,
            'notifyRing'          => $this->notifyRing,
            'throttleSec'         => $this->throttleSec,
            'publicApiEnabled'    => $this->publicApiEnabled,
            // Секрет не передаём, только признак, что настроен
            'apiSecretSet'        => $this->apiSecret !== '',
        ];
    }
}
