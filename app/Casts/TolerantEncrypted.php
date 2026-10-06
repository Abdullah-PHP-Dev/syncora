<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Transitional replacement for Laravel's `encrypted` cast on OAuth token
 * columns (docs/connection-hub-design.md §1a).
 *
 * social_accounts holds a mix of plaintext tokens (written while the
 * `encrypted` cast was commented out) and encrypted ones. The strict cast
 * throws DecryptException on the plaintext rows, so this one:
 *  - reads an encrypted payload by decrypting it (APP_KEY, then
 *    APP_PREVIOUS_KEYS - same encrypter as the strict cast);
 *  - reads plaintext as-is, until `connections:encrypt-tokens` has run;
 *  - reads an encrypted payload that no key can open as null, so a
 *    ciphertext is never sent to a provider as if it were a token;
 *  - always writes encrypted.
 *
 * Once the dry-run reports zero plaintext, swap this for `encrypted`.
 */
class TolerantEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! self::isEncryptedPayload($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            Log::warning('Undecryptable token column - run connections:encrypt-tokens', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'column' => $key,
            ]);

            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }

    /**
     * True when $value has the shape of a Laravel encrypter payload
     * (base64 JSON with iv/value/mac) - whether or not the current keys can
     * open it. Distinguishes "plaintext token" from "ciphertext under a key
     * we no longer have", which a decrypt attempt alone can't.
     */
    public static function isEncryptedPayload(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac'])
            && is_string($payload['iv'])
            && is_string($payload['value']);
    }
}
