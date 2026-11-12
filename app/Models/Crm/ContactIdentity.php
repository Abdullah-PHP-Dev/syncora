<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A contact's handle on one channel; the key that links future conversations. */
class ContactIdentity extends Model
{
    use BelongsToWorkspace;

    protected $table = 'crm_contact_identities';

    protected $fillable = ['workspace_id', 'contact_id', 'platform', 'external_id', 'handle'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
