<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use BelongsToWorkspace;

    protected $table = 'crm_automations';

    protected $fillable = ['workspace_id', 'name', 'trigger', 'trigger_config', 'conditions', 'actions', 'is_active', 'runs_count', 'last_run_at', 'created_by'];

    protected $casts = ['trigger_config' => 'array', 'conditions' => 'array', 'actions' => 'array', 'is_active' => 'boolean', 'last_run_at' => 'datetime'];

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
