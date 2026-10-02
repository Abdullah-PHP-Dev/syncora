<?php

namespace Tests\Feature\XChat;

use App\Services\MessagingServices\XChat\XChatMediaService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * XChatMediaService::postWithRetry(): transient X failures (5xx / 429) are
 * retried with a 1s then 3s backoff, everything else is returned at once,
 * and a final failure is RETURNED (never thrown) so uploadForMessage() can
 * turn it into a readable XChatException.
 */
class XChatMediaRetryTest extends TestCase
{
    private const URL = 'https://api.x.com/2/chat/media/upload/initialize';

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    private function callPostWithRetry(array $body = ['conversation_id' => '1:2', 'total_bytes' => 1024]): Response
    {
        $method = new \ReflectionMethod(XChatMediaService::class, 'postWithRetry');

        return $method->invoke(app(XChatMediaService::class), 'test-token', 'chat/media/upload/initialize', $body);
    }

    private function unavailable(): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['title' => 'Service Unavailable', 'detail' => 'Service Unavailable', 'status' => 503], 503, ['x-transaction-id' => 'txn-503']);
    }

    public function test_success_on_first_try_sends_once_without_waiting(): void
    {
        Http::fake([self::URL => Http::response(['data' => ['session_id' => 's1', 'media_hash_key' => 'h1']], 200)]);
        Log::spy();

        $response = $this->callPostWithRetry();

        $this->assertSame(200, $response->status());
        $this->assertSame('s1', $response->json('data.session_id'));
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
        Log::shouldNotHaveReceived('warning');
    }

    public function test_sends_bearer_token_and_json_body_to_the_x_api(): void
    {
        Http::fake([self::URL => Http::response(['data' => []], 200)]);

        $this->callPostWithRetry(['conversation_id' => '111:222', 'total_bytes' => 4096]);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->isJson()
            && $request['conversation_id'] === '111:222'
            && $request['total_bytes'] === 4096);
    }

    public function test_persistent_503_is_retried_three_times_then_returned_and_logged(): void
    {
        Http::fake([self::URL => $this->unavailable()]);
        Log::spy();

        $response = $this->callPostWithRetry();

        $this->assertSame(503, $response->status());
        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(3)->seconds()]);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $message === 'X Chat media API call failed.'
            && $context['status'] === 503
            && $context['attempts'] === 3
            && $context['x_transaction_id'] === 'txn-503'
            && $context['title'] === 'Service Unavailable'
            && !array_key_exists('body', $context));
    }

    public function test_503_then_success_returns_the_success(): void
    {
        Http::fake([self::URL => Http::sequence()->pushResponse($this->unavailable())->push(['data' => ['session_id' => 's2']], 200)]);
        Log::spy();

        $response = $this->callPostWithRetry();

        $this->assertSame(200, $response->status());
        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1)->second()]);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_429_rate_limit_is_retried(): void
    {
        Http::fake([self::URL => Http::sequence()->push(['title' => 'Too Many Requests'], 429)->push(['data' => []], 200)]);

        $this->assertSame(200, $this->callPostWithRetry()->status());
        Http::assertSentCount(2);
    }

    public function test_client_errors_are_not_retried(): void
    {
        foreach ([400, 401, 403] as $status) {
            Http::fake([self::URL => Http::response(['title' => 'Error ' . $status], $status)]);
            Log::spy();

            $response = $this->callPostWithRetry();

            $this->assertSame($status, $response->status());
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
            Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $context['status'] === $status && $context['attempts'] === 1);

            $this->refreshApplication();
            Sleep::fake();
        }
    }
}
