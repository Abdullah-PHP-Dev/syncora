<?php

namespace Tests\Feature\Notifications;

use App\Http\Controllers\Admin\NotificationController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** The navbar notification center (GET /notifications). */
class NotificationIndexTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        Schema::create('post_comments', function ($t) {
            $t->id();
            foreach (['platform', 'comment_id', 'sender_type', 'user_name', 'status'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['parent_comment_id', 'user_id', 'social_account_id', 'post_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->text('user_avatar_url')->nullable();
            $t->text('content')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('conversations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('social_account_id');
            $t->string('platform')->nullable();
            $t->string('customer_name')->nullable();
            $t->text('customer_avatar_url')->nullable();
            $t->string('last_message_preview')->nullable();
            $t->unsignedInteger('unread_count')->default(0);
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    public function test_a_comment_without_a_post_does_not_break_the_list(): void
    {
        // Webhook comments can arrive before (or without) being matched to a post.
        DB::table('post_comments')->insert([
            ['user_id' => $this->user->id, 'platform' => 'facebook', 'user_name' => 'Sara', 'content' => 'Unlinked', 'post_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->user->id, 'platform' => 'facebook', 'user_name' => 'Omar', 'content' => 'Linked', 'post_id' => 42, 'created_at' => now()->subMinute(), 'updated_at' => now()],
        ]);

        $data = app(NotificationController::class)->index()->getData(true);

        $this->assertSame(2, $data['count']);
        $urls = collect($data['items'])->pluck('url', 'preview');
        $this->assertSame(route('admin.comments.dashboard'), $urls['Unlinked']);
        $this->assertStringEndsWith('/posts/42#comment-2', $urls['Linked']);
    }
}
