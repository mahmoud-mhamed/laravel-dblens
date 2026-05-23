<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Support\Facades\Schema;

/**
 * Thin wrapper around spatie/laravel-activitylog. Silently no-ops when the
 * package isn't installed or when the activity_log table can't be reached.
 *
 * The properties payload is enriched per request with structured sections
 * (request / device / performance / app / session / execution) so log
 * entries are useful when browsed later — same shape as the sibling
 * `mahmoud-mhamed/spatie-activitylog-browse` package. Each section can be
 * disabled via `dblens.activity_log.enrich.*` to cut overhead.
 */
class ActivityLogger
{
    protected ?bool $enabledCache = null;

    public function __construct(protected ?ActivityContext $context = null) {}

    protected function context(): ActivityContext
    {
        return $this->context ??= app(ActivityContext::class);
    }

    public function isEnabled(): bool
    {
        if ($this->enabledCache !== null) return $this->enabledCache;
        return $this->enabledCache = $this->resolveEnabled();
    }

    protected function resolveEnabled(): bool
    {
        $cfg = config('dblens.activity_log.enabled', 'auto');
        if ($cfg === false || $cfg === 'false' || $cfg === 0 || $cfg === '0') return false;

        if (! class_exists(\Spatie\Activitylog\Models\Activity::class)) return false;

        if ($cfg === true || $cfg === 'true' || $cfg === 1 || $cfg === '1') return true;

        try {
            $connection = config('dblens.activity_log.connection')
                ?: config('activitylog.database_connection')
                ?: config('database.default');
            $table = config('activitylog.table_name', 'activity_log');
            return Schema::connection($connection)->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isEventEnabled(string $event): bool
    {
        $map = (array) config('dblens.activity_log.events', []);
        if (isset($map[$event])) return (bool) $map[$event];
        foreach ($map as $pattern => $on) {
            if (! str_ends_with($pattern, '*')) continue;
            $prefix = rtrim($pattern, '*');
            if (str_starts_with($event, $prefix)) return (bool) $on;
        }
        return true;
    }

    /**
     * Record a DbLens activity. No-ops when disabled or the event is filtered.
     *
     * @param  array<string,mixed>  $properties  arbitrary context (will be
     *         scrubbed for redacted columns, enriched, and truncated)
     */
    public function log(string $event, string $description, array $properties = []): void
    {
        if (! $this->isEnabled() || ! $this->isEventEnabled($event)) return;

        $properties = $this->scrub($properties);
        $properties = $this->enrich($properties);

        try {
            $logger = activity((string) config('dblens.activity_log.log_name', 'dblens'))
                ->event($event)
                ->withProperties($properties);
            if ($user = auth()->user()) $logger->causedBy($user);
            $logger->log($description);
        } catch (\Throwable $e) {
            // Logging must never break the user's action.
        }
    }

    /**
     * Layer the configured enrichment sections onto a properties payload.
     * Caller-supplied keys win — if `request_data` was already provided we
     * don't overwrite it.
     */
    protected function enrich(array $props): array
    {
        $sections = (array) config('dblens.activity_log.enrich', []);
        $request = function_exists('request') ? request() : null;
        $ctx = $this->context();

        if (($sections['request_data'] ?? true) && $request) {
            $props['request_data'] ??= [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'previous_url' => url()->previous(),
            ];
        }

        if (($sections['device_data'] ?? true) && $request) {
            $props['device_data'] ??= [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->headers->get('referer'),
            ];
        }

        if ($sections['performance_data'] ?? true) {
            $props['performance_data'] ??= [
                'duration_ms' => $ctx->durationMs(),
                'peak_memory_bytes' => $ctx->peakMemoryBytes(),
                'query_count' => $ctx->queryCount(),
            ];
        }

        if ($sections['app_data'] ?? true) {
            $props['app_data'] ??= [
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'hostname' => gethostname() ?: null,
            ];
        }

        if (($sections['session_data'] ?? true)) {
            $guard = null;
            try {
                $guard = auth()->getDefaultDriver();
            } catch (\Throwable $e) {}
            $props['session_data'] ??= [
                'guard' => $guard,
                'authenticated' => (bool) auth()->user(),
            ];
        }

        if ($sections['execution_context'] ?? true) {
            $argv = $_SERVER['argv'] ?? [];
            $props['execution_context'] ??= [
                'source' => $ctx->source(),
                'command' => app()->runningInConsole() ? ($argv[1] ?? null) : null,
                'job' => null,
            ];
        }

        return $props;
    }

    protected function scrub(array $props): array
    {
        $redact = array_map('strtolower', (array) config('dblens.activity_log.redact_columns', []));
        $masked = array_map('strtolower', (array) config('dblens.masked_columns', []));
        $redact = array_values(array_unique(array_merge($redact, $masked)));
        $max = (int) config('dblens.activity_log.max_value_length', 5000);

        $walk = function ($value) use (&$walk, $max) {
            if (is_array($value)) return array_map($walk, $value);
            if (is_string($value) && strlen($value) > $max) return substr($value, 0, $max).'…(truncated)';
            return $value;
        };

        // `attributes` / `old` is the spatie convention; `new` / `values` are
        // kept for callers that still emit the older shape.
        foreach (['attributes', 'old', 'new', 'values'] as $key) {
            if (! isset($props[$key]) || ! is_array($props[$key])) continue;
            foreach ($props[$key] as $col => $_) {
                if (in_array(strtolower((string) $col), $redact, true)) {
                    $props[$key][$col] = '•••';
                }
            }
        }
        return $walk($props);
    }
}
