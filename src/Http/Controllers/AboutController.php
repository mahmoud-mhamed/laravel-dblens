<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use MahmoudMhamed\DbLens\Services\ActivityLogger;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class AboutController extends Controller
{
    public function show(ConnectionManager $cm, SchemaInspector $schema)
    {
        $connection = $cm->default();
        $database = null;
        $tables = [];
        $driverName = null;
        $serverVersion = null;
        $totalSize = 0;
        try {
            $database = $cm->databaseName($connection);
            $tables = $schema->tables($connection);
            $conn = $cm->connection($connection);
            $driverName = $conn->getDriverName();
            $serverVersion = $this->serverVersion($conn);
            foreach ($tables as $t) {
                $totalSize += (int) ($t['size'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Don't crash the about page just because the DB is unreachable.
        }

        return view('dblens::about', [
            'connection' => $connection,
            'connections' => $cm->available(),
            'database' => $database,
            'tables' => $tables,
            'driver_name' => $driverName,
            'server_version' => $serverVersion,
            'total_size_bytes' => $totalSize,
            'version' => $this->packageVersion(),
            'activity_log_stats' => $this->activityLogStats(),
        ]);
    }

    protected function activityLogStats(): array
    {
        $logger = app(ActivityLogger::class);
        if (! $logger->isEnabled()) {
            return ['enabled' => false];
        }
        $connection = config('dblens.activity_log.connection')
            ?: config('activitylog.database_connection')
            ?: config('database.default');
        $table = (string) config('activitylog.table_name', 'activity_log');

        try {
            $total = (int) (DB::connection($connection)->selectOne("SELECT COUNT(*) as c FROM {$table}")->c ?? 0);
            $today = (int) (DB::connection($connection)->selectOne(
                "SELECT COUNT(*) as c FROM {$table} WHERE created_at >= ?",
                [now()->subDay()->format('Y-m-d H:i:s')]
            )->c ?? 0);
            $latest = DB::connection($connection)->selectOne("SELECT created_at FROM {$table} ORDER BY id DESC LIMIT 1");
            return [
                'enabled' => true,
                'total' => $total,
                'last_24h' => $today,
                'latest_at' => $latest->created_at ?? null,
                'log_name' => (string) config('dblens.activity_log.log_name', 'dblens'),
            ];
        } catch (\Throwable $e) {
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }

    protected function serverVersion($conn): ?string
    {
        try {
            return (string) $conn->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function packageVersion(): string
    {
        $composer = dirname(__DIR__, 3).'/composer.json';
        if (! is_file($composer)) return 'dev';
        $data = json_decode((string) file_get_contents($composer), true);
        return (string) ($data['version'] ?? 'dev');
    }
}
