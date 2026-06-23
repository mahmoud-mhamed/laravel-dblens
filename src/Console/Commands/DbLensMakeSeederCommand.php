<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use MahmoudMhamed\DbLens\Console\Concerns\GeneratesFromTable;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\SchemaInspector;

class DbLensMakeSeederCommand extends Command
{
    use GeneratesFromTable;

    protected $signature = 'dblens:make-seeder
        {table : Table to seed}
        {--connection= : Connection name (defaults to dblens.connections[0] or db default)}
        {--model= : Model class name (defaults to the singular StudlyCase of the table)}
        {--count=10 : How many rows the seeder should create}
        {--path= : Output directory (defaults to database/seeders)}
        {--force : Overwrite an existing file}';

    protected $description = 'Generate a database seeder for an existing table (uses the model factory)';

    public function handle(ConnectionManager $cm, SchemaInspector $schema): int
    {
        $connection = $this->resolveConnection($cm);
        $table = (string) $this->argument('table');
        if (! $this->ensureTable($schema, $connection, $table)) return self::FAILURE;

        $model = (string) ($this->option('model') ?: $this->modelName($table));
        $count = max(1, (int) $this->option('count'));
        $class = "{$model}Seeder";

        $stub = <<<PHP
<?php

namespace Database\\Seeders;

use App\\Models\\{$model};
use Illuminate\\Database\\Seeder;

class {$class} extends Seeder
{
    public function run(): void
    {
        // Requires App\\Models\\{$model} and its factory.
        // Generate them with: php artisan dblens:make-model {$table} && php artisan dblens:make-factory {$table}
        {$model}::factory()->count({$count})->create();
    }
}

PHP;

        $path = (string) ($this->option('path') ?: database_path('seeders'));
        $this->putFile(rtrim($path, '/')."/{$class}.php", $stub);

        return self::SUCCESS;
    }
}
