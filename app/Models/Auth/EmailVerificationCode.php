<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Hashed, short-lived email-verification credentials (see App\Services\Auth\EmailVerification). */
class EmailVerificationCode extends Model
{
    protected $table = 'email_verification_codes';

    protected $fillable = ['user_id', 'email', 'code_hash', 'token_hash', 'code_expires_at', 'token_expires_at', 'attempts', 'sent_at'];

    protected $hidden = ['code_hash', 'token_hash'];

    protected $casts = ['code_expires_at' => 'datetime', 'token_expires_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
