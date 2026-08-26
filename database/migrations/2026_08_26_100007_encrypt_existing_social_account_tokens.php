<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * backfill_social_accounts_data copied access_token/refresh_token in as
     * plain text via raw DB::table() inserts (the old post_accounts/
     * ad_accounts/message_channels never encrypted them either) - bypassing
     * Eloquent entirely means SocialAccount's `encrypted` cast never ran
     * over that data, so reading it back through the model throws
     * DecryptException. Encrypts every already-inserted plaintext value in
     * place with the same encrypter the cast uses, skipping any value that
     * already decrypts successfully so this is safe to run more than once.
     */
    public function up(): void
    {
        foreach (DB::table('social_accounts')->select('id', 'access_token', 'refresh_token')->get() as $row) {
            $update = [];

            foreach (['access_token', 'refresh_token'] as $column) {
                $value = $row->$column;

                if ($value === null || $value === '') {
                    continue;
                }

                try {
                    Crypt::decryptString($value);
                    // Already encrypted - leave as-is.
                } catch (\Throwable $e) {
                    $update[$column] = Crypt::encryptString($value);
                }
            }

            if ($update) {
                DB::table('social_accounts')->where('id', $row->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('social_accounts')->select('id', 'access_token', 'refresh_token')->get() as $row) {
            $update = [];

            foreach (['access_token', 'refresh_token'] as $column) {
                $value = $row->$column;

                if ($value === null || $value === '') {
                    continue;
                }

                try {
                    $update[$column] = Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    // Already plaintext - leave as-is.
                }
            }

            if ($update) {
                DB::table('social_accounts')->where('id', $row->id)->update($update);
            }
        }
    }
};
