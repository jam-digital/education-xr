<?php

namespace Tests\Feature;

use App\Enums\LicenseType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PromoType;
use App\Enums\Role;
use App\Models\LessonLicense;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\Money;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_then_confirm_generates_act_and_opens_access(): void
    {
        Storage::fake('local');
        $finance = User::factory()->role(Role::FinanceViewer)->create();
        $this->actingAs($finance);
        $client = User::factory()->create(['edrpou_tax_id' => '12345678']);
        $lesson = VrLesson::create(['app_key' => 'a', 'title' => 'Фізика: Оптика', 'android_package_name' => 'com.i.a', 'base_price' => 4500]);

        $svc = app(PaymentService::class);
        $p = $svc->createInvoice($client, $lesson, null);
        $this->assertSame(PaymentStatus::Pending, $p->status);
        $this->assertSame('4500.00', (string) $p->amount);
        $this->assertSame(100, $p->tickets_added);
        Storage::disk('local')->assertExists($p->invoice_pdf_url);
        $this->assertSame(0, LessonLicense::count());

        $p = $svc->confirm($p, 'п/д №77');
        $this->assertSame(PaymentStatus::Completed, $p->status);
        Storage::disk('local')->assertExists($p->act_pdf_url);
        $this->assertSame(100, LessonLicense::sole()->tickets_remaining);
        $this->assertDatabaseHas('admin_audit_logs', ['action_type' => 'manual_payment', 'admin_user_id' => $finance->id]);

        $this->get(route('admin.payment.document', [$p, 'act']))->assertOk();
    }

    public function test_bundle_promotion_grants_unlimited_on_all_apps(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->role(Role::SuperAdmin)->create());
        $client = User::factory()->create();
        foreach (['a', 'b', 'c'] as $k) {
            VrLesson::create(['app_key' => $k, 'title' => $k, 'android_package_name' => "com.i.$k"]);
        }
        $promo = Promotion::create(['title' => 'Рік безліміту', 'promo_type' => PromoType::AllSubjectsBundle, 'price' => 100000,
            'currency' => 'UAH', 'included_lesson_ids' => ['ALL'], 'grants_duration_days' => 365, 'is_active' => true]);

        app(PaymentService::class)->recordManual($client, null, $promo, PaymentMethod::BankInvoice);

        $this->assertSame(3, LessonLicense::where('license_type', LicenseType::UnlimitedPromo->value)->count());
        $this->assertSame(1, $promo->refresh()->redemptions_count);
    }

    public function test_finance_report_downloads_xlsx_and_csv(): void
    {
        $this->actingAs(User::factory()->role(Role::FinanceViewer)->create());
        foreach (['xlsx', 'csv'] as $f) {
            $this->get(route('admin.report.finance', ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString(), 'format' => $f]))
                ->assertOk()->assertDownload();
        }
    }

    public function test_manager_cannot_download_finance_report(): void
    {
        $this->actingAs(User::factory()->role(Role::Manager)->create());
        $this->get(route('admin.report.finance', ['from' => '2026-01-01', 'to' => '2026-12-31', 'format' => 'csv']))->assertForbidden();
    }

    public function test_amount_in_words(): void
    {
        $this->assertSame('Сто тисяч гривень 00 копійок', Money::inWordsUah(100000));
        $this->assertSame('Чотири тисячі п\'ятсот гривень 50 копійок', Money::inWordsUah(4500.5));
        $this->assertSame('Двадцять одна гривня 01 копійка', Money::inWordsUah(21.01));
    }
}
