<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\App;

return tap(
    Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
            commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
            health: '/up',
        )
        ->withMiddleware(function (Middleware $middleware) {

            $middleware->redirectUsersTo(fn ($request) => app(\App\Services\DashboardService::class)->url($request->user()));

            // App\Http\Middleware\SetLocale used to run here, appended to
            // the global 'web' middleware group. Deliberately deleted in
            // the "Translation" commit (702d893) - it manually replicated
            // exactly what mcamara/laravel-localization's own middleware
            // stack already does (LaravelLocalizationRoutes/
            // LocaleSessionRedirect/LocaleCookieRedirect/
            // LaravelLocalizationRedirectFilter/LaravelLocalizationViewPath,
            // all applied in routes/web.php's outer route group). Since
            // that group's own middleware array includes the literal
            // string 'web', appending SetLocale here would run it first,
            // deciding the locale from the request segment/session before
            // the package's own middleware got a chance to - two
            // independent implementations of the same decision, racing
            // each other on every request. Left removed.
	        $middleware->alias([
		                           'active.user' => \App\Http\Middleware\EnsureActiveUser::class,
                               'seller' => \App\Http\Middleware\EnsureSeller::class,
                               'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
                               'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
	                           ]);

            // RFC 8058 one-click unsubscribe requests are POSTed directly
            // by the recipient's mail provider (Gmail/Yahoo/Outlook's own
            // servers), never by a page this app rendered - there's no
            // CSRF token to send, so this route is exempted the same way
            // any true webhook endpoint would need to be.
            $middleware->validateCsrfTokens(except: [
                'email/unsubscribe/*',
            ]);
        })
        ->withSchedule(function (Schedule $schedule) {
            // X has no realistically obtainable real-time DM webhook (see
            // PollXDirectMessages) - every-minute polling is the closest
            // approximation of "real time" available on standard API tiers.
            $schedule->command('messaging:poll-x-dms')->everyMinute()->withoutOverlapping();

            // Fires any Email Marketing campaign whose scheduled send time
            // has arrived - see SendScheduledEmailCampaigns.
            $schedule->command('email-marketing:send-scheduled')->everyMinute()->withoutOverlapping();
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