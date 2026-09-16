<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
	protected $appends = ['name'];
	protected $fillable = [
		'slug',
        'name_en',
		'name_ar',
		'price',
		'discount_price',
		'currency',
		'is_default',
		'is_free',
		'is_popular',
		'is_active',
		'version',
		'sort_order',
		'allow_integration',
		'features',
		'allowed_categories',
		'allowed_product_rules',
		'limits',
		'meta',
	];

	/*
	|--------------------------------------------------------------------------
	| CASTS (VERY IMPORTANT FOR JSON FIELDS)
	|--------------------------------------------------------------------------
	*/
	protected $casts = [
		'features' => 'array',
		'allowed_categories' => 'array',
		'allowed_product_rules' => 'array',
		'limits' => 'array',
		'meta' => 'array',
		'is_default' => 'boolean',
		'is_free' => 'boolean',
		'is_popular' => 'boolean',
		'is_active' => 'boolean',
		'allow_integration' => 'boolean',
	];

	/*
	|--------------------------------------------------------------------------
	| ACCESSOR (AUTO LANGUAGE SWITCH)
	|--------------------------------------------------------------------------
	*/
	public function getNameAttribute()
	{
		return lang()  === 'ar'
			? ($this->name_ar ?: $this->name_en)
			: $this->name_en;
	}

    public function getDescriptionAttribute(): string
    {
        return (string) (data_get($this->meta, 'description_'.app()->getLocale()) ?: data_get($this->meta, 'description_en', ''));
    }

    public function getDisplayFeaturesAttribute(): array
    {
        $features = data_get($this->meta, 'display_features_'.app()->getLocale());
        return $features ?: data_get($this->meta, 'display_features_en', []);
    }

    public function getYearlyPriceAttribute(): float
    {
        return (float) data_get($this->meta, 'yearly_price', (float) $this->price * 12);
    }

	public function getCallbackAttribute(): ?string
	{
		return route('payment.callback');
	}
}
