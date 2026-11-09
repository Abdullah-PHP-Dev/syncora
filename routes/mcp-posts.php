<?php

use App\Http\Controllers\Api\McpPublishingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/mcp/v1/publishing-accounts', [McpPublishingController::class, 'accounts']);
    Route::post('/mcp/v1/posts', [McpPublishingController::class, 'store']);
    Route::get('/mcp/v1/posts/{post}', [McpPublishingController::class, 'show'])->whereNumber('post');
});
