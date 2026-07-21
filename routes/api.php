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
});
