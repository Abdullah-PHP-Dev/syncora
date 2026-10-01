<?php

namespace App\Models\Messaging;

use Illuminate\Database\Eloquent\Model;

/** A participant's PUBLIC X Chat keys for one key version (cached from GET /2/users/{id}/public_keys). */
class XChatPublicKey extends Model
{
    protected $fillable = [
        'x_user_id', 'public_key_version', 'public_key', 'signing_public_key',
        'identity_public_key_signature', 'fetched_at',
    ];

    protected $casts = ['fetched_at' => 'datetime'];

    /** The XDK's SigningKeyEntry shape. */
    public function toSigningKeyEntry(): array
    {
        return [
            'userId'                     => $this->x_user_id,
            'publicKeyVersion'           => $this->public_key_version,
            'publicKey'                  => $this->signing_public_key,
            'identityPublicKey'          => $this->public_key,
            'identityPublicKeySignature' => $this->identity_public_key_signature,
        ];
    }
}
