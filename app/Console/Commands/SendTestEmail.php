<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a test email right now (no queue) from the configured sender, e.g. support@socialeaz.com.
 *
 *   php artisan mail:test someone@gmail.com             plain test email, no links
 *   php artisan mail:test someone@gmail.com --verify    the real signup verification email
 *
 * "Sent" means the mail server accepted it. If it never arrives, check the support@ inbox
 * for a bounce (e.g. "550 5.7.1 [CS] Message blocked").
 */
class SendTestEmail extends Command
{
    protected $signature = 'mail:test {email? : Address to send the test to}
                            {--verify : Send the signup verification email (uses a test account that is not saved)}';

    protected $description = 'Send a test email from the configured sender to check SMTP delivery';

    public function handle(): int
    {
        $email = trim((string) ($this->argument('email') ?: $this->ask('Send the test email to which address?')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$email}\" is not a valid email address.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $this->table(['Setting', 'Value'], [
            ['Mailer', $mailer],
            ['Host', config("mail.mailers.{$mailer}.host", '—').':'.config("mail.mailers.{$mailer}.port", '—')],
            ['Encryption / scheme', (string) (config("mail.mailers.{$mailer}.scheme") ?? config("mail.mailers.{$mailer}.encryption") ?? '—')],
            ['Username', (string) config("mail.mailers.{$mailer}.username", '—')],
            ['From', config('mail.from.name').' <'.$from.'>'],
            ['To', $email],
            ['Links use (APP_URL)', config('app.url')],
        ]);
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is \"{$mailer}\": nothing will leave this computer. Set MAIL_MAILER=smtp in .env to really send.");
        }

        $started = microtime(true);
        try {
            if ($this->option('verify')) {
                $user = User::make(['name' => 'Test User', 'email' => $email]);
                $user->id = 0; // not saved; the link in this email won't verify anything
                (new \App\Notifications\Auth\BrandedVerifyEmail)->toMail($user)->send(Mail::mailer());
                $what = 'verification email';
            } else {
                $sentAt = now()->toDayDateTimeString().' '.config('app.timezone');
                $sent = Mail::raw(
                    "Hello,\n\nThis is a test email from ".config('app.name')." sent with `php artisan mail:test` at {$sentAt}.\n\n"
                    ."If you can read this, email sending from {$from} works.\n\nNo action is needed.",
                    fn (Message $m) => $m->to($email)->subject(config('app.name').' test email ('.now()->format('H:i:s').')')
                );
                $what = 'test email';
            }
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Not sent: '.$e->getMessage());
            $this->line('Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_ENCRYPTION/MAIL_SCHEME in .env, then run php artisan config:clear.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf('✓ The mail server accepted the %s for %s (%.1fs).', $what, $email, microtime(true) - $started));
        if (isset($sent) && $sent?->getMessageId()) {
            $this->line('Message ID: '.$sent->getMessageId());
        }
        $this->line('Check the inbox (and Spam). If it does not arrive within a few minutes, look in the '.$from.' inbox for a bounce.');

        return self::SUCCESS;
    }
}
