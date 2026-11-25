<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;

/** Platform mail is never sent to these addresses (hard bounce, complaint, manual). */
class EmailSuppression extends Model
{
    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['email', 'reason', 'source'];

    public static function blocks(?string $email): bool
    {
        return $email !== null && $email !== '' && static::whereKey(mb_strtolower(trim($email)))->exists();
    }

    public static function add(string $email, string $reason, ?string $source = null): void
    {
        static::updateOrCreate(['email' => mb_strtolower(trim($email))], ['reason' => $reason, 'source' => $source]);
    }
}
