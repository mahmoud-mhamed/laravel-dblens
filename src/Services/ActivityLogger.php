<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thin wrapper around spatie/laravel-activitylog. Silently no-ops when the
 * package isn't installed or when the activity_log table can't be reached.
 *
 * Resolve via the container so the cached `enabled` decision is shared:
 *   app(ActivityLogger::class)->log('row_updated', 'mysql', 'users', [...]);
 */
class ActivityLogger
{
    protected ?bool $enabledCache = null;

    public function isEnabled(): bool
    {
        if ($this->enabledCache !== null) return $this->enabledCache;
        return $this->enabledCache = $this->resolveEnabled();
    }

    protected function resolveEnabled(): bool
    {
        $cfg = config('dblens.activity_log.enabled', 'auto');
        if ($cfg === false || $cfg === 'false' || $cfg === 0 || $cfg === '0') return false;

        // Spatie's package must be installed.
        if (! class_exists(\Spatie\Activitylog\Models\Activity::class)) return false;

        if ($cfg === true || $cfg === 'true' || $cfg === 1 || $cfg === '1') return true;

        // 'auto' (or anything else) → check that the activity_log table exists
        // on the connection that spatie will write to.
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
     *         scrubbed for redacted columns and truncated)
     */
    public function log(string $event, string $description, array $properties = []): void
    {
        if (! $this->isEnabled() || ! $this->isEventEnabled($event)) return;

        $properties = $this->scrub($properties);
        $properties['ip'] ??= request()?->ip();
        $properties['user_agent'] ??= request()?->userAgent();

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

    protected function scrub(array $props): array
    {
        $redact = array_map('strtolower', (array) config('dblens.activity_log.redact_columns', []));
        $masked = array_map('strtolower', (array) config('dblens.masked', []));
        $redact = array_unique(array_merge($redact, $masked));
        $max = (int) config('dblens.activity_log.max_value_length', 5000);

        $walk = function ($value) use (&$walk, $max) {
            if (is_array($value)) return array_map($walk, $value);
            if (is_string($value) && strlen($value) > $max) return substr($value, 0, $max).'…(truncated)';
            return $value;
        };

        foreach (['old', 'new', 'diff', 'values'] as $key) {
            if (! isset($props[$key]) || ! is_array($props[$key])) continue;
            foreach ($props[$key] as $col => $v) {
                if (in_array(strtolower((string) $col), $redact, true)) {
                    $props[$key][$col] = '•••';
                }
            }
        }
        return $walk($props);
    }
}
