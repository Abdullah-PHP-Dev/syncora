<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdCreativeMedia extends Model
{
    protected $table = "ad_creative_media";
    protected $fillable = ['ad_creative_id', 'ad_media_id'];

    public function creative(): BelongsTo
    {
        return $this->belongsTo(AdCreative::class, 'ad_creative_id', 'id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(
            AdMedia::class,
            'ad_media_id'
        );
    }
}
