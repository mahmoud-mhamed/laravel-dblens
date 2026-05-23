<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepare the spatie/laravel-activitylog table for fast browsing.
 *
 * Mirrors the spirit of `mahmoud-mhamed/spatie-activitylog-browse`'s install
 * step: it doesn't replace spatie's own migrations — those are still the
 * source of truth — it just adds composite indexes that turn the common
 * "filter by log_name + created_at" / "filter by causer + event" queries
 * from full-scan into index-range scans.
 */
class DbLensActivityLogInstallCommand extends Command
{
    protected $signature = 'dblens:activitylog-install
        {--force : Recreate indexes even if they already exist}';

    protected $description = 'Add composite indexes to the spatie activity_log table for fast browsing';

    public function handle(): int
    {
        $this->info('━━━━━━━━━━━ DbLens activity log indexes ━━━━━━━━━━━');

        if (! class_exists(\Spatie\Activitylog\Models\Activity::class)) {
            $this->error('spatie/laravel-activitylog is not installed.');
            $this->line('   composer require spatie/laravel-activitylog');
            return self::FAILURE;
        }

        $connection = (string) (config('dblens.activity_log.connection')
            ?: config('activitylog.database_connection')
            ?: config('database.default'));
        $table = (string) config('activitylog.table_name', 'activity_log');

        if (! Schema::connection($connection)->hasTable($table)) {
            $this->error("Table [{$table}] not found on connection [{$connection}].");
            $this->line('   Run: php artisan migrate  (after publishing spatie migrations)');
            return self::FAILURE;
        }

        $driver = DB::connection($connection)->getDriverName();
        $indexes = [
            'dblens_al_logname_created' => ['log_name', 'created_at'],
            'dblens_al_event_created'   => ['event', 'created_at'],
            'dblens_al_causer'          => ['causer_type', 'causer_id'],
            'dblens_al_subject'         => ['subject_type', 'subject_id'],
        ];

        foreach ($indexes as $name => $cols) {
            $this->ensureIndex($connection, $driver, $table, $name, $cols);
        }

        $this->line('');
        $this->info('✓ Done.');
        return self::SUCCESS;
    }

    /** @param array<int,string> $cols */
    protected function ensureIndex(string $connection, string $driver, string $table, string $name, array $cols): void
    {
        $exists = $this->indexExists($connection, $driver, $table, $name);
        if ($exists && ! $this->option('force')) {
            $this->line("   <fg=gray>•</> {$name} (already present)");
            return;
        }
        if ($exists && $this->option('force')) {
            try {
                Schema::connection($connection)->table($table, function ($t) use ($name) {
                    $t->dropIndex($name);
                });
            } catch (\Throwable $e) {
                // ignore — Schema drop is best-effort
            }
        }

        try {
            Schema::connection($connection)->table($table, function ($t) use ($name, $cols) {
                $t->index($cols, $name);
            });
            $this->line("   <fg=green>✓</> {$name} (".implode(', ', $cols).')');
        } catch (\Throwable $e) {
            $this->line("   <fg=red>✗</> {$name} — ".$e->getMessage());
        }
    }

    protected function indexExists(string $connection, string $driver, string $table, string $name): bool
    {
        try {
            return match ($driver) {
                'mysql', 'mariadb' => (bool) DB::connection($connection)->selectOne(
                    'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
                    [$table, $name],
                ),
                'pgsql' => (bool) DB::connection($connection)->selectOne(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1",
                    [$table, $name],
                ),
                'sqlite' => (bool) DB::connection($connection)->selectOne(
                    "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ? LIMIT 1",
                    [$name],
                ),
                'sqlsrv' => (bool) DB::connection($connection)->selectOne(
                    "SELECT 1 FROM sys.indexes WHERE name = ? LIMIT 1",
                    [$name],
                ),
                default => false,
            };
        } catch (\Throwable $e) {
            return false;
        }
    }
}
