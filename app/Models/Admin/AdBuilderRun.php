<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

/** A campaign-builder submission and the Meta objects it created (see FacebookCampaignBuilder). */
class AdBuilderRun extends Model
{
    protected $fillable = ['user_id', 'social_account_id', 'platform', 'idempotency_key', 'payload_hash', 'status', 'results', 'failed_step', 'error'];

    protected $casts = ['results' => 'array'];

    public function result(string $key, $default = null)
    {
        return data_get($this->results ?? [], $key, $default);
    }

    /** Record one created object immediately, so a later failure keeps it. */
    public function remember(string $key, $value): void
    {
        $results = $this->results ?? [];
        data_set($results, $key, $value);
        $this->forceFill(['results' => $results])->save();
    }
}
