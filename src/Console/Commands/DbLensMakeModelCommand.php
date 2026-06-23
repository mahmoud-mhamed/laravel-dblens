<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MahmoudMhamed\DbLens\Console\Concerns\GeneratesFromTable;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DbLensMakeModelCommand extends Command
{
    use GeneratesFromTable;

    protected $signature = 'dblens:make-model
        {table : Table to reverse-engineer}
        {--connection= : Connection name (defaults to dblens.connections[0] or db default)}
        {--namespace=App\\Models : Model namespace}
        {--path= : Output directory (defaults to app/Models)}
        {--force : Overwrite an existing file}';

    protected $description = 'Generate an Eloquent model from an existing table';

    public function handle(ConnectionManager $cm, SchemaInspector $schema): int
    {
        $connection = $this->resolveConnection($cm);
        $table = (string) $this->argument('table');
        if (! $this->ensureTable($schema, $connection, $table)) return self::FAILURE;

        $columns = $schema->columns($connection, $table);
        $pk = $schema->primaryKey($connection, $table);
        $colNames = array_map(fn ($c) => $c['name'], $columns);

        $class = $this->modelName($table);
        $namespace = trim((string) $this->option('namespace'), '\\');

        $fillable = $this->fillableColumns($columns, $pk);
        $casts = $this->casts($columns, $pk);
        $hasTimestamps = in_array('created_at', $colNames, true) && in_array('updated_at', $colNames, true);
        $hasSoftDeletes = in_array('deleted_at', $colNames, true);

        $body = $this->classBody($table, $class, $pk, $columns, $fillable, $casts, $hasTimestamps, $hasSoftDeletes);

        $uses = ["use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;", 'use Illuminate\\Database\\Eloquent\\Model;'];
        if ($hasSoftDeletes) $uses[] = 'use Illuminate\\Database\\Eloquent\\SoftDeletes;';
        sort($uses);

        $stub = "<?php\n\nnamespace {$namespace};\n\n".implode("\n", $uses)."\n\nclass {$class} extends Model\n{\n{$body}}\n";

        $path = (string) ($this->option('path') ?: app_path('Models'));
        $this->putFile(rtrim($path, '/')."/{$class}.php", $stub);

        return self::SUCCESS;
    }

    protected function classBody(string $table, string $class, array $pk, array $columns, array $fillable, array $casts, bool $hasTimestamps, bool $hasSoftDeletes): string
    {
        $traits = ['HasFactory'];
        if ($hasSoftDeletes) $traits[] = 'SoftDeletes';
        $lines = ['    use '.implode(', ', $traits).";", ''];

        // table name if it isn't the convention (snake plural of the class)
        if ($table !== Str::snake(Str::pluralStudly($class))) {
            $lines[] = "    protected \$table = '{$table}';";
        }

        // primary key tweaks
        if (count($pk) === 1 && $pk[0] !== 'id') {
            $lines[] = "    protected \$primaryKey = '{$pk[0]}';";
        }
        if (count($pk) === 1) {
            $pkCol = collect($columns)->firstWhere('name', $pk[0]);
            $isInt = $pkCol && preg_match('/int/i', (string) $pkCol['type']);
            $isAuto = $pkCol && stripos((string) ($pkCol['extra'] ?? ''), 'auto_increment') !== false;
            if (! $isInt) {
                $lines[] = "    protected \$keyType = 'string';";
            }
            if (! $isAuto && ! $isInt) {
                $lines[] = "    public \$incrementing = false;";
            }
        }

        if (! $hasTimestamps) {
            $lines[] = '    public $timestamps = false;';
        }

        $lines[] = '';
        $lines[] = '    protected $fillable = ['.$this->phpList($fillable).'];';

        if (! empty($casts)) {
            $lines[] = '';
            $lines[] = '    protected $casts = [';
            foreach ($casts as $col => $cast) {
                $lines[] = "        '{$col}' => '{$cast}',";
            }
            $lines[] = '    ];';
        }

        return implode("\n", $lines)."\n";
    }

    protected function phpList(array $items): string
    {
        return implode(', ', array_map(fn ($i) => "'{$i}'", $items));
    }

    protected function casts(array $columns, array $pk): array
    {
        $skip = array_merge($pk, ['created_at', 'updated_at', 'deleted_at']);
        $out = [];
        foreach ($columns as $c) {
            if (in_array($c['name'], $skip, true)) continue;
            $cast = $this->castFor($c);
            if ($cast) $out[$c['name']] = $cast;
        }

        return $out;
    }

    protected function castFor(array $c): ?string
    {
        $type = strtolower((string) $c['type']);
        if (preg_match('/tinyint\(1\)/', $type) || str_starts_with($type, 'bool')) return 'boolean';
        if (preg_match('/^(tinyint|smallint|mediumint|bigint|int|integer)/', $type)) return 'integer';
        if (preg_match('/^(decimal|numeric)/', $type)) {
            return 'decimal:'.(preg_match('/\(\d+,\s*(\d+)\)/', $type, $m) ? $m[1] : '2');
        }
        if (preg_match('/^(float|double|real)/', $type)) return 'float';
        if (str_starts_with($type, 'json')) return 'array';
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) return 'datetime';
        if (str_starts_with($type, 'date')) return 'date';

        return null;
    }
}
