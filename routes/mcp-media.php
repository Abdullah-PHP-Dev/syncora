<?php

use App\Http\Controllers\Api\McpMediaController;
use App\Http\Controllers\Api\McpListMediaController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp/v1/media-gallery', McpMediaController::class)->middleware(['auth:sanctum', 'throttle:10,1']);

Route::get('/mcp/v1/media-gallery', McpListMediaController::class)->middleware(['auth:sanctum', 'throttle:60,1']);
