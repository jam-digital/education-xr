<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\Payment;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CabinetTest extends TestCase
{
    use RefreshDatabase;

    private User $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = User::factory()->create(['email' => 'lyceum@example.ua', 'school_or_org_name' => 'Ліцей №142']);
    }

    private function lesson(string $key, string $title): VrLesson
    {
        return VrLesson::create(['app_key' => $key, 'title' => $title, 'android_package_name' => "com.vrosvita.$key"]);
    }

    public function test_guest_is_sent_to_login_and_school_logs_in(): void
    {
        $this->get('/cabinet')->assertRedirect('/cabinet/login');
        $this->get('/cabinet/login')->assertOk()->assertSee('Кабінет закладу освіти');

        $this->post('/cabinet/login', ['email' => 'lyceum@example.ua', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/cabinet/login', ['email' => 'Lyceum@Example.ua', 'password' => 'password'])->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($this->school);
    }

    public function test_staff_and_suspended_accounts_cannot_use_cabinet(): void
    {
        User::factory()->role(Role::Manager)->create(['email' => 'methodist@vr-osvita.ua']);
        $this->post('/cabinet/login', ['email' => 'methodist@vr-osvita.ua', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->school->update(['status' => UserStatus::Suspended]);
        $this->post('/cabinet/login', ['email' => 'lyceum@example.ua', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($this->school)->get('/cabinet')->assertRedirect('/cabinet/login');
    }

    public function test_cabinet_shows_only_own_lessons_devices_and_documents(): void
    {
        $physics = $this->lesson('physics-optics', 'Фізика: Оптика');
        $chem = $this->lesson('chem-atoms', 'Хімія: Атоми');
        app(LicenseService::class)->grant($this->school, [$physics->id], LicenseType::TicketPool, tickets: 85, audit: false);
        Device::create(['user_id' => $this->school->id, 'hardware_serial' => 'Q3-OWN', 'device_name' => 'Шолом №3', 'status' => DeviceStatus::Active]);

        $other = User::factory()->create();
        app(LicenseService::class)->grant($other, [$chem->id], LicenseType::TicketPool, tickets: 10, audit: false);
        Device::create(['user_id' => $other->id, 'hardware_serial' => 'Q3-FOREIGN', 'status' => DeviceStatus::Active]);

        $this->actingAs($this->school);
        $this->get('/cabinet')->assertOk()->assertSee('Ліцей №142')->assertSee('Фізика: Оптика')
            ->assertSee('Доступно запусків: 85')->assertDontSee('Хімія: Атоми');
        $this->get('/cabinet/devices')->assertOk()->assertSee('Q3-OWN')->assertDontSee('Q3-FOREIGN');

        Storage::fake('local');
        Storage::disk('local')->put('docs/own.pdf', 'pdf');
        Storage::disk('local')->put('docs/foreign.pdf', 'pdf');
        $own = Payment::create(['invoice_number' => 'EDU-1', 'user_id' => $this->school->id, 'amount' => 3000, 'payment_method' => 'card_online',
            'status' => PaymentStatus::Completed, 'invoice_pdf_url' => 'docs/own.pdf', 'paid_at' => now()]);
        $foreign = Payment::create(['invoice_number' => 'EDU-2', 'user_id' => $other->id, 'amount' => 3000, 'payment_method' => 'card_online',
            'status' => PaymentStatus::Completed, 'invoice_pdf_url' => 'docs/foreign.pdf', 'paid_at' => now()]);

        $this->get('/cabinet/payments')->assertOk()->assertSee('EDU-1')->assertDontSee('EDU-2');
        $this->get("/cabinet/payments/{$own->id}/invoice")->assertOk();
        $this->get("/cabinet/payments/{$foreign->id}/invoice")->assertNotFound();
    }

    public function test_school_changes_pin_and_password(): void
    {
        $this->actingAs($this->school);
        $this->post('/cabinet/settings/pin', ['kiosk_pin_code' => '12a4'])->assertSessionHasErrors('kiosk_pin_code');
        $this->post('/cabinet/settings/pin', ['kiosk_pin_code' => '2580'])->assertRedirect();
        $this->assertSame('2580', $this->school->fresh()->kiosk_pin_code);

        $this->post('/cabinet/settings/password', ['current_password' => 'bad', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('current_password');
        $this->post('/cabinet/settings/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect();
        $this->post('/cabinet/logout');
        $this->post('/cabinet/login', ['email' => 'lyceum@example.ua', 'password' => 'newpass123'])->assertRedirect('/cabinet');
    }
}
