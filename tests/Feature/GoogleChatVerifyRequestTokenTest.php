<?php

namespace Tests\Feature;

use App\Models\Messaging\MessageChannel;
use App\Services\MessagingServices\GoogleChatMessagingService;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Concerns\CreatesAdminSettingsTable;
use Tests\TestCase;

/**
 * Covers the three mutually incompatible bearer tokens Google sends to a
 * Chat app's HTTP endpoint depending on its Cloud console configuration -
 * see GoogleChatMessagingService::verifyRequestToken(). Both key sets are
 * primed in the cache with one locally generated test key so each mode is
 * verified against a real signature without any network access.
 */
class GoogleChatVerifyRequestTokenTest extends TestCase
{
    use CreatesAdminSettingsTable;

    private const PROJECT_NUMBER = '868193692422';
    private const ENDPOINT = 'https://socialeaz.com/api/messaging/google-chat/3';
    private const CHAT_ISSUER = 'chat@system.gserviceaccount.com';
    private const ADD_ON_SIGNER = 'service-868193692422@gcp-sa-gsuiteaddons.iam.gserviceaccount.com';

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAdminSettingsTable();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $this->privateKey);
        $details = openssl_pkey_get_details($resource);

        $jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => 'test-key',
            'use' => 'sig',
            'alg' => 'RS256',
            'n'   => $this->b64u($details['rsa']['n']),
            'e'   => $this->b64u($details['rsa']['e']),
        ]]];

        foreach ([
            'https://www.googleapis.com/oauth2/v3/certs',
            'https://www.googleapis.com/service_accounts/v1/jwk/' . self::CHAT_ISSUER,
        ] as $url) {
            Cache::put('google_chat_jwks_' . md5($url), $jwks, 3600);
        }
    }

    private function b64u(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function channel(): MessageChannel
    {
        $channel = new MessageChannel();
        $channel->id = 3;
        $channel->platform = 'google_chat';
        $channel->meta = [
            'project_number' => self::PROJECT_NUMBER,
            'endpoint_url'   => self::ENDPOINT,
        ];

        return $channel;
    }

    private function verify(array $claims): bool
    {
        $jwt = JWT::encode(
            $claims + ['iat' => time() - 5, 'exp' => time() + 300],
            $this->privateKey,
            'RS256',
            'test-key'
        );

        $request = Request::create('/api/messaging/google-chat/3', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $jwt);

        return app(GoogleChatMessagingService::class)->verifyRequestToken($request, $this->channel());
    }

    public function test_it_accepts_a_workspace_add_on_id_token(): void
    {
        $this->assertTrue($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::ENDPOINT,
            'email'          => self::ADD_ON_SIGNER,
            'email_verified' => true,
        ]));
    }

    public function test_it_accepts_a_classic_app_url_id_token(): void
    {
        $this->assertTrue($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::ENDPOINT,
            'email'          => self::CHAT_ISSUER,
            'email_verified' => true,
        ]));
    }

    public function test_it_accepts_a_classic_project_number_jwt(): void
    {
        $this->assertTrue($this->verify([
            'iss' => self::CHAT_ISSUER,
            'aud' => self::PROJECT_NUMBER,
        ]));
    }

    public function test_it_tolerates_a_trailing_slash_difference_in_the_audience(): void
    {
        $this->assertTrue($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::ENDPOINT . '/',
            'email'          => self::ADD_ON_SIGNER,
            'email_verified' => true,
        ]));
    }

    public function test_it_rejects_an_add_on_token_minted_for_another_project(): void
    {
        $this->assertFalse($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::ENDPOINT,
            'email'          => 'service-999999999999@gcp-sa-gsuiteaddons.iam.gserviceaccount.com',
            'email_verified' => true,
        ]));
    }

    public function test_it_rejects_an_id_token_minted_for_another_endpoint(): void
    {
        $this->assertFalse($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => 'https://attacker.example.com/api/messaging/google-chat/3',
            'email'          => self::ADD_ON_SIGNER,
            'email_verified' => true,
        ]));
    }

    public function test_it_rejects_an_unverified_signer_email(): void
    {
        $this->assertFalse($this->verify([
            'iss'            => 'https://accounts.google.com',
            'aud'            => self::ENDPOINT,
            'email'          => self::ADD_ON_SIGNER,
            'email_verified' => false,
        ]));
    }

    public function test_it_rejects_a_project_number_jwt_with_the_wrong_audience(): void
    {
        $this->assertFalse($this->verify([
            'iss' => self::CHAT_ISSUER,
            'aud' => '111111111111',
        ]));
    }

    public function test_it_rejects_a_missing_authorization_header(): void
    {
        $request = Request::create('/api/messaging/google-chat/3', 'POST');

        $this->assertFalse(
            app(GoogleChatMessagingService::class)->verifyRequestToken($request, $this->channel())
        );
    }
}
