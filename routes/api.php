<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'api'], function ($router) {
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
    });
});
