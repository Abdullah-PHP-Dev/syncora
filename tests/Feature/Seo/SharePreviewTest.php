<?php

namespace Tests\Feature\Seo;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The public, unauthenticated /{locale}/share/posts/{id} page that Snap's
 * Creative Kit (and other og:-reading share buttons) fetch server-side.
 */
class SharePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('ROUTING_LOCALE=en');
        parent::setUp();
        // posts / post_media are legacy-era tables; only the columns the page reads.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->text('content')->nullable();
            $table->string('status')->nullable();
            $table->string('visibility')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->timestamps();
        });
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        putenv('ROUTING_LOCALE');
        parent::tearDown();
    }

    private function makePost(array $attributes): int
    {
        $id = DB::table('posts')->insertGetId($attributes + ['content' => 'Our new autumn menu is live', 'visibility' => 'public']);
        DB::table('post_media')->insert(['post_id' => $id, 'media_url' => 'https://cdn.example.com/autumn.jpg', 'media_type' => 'image']);

        return $id;
    }

    public function test_published_post_renders_social_preview_tags_and_is_noindex(): void
    {
        $id = $this->makePost(['status' => 'completed']);

        // Share-button crawlers send no cookies and a bot user agent.
        $this->withHeaders(['User-Agent' => 'Snapchat/1.0 (+https://developers.snap.com)'])
            ->get("/en/share/posts/{$id}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<meta property="og:title" content="Our new autumn menu is live" />', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/autumn.jpg" />', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false);
    }

    public function test_post_being_published_now_is_shareable(): void
    {
        // Created 'pending' and published by a background job - the Snap
        // button is offered (and fetched) before that job finishes.
        $this->get('/en/share/posts/'.$this->makePost(['status' => 'pending']))->assertOk();
        $this->get('/en/share/posts/'.$this->makePost(['status' => 'pending', 'schedule_at' => now()->subMinute()]))->assertOk();
        $this->get('/en/share/posts/'.$this->makePost(['status' => 'published', 'visibility' => null]))->assertOk();
    }

    public function test_missing_and_non_public_posts_are_not_found(): void
    {
        $hidden = [
            'draft' => ['status' => 'draft'],
            'failed' => ['status' => 'failed'],
            'future scheduled' => ['status' => 'pending', 'schedule_at' => now()->addDay()],
            'private' => ['status' => 'completed', 'visibility' => 'private'],
            'followers only' => ['status' => 'completed', 'visibility' => 'followers'],
            'no status' => ['status' => null],
        ];
        foreach ($hidden as $case => $attributes) {
            $response = $this->get('/en/share/posts/'.$this->makePost($attributes));
            $this->assertSame(404, $response->getStatusCode(), "$case post is exposed");
            $response->assertDontSee('Our new autumn menu is live');
        }
        $this->get('/en/share/posts/999999')->assertNotFound();
    }
}
