<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'integration_id',
        'credentials',
        'is_enabled',
        'last_synced_at',
    ];

    protected $casts = [
        // Encrypted at rest - Eloquent decrypts/re-encrypts transparently on
        // read/write, so $userIntegration->credentials always behaves like a
        // plain array in application code.
        'credentials'     => 'encrypted:array',
        'is_enabled'      => 'boolean',
        'last_synced_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
