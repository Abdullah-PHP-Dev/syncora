<?php

namespace App\Services\Auth;

use App\Models\Auth\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Auth\BrandedVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Email verification with two interchangeable credentials, sent together in one email:
 *
 *  - a six-digit code (10 minutes, 5 wrong tries) typed or pasted on the verification screen;
 *  - a magic-link token (60 minutes) that opens a confirmation page. Opening the link never
 *    verifies by itself (mail scanners open links); the person confirms with a POST.
 *
 * Only hashes are stored, one row per user. Every send replaces the row (old code and link stop
 * working), and a successful verification deletes it (the other credential stops working too).
 */
class EmailVerification
{
    public const CODE_MINUTES = 10;

    public const LINK_MINUTES = 60;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    // verifyCode() / confirmLink() results
    public const VERIFIED = 'verified';

    public const ALREADY = 'already';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    /**
     * Issues fresh credentials and emails them. Returns false when the mail server refused.
     * $afterResponse: send once the page has been returned (signup); the result is then unknown (true).
     */
    public function send(User $user, bool $afterResponse = false): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }
        [$code, $token] = $this->issue($user);
        $notification = new BrandedVerifyEmail($code, $token);
        if ($afterResponse) {
            \App\Support\AfterResponse::run(fn () => $user->sendAccountEmail($notification), 'verification email');

            return true;
        }

        return $user->sendAccountEmail($notification);
    }

    /** @return array{0: string, 1: string} the plain code and link token, for this one email only */
    public function issue(User $user): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(64);
        EmailVerificationCode::updateOrCreate(['user_id' => $user->id], [
            'email' => $user->email,
            'code_hash' => $this->codeHash($user, $code),
            'token_hash' => hash('sha256', $token),
            'code_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'token_expires_at' => now()->addMinutes(self::LINK_MINUTES),
            'attempts' => 0,
            'sent_at' => now(),
        ]);

        return [$code, $token];
    }

    /** Seconds until another email may be sent (0 = now). */
    public function resendWait(User $user): int
    {
        $sent = EmailVerificationCode::where('user_id', $user->id)->value('sent_at');

        return $sent ? max(0, self::RESEND_SECONDS - (int) now()->diffInSeconds($sent, true)) : 0;
    }

    /** @return string one of the result constants */
    public function verifyCode(User $user, string $code): string
    {
        return DB::transaction(function () use ($user, $code) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasVerifiedEmail()) {
                return self::ALREADY;
            }
            $row = EmailVerificationCode::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $row || $row->email !== $user->email || $row->code_expires_at->isPast()) {
                return self::EXPIRED;
            }
            if ($row->attempts >= self::MAX_ATTEMPTS) {
                return self::LOCKED;
            }
            if (! preg_match('/^\d{6}$/', $code) || ! hash_equals($row->code_hash, $this->codeHash($user, $code))) {
                $row->increment('attempts');

                return $row->attempts >= self::MAX_ATTEMPTS ? self::LOCKED : self::INVALID;
            }
            $this->complete($user, $row);

            return self::VERIFIED;
        });
    }

    /** The account a link token belongs to, without using it (for the confirmation page). */
    public function inspectLink(string $token): array
    {
        $row = $this->rowForToken($token);
        if (! $row || ! $row->user) {
            return [self::INVALID, null];
        }
        if ($row->user->hasVerifiedEmail()) {
            return [self::ALREADY, $row->user];
        }
        if ($row->email !== $row->user->email || $row->token_expires_at->isPast()) {
            return [self::EXPIRED, $row->user];
        }

        return [self::VERIFIED, $row->user]; // valid and unused: may be confirmed
    }

    /** Uses a link token (the person pressed "Confirm"). @return array{0: string, 1: ?User} */
    public function confirmLink(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $row = EmailVerificationCode::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            $user = $row ? User::whereKey($row->user_id)->lockForUpdate()->first() : null;
            if (! $row || ! $user) {
                return [self::INVALID, null];
            }
            if ($user->hasVerifiedEmail()) {
                return [self::ALREADY, $user];
            }
            if ($row->email !== $user->email || $row->token_expires_at->isPast()) {
                return [self::EXPIRED, $user];
            }
            $this->complete($user, $row);

            return [self::VERIFIED, $user];
        });
    }

    /** Where a verified person continues: new sellers connect a social account first. */
    public function destination(User $user): string
    {
        return $user->hasRole('seller') && Route::has('admin.connections.index')
            ? route('admin.connections.index')
            : app(\App\Services\DashboardService::class)->url($user);
    }

    /** "abdullah@example.com" → "ab******@example.com" */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_strlen($local) <= 2 ? 1 : 2;

        return mb_substr($local, 0, $keep).str_repeat('*', max(3, mb_strlen($local) - $keep)).($domain !== '' ? '@'.$domain : '');
    }

    private function complete(User $user, EmailVerificationCode $row): void
    {
        $row->delete(); // both credentials stop working
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }

    private function rowForToken(string $token): ?EmailVerificationCode
    {
        return strlen($token) === 64 ? EmailVerificationCode::with('user')->where('token_hash', hash('sha256', $token))->first() : null;
    }

    private function codeHash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.mb_strtolower($user->email).'|'.$code, (string) config('app.key'));
    }
}
