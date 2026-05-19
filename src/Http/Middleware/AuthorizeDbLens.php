<?php

namespace MahmoudMhamed\DbLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDbLens
{
    public const SESSION_KEY = 'dblens_authenticated';

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        // Always allow the login flow itself.
        if (in_array($routeName, ['dblens.login', 'dblens.login.submit'], true)) {
            return $next($request);
        }

        // Password gate (session-based)
        $password = config('dblens.viewer.password');
        if ($password !== null && $password !== '') {
            if (! $request->session()->get(self::SESSION_KEY)) {
                return redirect()->route('dblens.login');
            }
        }

        // Optional Gate ability
        $ability = config('dblens.gate');
        if ($ability) {
            if (Gate::check($ability, [$request->user()])) {
                return $next($request);
            }
            if (app()->environment('local') && config('dblens.enable_local', true)) {
                return $next($request);
            }
            abort(403, 'DbLens: not authorized.');
        }

        return $next($request);
    }
}
