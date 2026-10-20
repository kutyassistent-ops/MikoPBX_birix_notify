# ModuleBitrixNotify — HTTP API

Все роуты модуля живут под `/pbxcore/api/modules/ModuleBitrixNotify/`.

Формат ответа: JSON `{"result": true|false, "data": {...}, "messages": [...], "function": "..."}`.

---

## Защищённые роуты (Bearer JWT / API-ключ)

Стандартный модульный роут, ставится через `moduleRestAPICallback`. Аутентификация — обязательна.

### GET `{BaseURL}/pbxcore/api/modules/ModuleBitrixNotify/check`

Статус модуля.

```json
{
  "result": true,
  "data": {
    "initialized": true,
    "error": "",
    "portal": "https://company.bitrix24.ru",
    "tokenSource": "settings",
    "defaultRecipients": ["1711"],
    "numbersMapCount": 2,
    "notifyMissed": true,
    "notifyRing": false,
    "throttleSec": 60,
    "publicApiEnabled": false,
    "apiSecretSet": false,
    "workerMode": "finalize",
    "version": "2.0.9"
  }
}
```

`tokenSource`: `settings` | `webhook-url` | `env` | `file` | `""`. Значения токена и секрета никогда не возвращаются.

### POST `.../ping` — тестовое сообщение

Параметры (POST/GET): `text` (необязателен), `uid` (необязателен — иначе получатели по умолчанию).
Дополнительно возвращает `webhookOwner` (результат `user.get` владельца вебхука).

### POST `.../send` — произвольное сообщение

| Параметр | Обязателен | Описание |
|---|---|---|
| `message` | да | текст (до 512 символов) |
| `uid` | нет | конкретный Битрикс24 user id |
| `tag` | нет | суффикс тега im.notify (объединение сообщений) |

```bash
curl -X POST -H "Authorization: Bearer $KEY" \
  -d "message=Ночной бэкап завершён" -d "uid=1711" \
  https://<PBX>/pbxcore/api/modules/ModuleBitrixNotify/send
```

### POST `.../test-missed` — имитация пропущенного

Параметры: `src` (необязателен, default `+79001234567`), `dst` (необязателен, default `201`).
Прогоняет реальный шаблон и правила маршрутизации получателей, возвращает текст и доставку:

```json
{
  "result": true,
  "data": {
    "message": "Пропущенный вызов 20.10.2026 14:07: +79001234567 на 201 — не отвечено.",
    "recipients": ["1711"],
    "delivery": { "1711": { "ok": true, "error": "" } }
  }
}
```

---

## Публичные роуты (NoAuth=true, без Bearer)

Регистрируются в ядре через `getPBXCoreRESTAdditionalRoutes` (Pattern 2) с `NoAuth=true`.
Работают **только если** в настройках модуля включён тумблер «Разрешить отправку без авторизации» и задан секрет (в поле, несоответствие → `result:false, messages:["Forbidden"]`).

### GET `.../public/status` — health

```json
{
  "result": true,
  "data": {
    "status": "ok",
    "module": "ModuleBitrixNotify",
    "version": "2.0.9",
    "workerMode": "finalize",
    "timestamp": "2026-10-20T14:07:31+03:00"
  }
}
```

`workerMode` зависит от ядра: `finalize` (ядро ≥ 2026.4) или `polling` (старее).
Секретной информации нет, эндпоинт задуман как health-check для мониторинга.

### POST `.../public/send` — отправка по секрету

| Параметр | Обязателен | Описание |
|---|---|---|
| `secret` | да | совпадение по `hash_equals` |
| `text` | да | текст сообщения |
| `uid` | нет | Битрикс24 user id; по умолчанию первый из списка получателей |

```bash
SECRET='мy48hexchars'
PBX='192.168.2.77'
curl -s -X POST "https://${PBX}/pbxcore/api/modules/ModuleBitrixNotify/public/send" \
  -d "secret=${SECRET}" -d "text=Дисковый массив почти заполнен" -d "uid=1711"
```

Особенности:
- **только POST** (GET для `send` не поддерживается) — секрет не попадает в URL и access-логи;
- применяет антифлуд из настроек (`throttle_sec`) — при превышении получите `{result:false, "messages":["Throttled"]}`;
- `uid` — только цифры; его отсутствие берёт первый uid из списка получателей по умолчанию;
- без валидного секрета никаких деталей не раскрывается, попытки логируются в syslog (без значения секрета).

---

## Ошибки

| Ситуация | Признак |
|---|---|
| нет аутентификации на защищённых роутах | HTTP 401 от сервера |
| несогласованный/выключенный public | `result:false`, `messages:["Forbidden"]` |
| токен не задан | `result:false`, `messages[]` с текстом об отсутствии токена |
| нет получателя | `messages[]` «No recipients configured» |
| Битрикс отклонил | `data.delivery[uid].error` содержит `error` и `error_description` от портала |
