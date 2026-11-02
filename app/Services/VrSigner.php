<?php

namespace App\Services;

use RuntimeException;

/**
 * Ed25519-підпис токенів запуску уроку і офлайн-сертифікатів (ТЗ 5.3, 6.1).
 * Формат токена: base64url(JSON) . "." . base64url(підпис). Публічний ключ віддає GET /api/v1/vr/public-key,
 * його вшивають у клієнт VR-освіта і плагін VR-Osvita Gate.
 */
class VrSigner
{
    private ?string $secret = null;

    public function sign(array $payload): string
    {
        $body = self::b64(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $body.'.'.self::b64(sodium_crypto_sign_detached($body, $this->secretKey()));
    }

    /** Повертає payload, якщо підпис дійсний і exp (якщо є) не минув; інакше null */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        $sig = self::unb64($parts[1]);
        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($sig, $parts[0], $this->publicKey())) {
            return null;
        }
        $payload = json_decode(self::unb64($parts[0]), true);
        if (! is_array($payload) || (isset($payload['exp']) && $payload['exp'] < time())) {
            return null;
        }

        return $payload;
    }

    public function publicKey(): string
    {
        return sodium_crypto_sign_publickey_from_secretkey($this->secretKey());
    }

    public function publicKeyBase64(): string
    {
        return base64_encode($this->publicKey());
    }

    private function secretKey(): string
    {
        if ($this->secret === null) {
            $seed = config('vrosvita.vr.signing_seed');
            $seed = $seed ? base64_decode($seed, true) : hash('sha256', 'vr-osvita-sign|'.config('app.key'), true);
            if (! $seed || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
                throw new RuntimeException('VR_SIGNING_SEED має бути 32 байти в base64.');
            }
            $this->secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));
        }

        return $this->secret;
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
