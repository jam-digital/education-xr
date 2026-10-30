<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DeviceStatus;
use App\Enums\LicenseStatus;
use App\Enums\LicenseType;
use App\Enums\Role;
use App\Models\AdminAuditLog;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\DeviceService;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LicensingTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private VrLesson $vrApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->role(Role::Manager)->create());
        $this->client = User::factory()->create();
        $this->vrApp = VrLesson::create(['app_key' => 'physics-optics-vr', 'title' => 'Фізика: Оптика', 'android_package_name' => 'com.vrosvita.lesson.physics01', 'base_price' => 4500]);
    }

    public function test_ticket_pool_is_topped_up_not_duplicated(): void
    {
        $svc = app(LicenseService::class);
        $svc->grant($this->client, [$this->vrApp->id], LicenseType::TicketPool, tickets: 100);
        $svc->grant($this->client, [$this->vrApp->id], LicenseType::TicketPool, tickets: 50);

        $this->assertSame(1, LessonLicense::count());
        $l = LessonLicense::first();
        $this->assertSame(150, $l->tickets_remaining);
        $this->assertSame(150, $l->tickets_total_purchased);
        $this->assertSame(2, AdminAuditLog::where('action_type', AuditAction::GrantAccess)->count());
    }

    public function test_subscription_extends_from_current_end(): void
    {
        $svc = app(LicenseService::class);
        $svc->grant($this->client, 'ALL', LicenseType::Trial, days: 7);
        $svc->grant($this->client, 'ALL', LicenseType::TimeSubscription, days: 30);

        $l = LessonLicense::sole();
        $this->assertSame(LicenseType::TimeSubscription, $l->license_type);
        $this->assertEqualsWithDelta(now()->addDays(37)->timestamp, $l->valid_until->timestamp, 5);
    }

    public function test_adjust_balance_requires_reason_and_cannot_go_negative(): void
    {
        $svc = app(LicenseService::class);
        [$l] = $svc->grant($this->client, [$this->vrApp->id], LicenseType::TicketPool, tickets: 10);

        $l = $svc->adjustBalance($l, -4, 'Тестові запуски');
        $this->assertSame(6, $l->tickets_remaining);
        $this->assertDatabaseHas('admin_audit_logs', ['action_type' => 'manual_ticket_edit', 'reason_comment' => 'Тестові запуски']);

        $this->expectException(InvalidArgumentException::class);
        $svc->adjustBalance($l, -7, 'Забагато');
    }

    public function test_revoke_blocks_license(): void
    {
        $svc = app(LicenseService::class);
        [$l] = $svc->grant($this->client, [$this->vrApp->id], LicenseType::TicketPool, tickets: 10);
        $svc->revoke([$l->load('lesson', 'user')], 'Борг за договором');

        $this->assertSame(LicenseStatus::Revoked, $l->refresh()->status);
        $this->assertFalse($l->isUsable());
    }

    public function test_device_swap_moves_bindings_and_decommissions_old(): void
    {
        $devices = app(DeviceService::class);
        $old = $devices->register($this->client, 'OLD123', 'Зал №1', 'Meta Quest 3');
        [$l] = app(LicenseService::class)->grant($this->client, [$this->vrApp->id], LicenseType::TicketPool, tickets: 40, deviceIds: [$old->id]);

        $new = $devices->swap($old, 'NEW456', null, 'Розбита лінза');

        $this->assertSame(DeviceStatus::Replaced, $old->refresh()->status);
        $this->assertSame($new->id, $old->replaced_by_device_id);
        $this->assertSame('Зал №1', $new->device_name);
        $this->assertSame([$new->id], $l->refresh()->allowed_device_ids);
        $this->assertSame(40, $l->tickets_remaining);
        $this->assertDatabaseHas('admin_audit_logs', ['action_type' => 'device_swap', 'target_device_id' => $old->id]);
    }

    public function test_serial_of_other_client_is_rejected(): void
    {
        app(DeviceService::class)->register(User::factory()->create(), 'TAKEN1');
        $this->expectException(InvalidArgumentException::class);
        app(DeviceService::class)->register($this->client, 'TAKEN1');
    }

    public function test_pairing_code_is_six_digits(): void
    {
        $d = app(DeviceService::class)->createPairingCode($this->client);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $d->pairing_code);
        $this->assertSame(DeviceStatus::PendingPairing, $d->status);
        $this->assertNull(Device::find($d->id)->hardware_serial);
    }
}
