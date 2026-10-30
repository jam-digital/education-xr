<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Subject;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\VrLesson;
use App\Services\LiqPayService;
use App\Services\PaymentService;
use App\Services\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __construct(
        private Storefront $storefront,
        private LiqPayService $liqpay,
        private PaymentService $payments,
    ) {}

    public function index(): View
    {
        return view('landing.index', [
            'lessons' => VrLesson::where('is_active', true)->orderBy('sort')->orderBy('title')->get(),
            'subjects' => Subject::cases(),
            'packages' => Storefront::packages(),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:3000'],
            'website' => ['prohibited'], // honeypot для ботів
        ]);
        unset($data['website']);
        Lead::create($data + ['source' => 'landing']);

        return redirect()->to(route('landing').'#contact')->with('contact_sent', true);
    }

    /** Кнопка «Купити»: заклад + платіж «Очікує оплати», далі сторінка переходу на LiqPay */
    public function buy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'package' => ['required', 'string', 'in:'.implode(',', array_keys(Storefront::packages()))],
            'lesson_id' => ['nullable', 'uuid', 'exists:vr_lessons,id'],
            'subject' => ['nullable', Rule::enum(Subject::class)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['prohibited'],
        ]);
        $lesson = ! empty($data['lesson_id']) ? VrLesson::where('is_active', true)->find($data['lesson_id']) : null;
        $subject = ! empty($data['subject']) ? Subject::from($data['subject']) : null;
        $scope = Storefront::package($data['package'])['scope'];
        if ($scope === 'lesson' && ! $lesson) {
            return back()->withErrors(['lesson_id' => 'Оберіть VR-урок для пакета запусків.'])->withInput();
        }
        if ($scope === 'subject' && ! $subject) {
            return back()->withErrors(['subject' => 'Оберіть предмет для курсу.'])->withInput();
        }

        $payment = $this->storefront->createOrder($data['package'], $data, $lesson, $subject);

        return redirect()->route('pay.show', $payment);
    }

    /** Сторінка з автоматичним переходом на LiqPay (або повідомлення, якщо шлюз не налаштовано) */
    public function pay(Payment $payment): View|RedirectResponse
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->route('pay.result', $payment);
        }

        return view('landing.pay', [
            'payment' => $payment,
            'form' => $this->liqpay->isConfigured() ? $this->liqpay->checkout($payment) : null,
            'checkoutUrl' => LiqPayService::CHECKOUT_URL,
        ]);
    }

    public function result(Payment $payment): View
    {
        return view('landing.result', ['payment' => $payment->refresh()]);
    }

    /** server_url LiqPay: data + signature; успіх → оплата підтверджена, доступ нараховано */
    public function callback(Request $request): Response
    {
        $data = (string) $request->input('data');
        $signature = (string) $request->input('signature');
        if (! $data || ! $this->liqpay->verify($data, $signature)) {
            Log::warning('LiqPay callback: невірний підпис', ['ip' => $request->ip()]);

            return response('bad signature', 400);
        }

        $p = $this->liqpay->decode($data);
        $payment = Payment::where('invoice_number', $p['order_id'] ?? '')->first();
        if (! $payment) {
            return response('unknown order', 404);
        }

        $status = $p['status'] ?? '';
        $txn = (string) ($p['payment_id'] ?? $p['transaction_id'] ?? '');
        if (in_array($status, ['success', 'sandbox', 'subscribed'], true)) {
            if ($payment->status === PaymentStatus::Pending) {
                if ((float) ($p['amount'] ?? 0) + 0.001 < (float) $payment->amount || ($p['currency'] ?? '') !== $payment->currency) {
                    Log::warning('LiqPay callback: сума або валюта не збігається', ['order' => $payment->invoice_number, 'got' => $p]);

                    return response('amount mismatch', 400);
                }
                $this->payments->confirmFromGateway($payment, $txn, "LiqPay {$status}, платіж {$txn}");
            }
        } elseif (in_array($status, ['failure', 'error', 'reversed'], true)) {
            $this->payments->fail($payment, "LiqPay {$status}: ".($p['err_description'] ?? $p['err_code'] ?? ''));
        }
        // wait_accept, processing тощо: лишаємо «Очікує оплати»

        return response('ok');
    }
}
