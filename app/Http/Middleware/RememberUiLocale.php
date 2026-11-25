<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the language each person last used the app in (users.ui_locale), so emails
 * and notifications reach them in it when they haven't chosen one (audit §7.8).
 * One cheap comparison per page view; written only when it changes.
 */
class RememberUiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = app()->getLocale();
        if ($user && $request->isMethod('GET') && in_array($locale, \App\Services\Notifications\RecipientContext::LOCALES, true)
            && $user->ui_locale !== $locale) {
            rescue(fn () => $user->forceFill(['ui_locale' => $locale])->saveQuietly(), null, false);
        }

        return $next($request);
    }
}
