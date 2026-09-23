<?php

namespace App\Services\EmailMarketingServices;

use App\Models\EmailMarketing\EmailSubaccount;
use App\Models\EmailMarketing\SenderIdentity;

/**
 * Sender Identity (/v3/senders) - requires a physical mailing address
 * (CAN-SPAM), not an extra validation this app invented; SendGrid's own
 * endpoint rejects creation without one.
 */
class SendGridSenderService
{
    public function __construct(protected SendGridClient $client)
    {
    }

    private function forSubaccount(EmailSubaccount $subaccount): SendGridClient
    {
        return $this->client->asSubaccount($subaccount->apiKeyValue(), $subaccount->region);
    }

    public function create(EmailSubaccount $subaccount, array $attributes): array
    {
        if (!$subaccount->isActive()) {
            return ['success' => false, 'error' => 'This account\'s SendGrid subaccount is not active yet.'];
        }

        $response = $this->forSubaccount($subaccount)->post('senders', [
            'nickname' => $attributes['nickname'],
            'from'     => ['email' => $attributes['from_email'], 'name' => $attributes['from_name']],
            'reply_to' => ['email' => $attributes['reply_to'] ?? $attributes['from_email']],
            'address'  => $attributes['address'],
            'address_2' => $attributes['address_2'] ?? null,
            'city'     => $attributes['city'],
            'state'    => $attributes['state'] ?? null,
            'zip'      => $attributes['zip'] ?? null,
            'country'  => $attributes['country'],
        ]);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $data = $response['data'];

        $sender = SenderIdentity::create([
            'user_id'             => $subaccount->user_id,
            'email_subaccount_id' => $subaccount->id,
            'sendgrid_sender_id'  => $data['id'] ?? null,
            'nickname'            => $attributes['nickname'],
            'from_name'           => $attributes['from_name'],
            'from_email'          => $attributes['from_email'],
            'reply_to'            => $attributes['reply_to'] ?? $attributes['from_email'],
            'address'             => $attributes['address'],
            'address_2'           => $attributes['address_2'] ?? null,
            'city'                => $attributes['city'],
            'state'               => $attributes['state'] ?? null,
            'zip'                 => $attributes['zip'] ?? null,
            'country'             => $attributes['country'],
            // SendGrid emails the from_email a verification link
            // immediately on creation - there is no "verified: true"
            // returned synchronously, so the row starts here and only
            // moves to 'verified' once a status check (below) confirms it.
            'status'              => 'verification_sent',
        ]);

        return ['success' => true, 'sender' => $sender];
    }

    /**
     * Real check against SendGrid, same "never fake it locally" rule as
     * domain verification - a sender is only ever marked verified after
     * SendGrid itself confirms the emailed link was clicked.
     */
    public function refreshStatus(SenderIdentity $sender): array
    {
        $response = $this->forSubaccount($sender->subaccount)->get("senders/{$sender->sendgrid_sender_id}");

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $verified = $response['data']['verified']['status'] ?? false;

        $sender->update([
            'status'      => $verified ? 'verified' : $sender->status,
            'verified_at' => $verified ? now() : $sender->verified_at,
        ]);

        return ['success' => true, 'verified' => $verified, 'sender' => $sender->fresh()];
    }

    public function resendVerification(SenderIdentity $sender): array
    {
        $response = $this->forSubaccount($sender->subaccount)->post("senders/{$sender->sendgrid_sender_id}/resend_verification");

        return $response['success']
            ? ['success' => true]
            : ['success' => false, 'error' => $response['error']];
    }
}
