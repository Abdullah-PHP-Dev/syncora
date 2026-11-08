<?php

use App\Http\Controllers\Api\McpMediaController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp/v1/media-gallery', McpMediaController::class)->middleware(['auth:sanctum', 'throttle:10,1']);
