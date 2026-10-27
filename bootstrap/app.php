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
                               'package.feature' => \App\Http\Middleware\EnsurePackageFeature::class,
                               'connected' => \App\Http\Middleware\EnsureConnectedAccount::class,
	                           ]);

            // RFC 8058 one-click unsubscribe requests are POSTed directly
            // by the recipient's mail provider (Gmail/Yahoo/Outlook's own
            // servers), never by a page this app rendered - there's no
            // CSRF token to send, so this route is exempted the same way
            // any true webhook endpoint would need to be.
            // Return-to-origin for every OAuth connect flow (see the class).
            // Global (before routing): /en/ and /en/pricing/ 301 to the slashless URL.
            $middleware->prepend(\App\Http\Middleware\RemoveTrailingSlash::class);
            // Global (before routing): /index.php/en/pricing 301s to /en/pricing.
            $middleware->prepend(\App\Http\Middleware\RemoveIndexPhp::class);
            $middleware->appendToGroup('web', \App\Http\Middleware\ReturnToOrigin::class);
            $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
            // Current platform/module for cross-module links (App\Support\WorkContext).
            $middleware->appendToGroup('web', \App\Http\Middleware\RememberWorkContext::class);
            $middleware->appendToGroup('web', \App\Http\Middleware\UseSellerWorkspace::class);

            $middleware->validateCsrfTokens(except: [
                'email/unsubscribe/*',
            ]);
        })
        ->withSchedule(function (Schedule $schedule) {
            // X has no realistically obtainable real-time DM webhook (see
            // PollXDirectMessages) - every-minute polling is the closest
            // approximation of "real time" available on standard API tiers.
            // $schedule->command('messaging:poll-x-dms')->everyMinute()->withoutOverlapping();

            // X Chat messages that arrived before the account's X Chat PIN
            // was set (or while the xchat-worker was down) - see
            // DecryptPendingXChatMessages.
            $schedule->command('messaging:x-chat-decrypt-pending')->everyFiveMinutes()->withoutOverlapping();

            // Fires any Email Marketing campaign whose scheduled send time
            // has arrived - see SendScheduledEmailCampaigns.
            $schedule->command('email-marketing:send-scheduled')->everyMinute()->withoutOverlapping();

            // Backstop for a FAQ that failed to embed at save time (Gemini
            // outage) or was published without a content change - see
            // ReembedFaqs's own docblock. Hourly since this is a backstop,
            // not the primary embed path.
            $schedule->command('ai-copilot:reembed-faqs')->hourly()->withoutOverlapping();

            // Connection Hub: token expiry + provider revocation checks feed
            // the Hub's warnings and the notification bell - see
            // CheckConnectionStatus.
            $schedule->command('connections:check-status')->hourly()->withoutOverlapping();

            // Google Suite: warm GA4 / Search Console reports per workspace
            // sync preference - see SyncGoogleSuites.
            $schedule->command('google-suite:sync')->hourlyAt(20)->withoutOverlapping();

            // Facebook sends no webhook for shares to people's own
            // timelines - re-read recent posts' counts (SyncPostEngagement).
            $schedule->command('posts:sync-engagement')->everyThirtyMinutes()->withoutOverlapping();

            // TikTok has no comment webhook for posted videos - pull
            // comments through the "TikTok comments" connection.
            $schedule->command('tiktok:sync-comments')->hourly()->withoutOverlapping();
        })
        ->withExceptions(function (Exceptions $exceptions) {
            // OAuth callbacks (…/callback): a plan-limit ValidationException or
            // a "module not in your plan" 403 thrown while saving the new
            // accounts must not use Laravel's default redirect()->back() - on a
            // callback "back" is the Referer, i.e. facebook.com / google.com,
            // which dumped users on the provider's site. Send them to an app
            // page with the reason instead.
            $oauthLanding = function (\Illuminate\Http\Request $request, string $message) {
                if ($request->expectsJson() || ! str_ends_with((string) $request->route()?->getName(), '.callback')) {
                    return null;
                }
                $name = (string) $request->route()->getName();
                $target = match (true) {
                    str_contains($name, '.ads.') => 'admin.ads.dashboard',
                    str_contains($name, '.messaging.') => 'admin.chats.dashboard',
                    \Illuminate\Support\Facades\Route::has('admin.connections.index') => 'admin.connections.index',
                    default => 'admin.posts.create',
                };

                return redirect()->route($target)->with('error', $message);
            };
            $exceptions->render(fn (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) => $oauthLanding($request, collect($e->errors())->flatten()->first() ?: $e->getMessage()));
            $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) use ($oauthLanding) {
                return $e->getStatusCode() === 403 ? $oauthLanding($request, $e->getMessage() ?: __('This action is not included in your plan.')) : null;
            });
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
