<?php

namespace Tests\Feature\Posts;

use App\Http\Controllers\Admin\PostController;
use App\Http\Requests\Admin\PostRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\PlatformTextLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Composer: the title is kept, and each platform's API text limit is enforced. */
class PostTextLimitsTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Schema::create('post_categories', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('name');
            $t->timestamps();
        });
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->categoryId = DB::table('post_categories')->insertGetId(['user_id' => $this->user->id, 'name' => 'General']);
        Http::fake(['api.x.com/*' => Http::response(['data' => ['subscription_type' => 'None']])]);
    }

    private function account(string $platform, array $overrides = []): SocialAccount
    {
        return SocialAccount::create($overrides + ['user_id' => $this->user->id, 'platform' => $platform, 'platform_account_id' => $platform . '-' . uniqid(), 'name' => $platform, 'access_token' => 't', 'has_posting_permission' => true, 'is_token_valid' => true]);
    }

    /** Run PostRequest's validation for $data; returns validated data or throws. */
    private function validate(array $data): array
    {
        $request = PostRequest::create('/posts', 'POST', $data + ['category_id' => $this->categoryId]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $this->user);
        $request->validateResolved();

        return $request->validated();
    }

    public function test_the_title_is_kept(): void
    {
        $fb = $this->account('facebook');

        $data = $this->validate(['title' => 'Launch week', 'content' => 'Hello', 'platforms' => ['facebook'], 'facebook' => ['pages' => [$fb->id]]]);

        $this->assertSame('Launch week', $data['title']);
    }

    public function test_a_title_over_100_characters_is_refused(): void
    {
        $fb = $this->account('facebook');

        $this->expectException(ValidationException::class);
        $this->validate(['title' => str_repeat('t', 101), 'content' => 'Hello', 'platforms' => ['facebook'], 'facebook' => ['pages' => [$fb->id]]]);
    }

    public function test_each_selected_platform_limit_is_enforced_by_name(): void
    {
        $fb = $this->account('facebook');
        $ig = $this->account('instagram');
        $text = str_repeat('a', 2500);

        $this->assertSame($text, $this->validate(['content' => $text, 'platforms' => ['facebook'], 'facebook' => ['pages' => [$fb->id]]])['content']); // Facebook: fine

        try {
            $this->validate(['content' => $text, 'platforms' => ['facebook', 'instagram'], 'facebook' => ['pages' => [$fb->id]], 'instagram' => ['pages' => [$ig->id]]]);
            $this->fail('Instagram limit not enforced');
        } catch (ValidationException $e) {
            $this->assertSame(['Instagram allows 2,200 characters - this text is 2,500.'], $e->errors()['content']);
        }
    }

    public function test_x_depends_on_the_accounts_subscription(): void
    {
        $basic = $this->account('x', ['metadata' => ['x_subscription_type' => 'None', 'x_subscription_checked_at' => now()->toIso8601String()]]);
        $premium = $this->account('x', ['metadata' => ['x_subscription_type' => 'Premium', 'x_subscription_checked_at' => now()->toIso8601String()]]);
        $text = str_repeat('a', 500);

        $this->assertSame($text, $this->validate(['content' => $text, 'platforms' => ['x'], 'x' => ['pages' => [$premium->id]]])['content']);

        try {
            $this->validate(['content' => $text, 'platforms' => ['x'], 'x' => ['pages' => [$premium->id, $basic->id]]]); // strictest wins
            $this->fail('X limit not enforced');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('X allows 280 characters - this text is 500. (25,000 with X Premium.)', $e->errors()['content'][0]);
        }
    }

    public function test_platform_rules(): void
    {
        $limits = PlatformTextLimits::LIMITS;

        $this->assertSame([], PlatformTextLimits::problems($limits['facebook'], str_repeat('a', 63206)));
        $this->assertNotEmpty(PlatformTextLimits::problems($limits['facebook'], str_repeat('a', 63207)));
        $this->assertSame(['Instagram allows up to 30 hashtags.'], PlatformTextLimits::problems($limits['instagram'], implode(' ', array_map(fn ($i) => "#tag{$i}", range(1, 31)))));
        $this->assertSame(['LinkedIn allows 3,000 characters - this text is 3,001.'], PlatformTextLimits::problems($limits['linkedin'], str_repeat('a', 3001)));
        $this->assertSame(['Threads allows 500 characters - this text is 501.'], PlatformTextLimits::problems($limits['threads'], str_repeat('a', 501)));
        // YouTube counts bytes: 2,600 Arabic letters = 5,200 bytes.
        $this->assertSame(['YouTube allows 5,000 bytes - this text is 5,200.'], PlatformTextLimits::problems($limits['youtube'], str_repeat('ب', 2600)));
        $this->assertSame(["YouTube doesn't allow < or > in the title or description."], PlatformTextLimits::problems($limits['youtube'], 'Price <10', 'Sale'));
        // X counts links as 23 and emoji as 2.
        $this->assertSame([], PlatformTextLimits::problems($limits['x'], str_repeat('a', 250) . ' https://example.com/' . str_repeat('x', 200) . ' 👋'));
    }

    public function test_empty_title_falls_back_to_the_first_line_not_the_users_name(): void
    {
        $method = new \ReflectionMethod(PostController::class, 'postTitle');

        $this->assertSame('Launch week', $method->invoke(app(PostController::class), '  Launch week ', 'Body'));
        $this->assertSame('Big news today!', $method->invoke(app(PostController::class), '', "Big news today!\nMore details below."));
        $this->assertSame(100, mb_strlen($method->invoke(app(PostController::class), null, str_repeat('long ', 50))));
        $this->assertNull($method->invoke(app(PostController::class), null, ''));
    }

    public function test_a_platform_without_a_selected_account_is_named(): void
    {
        try {
            $this->validate(['content' => 'Hello', 'platforms' => ['instagram']]);
            $this->fail('Missing account not reported');
        } catch (ValidationException $e) {
            $this->assertSame(['Select at least one Instagram account to post to.'], $e->errors()['instagram.pages']);
        }
    }
}
