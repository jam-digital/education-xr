<?php

return [
    // Персонал для сідера (admin@/methodist@/finance@vr-osvita.ua) і демо-дані на порожній базі
    'admin_password' => env('ADMIN_PASSWORD', 'password'),
    'seed_demo' => (bool) env('SEED_DEMO', false),

    // Реквізити продавця для рахунків-фактур і актів
    'seller' => [
        'name' => env('SELLER_NAME', 'ТОВ «ВР-ОСВІТА»'),
        'edrpou' => env('SELLER_EDRPOU', '00000000'),
        'address' => env('SELLER_ADDRESS', 'м. Київ'),
        'iban' => env('SELLER_IBAN', 'UA000000000000000000000000000'),
        'bank' => env('SELLER_BANK', 'АТ «Банк»'),
        'tax_note' => env('SELLER_TAX_NOTE', 'Без ПДВ'),
        'signer' => env('SELLER_SIGNER', 'Директор'),
        'service' => env('SELLER_SERVICE', 'Надання доступу до освітніх VR-уроків платформи «VR-освіта»'),
    ],

    // Сховище білдів APK/OBB: r2 (Cloudflare R2, пряме завантаження з браузера) або builds_local
    'builds_disk' => env('BUILDS_DISK', 'builds_local'),

    // Розмір частини multipart-завантаження, байт (R2/S3: від 5 МБ)
    'upload_part_size' => (int) env('UPLOAD_PART_SIZE', 64 * 1024 * 1024),

    // Розмір частини для локального диска (через PHP; менше за post_max_size)
    'upload_local_part_size' => (int) env('UPLOAD_LOCAL_PART_SIZE', 4 * 1024 * 1024),

    // Лендінг: пакети для кнопок «Купити». Ціни можна змінити тут або через env.
    // scope: lesson = 100 запусків одного VR-уроку; subject = безліміт уроків одного предмета на рік; all = усі предмети на рік.
    'packages' => [
        'lesson' => [
            'title' => 'Пакет запусків',
            'price' => (float) env('PACK_LESSON_PRICE', 3000),
            'currency' => env('PACK_LESSON_CURRENCY', 'UAH'),
            'scope' => 'lesson',
            'tickets' => 100,
            'days' => 0,
            'tagline' => '100 проведень одного VR-уроку',
            'features' => ['100 запусків обраного уроку', 'Усі окуляри класу', 'Офлайн-режим без Wi-Fi', 'Запуски не згорають', 'PDF-методичка для вчителя'],
        ],
        'subject' => [
            'title' => 'Предметний курс',
            'price' => (float) env('PACK_SUBJECT_PRICE', 30000),
            'currency' => env('PACK_SUBJECT_CURRENCY', 'UAH'),
            'scope' => 'subject',
            'tickets' => 0,
            'days' => 365,
            'tagline' => 'Усі VR-уроки одного предмета на навчальний рік',
            'features' => ['Фізика, Хімія або Математика на вибір', 'Безліміт уроків 12 місяців', 'Нові уроки предмета додаються автоматично', 'Статистика проведених уроків'],
        ],
        'school' => [
            'title' => 'Цифрова школа',
            'price' => (float) env('PACK_SCHOOL_PRICE', 100000),
            'currency' => env('PACK_SCHOOL_CURRENCY', 'UAH'),
            'scope' => 'all',
            'tickets' => 0,
            'days' => 365,
            'tagline' => 'Усі предмети без обмежень на весь навчальний рік',
            'features' => ['Фізика, Хімія, Математика та всі інші уроки', 'Безліміт запусків на рік', 'Налаштування окулярів і навчання вчителів', 'Кіоск-режим з PIN-кодом вчителя', 'Рахунок і акт для казначейства', 'Персональний методист'],
        ],
    ],

    // LiqPay: публічний і приватний ключі з кабінету liqpay.ua; sandbox-ключі дають тестові платежі
    'liqpay' => [
        'public_key' => env('LIQPAY_PUBLIC_KEY'),
        'private_key' => env('LIQPAY_PRIVATE_KEY'),
    ],

    // Контакти в підвалі лендінгу
    'contact' => [
        'email' => env('CONTACT_EMAIL', 'hello@vr-osvita.ua'),
        'phone' => env('CONTACT_PHONE', '+380 (67) 000 00 00'),
    ],

    // API клієнта в окулярах (/api/v1/vr, ТЗ розділи 5–7)
    'vr' => [
        // Ed25519-ключ підпису токенів запуску й офлайн-сертифікатів: 32 байти base64. Порожньо = виводиться з APP_KEY
        'signing_seed' => env('VR_SIGNING_SEED'),
        'launch_token_ttl' => (int) env('VR_LAUNCH_TOKEN_TTL', 60),
        'offline_ttl_days' => (int) env('VR_OFFLINE_TTL_DAYS', 7),
        // Скільки запусків квиткового пулу окуляри можуть витратити офлайн до синхронізації
        'offline_quota_per_device' => (int) env('VR_OFFLINE_QUOTA', 30),
        'download_url_ttl_minutes' => (int) env('VR_DOWNLOAD_TTL', 120),
        // nginx віддає білди з диска сам (X-Accel-Redirect на /_builds/), PHP лише перевіряє підпис посилання
        'x_accel' => (bool) env('VR_X_ACCEL', false),
    ],

    // Smart Alerts
    'alerts' => [
        'low_tickets' => 10,
        'expiring_days' => 3,
    ],
];
