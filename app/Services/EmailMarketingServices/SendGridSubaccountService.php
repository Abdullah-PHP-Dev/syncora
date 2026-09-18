<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Provisions the one SendGrid subuser + API key a seller's whole Email
 * Marketing integration runs through (domains, senders, contacts,
 * campaigns all authenticate as this subuser from here on - see
 * SendGridClient's docblock for why on-behalf-of stops being used after
 * this step).
 */
class SendGridSubaccountService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    public function config(): ?string
    {
        $key = adminSetting('email_marketing.sendgrid.parent_api_key');

        return $key ?: null;
    }

    public function isConfigured(): bool
    {
        return $this->config() !== null;
    }

    /**
     * Idempotent: if a subaccount row already exists for this user (any
     * status other than 'failed'), returns it unchanged instead of
     * calling SendGrid again - a page refresh mid-setup, or a retried
     * request, must never create a second subuser for the same seller.
     * A prior 'failed' attempt is allowed to retry.
     */
    public function provision(User $user): array
    {
        $existing = EmailSubaccount::where('user_id', $user->id)->first();

        if ($existing && $existing->status !== 'failed') {
            return ['success' => true, 'subaccount' => $existing, 'already_existed' => true];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'SendGrid is not configured yet. Add the parent API key under Admin > APIs.'];
        }

        $subaccount = $existing ?: new EmailSubaccount(['user_id' => $user->id]);
        $subaccount->status = 'provisioning';
        $subaccount->save();

        // Deterministic, unique-per-user username - SendGrid usernames
        // must be unique account-wide, and re-deriving it from the user id
        // (rather than a random string) means a retried provision attempt
        // after a partial failure reuses the same username instead of
        // leaving an orphaned one behind on SendGrid's side.
        $username = 'socialeaz_user_' . $user->id;
        $password = Str::random(24) . 'Aa1!';

        $subuserResponse = $this->client->asParent()->post('subusers', [
            'username' => $username,
            'email'    => $user->email,
            'password' => $password,
            // Empty array = shared IP pool - confirmed against SendGrid's
            // docs this session that dedicated IPs are not required to
            // create a subuser.
            'ips'      => [],
        ]);

        if (!$subuserResponse['success']) {
            $subaccount->update(['status' => 'failed', 'error_message' => $subuserResponse['error']]);

            return ['success' => false, 'error' => $subuserResponse['error']];
        }

        $sendgridUserId = $subuserResponse['data']['user_id'] ?? null;

        // API key creation on-behalf-of the new subuser - the last call
        // that uses the parent key at all for this seller. Full Marketing
        // Campaigns + Mail Send access, not Full Access - the spec calls
        // for minimum required permissions rather than an admin-scoped key.
        $keyResponse = $this->client->asParent($username)->post('api_keys', [
            'name'   => 'socialeaz-email-marketing',
            'scopes' => [
                'mail.send',
                'marketing.read', 'marketing.write',
                'sender_verification_eligible',
                'whitelabel.read', 'whitelabel.create', 'whitelabel.update', 'whitelabel.delete', 'whitelabel.validate',
                'user.webhooks.event.settings.read', 'user.webhooks.event.settings.update',
            ],
        ]);

        if (!$keyResponse['success']) {
            $subaccount->update(['status' => 'failed', 'error_message' => $keyResponse['error']]);

            return ['success' => false, 'error' => $keyResponse['error']];
        }

        $subaccount->update([
            'sendgrid_user_id'   => $sendgridUserId,
            'sendgrid_username'  => $username,
            'sendgrid_email'     => $user->email,
            'status'             => 'active',
            'api_key'            => [
                'key'    => $keyResponse['data']['api_key'] ?? null,
                'key_id' => $keyResponse['data']['api_key_id'] ?? null,
            ],
            'last_synced_at'     => now(),
            'error_message'      => null,
        ]);

        return ['success' => true, 'subaccount' => $subaccount->fresh(), 'already_existed' => false];
    }
}
