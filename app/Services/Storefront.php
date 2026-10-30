<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\Subject;
use App\Enums\UserStatus;
use App\Models\Payment;
use App\Models\User;
use App\Models\VrLesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Замовлення з лендінгу: пакет → заклад (новий або наявний за email) → платіж «Очікує оплати» */
class Storefront
{
    /** @return array<string, array<string, mixed>> */
    public static function packages(): array
    {
        return config('vrosvita.packages', []);
    }

    /** @return array<string, mixed> */
    public static function package(string $key): array
    {
        $pack = static::packages()[$key] ?? null;
        if (! $pack) {
            throw new InvalidArgumentException("Невідомий пакет: {$key}");
        }

        return $pack + ['key' => $key];
    }

    public static function priceLabel(array $pack): string
    {
        $symbol = match ($pack['currency']) {
            'USD' => '$', 'EUR' => '€', default => '₴',
        };
        $amount = number_format((float) $pack['price'], 0, ',', ' ');

        return $pack['currency'] === 'UAH' ? "{$amount} {$symbol}" : "{$symbol}{$amount}";
    }

    /**
     * @param  array{name: string, email: string, company?: ?string, phone?: ?string}  $customer
     */
    public function createOrder(string $packageKey, array $customer, ?VrLesson $lesson = null, ?Subject $subject = null): Payment
    {
        $pack = static::package($packageKey);
        if ($pack['scope'] === 'lesson' && ! $lesson) {
            throw new InvalidArgumentException('Для пакета запусків оберіть VR-урок.');
        }
        if ($pack['scope'] === 'subject' && ! $subject) {
            throw new InvalidArgumentException('Для предметного курсу оберіть предмет.');
        }

        return DB::transaction(function () use ($pack, $customer, $lesson, $subject) {
            $client = User::query()->where('email', $customer['email'])->first();
            if (! $client) {
                $client = User::create([
                    'contact_person_name' => $customer['name'],
                    'email' => $customer['email'],
                    'school_or_org_name' => $customer['company'] ?? null,
                    'phone' => $customer['phone'] ?? null,
                    'password' => Str::random(32),
                    'role' => Role::Client,
                    'status' => UserStatus::Active,
                    'admin_notes' => 'Зареєструвався через лендінг',
                ]);
            }

            return Payment::create([
                'invoice_number' => Payment::nextInvoiceNumber(),
                'user_id' => $client->id,
                'lesson_id' => $pack['scope'] === 'lesson' ? $lesson->id : null,
                'package' => $pack['key'],
                'subject_category' => $pack['scope'] === 'subject' ? $subject->value : null,
                'amount' => round((float) $pack['price'], 2),
                'currency' => $pack['currency'],
                'payment_method' => PaymentMethod::CardOnline,
                'gateway_name' => 'liqpay',
                'tickets_added' => (int) $pack['tickets'],
                'subscription_days_added' => (int) $pack['days'],
                'status' => PaymentStatus::Pending,
                'comment' => 'Пакет «'.$pack['title'].'»'.($pack['scope'] === 'subject' ? ' · '.$subject->getLabel() : '').' з лендінгу',
            ]);
        });
    }
}
