<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use BelongsToWorkspace;

    protected $table = 'crm_settings';

    protected $fillable = ['workspace_id', 'settings', 'capture_token'];

    protected $casts = ['settings' => 'array'];
}
