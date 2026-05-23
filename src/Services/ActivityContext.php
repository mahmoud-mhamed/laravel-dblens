<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Support\Facades\DB;

/**
 * Per-request snapshot used by {@see ActivityLogger} to enrich each log entry
 * with performance, request and execution context.
 *
 * Bootstrapped once from the service provider so the duration / query-count
 * counters cover the whole request lifecycle. Cheap to keep around when
 * enrichment is disabled — it simply isn't read.
 */
class ActivityContext
{
    protected float $startedAt;
    protected int $startMemory;
    protected int $queryCount = 0;
    protected bool $queryListenerAttached = false;
    protected string $source;

    public function __construct()
    {
        $this->startedAt = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->source = $this->detectSource();
    }

    /**
     * Wire a DB::listen() callback once so we can report query_count alongside
     * each activity entry. Safe to call multiple times.
     */
    public function startCollecting(): void
    {
        if ($this->queryListenerAttached) return;
        $this->queryListenerAttached = true;
        try {
            DB::listen(function () {
                $this->queryCount++;
            });
        } catch (\Throwable $e) {
            // No DB connection during boot — fine, we just lose query counts.
        }
    }

    public function durationMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    public function peakMemoryBytes(): int
    {
        return memory_get_peak_usage(true);
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function source(): string
    {
        return $this->source;
    }

    protected function detectSource(): string
    {
        if (app()->runningInConsole()) {
            // Distinguish scheduler / queue worker / artisan command.
            $argv = $_SERVER['argv'] ?? [];
            $cmd = $argv[1] ?? '';
            if (str_starts_with($cmd, 'queue:')) return 'queue';
            if (str_starts_with($cmd, 'schedule:')) return 'schedule';
            return 'console';
        }
        return 'web';
    }
}
