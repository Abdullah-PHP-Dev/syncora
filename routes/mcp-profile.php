<?php

use App\Http\Controllers\Api\McpProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/mcp/v1/me', McpProfileController::class)->middleware(['auth:sanctum', 'throttle:60,1']);
