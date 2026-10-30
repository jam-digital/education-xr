# VR-освіта: освітня VR-платформа (лендінг + адмінка)

Контекст для Claude Code. Мова спілкування із замовником (Дмитро, emuseum): українська.

- Джерело вимог: ТЗ «VR-освіта» v4.0 (docx від Дмитра, 30.10.2026): VR-уроки з фізики, хімії, математики тощо для шкільних VR-окулярів. Код скопійовано з Imersum XR (emuseum-code/imersum-xr) і адаптовано: VrApp → VrLesson (`vr_lessons`, предмет `subject_category`, класи, PDF-методичка), AppLicense → LessonLicense (`lesson_licenses`), клієнти → заклади освіти. Зараз є лендінг і адмінка (розділи 2–3). Кабінет `/cabinet`, API `/api/v1/vr`, лаунчер і плагін VR-Osvita Gate ще не робили.
- Стек: Laravel 13 + Filament 5, PostgreSQL у проді, SQLite локально/в тестах.
- Структура: `app/Enums` (статуси й предмети `Subject` з підписами українською), `app/Models`, `app/Services` (LicenseService, DeviceService, PaymentService, Storefront, LiqPayService, DocumentService, FinanceReport, BuildUploads, Audit), `app/Filament` (ресурси, віджети, `Support/RoleAccess` = рольова модель).
- Роль `Role::Manager` має значення `methodist_manager` (MethodistManager з ТЗ), клієнт = `school_client`.
- Кожна дія адміна, що змінює доступи, запуски, окуляри чи гроші, пишеться через `App\Services\Audit::log()`.
- У замиканнях Filament параметри називати `$query`, `$record`, `$state`, `$data`, `$component` (інакше прийде null).
- `ilike` лише через `Fmt::like()` (тести на SQLite).
- Перед push: `vendor/bin/pint` і `php artisan test`.
- VPS спільний з іншими проєктами: Docker-образи збирати лише на GitHub Actions, деплоїти по одному. Сервіси `edu-*`, псевдонім `eduxr-web` мають бути унікальними на сервері.
- Аватар без фото: кругла плашка з двома літерами (`Filament/Support/InitialsAvatar`).
- Тема світла, сіро-біла (Дмитро, 30.10.2026): адмінка `darkMode(false)` + `public/css/admin.css`, лендінг `public/css/landing.css`, логотип `public/img/logo.svg`.
