<?php

namespace App\Models\Messaging;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the X Chat XDK needs to recover one connected X account's private
 * keys from X's secure key backup. `pin` and `juicebox_config` are encrypted
 * at rest (APP_KEY) and hidden from serialization; see the
 * create_x_chat_tables migration.
 */
class XChatCredential extends Model
{
    protected $fillable = [
        'social_account_id', 'x_user_id', 'pin', 'juicebox_config',
        'public_key_version', 'status', 'last_error', 'verified_at',
    ];

    protected $casts = [
        'pin'             => 'encrypted',
        'juicebox_config' => 'encrypted',
        'verified_at'     => 'datetime',
    ];

    protected $hidden = ['pin', 'juicebox_config'];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
