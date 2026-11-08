<?php

use App\Http\Controllers\Api\McpFacebookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/mcp/v1/facebook/pages', [McpFacebookController::class, 'pages']);
    Route::post('/mcp/v1/facebook/posts', [McpFacebookController::class, 'store']);
    Route::get('/mcp/v1/facebook/posts/{post}', [McpFacebookController::class, 'show'])->whereNumber('post');
});
