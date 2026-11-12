<?php

namespace App\Models\Crm;

use App\Models\Crm\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stage extends Model
{
    use BelongsToWorkspace;

    public const KINDS = ['open', 'won', 'lost'];

    protected $table = 'crm_stages';

    protected $fillable = ['workspace_id', 'pipeline_id', 'name', 'kind', 'probability', 'color', 'sort_order'];

    protected $casts = ['probability' => 'integer'];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }
}
