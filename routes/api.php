<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Route::group(['prefix' => 'api'], function ($router) {
    // Post-comment webhooks - separate from the messaging.* group below,
    // which is DMs only (entry[].messaging[]) and never sees entry[].changes[]
    // (comments). Previously every route here used a broken controller
    // string (`App\Https\Api\...` - wrong namespace, and missing
    // "Controllers") which doesn't resolve to any real class; that alone was
    // enough to make `php artisan route:list` fatal-error for the entire
    // app, since it reflects every registered controller. The old routes
    // also carried a per-user {userId} segment, which doesn't match how
    // Meta webhooks actually work - there is exactly one App-level callback
    // URL, and events for every connected Page/Instagram account arrive on
    // it, disambiguated via entry[].id (see FacebookCommentWebhookController
    // / InstagramCommentWebhookController), the same shared-URL model
    // already used by messaging.facebook / messaging.instagram below.
    Route::prefix('comments')->name('comments.webhook.')->group(function () {
        Route::get('/facebook', [\App\Http\Controllers\Api\Comments\FacebookCommentWebhookController::class, 'verify'])->name('facebook.verify');
        Route::post('/facebook', [\App\Http\Controllers\Api\Comments\FacebookCommentWebhookController::class, 'receive'])->name('facebook.receive');

        Route::get('/instagram', [\App\Http\Controllers\Api\Comments\InstagramCommentWebhookController::class, 'verify'])->name('instagram.verify');
        Route::post('/instagram', [\App\Http\Controllers\Api\Comments\InstagramCommentWebhookController::class, 'receive'])->name('instagram.receive');

        // YouTube's PubSubHubbub (WebSub) callback - not a Meta-style
        // webhook, only announces new/updated video publishes (see
        // YoutubeWebhookController's docblock). No 'active comment
        // webhook' exists for YouTube at all; comments/likes/shares are
        // pull-only via YoutubePostService::backfillRecentPosts().
        Route::get('/youtube', [\App\Http\Controllers\Api\Comments\YoutubeWebhookController::class, 'verify'])->name('youtube.verify');
        Route::post('/youtube', [\App\Http\Controllers\Api\Comments\YoutubeWebhookController::class, 'receive'])->name('youtube.receive');

        // LinkedIn has no GET verify handshake to register (unlike the Meta
        // platforms above) - LinkedIn has no public push/webhook product for
        // organic engagement in the first place under this module's scopes,
        // so there's no challenge-response step to answer. This is the URL
        // LinkedInPostService::subscribeToWebhooks() records on connect for
        // an admin to register by hand if the org is ever approved for a
        // push-capable LinkedIn product; until then comment/like/share data
        // is kept current by polling - see
        // LinkedInPostService::backfillRecentPosts() and
        // LinkedinCommentWebhookController's docblock.
        Route::post('/linkedin', [\App\Http\Controllers\Api\Comments\LinkedinCommentWebhookController::class, 'receive'])->name('linkedin.receive');

        // TikTok/X/WhatsApp/Telegram comment webhooks are not implemented
        // yet - TiktokController/XController are empty stub files (no class
        // defined) and a WhatsappController/TelegramController don't exist
        // anywhere in this codebase. Left unregistered rather than pointed
        // at a broken controller string again, so route:list/route:cache
        // stay usable in the meantime.
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
        Route::match(['get', 'post'], '/google-chat/{userId}', [\App\Http\Controllers\Api\Messaging\GoogleChatWebhookController::class, 'receive'])->name('google_chat.receive');

        // Deliberately no Discord route here - Discord has no webhook
        // delivery for bot DMs at all, so there is no URL to register in
        // the Developer Portal for this. Inbound Discord messages are
        // received exclusively through the Gateway WebSocket daemon (see
        // RunDiscordGatewayListener / `php artisan messaging:discord-listen`).
    });

    // Ads - LinkedIn only for now. LinkedIn's webhook product does use a
    // GET ?challengeCode= ownership handshake (unlike what an earlier
    // version of this comment claimed), but it publishes no event type at
    // all for ad delivery metrics (spend/impressions/clicks) under this
    // module's r_ads/rw_ads/r_ads_reporting scopes - reporting is pull-only
    // via LinkedIn's adAnalytics finder - so nothing will ever call this
    // URL and there is no handshake to answer. See
    // LinkedinAdWebhookController's docblock for the handshake/signature
    // contract that would have to be implemented first, and
    // LinkedinAdService::registerAdEventsCallback().
    Route::prefix('ads')->name('ads.webhook.')->group(function () {
        Route::post('/linkedin', [\App\Http\Controllers\Api\Ads\LinkedinAdWebhookController::class, 'receive'])->name('linkedin.receive');
    });

    // Email Marketing - one shared endpoint for every Mailgun event type
    // (delivered/opened/clicked/unsubscribed/complained/failed), same
    // "one URL, dispatch on the event field" shape as the messaging
    // webhooks above. See MailgunWebhookController.
    Route::post('/email-marketing/mailgun', [\App\Http\Controllers\Api\EmailMarketing\MailgunWebhookController::class, 'receive'])->name('email_marketing.mailgun.receive');
// });
