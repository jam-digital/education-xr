<?php

namespace Tests\Feature;

use App\Enums\LicenseType;
use App\Enums\PaymentStatus;
use App\Enums\Subject;
use App\Models\Lead;
use App\Models\LessonLicense;
use App\Models\Payment;
use App\Models\User;
use App\Models\VrLesson;
use App\Services\LiqPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    private function app1(string $key = 'a1', Subject $subject = Subject::Chemistry): VrLesson
    {
        return VrLesson::create(['app_key' => $key, 'subject_category' => $subject, 'title' => 'Хімія: Таблиця Менделєєва',
            'android_package_name' => "com.test.$key", 'base_price' => 3500, 'is_active' => true]);
    }

    public function test_landing_lists_lessons_and_packages(): void
    {
        $this->app1();
        $this->get('/')->assertOk()->assertSee('Хімія: Таблиця Менделєєва')->assertSee('Цифрова школа')
            ->assertSee('data-filter="chemistry"', false)->assertSee('/admin/login', false);
    }

    public function test_contact_form_stores_lead(): void
    {
        $this->post('/contact', ['name' => 'Олена', 'email' => 'olena@museum.ua', 'message' => 'Хочемо демо'])
            ->assertRedirect();
        $this->assertDatabaseHas('leads', ['email' => 'olena@museum.ua', 'is_processed' => false]);
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@x.y', 'website' => 'spam'])->assertSessionHasErrors('website');
        $this->assertSame(1, Lead::count());
    }

    public function test_buy_creates_client_and_pending_payment(): void
    {
        $lesson = $this->app1();
        $this->post('/buy', ['package' => 'lesson', 'lesson_id' => $lesson->id, 'name' => 'Школа 117', 'email' => 'school@test.ua', 'company' => 'Школа №117'])
            ->assertRedirect();
        $client = User::where('email', 'school@test.ua')->firstOrFail();
        $payment = Payment::where('user_id', $client->id)->firstOrFail();
        $this->assertSame('lesson', $payment->package);
        $this->assertSame(100, $payment->tickets_added);
        $this->assertSame('UAH', $payment->currency);
        $this->assertStringStartsWith('EDU-', $payment->invoice_number);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->get("/pay/{$payment->id}")->assertOk()->assertSee($payment->invoice_number);

        // пакет запусків без уроку й курс без предмета не приймаються; «Цифрова школа» нічого не потребує
        $this->post('/buy', ['package' => 'lesson', 'name' => 'X', 'email' => 'x@test.ua'])->assertSessionHasErrors('lesson_id');
        $this->post('/buy', ['package' => 'subject', 'name' => 'X', 'email' => 'x@test.ua'])->assertSessionHasErrors('subject');
        $this->post('/buy', ['package' => 'school', 'name' => 'X', 'email' => 'school@test.ua'])->assertRedirect();
        $this->assertSame(1, User::where('email', 'school@test.ua')->count(), 'заклад не дублюється');
    }

    public function test_liqpay_callback_confirms_payment_and_grants_access(): void
    {
        config(['vrosvita.liqpay.public_key' => 'pub', 'vrosvita.liqpay.private_key' => 'priv']);
        $this->app1('a1');
        $this->app1('a2');
        $this->post('/buy', ['package' => 'school', 'name' => 'Ліцей', 'email' => 'm@test.ua'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $liqpay = app(LiqPayService::class);

        $data = base64_encode(json_encode(['order_id' => $payment->invoice_number, 'status' => 'success', 'payment_id' => 777,
            'amount' => (float) $payment->amount, 'currency' => $payment->currency]));

        $this->post('/pay/liqpay/callback', ['data' => $data, 'signature' => 'wrong'])->assertStatus(400);
        $this->post('/pay/liqpay/callback', ['data' => $data, 'signature' => $liqpay->sign($data)])->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('777', $payment->gateway_transaction_id);
        $this->assertSame(2, LessonLicense::where('user_id', $payment->user_id)->where('license_type', LicenseType::UnlimitedPromo)->count());

        // повторний callback нічого не ламає
        $this->post('/pay/liqpay/callback', ['data' => $data, 'signature' => $liqpay->sign($data)])->assertOk();
        $this->assertSame(2, LessonLicense::count());
        $this->get("/pay/{$payment->id}/result")->assertOk()->assertSee('Оплату отримано');
    }

    public function test_subject_course_grants_only_that_subject(): void
    {
        config(['vrosvita.liqpay.public_key' => 'pub', 'vrosvita.liqpay.private_key' => 'priv']);
        $chem = $this->app1('chem1');
        $this->app1('chem2');
        $this->app1('phys1', Subject::Physics);
        $this->post('/buy', ['package' => 'subject', 'subject' => 'chemistry', 'name' => 'Ліцей', 'email' => 'l@test.ua'])->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame('chemistry', $payment->subject_category);
        $this->assertStringContainsString('Хімія', $payment->grantLabel());

        $data = base64_encode(json_encode(['order_id' => $payment->invoice_number, 'status' => 'sandbox', 'payment_id' => 1,
            'amount' => (float) $payment->amount, 'currency' => $payment->currency]));
        $this->post('/pay/liqpay/callback', ['data' => $data, 'signature' => app(LiqPayService::class)->sign($data)])->assertOk();

        $licenses = LessonLicense::where('user_id', $payment->user_id)->with('lesson')->get();
        $this->assertCount(2, $licenses);
        $this->assertTrue($licenses->every(fn ($l) => $l->lesson->subject_category === Subject::Chemistry));
        $this->assertTrue($licenses->contains('lesson_id', $chem->id));
    }

    public function test_lesson_page_and_documents(): void
    {
        $lesson = $this->app1('chem-table');
        $this->app1('chem-other');
        $this->get('/')->assertSee('/uroky/chem-table', false);
        $this->get('/uroky/chem-table')->assertOk()->assertSee('Хімія: Таблиця Менделєєва')
            ->assertSee('og:title', false)->assertSee('LearningResource', false)
            ->assertSee('data-app="'.$lesson->id.'"', false)->assertSee('/uroky/chem-other', false);
        $this->get('/uroky/missing')->assertNotFound();
        VrLesson::where('app_key', 'chem-other')->update(['is_active' => false]);
        $this->get('/uroky/chem-other')->assertNotFound();

        $this->get('/oferta')->assertOk()->assertSee('Публічна оферта')->assertSee(config('vrosvita.seller.edrpou'));
        $this->get('/polityka-konfidentsiinosti')->assertOk()->assertSee('LiqPay');
        $this->get('/kontakty')->assertOk()->assertSee(config('vrosvita.contact.email'));
    }

    public function test_sitemap_and_robots(): void
    {
        $this->app1('chem-table');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/uroky/chem-table', false)->assertSee('/oferta', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: ', false)->assertSee('Disallow: /admin', false);
    }
}
