<?php

namespace Tests\Feature\Connections;

use App\Casts\TolerantEncrypted;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 0a: TolerantEncrypted cast + connections:encrypt-tokens. */
class TokenEncryptionTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_06_13_213124_create_permission_tables.php',
            '2026_08_26_100000_create_social_accounts_table.php',
            '2026_08_28_100000_create_social_account_post_details_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
    }

    /** Inserts a row with raw column values, bypassing the cast. */
    private function rawAccount(?string $access, ?string $refresh = null, array $extra = []): int
    {
        return DB::table('social_accounts')->insertGetId(array_merge([
            'user_id' => $this->user->id,
            'platform' => 'facebook',
            'platform_account_id' => uniqid(),
            'name' => 'Acct',
            'access_token' => $access,
            'refresh_token' => $refresh,
            'is_token_valid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function raw(int $id, string $column): ?string
    {
        return DB::table('social_accounts')->where('id', $id)->value($column);
    }

    private function foreignCiphertext(string $plain = 'lost-key-token'): string
    {
        return (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString($plain);
    }

    // ---- cast ----------------------------------------------------------

    public function test_cast_reads_legacy_plaintext_as_is(): void
    {
        $id = $this->rawAccount('plain-token');

        $this->assertSame('plain-token', SocialAccount::find($id)->access_token);
    }

    public function test_cast_writes_encrypted_and_reads_it_back(): void
    {
        $account = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'x', 'platform_account_id' => '1', 'name' => 'X', 'access_token' => 'secret-token']);

        $raw = $this->raw($account->id, 'access_token');
        $this->assertNotSame('secret-token', $raw);
        $this->assertTrue(TolerantEncrypted::isEncryptedPayload($raw));
        $this->assertSame('secret-token', SocialAccount::find($account->id)->access_token);
    }

    public function test_cast_returns_null_for_ciphertext_no_key_can_open(): void
    {
        $id = $this->rawAccount($this->foreignCiphertext());

        $this->assertNull(SocialAccount::find($id)->access_token);
    }

    public function test_payload_detection_does_not_flag_real_tokens(): void
    {
        foreach (['EAAGm0PX4ZCpsBA', 'ya29.a0AfB_byC', 'AQX1abc-def_ghi', base64_encode('not json')] as $token) {
            $this->assertFalse(TolerantEncrypted::isEncryptedPayload($token), $token);
        }
        $this->assertTrue(TolerantEncrypted::isEncryptedPayload(Crypt::encryptString('t')));
    }

    // ---- command -------------------------------------------------------

    public function test_dry_run_reports_counts_and_writes_nothing(): void
    {
        $plain = $this->rawAccount('plain-token', 'plain-refresh');
        $ok = $this->rawAccount(Crypt::encryptString('already'));
        $broken = $this->rawAccount($this->foreignCiphertext());

        $this->artisan('connections:encrypt-tokens', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsTable(
                ['column', 'null', 'plaintext → encrypt', 'encrypted (ok)', 'undecryptable → clear'],
                [['access_token', 0, 1, 1, 1], ['refresh_token', 2, 1, 0, 0]]
            )
            ->assertSuccessful();

        $this->assertSame('plain-token', $this->raw($plain, 'access_token'));
        $this->assertSame('plain-refresh', $this->raw($plain, 'refresh_token'));
        $this->assertNotNull($this->raw($broken, 'access_token'));
        $this->assertSame(1, (int) $this->raw($broken, 'is_token_valid'));
        $this->assertNotNull($ok);
    }

    public function test_run_encrypts_plaintext_without_changing_the_token(): void
    {
        $id = $this->rawAccount('plain-token', 'plain-refresh');

        $this->artisan('connections:encrypt-tokens')->assertSuccessful();

        $this->assertTrue(TolerantEncrypted::isEncryptedPayload($this->raw($id, 'access_token')));
        $this->assertSame('plain-token', Crypt::decryptString($this->raw($id, 'access_token')));
        $this->assertSame('plain-refresh', SocialAccount::find($id)->refresh_token);
    }

    public function test_run_leaves_valid_ciphertext_untouched(): void
    {
        $cipher = Crypt::encryptString('already');
        $id = $this->rawAccount($cipher);

        $this->artisan('connections:encrypt-tokens')->assertSuccessful();

        $this->assertSame($cipher, $this->raw($id, 'access_token'));
    }

    public function test_run_clears_undecryptable_token_and_flags_reconnect(): void
    {
        $id = $this->rawAccount($this->foreignCiphertext(), null, ['metadata' => json_encode(['currency' => 'SAR'])]);

        $this->artisan('connections:encrypt-tokens')
            ->expectsOutputToContain('need to reconnect')
            ->assertSuccessful();

        $this->assertNull($this->raw($id, 'access_token'));
        $this->assertSame(0, (int) $this->raw($id, 'is_token_valid'));
        $meta = json_decode($this->raw($id, 'metadata'), true);
        $this->assertSame('SAR', $meta['currency']);
        $this->assertSame('token_undecryptable', $meta['reauth']['reason']);
        $this->assertSame(['access_token'], $meta['reauth']['columns']);
    }

    public function test_run_never_double_encrypts_and_is_idempotent(): void
    {
        $id = $this->rawAccount('plain-token');

        $this->artisan('connections:encrypt-tokens')->assertSuccessful();
        $first = $this->raw($id, 'access_token');
        $this->artisan('connections:encrypt-tokens')->assertSuccessful();

        $this->assertSame($first, $this->raw($id, 'access_token'));
        $this->assertSame('plain-token', SocialAccount::find($id)->access_token);
    }

    public function test_previous_app_key_still_decrypts(): void
    {
        $oldKey = random_bytes(32);
        $id = $this->rawAccount((new Encrypter($oldKey, 'aes-256-cbc'))->encryptString('rotated-token'));

        // Same wiring as config('app.previous_keys') / APP_PREVIOUS_KEYS.
        $this->app->instance('encrypter', (new Encrypter(random_bytes(32), 'aes-256-cbc'))->previousKeys([$oldKey]));
        Crypt::clearResolvedInstances();

        $this->artisan('connections:encrypt-tokens')->assertSuccessful();

        $this->assertSame('rotated-token', SocialAccount::find($id)->access_token);
        $this->assertSame(1, (int) $this->raw($id, 'is_token_valid'));
    }
}
