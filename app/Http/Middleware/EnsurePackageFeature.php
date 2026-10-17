<?php

namespace App\Http\Middleware;

use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsurePackageFeature
{
    public function __construct(private PackageAccessService $access) {}

    public function handle(Request $request, Closure $next, ?string $module = null)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('seller') && ! $user->isTeamMember(), 403);
        $route = $request->route()?->getName() ?? '';
        $write = ! $request->isMethodSafe() || Str::is(config('package_access.write_get_routes'), $route);
        $modules = $module ? [$module] : $this->access->modulesForRoute($route);
        foreach ($modules as $feature) {
            if (! $this->access->allows($user, $feature, $write)) {
                $message = __('package_access.denied', ['module' => __(config("package_access.modules.$feature.label", $feature))]);
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'code' => 'package_feature_denied', 'feature' => $feature, 'upgrade_url' => route('admin.subscription.select')], 403);
                }

                return response()->view('errors.package-access', ['message' => $message], 403);
            }
        }
        if ($write && in_array('ai_content', $modules, true)) {
            $usage = app(PackageUsageService::class);
            $keys = ['ai_credits'];
            if ($route === 'admin.posts.generate-ai-content' && in_array($request->input('kind', 'all'), ['all', 'hashtags'], true)) {
                $keys[] = 'hashtag_generation';
            }
            $events = $usage->locked($user, function () use ($usage, $user, $keys) {
                $events = [];
                foreach ($keys as $key) {
                    $events[$key] = $usage->reserve($user, $key);
                }

                return $events;
            });
            try {
                $response = $next($request);
                if ($response->getStatusCode() >= 400
                    || ($response instanceof JsonResponse && ($response->getData(true)['success'] ?? null) === false)) {
                    foreach ($events as $key => $event) {
                        $usage->release($user, $key, $event);
                    }
                }

                return $response;
            } catch (\Throwable $exception) {
                foreach ($events as $key => $event) {
                    $usage->release($user, $key, $event);
                }
                throw $exception;
            }
        }

        return $next($request);
    }
}
