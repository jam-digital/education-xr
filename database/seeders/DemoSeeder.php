<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PromoType;
use App\Enums\ReleaseChannel;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Enums\Subject;
use App\Enums\TicketMode;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\TicketLog;
use App\Models\User;
use App\Models\VrLesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Демо-дані: 11 VR-уроків з 7 предметів, 8 закладів освіти, окуляри з телеметрією, ліцензії, платежі й 30 днів запусків */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (VrLesson::exists()) {
            return;
        }
        mt_srand(2026);

        $lessons = collect([
            ['physics-optics-vr', Subject::Physics, '8–9 класи', 'Фізика: Геометрична оптика та лінзи', 'com.vrosvita.lesson.physics01', 'Хід променів крізь лінзи й призми, побудова зображень, оптична лава у віртуальній лабораторії.', 3000, 2.1],
            ['physics-electricity-lab', Subject::Physics, '8 клас', 'Фізика: Електричні кола та закон Ома', 'com.vrosvita.lesson.physics02', 'Учні збирають послідовні й паралельні кола, вимірюють струм і напругу без ризику для обладнання.', 3000, 1.8],
            ['physics-mechanics-vr', Subject::Physics, '7–9 класи', 'Фізика: Закони Ньютона в невагомості', 'com.vrosvita.lesson.physics03', 'Досліди з інерцією, силою та прискоренням на борту орбітальної станції.', 3000, 2.6],
            ['chemistry-periodic-lab', Subject::Chemistry, '7–9 класи', 'Хімія: Молекулярні сполуки та таблиця Менделєєва', 'com.vrosvita.lesson.chemistry01', 'Інтерактивна таблиця Менделєєва: будова атома, валентність, складання молекул руками.', 3500, 2.4],
            ['chemistry-reactions-lab', Subject::Chemistry, '8–11 класи', 'Хімія: Безпечна лабораторія реакцій', 'com.vrosvita.lesson.chemistry02', 'Реакції обміну, горіння та нейтралізації, які небезпечно проводити в шкільному кабінеті.', 3500, 3.0],
            ['math-geometry-3d', Subject::Mathematics, '10–11 класи', 'Математика: Стереометрія в просторі', 'com.vrosvita.lesson.math01', 'Перерізи многогранників, тіла обертання й об’єми: фігури можна обійти й розрізати.', 2500, 1.2],
            ['math-functions-vr', Subject::Mathematics, '9–11 класи', 'Математика: Графіки функцій у 3D', 'com.vrosvita.lesson.math02', 'Параметри функції змінюються контролером, а графік перебудовується навколо учня.', 2500, 0.9],
            ['biology-cell-vr', Subject::Biology, '8–9 класи', 'Біологія: Подорож усередину клітини', 'com.vrosvita.lesson.biology01', 'Органели рослинної та тваринної клітини зсередини, поділ клітини в динаміці.', 3000, 2.2],
            ['astronomy-solar-system', Subject::Astronomy, '5–11 класи', 'Астрономія: Сонячна система', 'com.vrosvita.lesson.astro01', 'Масштаби планет, орбіти й фази Місяця у відкритому космосі.', 2500, 2.8],
            ['history-kyiv-rus', Subject::History, '7 клас', 'Історія: Київ часів Ярослава Мудрого', 'com.vrosvita.lesson.history01', 'Реконструкція Києва XI століття: Софійський собор, Золоті ворота, княжий двір.', 2500, 3.4],
            ['stem-robotics-lab', Subject::StemGeneral, '5–9 класи', 'STEM: Збери свого робота', 'com.vrosvita.lesson.stem01', 'Конструювання робота з модулів, датчики й прості алгоритми руху.', 2000, 1.5],
        ])->map(fn ($a, $i) => VrLesson::create([
            'app_key' => $a[0], 'subject_category' => $a[1], 'recommended_grades' => $a[2], 'title' => $a[3],
            'android_package_name' => $a[4], 'description' => $a[5],
            'base_price' => $a[6], 'default_tickets_count' => 100, 'grace_period_seconds' => 90,
            'max_session_duration_minutes' => 45, 'sort' => $i, 'is_active' => true,
            'released_at' => now()->subDays([400, 300, 20, 380, 12, 200, 150, 90, 260, 330, 5][$i]),
            'current_version_code' => 10 + $i, 'current_version_name' => '1.'.($i % 4).'.'.($i + 2),
            'release_channel' => ReleaseChannel::Production,
            'apk_storage_path' => "builds/{$a[0]}/demo.apk", 'apk_size_bytes' => (int) (180 + $i * 23) * 1024 * 1024,
            'apk_sha256_hash' => hash('sha256', $a[0].'apk'),
            'obb_storage_path' => "builds/{$a[0]}/demo.obb", 'obb_size_bytes' => (int) ($a[7] * 1024 ** 3),
            'obb_sha256_hash' => hash('sha256', $a[0].'obb'),
        ]));
        foreach ($lessons as $lesson) {
            $lesson->builds()->create([
                'version_code' => $lesson->current_version_code, 'version_name' => $lesson->current_version_name,
                'channel' => ReleaseChannel::Production, 'hash_status' => 'ready', 'published_at' => now()->subDays(20),
                ...$lesson->only(['apk_storage_path', 'apk_size_bytes', 'apk_sha256_hash', 'obb_storage_path', 'obb_size_bytes', 'obb_sha256_hash']),
            ]);
        }

        $clients = collect([
            ['Ліцей №142 м. Києва', 'Оксана Коваленко', '02223445', 'lyceum142@example.ua', 'Київ', 8],
            ['Гімназія №1 м. Львова', 'Тарас Гнатюк', '34567812', 'gym1.lviv@example.ua', 'Львів', 6],
            ['Харківський фізико-математичний ліцей №27', 'Максим Бондар', '43218765', 'fml27@example.ua', 'Харків', 10],
            ['Ірпінська громада: відділ освіти', 'Ірина Мельник', '38800112', 'osvita.irpin@example.ua', 'Ірпінь', 4],
            ['Одеська ЗОШ №35', 'Наталія Литвин', '02220011', 'school35.odesa@example.ua', 'Одеса', 5],
            ['Вінницький ліцей №7', 'Сергій Петренко', '24561230', 'lyceum7.vn@example.ua', 'Вінниця', 3],
            ['Освітній центр «STEM Lab»', 'Андрій Шевчук', '3012456789', 'stemlab@example.ua', 'Київ', 4],
            ['Бучанська гімназія №3 (пілот)', 'Віра Ткаченко', '24561231', 'bucha3@example.ua', 'Буча', 2],
        ])->map(fn ($c) => tap(User::create([
            'school_or_org_name' => $c[0], 'contact_person_name' => $c[1], 'edrpou_tax_id' => $c[2], 'email' => $c[3], 'city' => $c[4],
            'phone' => '+38050'.mt_rand(1000000, 9999999), 'password' => 'password', 'role' => Role::Client,
            'legal_address' => "Україна, м. {$c[4]}, вул. Шкільна, ".mt_rand(1, 50),
            'iban_treasury' => 'UA'.mt_rand(10, 99).'3052990000026000'.mt_rand(10000000, 99999999),
            'kiosk_pin_code' => str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'low_balance_threshold' => 10,
        ]), fn ($u) => $u->setAttribute('_devices', $c[5])));

        $models = ['Meta Quest 3', 'Meta Quest 3', 'Meta Quest 3S', 'Meta Quest 2', 'Pico 4'];
        $n = 0;
        foreach ($clients as $ci => $client) {
            $count = $client->getAttribute('_devices');
            unset($client->_devices);
            $myLessons = $lessons->shuffle()->take(mt_rand(2, 6));
            for ($i = 1; $i <= $count; $i++) {
                $n++;
                $installed = $myLessons->take(mt_rand(1, $myLessons->count()))->mapWithKeys(fn ($a) => [$a->app_key => ['title' => $a->title, 'version' => $a->current_version_name]])->all();
                $syncAgo = match (true) {
                    $n % 9 === 0 => 60 * 24 * 7, $n % 3 === 0 => mt_rand(30, 600), default => mt_rand(0, 8)
                };
                Device::create([
                    'user_id' => $client->id,
                    'hardware_serial' => strtoupper('2G0Y'.Str::random(10)),
                    'device_name' => ['Кабінет Фізики', 'Кабінет Хімії', 'Кабінет Математики'][$i % 3].' — Парта №'.$i,
                    'model' => $models[$n % 5], 'os_version' => 'Android 12 / v'.mt_rand(66, 72),
                    'client_hub_version' => '0.9.'.mt_rand(0, 4),
                    'battery_level_percent' => mt_rand(8, 100), 'free_storage_bytes' => mt_rand(3, 90) * 1024 ** 3,
                    'installed_lessons_state' => $installed, 'status' => DeviceStatus::Active,
                    'last_sync_at' => now()->subMinutes($syncAgo), 'last_ip' => '91.'.mt_rand(200, 250).'.'.mt_rand(0, 255).'.'.mt_rand(1, 254),
                    'last_monotonic_clock_ms' => mt_rand(1e6, 9e8),
                    'clock_tamper_detected_at' => $n === 7 ? now()->subHours(5) : null,
                ]);
            }

            foreach ($myLessons as $k => $lesson) {
                $sub = ($ci + $k) % 4 === 0;
                $total = $sub ? 0 : 100 * mt_rand(1, 3);
                $consumed = $sub ? 0 : min($total - 2, mt_rand(20, $total));
                if (! $sub && $k === 1 && $ci % 2 === 0) {
                    $consumed = $total - mt_rand(3, 9); // для «Розумних сповіщень»: запуски закінчуються
                }
                LessonLicense::create([
                    'user_id' => $client->id, 'lesson_id' => $lesson->id,
                    'license_type' => $sub ? ($ci === 7 ? LicenseType::Trial : LicenseType::TimeSubscription) : LicenseType::TicketPool,
                    'status' => LicenseStatus::Active,
                    'tickets_total_purchased' => $total, 'tickets_consumed' => $consumed, 'tickets_remaining' => $total - $consumed,
                    'valid_from' => now()->subDays(40),
                    'valid_until' => $sub ? now()->addDays([2, 120, 210, 1][$k % 4])->setTime(23, 59) : null,
                    'auto_renew' => $k === 0 && $ci % 2 === 0, 'allowed_device_ids' => ['ALL'],
                ]);
            }
        }
        // Окуляри, що очікують прив’язки за PIN
        Device::create(['user_id' => $clients[2]->id, 'device_name' => 'Шолом Хімія №11', 'status' => DeviceStatus::PendingPairing,
            'pairing_code' => '849102', 'pairing_expires_at' => now()->addHours(20)]);

        // Запуски за 30 днів
        $licenses = LessonLicense::with('user.devices')->get();
        $rows = [];
        foreach ($licenses as $l) {
            $devices = $l->user->devices->where('status', DeviceStatus::Active)->values();
            if ($devices->isEmpty()) {
                continue;
            }
            $sessions = $l->license_type === LicenseType::TicketPool ? $l->tickets_consumed : mt_rand(30, 120);
            for ($s = 0; $s < $sessions; $s++) {
                $start = now()->subMinutes(mt_rand(5, 60 * 24 * 30));
                $dur = mt_rand(6 * 60, 40 * 60);
                $offline = mt_rand(0, 9) === 0;
                $crash = mt_rand(0, 30) === 0;
                $rows[] = [
                    'id' => (string) Str::uuid(), 'license_id' => $l->id, 'user_id' => $l->user_id, 'lesson_id' => $l->lesson_id,
                    'device_id' => $devices[mt_rand(0, $devices->count() - 1)]->id, 'session_uuid' => (string) Str::uuid(),
                    'mode' => $offline ? TicketMode::Offline->value : TicketMode::Online->value,
                    'tickets_delta' => $l->license_type === LicenseType::TicketPool ? -1 : 0,
                    'started_at' => $start, 'ended_at' => $start->copy()->addSeconds($dur),
                    'synced_at' => $offline ? $start->copy()->addHours(mt_rand(1, 48)) : $start,
                    'session_duration_sec' => $dur, 'status' => SessionStatus::Completed->value,
                    'created_at' => $start, 'updated_at' => $start,
                ];
                if ($crash) {
                    $re = $start->copy()->addSeconds(40);
                    $rows[] = ['id' => (string) Str::uuid(), 'license_id' => $l->id, 'user_id' => $l->user_id, 'lesson_id' => $l->lesson_id,
                        'device_id' => end($rows)['device_id'], 'session_uuid' => (string) Str::uuid(),
                        'mode' => TicketMode::GraceReentry->value, 'tickets_delta' => 0, 'started_at' => $re, 'ended_at' => null,
                        'synced_at' => $re, 'session_duration_sec' => null, 'status' => SessionStatus::CrashedGraceReused->value,
                        'created_at' => $re, 'updated_at' => $re];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            TicketLog::insert($chunk);
        }

        // Акції
        $bundle = Promotion::create([
            'title' => 'Цифрова школа: усі VR-уроки на навчальний рік', 'promo_type' => PromoType::AllSubjectsBundle,
            'description' => 'Оплатіть 100 000 грн і отримайте повний безлімітний доступ до всіх VR-уроків з Фізики, Хімії, Математики та інших предметів на весь навчальний рік.',
            'price' => 100000, 'currency' => 'UAH', 'included_lesson_ids' => ['ALL'], 'grants_duration_days' => 365,
            'target_user_ids' => ['ALL'], 'is_active' => true, 'starts_at' => now()->subDays(10), 'expires_at' => now()->addMonths(2),
        ]);
        Promotion::create([
            'title' => 'Комплект «Фізика + Хімія + Математика» на семестр', 'promo_type' => PromoType::SubjectPack,
            'description' => 'Усі уроки трьох базових предметів без обмежень на 180 днів.',
            'price' => 45000, 'currency' => 'UAH', 'grants_duration_days' => 180,
            'included_lesson_ids' => $lessons->filter(fn ($l) => in_array($l->subject_category, [Subject::Physics, Subject::Chemistry, Subject::Mathematics], true))->pluck('id')->values()->all(),
            'target_user_ids' => ['ALL'], 'is_active' => true, 'starts_at' => now()->subDays(5), 'expires_at' => now()->addMonths(1),
        ]);
        Promotion::create([
            'title' => 'Новий урок: STEM «Збери свого робота» — 50 запусків у подарунок', 'promo_type' => PromoType::NewLessonRelease,
            'price' => 0, 'currency' => 'UAH', 'included_lesson_ids' => [$lessons[10]->id], 'grants_tickets_count' => 50,
            'target_user_ids' => ['ALL'], 'is_active' => true, 'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays(27),
        ]);
        Promotion::create([
            'title' => 'Пробний тиждень для нових шкіл', 'promo_type' => PromoType::TrialOffer, 'price' => 0, 'currency' => 'UAH',
            'included_lesson_ids' => [$lessons[0]->id, $lessons[3]->id, $lessons[5]->id], 'grants_duration_days' => 7,
            'target_user_ids' => [$clients[7]->id], 'is_active' => true, 'starts_at' => now()->subDays(1),
        ]);
        foreach (['GRANT-7K2M-Q9WD', 'GRANT-PL4X-82NN', 'GRANT-ZT6R-1HVA'] as $code) {
            Promotion::create(['title' => 'Грант «Нова українська школа»', 'promo_type' => PromoType::PromoCode, 'promo_code_str' => $code,
                'currency' => 'UAH', 'included_lesson_ids' => ['ALL'], 'grants_tickets_count' => 100, 'target_user_ids' => ['ALL'],
                'max_redemptions' => 1, 'batch_label' => 'Грант НУШ 2026', 'is_active' => true, 'expires_at' => now()->addMonths(3)]);
        }

        // Платежі
        $seq = 0;
        foreach ($licenses as $l) {
            $paidAt = now()->subDays(mt_rand(0, 60))->subMinutes(mt_rand(0, 600));
            $card = mt_rand(0, 1) === 1;
            $seq++;
            Payment::create([
                'invoice_number' => 'EDU-'.now()->year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'user_id' => $l->user_id, 'lesson_id' => $l->lesson_id, 'amount' => $l->lesson->base_price * max(1, $l->tickets_total_purchased / 100),
                'currency' => 'UAH', 'payment_method' => $card ? PaymentMethod::CardOnline : PaymentMethod::BankInvoice,
                'gateway_name' => $card ? ['WayForPay', 'LiqPay', 'MonoPay'][mt_rand(0, 2)] : 'Казначейство',
                'gateway_transaction_id' => $card ? strtoupper(Str::random(12)) : null,
                'tickets_added' => $l->tickets_total_purchased, 'subscription_days_added' => $l->license_type->isTimeBased() ? 30 : 0,
                'status' => PaymentStatus::Completed, 'paid_at' => $paidAt, 'created_at' => $paidAt, 'updated_at' => $paidAt,
            ]);
        }
        foreach ([[0, $lessons[1], 3000], [3, null, 100000]] as [$ci, $lesson, $amount]) {
            $seq++;
            Payment::create([
                'invoice_number' => 'EDU-'.now()->year.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'user_id' => $clients[$ci]->id, 'lesson_id' => $lesson?->id, 'promotion_id' => $lesson ? null : $bundle->id,
                'amount' => $amount, 'currency' => 'UAH', 'payment_method' => PaymentMethod::BankInvoice,
                'tickets_added' => $lesson ? 100 : 0, 'subscription_days_added' => $lesson ? 0 : 365,
                'status' => PaymentStatus::Pending, 'comment' => 'Рахунок для казначейства, очікує оплати',
            ]);
        }
    }
}
