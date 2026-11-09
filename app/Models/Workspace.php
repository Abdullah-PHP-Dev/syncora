<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A brand or client. Its data lives on its tenant account (account_id): every
 * module scopes by that user id, so workspaces never share connections, posts,
 * inbox, ads or media. The owner works inside it through UseSellerWorkspace.
 */
class Workspace extends Model
{
    protected $fillable = [
        'owner_id', 'account_id', 'name', 'kind', 'industry', 'country', 'currency',
        'timezone', 'content_language', 'goals', 'last_opened_at',
    ];

    protected function casts(): array
    {
        return ['goals' => 'array', 'last_opened_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /** The owner's original account (created at sign-up). */
    public function isDefault(): bool
    {
        return $this->account_id === $this->owner_id;
    }
}
