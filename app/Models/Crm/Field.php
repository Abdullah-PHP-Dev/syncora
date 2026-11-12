<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/** A custom field definition (S-04). Values live in the record's custom_fields JSON under `key`. */
class Field extends Model
{
    use BelongsToWorkspace;

    public const TYPES = ['text', 'number', 'date', 'dropdown'];

    protected $table = 'crm_fields';

    protected $fillable = ['workspace_id', 'entity', 'key', 'label', 'type', 'options', 'sort_order'];

    protected $casts = ['options' => 'array'];
}
