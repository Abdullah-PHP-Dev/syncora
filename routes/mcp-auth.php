<?php

use App\Http\Controllers\Api\McpSignInController;
use Illuminate\Support\Facades\Route;

// Required from routes/api.php, so this inherits its /api prefix.
Route::post('/mcp/v1/auth/token', McpSignInController::class)->middleware('throttle:20,1');
