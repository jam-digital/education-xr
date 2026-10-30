<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Subject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(VrLesson::class, 'lesson_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_admin_id');
    }

    /** Наступний номер рахунку виду EDU-2026-00142 */
    public static function nextInvoiceNumber(): string
    {
        $prefix = 'EDU-'.now()->year.'-';
        $last = static::where('invoice_number', 'like', $prefix.'%')->max('invoice_number');
        $n = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Що отримує заклад: «Хімія: Таблиця Менделєєва · 100 запусків» */
    public function grantLabel(): string
    {
        $pack = $this->package ? config("vrosvita.packages.{$this->package}.title") : null;
        $subject = $this->subject_category ? Subject::tryFrom($this->subject_category)?->getLabel() : null;
        $what = $this->promotion?->title ?? $this->lesson?->title
            ?? ($pack ? "Пакет «{$pack}», ".($subject ? "усі уроки предмета «{$subject}»" : 'усі предмети') : '—');
        $parts = array_filter([
            $this->tickets_added ? "{$this->tickets_added} запусків" : null,
            $this->subscription_days_added ? "{$this->subscription_days_added} дн. доступу" : null,
        ]);

        return $what.($parts ? ' · '.implode(', ', $parts) : '');
    }
}
