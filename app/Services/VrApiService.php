<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\ReleaseChannel;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Enums\TicketMode;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\LessonBuild;
use App\Models\LessonLicense;
use App\Models\TicketLog;
use App\Models\User;
use App\Models\VrLesson;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Логіка API клієнта «VR-освіта» в шкільних окулярах (ТЗ розділи 5–7):
 * авторизація окулярів, каталог доступних школі уроків, офлайн-сертифікат, списання запусків, офлайн-синхронізація.
 */
class VrApiService
{
    public function __construct(private VrSigner $signer, private BuildUploads $uploads) {}

    // ── Авторизація окулярів ──────────────────────────────────────────

    public function findBySerial(string $serial): ?Device
    {
        return Device::where('hardware_serial', trim($serial))->first();
    }

    /** Новий шолом: запис «очікує прив’язки» з 6-значним кодом (ТЗ 5.1) */
    public function pendingDevice(string $serial, array $telemetry): Device
    {
        $device = $this->findBySerial($serial) ?? new Device(['hardware_serial' => trim($serial), 'status' => DeviceStatus::PendingPairing]);
        if (! $device->pairing_code || $device->pairing_expires_at?->isPast()) {
            $device->pairing_code = $this->freePairingCode();
            $device->pairing_expires_at = now()->addHours(DeviceService::PAIRING_TTL_HOURS);
        }
        $device->fill($this->telemetry($telemetry))->save();

        return $device;
    }

    /** Вхід школи в клієнті email + паролем: окуляри закріплюються за акаунтом закладу */
    public function loginSchool(string $serial, string $email, string $password, array $telemetry): Device
    {
        $school = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $school || ! Hash::check($password, $school->password)) {
            throw new InvalidArgumentException('Невірний email або пароль.');
        }
        if ($school->role !== Role::Client) {
            throw new InvalidArgumentException('Це не акаунт закладу освіти.');
        }
        if ($school->status !== UserStatus::Active) {
            throw new InvalidArgumentException('Акаунт закладу заблоковано. Зверніться до методиста VR-освіти.');
        }

        $device = $this->findBySerial($serial);
        $this->assertBindable($device, $school);
        if (! $device || $device->user_id !== $school->id || $device->status !== DeviceStatus::Active) {
            $device = app(DeviceService::class)->register($school, $serial, $device?->device_name, $telemetry['model'] ?? null);
        }
        $device->fill($this->telemetry($telemetry))->save();

        return $device;
    }

    /** Прив’язка за 6-значним кодом, який методист згенерував в адмінці */
    public function pairByCode(string $serial, string $code, array $telemetry): Device
    {
        $serial = trim($serial);
        $slot = Device::where('pairing_code', $code)->whereNotNull('user_id')
            ->where('status', DeviceStatus::PendingPairing->value)->first();
        if (! $slot || $slot->pairing_expires_at?->isPast()) {
            throw new InvalidArgumentException('Код прив’язки недійсний або прострочений.');
        }
        $existing = $this->findBySerial($serial);
        $this->assertBindable($existing, $slot->user);

        return DB::transaction(function () use ($slot, $existing, $serial, $telemetry) {
            if ($existing && $existing->id !== $slot->id) {
                // шолом уже звертався до сервера і чекав прив’язки: прибираємо цей тимчасовий запис
                $existing->user_id === null ? $existing->delete() : $existing->update(['hardware_serial' => null]);
            }
            $slot->fill(['hardware_serial' => $serial, 'status' => DeviceStatus::Active, 'pairing_code' => null, 'pairing_expires_at' => null]
                + $this->telemetry($telemetry))->save();

            return $slot;
        });
    }

    /** Видає новий токен (старий перестає діяти) */
    public function issueToken(Device $device): string
    {
        $token = Str::random(60);
        $device->forceFill(['api_token_hash' => hash('sha256', $token), 'api_token_issued_at' => now()])->save();

        return $token;
    }

    public function telemetry(array $t): array
    {
        $out = array_filter([
            'model' => $t['model'] ?? null,
            'os_version' => $t['os_version'] ?? null,
            'client_hub_version' => $t['client_version'] ?? null,
            'battery_level_percent' => isset($t['battery_level_percent']) ? max(0, min(100, (int) $t['battery_level_percent'])) : null,
            'free_storage_bytes' => isset($t['free_storage_bytes']) ? (int) $t['free_storage_bytes'] : null,
            'last_monotonic_clock_ms' => isset($t['monotonic_clock_ms']) ? (int) $t['monotonic_clock_ms'] : null,
        ], fn ($v) => $v !== null);

        if (isset($t['installed_lessons']) && is_array($t['installed_lessons'])) {
            $titles = VrLesson::whereIn('app_key', array_keys($t['installed_lessons']))->pluck('title', 'app_key');
            $out['installed_lessons_state'] = collect($t['installed_lessons'])
                ->mapWithKeys(fn ($v, $k) => [$k => ['title' => $titles[$k] ?? $k, 'version' => (string) $v]])->all();
        }
        if (! empty($t['clock_tamper'])) {
            $out['clock_tamper_detected_at'] = now();
        }

        return $out + ['last_sync_at' => now(), 'last_ip' => request()?->ip()];
    }

    public function deviceInfo(Device $device): array
    {
        $school = $device->user;

        return [
            'device' => ['id' => $device->id, 'name' => $device->device_name, 'hardware_serial' => $device->hardware_serial, 'status' => $device->status->value],
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->display_name,
                'city' => $school->city,
                // PIN вчителя для виходу з кіоск-режиму: лише хеш sha256("<serial>:<pin>"), перевірка в окулярах офлайн
                'kiosk_pin_sha256' => hash('sha256', $device->hardware_serial.':'.($school->kiosk_pin_code ?: '0000')),
            ] : null,
        ];
    }

    // ── Каталог і офлайн-сертифікат ───────────────────────────────────

    /** Ліцензії школи, які стосуються цих окулярів, згруповані за уроком */
    public function licensesFor(Device $device): Collection
    {
        return LessonLicense::where('user_id', $device->user_id)
            ->whereIn('status', [LicenseStatus::Active->value, LicenseStatus::Expired->value])
            ->with('lesson')->get()
            ->filter(fn (LessonLicense $l) => $l->lesson?->is_active && $this->appliesTo($l, $device))
            ->groupBy('lesson_id');
    }

    public function library(Device $device): array
    {
        $lessons = $this->licensesFor($device)->map(function (Collection $licenses) use ($device) {
            $lesson = $licenses->first()->lesson;

            return [
                'id' => $lesson->id,
                'app_key' => $lesson->app_key,
                'title' => $lesson->title,
                'subject' => $lesson->subject_category->value,
                'subject_label' => $lesson->subject_category->getLabel(),
                'grades' => $lesson->recommended_grades,
                'description' => $lesson->description,
                'android_package_name' => $lesson->android_package_name,
                'cover_url' => $this->publicUrl($lesson->cover_image_url),
                'banner_vr_url' => $this->publicUrl($lesson->banner_vr_url),
                'methodical_guide_url' => $this->publicUrl($lesson->methodical_guide_pdf_url),
                'preview_video_url' => $lesson->preview_video_url,
                'grace_period_seconds' => $lesson->grace_period_seconds,
                'max_session_duration_minutes' => $lesson->max_session_duration_minutes,
                'build' => $this->buildFor($lesson, $device),
                'access' => $this->accessSummary($licenses),
                'sort' => $lesson->sort,
            ];
        })->sortBy([['sort', 'asc'], ['title', 'asc']])->values()->map(fn ($l) => collect($l)->except('sort')->all())->all();

        return [
            'server_time' => now()->toIso8601String(),
            'lessons' => $lessons,
            'offline_certificate' => $this->offlineCertificate($device),
            'public_key' => $this->signer->publicKeyBase64(),
        ] + $this->deviceInfo($device);
    }

    /** Офлайн-сертифікат (ТЗ 6.1): прив’язаний до серійного номера, з квотою запусків або датою кінця доступу */
    public function offlineCertificate(Device $device): string
    {
        $cap = config('vrosvita.vr.offline_quota_per_device');
        $lessons = $this->licensesFor($device)->map(function (Collection $licenses) use ($cap) {
            $lesson = $licenses->first()->lesson;
            $usable = $licenses->filter->isUsable();
            $time = $usable->filter(fn ($l) => $l->license_type->isTimeBased())
                ->sortByDesc(fn ($l) => $l->valid_until?->timestamp ?? PHP_INT_MAX)->first();
            $tickets = (int) $usable->where('license_type', LicenseType::TicketPool)->sum('tickets_remaining');

            return [
                'app_key' => $lesson->app_key,
                'package' => $lesson->android_package_name,
                'valid_until' => $time ? ($time->valid_until?->timestamp ?? 0) : null,
                'offline_tickets_quota' => $time ? null : min($tickets, $cap),
                'grace_period_seconds' => $lesson->grace_period_seconds,
            ];
        })->values()->all();

        return $this->signer->sign([
            'typ' => 'offline',
            'serial' => $device->hardware_serial,
            'device_id' => $device->id,
            'school_id' => $device->user_id,
            'iat' => time(),
            'exp' => now()->addDays(config('vrosvita.vr.offline_ttl_days'))->timestamp,
            'lessons' => $lessons,
        ]);
    }

    // ── Скачування білдів ─────────────────────────────────────────────

    public function downloads(Device $device, VrLesson $lesson): array
    {
        if (! $this->licensesFor($device)->has($lesson->id)) {
            throw new InvalidArgumentException('У закладу немає доступу до цього уроку.');
        }
        $build = $this->buildFor($lesson, $device);
        if (! $build) {
            throw new InvalidArgumentException('Для уроку ще не завантажено білд.');
        }

        $ttl = now()->addMinutes(config('vrosvita.vr.download_url_ttl_minutes'));
        $files = [];
        foreach (['apk', 'obb'] as $kind) {
            $path = $build['_paths'][$kind];
            if (! $path) {
                continue;
            }
            $files[$kind] = [
                'url' => $this->uploads->isS3()
                    ? $this->uploads->disk()->temporaryUrl($path, $ttl)
                    : URL::temporarySignedRoute('api.vr.file', $ttl, ['path' => base64_encode($path), 'device' => $device->id]),
                'size_bytes' => $build[$kind.'_size_bytes'],
                'sha256' => $build[$kind.'_sha256'],
                'obb_target_dir' => $kind === 'obb' ? "/sdcard/Android/obb/{$lesson->android_package_name}/" : null,
            ];
        }

        return ['version_code' => $build['version_code'], 'version_name' => $build['version_name'], 'expires_at' => $ttl->toIso8601String(), 'files' => $files];
    }

    // ── Сесії уроку ───────────────────────────────────────────────────

    /**
     * Онлайн-старт уроку (ТЗ 5.3, 6.4): grace period і продовження сесії не списують запуск,
     * часова підписка не списує, квитковий пул −1. Видає одноразовий токен запуску на 60 с.
     */
    public function startSession(Device $device, VrLesson $lesson, ?string $resumeUuid = null): array
    {
        $result = DB::transaction(function () use ($device, $lesson, $resumeUuid) {
            $licenses = LessonLicense::where('user_id', $device->user_id)->where('lesson_id', $lesson->id)
                ->active()->lockForUpdate()->get()->filter(fn ($l) => $this->appliesTo($l, $device));

            // 1) Продовження після паузи (зняли окуляри на 5 хв) у межах тривалості уроку
            $previous = $resumeUuid
                ? TicketLog::where('session_uuid', $resumeUuid)->where('device_id', $device->id)->where('lesson_id', $lesson->id)
                    ->whereIn('mode', [TicketMode::Online->value, TicketMode::Offline->value])
                    ->where('status', '!=', SessionStatus::DeniedNoTickets->value)->first()
                : null;
            $resumable = $previous && $previous->started_at->gt(now()->subMinutes($lesson->max_session_duration_minutes));

            // 2) Повторний старт того самого уроку на тих самих окулярах протягом grace period
            $recent = TicketLog::where('device_id', $device->id)->where('lesson_id', $lesson->id)
                ->whereIn('mode', [TicketMode::Online->value, TicketMode::Offline->value])
                ->where('status', '!=', SessionStatus::DeniedNoTickets->value)
                ->where('started_at', '>', now()->subSeconds($lesson->grace_period_seconds))
                ->latest('started_at')->first();

            if ($resumable || $recent) {
                $base = $resumable ? $previous : $recent;
                $log = TicketLog::create([
                    'license_id' => $base->license_id, 'user_id' => $device->user_id, 'lesson_id' => $lesson->id, 'device_id' => $device->id,
                    'session_uuid' => (string) Str::uuid(), 'mode' => TicketMode::GraceReentry, 'tickets_delta' => 0,
                    'started_at' => now(), 'synced_at' => now(), 'status' => SessionStatus::CrashedGraceReused,
                ]);

                return $this->sessionResponse($device, $lesson, $log, $licenses, charged: false);
            }

            $usable = $licenses->filter->isUsable();
            $license = $usable->first(fn ($l) => $l->license_type->isTimeBased())
                ?? $usable->where('license_type', LicenseType::TicketPool)->sortBy('created_at')->first();

            if (! $license) {
                return null;
            }

            $delta = 0;
            if ($license->license_type === LicenseType::TicketPool) {
                $license->decrement('tickets_remaining');
                $license->increment('tickets_consumed');
                $delta = -1;
            }
            $log = TicketLog::create([
                'license_id' => $license->id, 'user_id' => $device->user_id, 'lesson_id' => $lesson->id, 'device_id' => $device->id,
                'session_uuid' => (string) Str::uuid(), 'mode' => TicketMode::Online, 'tickets_delta' => $delta,
                'started_at' => now(), 'synced_at' => now(), 'status' => SessionStatus::Active,
            ]);

            return $this->sessionResponse($device, $lesson, $log, $licenses->map->refresh(), charged: $delta < 0);
        });

        if ($result === null) {
            // відмову фіксуємо поза транзакцією, щоб запис лишився в журналі
            TicketLog::create([
                'user_id' => $device->user_id, 'lesson_id' => $lesson->id, 'device_id' => $device->id,
                'session_uuid' => (string) Str::uuid(), 'mode' => TicketMode::Online, 'tickets_delta' => 0,
                'started_at' => now(), 'synced_at' => now(), 'status' => SessionStatus::DeniedNoTickets,
            ]);
            throw new InvalidArgumentException('Вичерпано ліміт запусків VR-уроку або завершився термін дії навчальної підписки.');
        }

        return $result;
    }

    public function endSession(Device $device, string $uuid, ?int $durationSec): ?TicketLog
    {
        $log = TicketLog::where('session_uuid', $uuid)->where('device_id', $device->id)->first();
        if ($log && $log->status === SessionStatus::Active) {
            $log->update([
                'status' => SessionStatus::Completed,
                'ended_at' => now(),
                'session_duration_sec' => $durationSec ?? (int) $log->started_at->diffInSeconds(now()),
            ]);
        }

        return $log;
    }

    /**
     * Офлайн-синхронізація (ТЗ 6.2–6.3): журнал уроків, проведених без інтернету, з дедуплікацією за session_uuid.
     * Запуски списуються з квиткового пулу школи (не нижче нуля); для підписки фіксуються без списання.
     *
     * @param  array<int, array{session_uuid: string, app_key: string, started_at: string, duration_sec?: int}>  $sessions
     */
    public function offlineSync(Device $device, array $sessions): array
    {
        $accepted = 0;
        $duplicates = 0;
        $unknown = [];
        $lessons = VrLesson::whereIn('app_key', collect($sessions)->pluck('app_key')->unique())->get()->keyBy('app_key');

        foreach ($sessions as $s) {
            if (TicketLog::where('session_uuid', $s['session_uuid'])->exists()) {
                $duplicates++;

                continue;
            }
            $lesson = $lessons[$s['app_key']] ?? null;
            if (! $lesson) {
                $unknown[] = $s['session_uuid'];

                continue;
            }
            $startedAt = Carbon::parse($s['started_at']);

            DB::transaction(function () use ($device, $lesson, $s, $startedAt) {
                $licenses = LessonLicense::where('user_id', $device->user_id)->where('lesson_id', $lesson->id)
                    ->active()->lockForUpdate()->get()->filter(fn ($l) => $this->appliesTo($l, $device));
                $time = $licenses->first(fn ($l) => $l->license_type->isTimeBased() && (! $l->valid_until || $l->valid_until->gte($startedAt)));
                $pool = $licenses->where('license_type', LicenseType::TicketPool)->firstWhere('tickets_remaining', '>', 0);
                $license = $time ?? $pool ?? $licenses->first();

                $delta = 0;
                if (! $time && $pool) {
                    $pool->decrement('tickets_remaining');
                    $pool->increment('tickets_consumed');
                    $delta = -1;
                }
                TicketLog::create([
                    'license_id' => $license?->id, 'user_id' => $device->user_id, 'lesson_id' => $lesson->id, 'device_id' => $device->id,
                    'session_uuid' => $s['session_uuid'], 'mode' => TicketMode::Offline, 'tickets_delta' => $delta,
                    'started_at' => $startedAt, 'ended_at' => isset($s['duration_sec']) ? $startedAt->copy()->addSeconds((int) $s['duration_sec']) : null,
                    'session_duration_sec' => $s['duration_sec'] ?? null, 'synced_at' => now(), 'status' => SessionStatus::Completed,
                ]);
            });
            $accepted++;
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'unknown_lessons' => $unknown,
            'offline_certificate' => $this->offlineCertificate($device->refresh()),
        ];
    }

    // ── Допоміжне ─────────────────────────────────────────────────────

    private function sessionResponse(Device $device, VrLesson $lesson, TicketLog $log, Collection $licenses, bool $charged): array
    {
        $ttl = config('vrosvita.vr.launch_token_ttl');

        return [
            'session_uuid' => $log->session_uuid,
            'charged' => $charged,
            'mode' => $log->mode->value,
            'launch_token' => $this->signer->sign([
                'typ' => 'launch',
                'serial' => $device->hardware_serial,
                'package' => $lesson->android_package_name,
                'app_key' => $lesson->app_key,
                'session' => $log->session_uuid,
                'iat' => time(),
                'exp' => time() + $ttl,
            ]),
            'launch_token_expires_in' => $ttl,
            'intent_extra' => 'VROSVITA_LAUNCH_TOKEN',
            'access' => $this->accessSummary($licenses),
        ];
    }

    private function accessSummary(Collection $licenses): array
    {
        $usable = $licenses->filter->isUsable();
        $time = $usable->filter(fn ($l) => $l->license_type->isTimeBased())
            ->sortByDesc(fn ($l) => $l->valid_until?->timestamp ?? PHP_INT_MAX)->first();
        $tickets = (int) $usable->where('license_type', LicenseType::TicketPool)->sum('tickets_remaining');

        return [
            'can_start' => $usable->isNotEmpty(),
            'type' => $time ? $time->license_type->value : ($tickets > 0 ? LicenseType::TicketPool->value : null),
            'tickets_remaining' => $tickets,
            'valid_until' => $time?->valid_until?->toIso8601String(),
            'label' => $time ? ($time->valid_until ? 'Підписка активна до '.$time->valid_until->format('d.m.Y') : 'Безстроковий доступ')
                : ($tickets > 0 ? "Доступно запусків: {$tickets}" : 'Запуски вичерпано'),
        ];
    }

    /** Остання опублікована версія, або новіша бета, якщо ці окуляри в списку тестових */
    private function buildFor(VrLesson $lesson, Device $device): ?array
    {
        $beta = LessonBuild::where('lesson_id', $lesson->id)->where('channel', ReleaseChannel::Beta->value)
            ->where('version_code', '>', (int) $lesson->current_version_code)->orderByDesc('version_code')->get()
            ->first(fn (LessonBuild $b) => in_array($device->id, $b->beta_device_ids ?? [], true));
        $src = $beta ?? ($lesson->apk_storage_path ? $lesson : null);
        if (! $src) {
            return null;
        }

        return [
            'version_code' => (int) ($beta ? $beta->version_code : $lesson->current_version_code),
            'version_name' => $beta ? $beta->version_name : $lesson->current_version_name,
            'channel' => $beta ? 'beta' : 'production',
            'apk_size_bytes' => $src->apk_size_bytes,
            'apk_sha256' => $src->apk_sha256_hash,
            'obb_size_bytes' => $src->obb_size_bytes,
            'obb_sha256' => $src->obb_sha256_hash,
            '_paths' => ['apk' => $src->apk_storage_path, 'obb' => $src->obb_storage_path],
        ];
    }

    private function appliesTo(LessonLicense $license, Device $device): bool
    {
        $ids = $license->allowed_device_ids ?? ['ALL'];

        return in_array('ALL', $ids, true) || in_array($device->id, $ids, true);
    }

    private function assertBindable(?Device $device, User $school): void
    {
        if ($device?->status === DeviceStatus::Replaced) {
            throw new InvalidArgumentException('Ці окуляри списані після заміни.');
        }
        if ($device?->status === DeviceStatus::Blocked) {
            throw new InvalidArgumentException('Окуляри заблоковано. Зверніться до методиста VR-освіти.');
        }
        if ($device?->user_id && $device->user_id !== $school->id) {
            throw new InvalidArgumentException('Окуляри прив’язані до іншого закладу.');
        }
    }

    private function freePairingCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Device::where('pairing_code', $code)->exists());

        return $code;
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? url($path) : Storage::disk('public')->url($path);
    }
}
