<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/** A saved contact filter (C-11); the same filters the contacts list takes. */
class Segment extends Model
{
    use BelongsToWorkspace;

    protected $table = 'crm_segments';

    protected $fillable = ['workspace_id', 'name', 'filters', 'created_by'];

    protected $casts = ['filters' => 'array'];
}
