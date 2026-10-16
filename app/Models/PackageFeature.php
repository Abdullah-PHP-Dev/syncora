<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageFeature extends Model
{
    protected $fillable = ['key', 'name_en', 'name_ar', 'description', 'type', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? ($this->name_ar ?: $this->name_en) : $this->name_en;
    }
}
