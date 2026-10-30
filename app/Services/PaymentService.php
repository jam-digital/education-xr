<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\LicenseType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VrLesson;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private LicenseService $licenses,
        private DocumentService $documents,
    ) {}

    /**
     * Виставити рахунок-фактуру закладу освіти (статус «Очікує оплати»).
     * Що нарахувати після оплати, береться з акції або з уроку (за замовчуванням 100 квитків).
     */
    public function createInvoice(User $client, ?VrLesson $lesson, ?Promotion $promo, ?float $amount = null,
        ?int $tickets = null, ?int $days = null, ?string $comment = null): Payment
    {
        [$amount, $tickets, $days] = $this->resolveTerms($lesson, $promo, $amount, $tickets, $days);

        $payment = Payment::create([
            'invoice_number' => Payment::nextInvoiceNumber(),
            'user_id' => $client->id,
            'lesson_id' => $lesson?->id,
            'promotion_id' => $promo?->id,
            'amount' => $amount,
            'currency' => $promo?->currency?->value ?? 'UAH',
            'payment_method' => PaymentMethod::BankInvoice,
            'tickets_added' => $tickets,
            'subscription_days_added' => $days,
            'status' => PaymentStatus::Pending,
            'comment' => $comment,
        ]);
        $payment->update(['invoice_pdf_url' => $this->documents->invoice($payment)]);

        return $payment;
    }

    /**
     * «Оплачено»: фінансовий запис стає completed, генерується акт, і миттєво відкривається доступ.
     */
    public function confirm(Payment $payment, ?string $comment = null): Payment
    {
        if ($payment->status === PaymentStatus::Completed) {
            throw new InvalidArgumentException("Рахунок {$payment->invoice_number} вже оплачено.");
        }

        DB::transaction(function () use ($payment, $comment) {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'confirmed_by_admin_id' => auth()->id(),
                'comment' => trim(($payment->comment ? $payment->comment."\n" : '').($comment ?? '')) ?: null,
            ]);
            $this->applyGrants($payment);
            if ($payment->promotion) {
                $payment->promotion->increment('redemptions_count');
            }
        });

        $payment->update([
            'invoice_pdf_url' => $payment->invoice_pdf_url ?: $this->documents->invoice($payment),
            'act_pdf_url' => $this->documents->act($payment),
        ]);

        Audit::log(AuditAction::ManualPayment, $payment->user, new: [
            'invoice' => $payment->invoice_number,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'grant' => $payment->grantLabel(),
        ], reason: $comment);

        return $payment->refresh();
    }

    /** Оплата через платіжний шлюз (LiqPay): підтвердження без адміна */
    public function confirmFromGateway(Payment $payment, string $transactionId, string $note): Payment
    {
        $payment->update(['gateway_transaction_id' => $transactionId]);

        return $this->confirm($payment, $note);
    }

    public function fail(Payment $payment, string $reason): void
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed, 'comment' => trim($payment->comment."\n".$reason)]);
        }
    }

    /** «Провести оплату вручну»: без попереднього рахунку, одразу оплачено */
    public function recordManual(User $client, ?VrLesson $lesson, ?Promotion $promo, PaymentMethod $method,
        ?float $amount = null, ?int $tickets = null, ?int $days = null, ?string $comment = null): Payment
    {
        [$amount, $tickets, $days] = $this->resolveTerms($lesson, $promo, $amount, $tickets, $days);

        $payment = Payment::create([
            'invoice_number' => Payment::nextInvoiceNumber(),
            'user_id' => $client->id,
            'lesson_id' => $lesson?->id,
            'promotion_id' => $promo?->id,
            'amount' => $amount,
            'currency' => $promo?->currency?->value ?? 'UAH',
            'payment_method' => $method,
            'tickets_added' => $tickets,
            'subscription_days_added' => $days,
            'status' => PaymentStatus::Pending,
        ]);

        return $this->confirm($payment, $comment);
    }

    public function refund(Payment $payment, string $reason): void
    {
        $payment->update(['status' => PaymentStatus::Refunded, 'comment' => trim($payment->comment."\nПовернення: ".$reason)]);
        Audit::log(AuditAction::ManualPayment, $payment->user, old: ['status' => 'completed'],
            new: ['status' => 'refunded', 'invoice' => $payment->invoice_number], reason: $reason);
    }

    /** @return array{0: float, 1: int, 2: int} сума, квитки, дні */
    private function resolveTerms(?VrLesson $lesson, ?Promotion $promo, ?float $amount, ?int $tickets, ?int $days): array
    {
        if (! $lesson && ! $promo) {
            throw new InvalidArgumentException('Оберіть урок або акцію.');
        }
        if ($promo) {
            $tickets ??= $promo->grants_tickets_count ?? 0;
            $days ??= $promo->grants_duration_days ?? 0;
            $amount ??= (float) $promo->price;
        } else {
            $pack = $lesson->default_tickets_count ?: 100;
            if (! $tickets && ! $days) {
                $tickets = $pack;
            }
            $amount ??= (float) $lesson->base_price * max(1, ($tickets ?: $pack) / $pack);
        }

        return [round((float) $amount, 2), (int) $tickets, (int) $days];
    }

    private function applyGrants(Payment $payment): void
    {
        // Пакет лендінгу: конкретний урок, усі уроки предмета («Предметний курс») або всі активні уроки
        $lessons = match (true) {
            (bool) $payment->promotion => $payment->promotion->lessonIds(),
            (bool) $payment->lesson_id => [$payment->lesson_id],
            (bool) $payment->subject_category => VrLesson::where('is_active', true)->where('subject_category', $payment->subject_category)->pluck('id')->all(),
            default => 'ALL',
        };
        $reason = "Оплата {$payment->invoice_number}";

        if ($payment->tickets_added > 0) {
            $this->licenses->grant($payment->user, $lessons, LicenseType::TicketPool, tickets: $payment->tickets_added, reason: $reason, audit: false);
        }
        if ($payment->subscription_days_added > 0) {
            $allLessons = $lessons === 'ALL' || ($payment->promotion && $payment->promotion->coversAllLessons());
            $type = $allLessons ? LicenseType::UnlimitedPromo : LicenseType::TimeSubscription;
            $this->licenses->grant($payment->user, $lessons, $type, days: $payment->subscription_days_added, reason: $reason, audit: false);
        }
    }
}
