<?php

namespace App\Services;

use App\Models\Payment;

/**
 * LiqPay Checkout (API v3): форма з data+signature, перевірка підпису callback.
 * Документація: https://www.liqpay.ua/documentation/api/aquiring/checkout/doc
 */
class LiqPayService
{
    public const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';

    public function isConfigured(): bool
    {
        return (bool) (config('vrosvita.liqpay.public_key') && config('vrosvita.liqpay.private_key'));
    }

    /** @return array{data: string, signature: string} */
    public function checkout(Payment $payment): array
    {
        $params = [
            'version' => 3,
            'public_key' => config('vrosvita.liqpay.public_key'),
            'action' => 'pay',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'description' => 'VR-освіта: '.$payment->grantLabel().' ('.$payment->invoice_number.')',
            'order_id' => $payment->invoice_number,
            'language' => 'uk',
            'result_url' => route('pay.result', $payment),
            'server_url' => route('pay.callback'),
        ];

        $data = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));

        return ['data' => $data, 'signature' => $this->sign($data)];
    }

    public function sign(string $data): string
    {
        $key = (string) config('vrosvita.liqpay.private_key');

        return base64_encode(sha1($key.$data.$key, true));
    }

    public function verify(string $data, string $signature): bool
    {
        return $this->isConfigured() && hash_equals($this->sign($data), $signature);
    }

    /** @return array<string, mixed> */
    public function decode(string $data): array
    {
        return json_decode(base64_decode($data), true) ?: [];
    }
}
