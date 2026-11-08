<?php

use App\Http\Controllers\Api\McpConnectionSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/mcp/v1/connection-summary', McpConnectionSummaryController::class)->middleware(['auth:sanctum', 'throttle:60,1']);
