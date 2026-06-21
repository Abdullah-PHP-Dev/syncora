<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\App;

return tap(
    Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
            commands: __DIR__.'/../routes/console.php',
            health: '/up',
        )
        ->withMiddleware(function (Middleware $middleware) {

            $middleware->web(append: [
                \App\Http\Middleware\SetLocale::class,
            ]);

	        $middleware->alias([
		                           'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
	                           ]);
        })
        ->withExceptions(function (Exceptions $exceptions) {
            //
        })
        ->create(),
    function ($app) {
        /**
         * ✅ FORCE LANGUAGE DIRECTORY REGISTRATION
         * Fixes __('file.key') returning raw key issue
         */
        $app->useLangPath(base_path('lang'));
    }
);