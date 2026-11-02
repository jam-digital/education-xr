<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\LicenseType;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Enums\TicketMode;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\TicketLog;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\DeviceService;
use App\Services\LicenseService;
use App\Services\VrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VrApiTest extends TestCase
{
    use RefreshDatabase;

    private User $school;

    private VrLesson $physics;

    private VrLesson $chemistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->role(Role::Manager)->create());
        $this->school = User::factory()->create(['email' => 'lyceum@example.ua', 'kiosk_pin_code' => '4321']);
        $this->physics = VrLesson::create(['app_key' => 'physics-optics', 'title' => 'Фізика: Оптика', 'android_package_name' => 'com.vrosvita.physics01',
            'apk_storage_path' => 'physics-optics/apk/v1.apk', 'apk_size_bytes' => 4, 'apk_sha256_hash' => str_repeat('a', 64),
            'current_version_code' => 1, 'current_version_name' => '1.0.0']);
        $this->chemistry = VrLesson::create(['app_key' => 'chem-atoms', 'title' => 'Хімія: Атоми', 'android_package_name' => 'com.vrosvita.chem01']);
        auth()->logout();
    }

    private function grant(VrLesson $lesson, LicenseType $type, ?int $tickets = null, ?int $days = null): void
    {
        app(LicenseService::class)->grant($this->school, [$lesson->id], $type, tickets: $tickets, days: $days, audit: false);
    }

    private function login(string $serial = 'Q3-SERIAL-1'): string
    {
        return $this->postJson('/api/v1/vr/device/login', ['hardware_serial' => $serial, 'email' => 'lyceum@example.ua', 'password' => 'password', 'model' => 'Meta Quest 3'])
            ->assertOk()->assertJsonPath('status', 'authorized')->json('token');
    }

    public function test_unknown_headset_gets_pairing_code(): void
    {
        $r = $this->postJson('/api/v1/vr/device/auth', ['hardware_serial' => 'NEW-1', 'battery_level_percent' => 80])->assertStatus(202);
        $r->assertJsonPath('status', 'pairing_required');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $r->json('pairing_code'));
        $this->assertSame(DeviceStatus::PendingPairing, Device::where('hardware_serial', 'NEW-1')->first()->status);

        // повторний запит не міняє код
        $this->postJson('/api/v1/vr/device/auth', ['hardware_serial' => 'NEW-1'])->assertJsonPath('pairing_code', $r->json('pairing_code'));
    }

    public function test_school_login_binds_headset_and_library_lists_only_granted_lessons(): void
    {
        $this->grant($this->physics, LicenseType::TicketPool, tickets: 100);
        $token = $this->login();

        $device = Device::where('hardware_serial', 'Q3-SERIAL-1')->first();
        $this->assertSame($this->school->id, $device->user_id);
        $this->assertSame(DeviceStatus::Active, $device->status);

        $lib = $this->withToken($token)->getJson('/api/v1/vr/library')->assertOk();
        $lib->assertJsonCount(1, 'lessons')
            ->assertJsonPath('lessons.0.app_key', 'physics-optics')
            ->assertJsonPath('lessons.0.access.tickets_remaining', 100)
            ->assertJsonPath('lessons.0.build.version_code', 1)
            ->assertJsonPath('school.kiosk_pin_sha256', hash('sha256', 'Q3-SERIAL-1:4321'));

        $cert = app(VrSigner::class)->verify($lib->json('offline_certificate'));
        $this->assertSame('Q3-SERIAL-1', $cert['serial']);
        $this->assertSame(30, $cert['lessons'][0]['offline_tickets_quota']);

        // після входу окуляри авторизуються самі за серійним номером
        $this->postJson('/api/v1/vr/device/auth', ['hardware_serial' => 'Q3-SERIAL-1'])->assertOk()->assertJsonPath('status', 'authorized');
    }

    public function test_wrong_password_and_foreign_headset_are_rejected(): void
    {
        $this->postJson('/api/v1/vr/device/login', ['hardware_serial' => 'X', 'email' => 'lyceum@example.ua', 'password' => 'nope'])
            ->assertStatus(422)->assertJsonPath('error', 'login_failed');

        $other = User::factory()->create();
        Device::create(['user_id' => $other->id, 'hardware_serial' => 'TAKEN', 'status' => DeviceStatus::Active]);
        $this->postJson('/api/v1/vr/device/login', ['hardware_serial' => 'TAKEN', 'email' => 'lyceum@example.ua', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_pairing_code_from_admin(): void
    {
        $this->actingAs(User::factory()->role(Role::Manager)->create());
        $slot = app(DeviceService::class)->createPairingCode($this->school, 'Шолом каб. 12');
        auth()->logout();
        $this->postJson('/api/v1/vr/device/auth', ['hardware_serial' => 'PICO-7'])->assertStatus(202);

        $this->postJson('/api/v1/vr/device/pair', ['hardware_serial' => 'PICO-7', 'pairing_code' => $slot->pairing_code])
            ->assertOk()->assertJsonPath('device.name', 'Шолом каб. 12');
        $this->assertSame(1, Device::where('hardware_serial', 'PICO-7')->count());
        $this->assertSame(DeviceStatus::Active, $slot->refresh()->status);
    }

    public function test_session_start_charges_one_ticket_and_grace_period_is_free(): void
    {
        $this->grant($this->physics, LicenseType::TicketPool, tickets: 2);
        $token = $this->login();

        $r = $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])->assertOk();
        $r->assertJsonPath('charged', true)->assertJsonPath('access.tickets_remaining', 1);
        $launch = app(VrSigner::class)->verify($r->json('launch_token'));
        $this->assertSame('com.vrosvita.physics01', $launch['package']);
        $this->assertSame('Q3-SERIAL-1', $launch['serial']);

        // перезапуск у межах 90 с не списує
        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])
            ->assertOk()->assertJsonPath('charged', false)->assertJsonPath('mode', TicketMode::GraceReentry->value);

        // після grace period — списує, потім квитків немає
        $this->travel(2)->minutes();
        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])->assertJsonPath('access.tickets_remaining', 0);
        $this->travel(2)->minutes();
        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])->assertStatus(402);
        $this->assertSame(1, TicketLog::where('status', SessionStatus::DeniedNoTickets->value)->count());
        $this->assertSame(0, LessonLicense::first()->tickets_remaining);
    }

    public function test_resume_after_pause_within_lesson_is_free(): void
    {
        $this->grant($this->physics, LicenseType::TicketPool, tickets: 5);
        $token = $this->login();
        $uuid = $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])->json('session_uuid');

        $this->travel(10)->minutes();
        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics', 'resume_session_uuid' => $uuid])
            ->assertJsonPath('charged', false);
        $this->assertSame(4, LessonLicense::first()->tickets_remaining);
    }

    public function test_subscription_does_not_charge_and_lesson_without_access_is_refused(): void
    {
        $this->grant($this->physics, LicenseType::TimeSubscription, days: 30);
        $token = $this->login();

        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'physics-optics'])
            ->assertOk()->assertJsonPath('charged', false)->assertJsonPath('access.type', LicenseType::TimeSubscription->value);
        $this->withToken($token)->postJson('/api/v1/vr/session/start', ['lesson' => 'chem-atoms'])->assertStatus(402);
        $this->withToken($token)->getJson("/api/v1/vr/lessons/{$this->chemistry->id}/download")->assertStatus(403);
    }

    public function test_offline_sync_deduplicates_and_charges_pool(): void
    {
        $this->grant($this->physics, LicenseType::TicketPool, tickets: 10);
        $token = $this->login();
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();
        $payload = ['sessions' => [
            ['session_uuid' => $a, 'app_key' => 'physics-optics', 'started_at' => now()->subHour()->toIso8601String(), 'duration_sec' => 1500],
            ['session_uuid' => $b, 'app_key' => 'physics-optics', 'started_at' => now()->subMinutes(30)->toIso8601String()],
        ]];

        $this->withToken($token)->postJson('/api/v1/vr/offline/sync', $payload)->assertOk()->assertJsonPath('accepted', 2);
        $this->withToken($token)->postJson('/api/v1/vr/offline/sync', $payload)->assertOk()->assertJsonPath('accepted', 0)->assertJsonPath('duplicates', 2);
        $this->assertSame(8, LessonLicense::first()->tickets_remaining);
        $this->assertSame(2, TicketLog::where('mode', TicketMode::Offline->value)->count());
    }

    public function test_download_link_streams_build_and_blocked_headset_loses_access(): void
    {
        Storage::fake('builds_local');
        Storage::disk('builds_local')->put('physics-optics/apk/v1.apk', 'APK!');
        $this->grant($this->physics, LicenseType::TicketPool, tickets: 1);
        $token = $this->login();

        $url = $this->withToken($token)->getJson("/api/v1/vr/lessons/{$this->physics->id}/download")
            ->assertOk()->assertJsonPath('files.apk.sha256', str_repeat('a', 64))->json('files.apk.url');
        $this->get($url)->assertOk();
        $this->get(preg_replace('/signature=\w+/', 'signature=bad', $url))->assertForbidden();

        Device::where('hardware_serial', 'Q3-SERIAL-1')->update(['status' => DeviceStatus::Blocked]);
        $this->withToken($token)->getJson('/api/v1/vr/library')->assertForbidden();
        $this->postJson('/api/v1/vr/device/auth', ['hardware_serial' => 'Q3-SERIAL-1'])->assertForbidden();
    }

    public function test_requests_without_token_are_rejected(): void
    {
        $this->getJson('/api/v1/vr/library')->assertUnauthorized();
        $this->getJson('/api/v1/vr/public-key')->assertOk()->assertJsonPath('alg', 'Ed25519');
    }
}
