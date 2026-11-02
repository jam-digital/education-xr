# API клієнта «VR-освіта» для шкільних VR-окулярів

Базова адреса: `https://<домен>/api/v1/vr`. Формат JSON, кодування UTF-8. Помилки: `{"error": "<код>", "message": "<текст українською для показу в окулярах>"}`.

## Як це працює

1. Клієнт на окулярах (лаунчер, кіоск-режим) при старті зчитує апаратний серійний номер і викликає `POST /device/auth`.
2. Якщо окуляри вже закріплені за школою, сервер одразу видає токен (`status: authorized`).
3. Якщо окуляри нові (`202`, `status: pairing_required`), клієнт показує два способи входу:
   * email і пароль закладу освіти (`POST /device/login`); акаунт школи створюється в адмінці, розділ «Заклади освіти»;
   * 6-значний код прив’язки, який методист згенерував в адмінці (`POST /device/pair`).
   Після входу окуляри закріплюються за школою, і наступні старти проходять автоматично.
4. `GET /library` повертає лише ті VR-уроки, до яких школі відкрито доступ в адмінці, з версіями білдів, залишками запусків, термінами підписок і підписаним офлайн-сертифікатом.
5. `GET /lessons/{id}/download` дає тимчасові посилання на APK і OBB (підтримують Range для дозавантаження).
6. «Розпочати VR-урок»: `POST /session/start` списує запуск і повертає токен запуску на 60 с, який лаунчер передає в APK уроку через Intent extra `VROSVITA_LAUNCH_TOKEN`. Плагін VR-Osvita Gate в уроці перевіряє підпис токена.
7. Без інтернету клієнт працює за офлайн-сертифікатом і пише журнал, а при появі мережі відправляє його в `POST /offline/sync`.

Після авторизації всі запити йдуть із заголовком `Authorization: Bearer <token>`. Новий вхід видає новий токен, старий перестає діяти. `401` означає, що треба знову викликати `/device/auth`; `403 device_blocked` означає, що окуляри або школу заблоковано в адмінці.

## Телеметрія

Поля, які можна передавати в `device/auth`, `device/login`, `device/pair`, `device/heartbeat`, `offline/sync`:

| Поле | Тип | Опис |
| --- | --- | --- |
| `hardware_serial` | string | серійний номер / Android ID (обов’язкове для auth, login, pair) |
| `model` | string | Meta Quest 3, Pico 4… |
| `os_version`, `client_version` | string | версії ОС і клієнта |
| `battery_level_percent` | int 0–100 | заряд |
| `free_storage_bytes` | int | вільна пам’ять |
| `installed_lessons` | object | `{ "<app_key>": "<version_name>" }` |
| `monotonic_clock_ms` | int | `SystemClock.elapsedRealtime()` |
| `clock_tamper` | bool | клієнт виявив відкат годинника; окуляри потраплять у «підозрілу активність» в адмінці |

## Ендпоінти

### POST /device/auth
Тіло: телеметрія. Відповіді:
* `200` `{status: "authorized", token, token_type, device: {id, name, hardware_serial, status}, school: {id, name, city, kiosk_pin_sha256}}`
* `202` `{status: "pairing_required", hardware_serial, pairing_code, pairing_expires_at, message}`
* `403` `device_blocked` або `device_replaced`

`kiosk_pin_sha256` = `sha256("<hardware_serial>:<PIN>")`. Клієнт перевіряє PIN вчителя для виходу з кіоск-режиму локально, сам PIN не передається.

### POST /device/login
Тіло: телеметрія + `email`, `password` (акаунт закладу освіти). Відповідь як у `authorized`, або `422 login_failed` з поясненням (невірний пароль, акаунт заблоковано, окуляри належать іншій школі).

### POST /device/pair
Тіло: телеметрія + `pairing_code` (6 цифр з адмінки). Відповідь як у `authorized`, або `422 pairing_failed`.

### POST /device/heartbeat, POST /device/logout
Оновлення телеметрії без нового токена; вихід (токен анулюється).

### GET /library
```json
{
  "server_time": "2026-11-02T18:00:00+02:00",
  "device": {...}, "school": {...},
  "lessons": [{
    "id": "uuid", "app_key": "physics-optics", "title": "Фізика: Оптика",
    "subject": "physics", "subject_label": "Фізика", "grades": "8-9", "description": "...",
    "android_package_name": "com.vrosvita.physics01",
    "cover_url": "...", "banner_vr_url": "...", "methodical_guide_url": "...", "preview_video_url": "...",
    "grace_period_seconds": 90, "max_session_duration_minutes": 45,
    "build": {"version_code": 12, "version_name": "1.2.0", "channel": "production",
              "apk_size_bytes": 0, "apk_sha256": "...", "obb_size_bytes": 0, "obb_sha256": "..."},
    "access": {"can_start": true, "type": "ticket_pool", "tickets_remaining": 85,
               "valid_until": null, "label": "Доступно запусків: 85"}
  }],
  "offline_certificate": "<токен>",
  "public_key": "<Ed25519, base64>"
}
```
`build` може бути `null`, якщо білд ще не завантажено. Якщо окуляри є в списку тестових для нової бета-версії, `channel` = `beta`. `access.label` готовий для показу на кнопці уроку.

### GET /lessons/{id}/download
```json
{"version_code": 12, "version_name": "1.2.0", "expires_at": "...",
 "files": {"apk": {"url": "...", "size_bytes": 0, "sha256": "...", "obb_target_dir": null},
           "obb": {"url": "...", "size_bytes": 0, "sha256": "...", "obb_target_dir": "/sdcard/Android/obb/<package>/"}}}
```
Посилання дійсні 2 години, підтримують HTTP Range. Після скачування перевірте SHA-256. `403 no_access`, якщо в школи немає доступу до уроку.

### POST /session/start
Тіло: `lesson` (app_key, package name або id), необов’язково `resume_session_uuid`.

Правила списання:
* часова підписка, пробний період, безліміт: нічого не списується;
* квитковий пул: −1 запуск;
* повторний старт того самого уроку на тих самих окулярах протягом `grace_period_seconds` (90 с): безкоштовно, `mode: grace_period_reentry`;
* `resume_session_uuid` сесії, розпочатої не раніше ніж `max_session_duration_minutes` (45 хв) тому: безкоштовно (учні зняли окуляри на паузу).

Відповідь `200`:
```json
{"session_uuid": "uuid", "charged": true, "mode": "online_realtime",
 "launch_token": "<токен>", "launch_token_expires_in": 60, "intent_extra": "VROSVITA_LAUNCH_TOKEN",
 "access": {...}}
```
`402 no_tickets`: запуски вичерпано або підписка завершилась (текст для показу в `message`).

### POST /session/end
Тіло: `session_uuid`, необов’язково `duration_sec`. Позначає урок завершеним (для статистики).

### POST /offline/sync
Тіло: `{"sessions": [{"session_uuid": "uuid", "app_key": "physics-optics", "started_at": "ISO-8601", "duration_sec": 1500}], "clock_tamper": false}`.
Повторна відправка тих самих `session_uuid` безпечна (дедуплікація). Запуски списуються з пулу школи (не нижче нуля). Відповідь: `{accepted, duplicates, unknown_lessons, offline_certificate}`.

### GET /public-key
`{"alg": "Ed25519", "public_key": "<base64>"}`. Ключ вшивається в клієнт і в плагін VR-Osvita Gate.

## Підписані токени

Токен запуску і офлайн-сертифікат мають формат `base64url(JSON).base64url(підпис Ed25519)`; підписується саме перший сегмент (рядок base64url). Перевірка: libsodium `crypto_sign_verify_detached(signature, segment1, public_key)`, потім `exp` (unix-секунди) має бути в майбутньому.

Токен запуску: `{"typ": "launch", "serial", "package", "app_key", "session", "iat", "exp"}` (60 с). Плагін Gate перевіряє підпис, `exp`, що `serial` = серійному номеру цих окулярів і `package` = власному package name уроку.

Офлайн-сертифікат: `{"typ": "offline", "serial", "device_id", "school_id", "iat", "exp", "lessons": [{"app_key", "package", "valid_until", "offline_tickets_quota", "grace_period_seconds"}]}`. `exp` = 7 днів (максимум роботи без мережі). Для підписки `valid_until` = unix-час кінця (0 = безстроково), `offline_tickets_quota` = null. Для квиткового пулу квота = залишок школи, але не більше 30 на окуляри до наступної синхронізації. Клієнт зберігає сертифікат у Android Keystore, локально зменшує квоту при кожному офлайн-запуску і блокує запуск, коли квота 0, минув `valid_until` або `exp`, або виявлено відкат годинника.

## Оплати

`POST /api/v1/webhooks/payment` приймає сповіщення LiqPay (те саме, що `/pay/liqpay/callback`): після успішної оплати школа автоматично отримує доступ, і він з’являється в `/library`.

## Налаштування сервера

| Змінна | Типово | Опис |
| --- | --- | --- |
| `VR_SIGNING_SEED` | з APP_KEY | 32 байти base64, ключ підпису Ed25519 |
| `VR_LAUNCH_TOKEN_TTL` | 60 | секунд дії токена запуску |
| `VR_OFFLINE_TTL_DAYS` | 7 | днів дії офлайн-сертифіката |
| `VR_OFFLINE_QUOTA` | 30 | максимум офлайн-запусків на окуляри між синхронізаціями |
| `VR_DOWNLOAD_TTL` | 120 | хвилин дії посилань на білди |
| `VR_X_ACCEL` | 1 на сервері | nginx віддає файли білдів сам |
