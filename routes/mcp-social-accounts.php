<?php

use App\Http\Controllers\Api\McpSocialAccountsController;
use Illuminate\Support\Facades\Route;

Route::get('/mcp/v1/social-accounts', McpSocialAccountsController::class)->middleware(['auth:sanctum', 'throttle:60,1']);
