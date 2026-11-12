<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    use BelongsToWorkspace;

    public const UPDATED_AT = null;

    protected $table = 'crm_automation_runs';

    protected $fillable = ['workspace_id', 'automation_id', 'subject_type', 'subject_id', 'run_key', 'status', 'result'];

    protected $casts = ['result' => 'array'];
}
