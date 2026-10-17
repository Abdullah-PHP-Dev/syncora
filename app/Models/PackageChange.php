<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageChange extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'actor_id', 'before', 'after'];

    protected $casts = ['before' => 'array', 'after' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
