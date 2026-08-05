<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::group(['prefix' => 'api'], function ($router) {
    Route::prefix('comments')->group(function () {
        Route::prefix('/whatsapp')->group(function () {
            Route::match(['get', 'post'], '/{userId}', 'App\Https\Api\WhatsappController@store')->name('post.whatsapp.webhook_url');
        });
        Route::match(['get', 'post'], '/facebook/{userId}', 
        'App\Https\Api\FacebookController@store')->name('post.facebook.webhook_url');
        Route::match(['get', 'post'], '/instagram/{userId}', 
        'App\Https\Api\InstagramController@store')->name('post.instagram.webhook_url');
        Route::match(['get', 'post'], '/tiktok/{userId}', 
        'App\Https\Api\TiktokController@store')->name('post.tiktok.webhook_url');
        Route::match(['get', 'post'], '/x/{userId}', 
        'App\Https\Api\XController@store')->name('post.x.webhook_url');
        Route::match(['get', 'post'], '/linkedin/{userId}', 
        'App\Https\Api\LinkedinController@store')->name('post.linkedin.webhook_url');
        Route::match(['get', 'post'], '/telegram/{userId}',
        'App\Https\Api\TelegramController@store')->name('post.telegram.webhook_url');
    });

    // Unified messaging inbox webhooks - separate from the (currently
    // broken, pre-existing) comments-webhook block above, which is a
    // different system entirely (post-comment moderation, not DMs).
    Route::prefix('messaging')->name('messaging.webhook.')->group(function () {
        Route::get('/facebook', [\App\Http\Controllers\Api\Messaging\FacebookMessengerWebhookController::class, 'verify'])->name('facebook.verify');
        Route::post('/facebook', [\App\Http\Controllers\Api\Messaging\FacebookMessengerWebhookController::class, 'receive'])->name('facebook.receive');

        Route::get('/instagram', [\App\Http\Controllers\Api\Messaging\InstagramMessengerWebhookController::class, 'verify'])->name('instagram.verify');
        Route::post('/instagram', [\App\Http\Controllers\Api\Messaging\InstagramMessengerWebhookController::class, 'receive'])->name('instagram.receive');

        Route::get('/whatsapp', [\App\Http\Controllers\Api\Messaging\WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
        Route::post('/whatsapp', [\App\Http\Controllers\Api\Messaging\WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');

        // Per-bot URL (see TelegramWebhookController docblock) rather than
        // one shared endpoint like the three Meta platforms above.
        Route::post('/telegram/{channel}', [\App\Http\Controllers\Api\Messaging\TelegramWebhookController::class, 'receive'])->name('telegram.receive');

        // Also per-channel - LINE has no app-level shared webhook the way
        // Meta does, each Messaging API channel gets its own URL.
        Route::post('/line/{channel}', [\App\Http\Controllers\Api\Messaging\LineWebhookController::class, 'receive'])->name('line.receive');

        // Shared per-App endpoint like the Meta platforms above - see
        // ZaloWebhookController docblock.
        Route::post('/zalo', [\App\Http\Controllers\Api\Messaging\ZaloWebhookController::class, 'receive'])->name('zalo.receive');

        // Also a shared per-App endpoint - Slack's one-time url_verification
        // handshake and every event_callback delivery both land here, see
        // SlackWebhookController docblock.
        Route::post('/slack', [\App\Http\Controllers\Api\Messaging\SlackWebhookController::class, 'receive'])->name('slack.receive');

        // Per-channel again, like Telegram/LINE - each Azure Bot
        // registration's "Messaging endpoint" is set by hand in the Azure
        // Portal, see TeamsWebhookController docblock.
        Route::post('/teams/{channel}', [\App\Http\Controllers\Api\Messaging\TeamsWebhookController::class, 'receive'])->name('teams.receive');

        // Per-channel too - each Google Cloud project's Chat app has its
        // own App URL, configured by hand in the Google Cloud Console, see
        // GoogleChatWebhookController docblock.
        Route::match(['get', 'post'], '/google-chat/{channel}', [\App\Http\Controllers\Api\Messaging\GoogleChatWebhookController::class, 'receive'])->name('google_chat.receive');

        // Deliberately no Discord route here - Discord has no webhook
        // delivery for bot DMs at all, so there is no URL to register in
        // the Developer Portal for this. Inbound Discord messages are
        // received exclusively through the Gateway WebSocket daemon (see
        // RunDiscordGatewayListener / `php artisan messaging:discord-listen`).
    });

    // Email Marketing - one shared endpoint for every Mailgun event type
    // (delivered/opened/clicked/unsubscribed/complained/failed), same
    // "one URL, dispatch on the event field" shape as the messaging
    // webhooks above. See MailgunWebhookController.
    Route::post('/email-marketing/mailgun', [\App\Http\Controllers\Api\EmailMarketing\MailgunWebhookController::class, 'receive'])->name('email_marketing.mailgun.receive');
// });
