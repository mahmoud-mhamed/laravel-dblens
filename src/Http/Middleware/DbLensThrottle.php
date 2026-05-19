<?php

namespace MahmoudMhamed\DbLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * File-based throttle that does not depend on the user's cache driver.
 */
class DbLensThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $attempts = (int) config('dblens.throttle.attempts', 120);
        $minutes = (int) config('dblens.throttle.minutes', 1);
        if ($attempts <= 0) {
            return $next($request);
        }

        $dir = storage_path('framework/cache/dblens');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $key = sha1($request->ip() . '|' . optional($request->user())->getAuthIdentifier());
        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $now = time();
        $window = $minutes * 60;

        $data = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded) && ($now - (int)($decoded['start'] ?? 0)) < $window) {
                $data = $decoded;
            }
        }
        $data['count'] = (int)($data['count'] ?? 0) + 1;
        @file_put_contents($file, json_encode($data));

        if ($data['count'] > $attempts) {
            abort(429, 'DbLens: too many requests.');
        }

        return $next($request);
    }
}
