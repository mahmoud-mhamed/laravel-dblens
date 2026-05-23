<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DbLensMakeMigrationCommand extends Command
{
    protected $signature = 'dblens:make-migration
        {table : Table name to reverse-engineer}
        {--connection= : Connection name (defaults to config dblens.connections[0] or db default)}
        {--path= : Output directory (defaults to database/migrations)}
        {--with-data : Also generate a data dump migration (small tables only)}';

    protected $description = 'Generate a Laravel migration file from an existing table';

    public function handle(ConnectionManager $cm, SchemaInspector $schema): int
    {
        $connection = (string) ($this->option('connection') ?: ($cm->available()[0] ?? config('database.default')));
        $table = $this->argument('table');

        if (! $schema->tableExists($connection, $table)) {
            $this->error("Table [{$table}] not found on connection [{$connection}].");
            return self::FAILURE;
        }

        $columns = $schema->columns($connection, $table);
        $indexes = $schema->indexes($connection, $table);
        $fks = $schema->foreignKeys($connection, $table);
        $pk = $schema->primaryKey($connection, $table);

        $body = $this->buildSchemaBody($table, $columns, $indexes, $fks, $pk);

        $stub = $this->stub($table, $body);
        $path = (string) ($this->option('path') ?: database_path('migrations'));
        if (! is_dir($path)) mkdir($path, 0755, true);

        $filename = date('Y_m_d_His')."_create_{$table}_table.php";
        $full = rtrim($path, '/').'/'.$filename;
        file_put_contents($full, $stub);

        $this->info("✓ Created {$full}");
        return self::SUCCESS;
    }

    protected function buildSchemaBody(string $table, array $columns, array $indexes, array $fks, array $pk): string
    {
        $lines = [];
        $autoIncCol = null;
        foreach ($columns as $c) {
            if (str_contains(strtolower($c['extra'] ?? ''), 'auto_increment')) {
                $autoIncCol = $c['name'];
                break;
            }
        }

        $hasStandardId = $autoIncCol === 'id';
        $hasTimestamps = $this->hasCols($columns, ['created_at', 'updated_at']);
        $hasSoftDeletes = $this->hasCols($columns, ['deleted_at']);

        if ($hasStandardId) $lines[] = "\$table->id();";

        $skip = [];
        if ($hasStandardId) $skip[] = 'id';
        if ($hasTimestamps) { $skip[] = 'created_at'; $skip[] = 'updated_at'; }
        if ($hasSoftDeletes) $skip[] = 'deleted_at';

        foreach ($columns as $c) {
            if (in_array($c['name'], $skip, true)) continue;
            $lines[] = $this->columnLine($c, $pk);
        }

        if ($hasTimestamps) $lines[] = "\$table->timestamps();";
        if ($hasSoftDeletes) $lines[] = "\$table->softDeletes();";

        if (count($pk) > 1) {
            $cols = "['".implode("', '", $pk)."']";
            $lines[] = "\$table->primary({$cols});";
        }

        foreach ($indexes as $idx) {
            if ($idx['primary']) continue;
            $cols = "['".implode("', '", $idx['columns'])."']";
            $method = $idx['unique'] ? 'unique' : 'index';
            $lines[] = "\$table->{$method}({$cols}, '{$idx['name']}');";
        }

        foreach ($fks as $fk) {
            $line = "\$table->foreign('{$fk['column']}', '{$fk['name']}')->references('{$fk['foreign_column']}')->on('{$fk['foreign_table']}')";
            if (! empty($fk['on_delete']) && $fk['on_delete'] !== 'NO ACTION') {
                $line .= "->onDelete('".strtolower($fk['on_delete'])."')";
            }
            if (! empty($fk['on_update']) && $fk['on_update'] !== 'NO ACTION') {
                $line .= "->onUpdate('".strtolower($fk['on_update'])."')";
            }
            $lines[] = $line.';';
        }

        return implode("\n            ", $lines);
    }

    protected function hasCols(array $columns, array $names): bool
    {
        $colNames = array_map(fn ($c) => $c['name'], $columns);
        foreach ($names as $n) {
            if (! in_array($n, $colNames, true)) return false;
        }
        return true;
    }

    protected function columnLine(array $c, array $pk): string
    {
        $name = $c['name'];
        $type = strtolower($c['type']);
        $method = 'string';
        $args = "'{$name}'";

        if (preg_match('/^(tinyint|smallint|mediumint|bigint|int|integer)\b/', $type, $m)) {
            $map = ['tinyint'=>'tinyInteger','smallint'=>'smallInteger','mediumint'=>'mediumInteger','bigint'=>'bigInteger','int'=>'integer','integer'=>'integer'];
            $method = $map[$m[1]] ?? 'integer';
            if (preg_match('/unsigned/', $type)) $method = 'unsigned'.ucfirst($method);
            if (preg_match('/tinyint\(1\)/', $type)) { $method = 'boolean'; }
        } elseif (preg_match('/^varchar\((\d+)\)/', $type, $m)) {
            $method = 'string'; $args .= ", {$m[1]}";
        } elseif (preg_match('/^char\((\d+)\)/', $type, $m)) {
            $method = 'char'; $args .= ", {$m[1]}";
        } elseif (str_starts_with($type, 'longtext')) { $method = 'longText'; }
        elseif (str_starts_with($type, 'mediumtext')) { $method = 'mediumText'; }
        elseif (str_starts_with($type, 'text')) { $method = 'text'; }
        elseif (str_starts_with($type, 'tinytext')) { $method = 'tinyText'; }
        elseif (str_starts_with($type, 'json')) { $method = 'json'; }
        elseif (str_starts_with($type, 'uuid')) { $method = 'uuid'; }
        elseif (str_starts_with($type, 'date')) {
            $method = str_starts_with($type, 'datetime') ? 'dateTime'
                   : (str_starts_with($type, 'timestamp') ? 'timestamp' : 'date');
        } elseif (str_starts_with($type, 'time')) { $method = 'time'; }
        elseif (preg_match('/^decimal\((\d+),\s*(\d+)\)/', $type, $m)) {
            $method = 'decimal'; $args .= ", {$m[1]}, {$m[2]}";
        } elseif (str_starts_with($type, 'float')) { $method = 'float'; }
        elseif (str_starts_with($type, 'double')) { $method = 'double'; }
        elseif (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
            $method = 'enum'; $args .= ', ['.$m[1].']';
        } elseif (preg_match('/^(binary|blob|bytea)/', $type)) {
            $method = 'binary';
        }

        $line = "\$table->{$method}({$args})";

        if ($c['nullable']) $line .= '->nullable()';

        $default = $c['default'] ?? null;
        if ($default !== null && ! str_contains(strtolower($c['extra'] ?? ''), 'auto_increment')) {
            if (preg_match('/^CURRENT_TIMESTAMP/i', $default)) {
                $line .= "->useCurrent()";
            } elseif (is_numeric($default)) {
                $line .= "->default({$default})";
            } else {
                $line .= "->default('".addslashes($default)."')";
            }
        }

        if (! empty($c['comment'])) {
            $line .= "->comment('".addslashes($c['comment'])."')";
        }

        return $line.';';
    }

    protected function stub(string $table, string $body): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            {$body}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
    }
}
