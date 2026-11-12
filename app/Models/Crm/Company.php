<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use BelongsToWorkspace, SoftDeletes;

    /** Columns that make a record "theirs" for teammates limited to own records. */
    public const CRM_VISIBILITY = ['owner_id'];

    protected $table = 'crm_companies';

    protected $fillable = ['workspace_id', 'owner_id', 'name', 'domain', 'industry', 'phone', 'email', 'city', 'country', 'notes', 'tags', 'custom_fields', 'created_by'];

    protected $casts = ['tags' => 'array', 'custom_fields' => 'array'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
