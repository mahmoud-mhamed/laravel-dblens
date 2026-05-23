<?php

namespace MahmoudMhamed\DbLens\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache-backed throttle for DbLens routes. Uses Laravel's built-in
 * RateLimiter (atomic increments, automatic expiry) so we don't need a
 * file-based store of our own.
 */
class DbLensThrottle
{
    public function __construct(protected RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $attempts = (int) config('dblens.throttle.attempts', 120);
        $minutes = (int) config('dblens.throttle.minutes', 1);
        if ($attempts <= 0) {
            return $next($request);
        }

        $key = 'dblens|' . sha1($request->ip() . '|' . optional($request->user())->getAuthIdentifier());

        if ($this->limiter->tooManyAttempts($key, $attempts)) {
            abort(429, 'DbLens: too many requests.');
        }
        $this->limiter->hit($key, $minutes * 60);

        return $next($request);
    }
}
