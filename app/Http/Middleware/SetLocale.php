<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('laravellocalization.supportedLocales');
        $requested = $request->segment(1);
        $locale = isset($supported[$requested])
            ? $requested
            : Session::get('locale', app()->getLocale());

        if (!isset($supported[$locale])) {
            $locale = config('app.locale');
        }

        Session::put('locale', $locale);

        App::setLocale($locale);

        return $next($request);
    }
}