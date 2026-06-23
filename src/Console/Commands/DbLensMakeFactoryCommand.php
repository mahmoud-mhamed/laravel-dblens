<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\DbLens\Console\Concerns\GeneratesFromTable;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Support\ColumnFaker;

class DbLensMakeFactoryCommand extends Command
{
    use GeneratesFromTable;

    protected $signature = 'dblens:make-factory
        {table : Table to reverse-engineer}
        {--connection= : Connection name (defaults to dblens.connections[0] or db default)}
        {--model= : Model class name (defaults to the singular StudlyCase of the table)}
        {--namespace=Database\\Factories : Factory namespace}
        {--path= : Output directory (defaults to database/factories)}
        {--force : Overwrite an existing file}';

    protected $description = 'Generate a model factory (Faker definitions) from an existing table';

    public function handle(ConnectionManager $cm, SchemaInspector $schema): int
    {
        $connection = $this->resolveConnection($cm);
        $table = (string) $this->argument('table');
        if (! $this->ensureTable($schema, $connection, $table)) return self::FAILURE;

        $columns = $schema->columns($connection, $table);
        $pk = $schema->primaryKey($connection, $table);
        $fillable = $this->fillableColumns($columns, $pk);
        $byName = collect($columns)->keyBy('name');

        $model = (string) ($this->option('model') ?: $this->modelName($table));
        $namespace = trim((string) $this->option('namespace'), '\\');

        $defs = [];
        foreach ($fillable as $col) {
            $defs[] = "            '{$col}' => ".ColumnFaker::expression($byName[$col]).',';
        }

        $body = implode("\n", $defs);
        $class = "{$model}Factory";

        $stub = <<<PHP
<?php

namespace {$namespace};

use App\\Models\\{$model};
use Illuminate\\Database\\Eloquent\\Factories\\Factory;

/**
 * @extends \\Illuminate\\Database\\Eloquent\\Factories\\Factory<\\App\\Models\\{$model}>
 */
class {$class} extends Factory
{
    protected \$model = {$model}::class;

    public function definition(): array
    {
        return [
{$body}
        ];
    }
}

PHP;

        $path = (string) ($this->option('path') ?: database_path('factories'));
        $this->putFile(rtrim($path, '/')."/{$class}.php", $stub);

        return self::SUCCESS;
    }
}
