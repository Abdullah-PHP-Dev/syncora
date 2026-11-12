<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use BelongsToWorkspace;

    protected $table = 'crm_pipelines';

    protected $fillable = ['workspace_id', 'name', 'is_default', 'sort_order'];

    protected $casts = ['is_default' => 'boolean'];

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
