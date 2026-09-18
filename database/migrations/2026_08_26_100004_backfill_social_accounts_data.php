<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only migration: copies post_accounts/ad_accounts/message_channels
     * rows into social_accounts (merging into one row when the same
     * platform+platform_account_id+user was independently connected under
     * more than one module - e.g. the same Instagram business account
     * connected for posting, ads, and messaging all end up as one row with
     * all three has_*_permission flags set), then repoints every child
     * table's FK using the old-id -> new-id maps built along the way.
     * Uses DB::table throughout (no Eloquent models) so it keeps working
     * regardless of later model changes.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $postAccountMap = $this->importPostAccounts();
            $adAccountMap = $this->importAdAccounts();
            $messageChannelMap = $this->importMessageChannels();

            $this->repointChildren('posts', 'post_account_id', $postAccountMap);
            $this->repointChildren('post_media', 'post_account_id', $postAccountMap);
            $this->repointChildren('post_comments', 'post_account_id', $postAccountMap);

            foreach (['ad_campaigns', 'ad_adgroups', 'ad_creatives', 'ad_media', 'ads', 'platform_pages'] as $table) {
                $this->repointChildren($table, 'ad_account_id', $adAccountMap);
            }

            $this->repointChildren('conversations', 'message_channel_id', $messageChannelMap);

            foreach ($messageChannelMap as $oldId => $socialAccountId) {
                DB::table('message_channels')->where('id', $oldId)->update([
                    'social_account_id' => $socialAccountId,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Data-only migration - the schema-level down() in the migrations
        // that added/will drop these columns handles reversal; re-running
        // this would just re-derive the same mapping.
        DB::table('social_accounts')->truncate();
    }

    /**
     * @return array<int, int> old post_accounts.id => social_accounts.id
     */
    private function importPostAccounts(): array
    {
        $map = [];

        foreach (DB::table('post_accounts')->orderBy('id')->get() as $row) {
            $metadata = array_filter([
                'legacy_client_id' => $row->client_id,
                'legacy_token_secret' => $row->token_secret,
                'parent_account_id' => $row->parent_account_id,
                'description' => $row->description,
                'account_url' => $row->account_url,
                'is_default' => $row->is_default,
                'webhook_subscriptions' => $row->webhook_subscriptions,
                'settings' => $row->settings ? json_decode($row->settings, true) : null,
                'insights' => $row->insights ? json_decode($row->insights, true) : null,
                'following_count' => $row->following_count,
                'media_count' => $row->media_count,
                'views_count' => $row->views_count,
            ], fn ($value) => $value !== null);

            $id = $this->upsertSocialAccount([
                'user_id' => $row->user_id,
                'platform' => $row->platform,
                'platform_account_id' => $row->account_id,
                'name' => $row->name,
                'username' => $row->username,
                'avatar_url' => $row->image,
                'followers_count' => $row->follower_count,
                'likes_count' => $row->likes_count,
                'access_token' => $row->access_token,
                'refresh_token' => $row->refresh_token,
                'expires_at' => $row->expires_in,
                'is_token_valid' => (bool) ($row->is_active ?? true) && $row->status !== 'inactive',
                'has_posting_permission' => true,
                'metadata' => $metadata,
            ]);

            $map[$row->id] = $id;
        }

        return $map;
    }

    /**
     * @return array<int, int> old ad_accounts.id => social_accounts.id
     */
    private function importAdAccounts(): array
    {
        $map = [];

        foreach (DB::table('ad_accounts')->orderBy('id')->get() as $row) {
            $metadata = array_filter([
                'currency' => $row->currency,
                'legacy_client_id' => $row->client_id,
                'legacy_token_secret' => $row->token_secret,
                'profile_id' => $row->profile_id,
                'settings' => $row->settings ? json_decode($row->settings, true) : null,
            ], fn ($value) => $value !== null);

            $id = $this->upsertSocialAccount([
                'user_id' => $row->user_id,
                'platform' => $row->platform,
                'platform_account_id' => $row->ad_account_id,
                'name' => $row->name,
                'account_type' => 'ad_account',
                'access_token' => $row->access_token,
                'refresh_token' => $row->refresh_token,
                'expires_at' => $row->expires_at,
                'is_token_valid' => $row->status !== 'inactive',
                'has_ads_permission' => true,
                'metadata' => $metadata,
            ]);

            $map[$row->id] = $id;
        }

        return $map;
    }

    /**
     * @return array<int, int> old message_channels.id => social_accounts.id
     */
    private function importMessageChannels(): array
    {
        $map = [];

        foreach (DB::table('message_channels')->orderBy('id')->get() as $row) {
            $id = $this->upsertSocialAccount([
                'user_id' => $row->user_id,
                'platform' => $row->platform,
                'platform_account_id' => $row->external_id,
                'name' => $row->name,
                'username' => $row->username,
                'avatar_url' => $row->avatar_url,
                'access_token' => $row->access_token,
                'refresh_token' => $row->refresh_token,
                'expires_at' => $row->expires_at,
                'is_token_valid' => (bool) $row->status,
                'has_messaging_permission' => true,
            ]);

            $map[$row->id] = $id;
        }

        return $map;
    }

    /**
     * Merge into an existing social_accounts row matching
     * (platform, platform_account_id, user_id), or insert a new one.
     * Merging never overwrites an existing access_token/refresh_token with
     * an empty one, and only sets a has_*_permission flag - it never
     * clears one another source already set.
     */
    private function upsertSocialAccount(array $data): int
    {
        $metadata = $data['metadata'] ?? [];
        unset($data['metadata']);

        // SocialAccount casts these `encrypted` - written here via raw
        // DB::table (no Eloquent, no casts) so the encryption has to happen
        // explicitly, with the same encrypter the cast uses, or reading the
        // row back through the model throws DecryptException.
        foreach (['access_token', 'refresh_token'] as $tokenField) {
            if (!empty($data[$tokenField])) {
                $data[$tokenField] = Crypt::encryptString($data[$tokenField]);
            }
        }

        $existing = DB::table('social_accounts')
            ->where('platform', $data['platform'])
            ->where('user_id', $data['user_id'])
            ->where('platform_account_id', $data['platform_account_id'])
            ->first();

        if (! $existing) {
            $data['metadata'] = $metadata ? json_encode($metadata) : null;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            return DB::table('social_accounts')->insertGetId($data);
        }

        $update = [];

        foreach (['has_posting_permission', 'has_messaging_permission', 'has_ads_permission'] as $flag) {
            if (! empty($data[$flag])) {
                $update[$flag] = true;
            }
        }

        foreach (['name', 'username', 'avatar_url', 'category', 'account_type', 'followers_count', 'likes_count'] as $field) {
            if (empty($existing->$field) && ! empty($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($existing->access_token) && ! empty($data['access_token'])) {
            $update['access_token'] = $data['access_token'];
            $update['refresh_token'] = $data['refresh_token'] ?? null;
            $update['expires_at'] = $data['expires_at'] ?? null;
        }

        if ($metadata) {
            $existingMetadata = $existing->metadata ? json_decode($existing->metadata, true) : [];
            $update['metadata'] = json_encode(array_merge($existingMetadata, $metadata));
        }

        if ($update) {
            $update['updated_at'] = now();
            DB::table('social_accounts')->where('id', $existing->id)->update($update);
        }

        return $existing->id;
    }

    /**
     * @param  array<int, int>  $map  old id => social_accounts.id
     */
    private function repointChildren(string $table, string $oldColumn, array $map): void
    {
        foreach ($map as $oldId => $newId) {
            DB::table($table)->where($oldColumn, $oldId)->update(['social_account_id' => $newId]);
        }
    }
};
