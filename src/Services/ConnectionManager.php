<?php

namespace MahmoudMhamed\DbLens\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MahmoudMhamed\DbLens\Support\Drivers\DriverInterface;
use MahmoudMhamed\DbLens\Support\Drivers\MySqlDriver;
use MahmoudMhamed\DbLens\Support\Drivers\PgsqlDriver;
use MahmoudMhamed\DbLens\Support\Drivers\SqliteDriver;

class ConnectionManager
{
    /**
     * Return the list of connection names DbLens may use.
     *
     * @return array<int,string>
     */
    public function available(): array
    {
        $all = array_keys(config('database.connections', []));
        $allowed = (array) config('dblens.connections.allowed', []);
        if (empty($allowed)) {
            return $all;
        }
        return array_values(array_intersect($all, $allowed));
    }

    public function default(): string
    {
        $cfg = config('dblens.connections.default') ?: config('database.default');
        $available = $this->available();
        if (in_array($cfg, $available, true)) {
            return $cfg;
        }
        if (! empty($available)) {
            return $available[0];
        }
        throw new InvalidArgumentException('DbLens: no usable database connection.');
    }

    public function assertAllowed(string $name): void
    {
        if (! in_array($name, $this->available(), true)) {
            throw new InvalidArgumentException("DbLens: connection [{$name}] is not allowed.");
        }
    }

    public function connection(string $name): Connection
    {
        $this->assertAllowed($name);
        return DB::connection($name);
    }

    public function driver(string $name): DriverInterface
    {
        $conn = $this->connection($name);
        return match ($conn->getDriverName()) {
            'mysql', 'mariadb' => new MySqlDriver($conn),
            'pgsql' => new PgsqlDriver($conn),
            'sqlite' => new SqliteDriver($conn),
            default => throw new InvalidArgumentException('DbLens: driver ['.$conn->getDriverName().'] not supported yet.'),
        };
    }

    public function databaseName(string $connection): string
    {
        return (string) $this->connection($connection)->getDatabaseName();
    }
}
