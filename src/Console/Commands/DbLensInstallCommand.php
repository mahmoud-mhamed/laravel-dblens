<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MahmoudMhamed\DbLens\Services\ConnectionManager;

class DbLensInstallCommand extends Command
{
    protected $signature = 'dblens:install
        {--force : Overwrite existing files}
        {--views : Also publish the Blade views for customization}';

    protected $description = 'Publish DbLens config (and optionally views) and verify the environment';

    public function handle(ConnectionManager $cm): int
    {
        $this->line('');
        $this->info('━━━━━━━━━━━ DbLens install ━━━━━━━━━━━');

        $this->task('Publishing config', function () {
            return 0 === $this->call('vendor:publish', [
                '--tag' => 'dblens-config',
                '--force' => $this->option('force'),
            ]);
        });

        if ($this->option('views')) {
            $this->task('Publishing views', function () {
                return 0 === $this->call('vendor:publish', [
                    '--tag' => 'dblens-views',
                    '--force' => $this->option('force'),
                ]);
            });
        }

        $this->line('');
        $this->info('Environment checks');
        $this->line(str_repeat('─', 38));

        $this->check('Storage path writable', is_writable(storage_path()), 'Run: chmod -R ug+w '.storage_path());

        $env = $this->laravel->environment();
        $this->check("Environment: {$env}", true);

        $local = config('dblens.enable_local', true);
        $prod = config('dblens.enable_production', false);
        if ($env === 'local') {
            $this->check('Enabled in local', (bool) $local, $local ? null : 'Set dblens.enable_local=true');
        } else {
            $this->check("Enabled in {$env}", (bool) $prod, $prod ? null : "Set dblens.enable_production=true to expose in {$env}");
        }

        $this->check('viewDbLens gate registered', \Illuminate\Support\Facades\Gate::has('viewDbLens'),
            'Define Gate::define(\'viewDbLens\', fn ($user) => …) in AppServiceProvider');

        $readOnly = config('dblens.read_only', false);
        $this->line(sprintf('   <fg=%s>•</> read_only mode: %s', $readOnly ? 'yellow' : 'gray', $readOnly ? 'ON' : 'off'));

        $logger = $this->laravel->make(\MahmoudMhamed\DbLens\Services\ActivityLogger::class);
        $cfg = config('dblens.activity_log.enabled', 'auto');
        $cfgLabel = $cfg === 'auto' ? 'auto' : ($cfg ? 'on' : 'off');
        $live = $logger->isEnabled() ? 'active' : 'inactive';
        $color = $logger->isEnabled() ? 'green' : 'gray';
        $this->line(sprintf('   <fg=%s>•</> activity_log: %s (%s)', $color, $cfgLabel, $live));
        if ($cfg === 'auto' && ! $logger->isEnabled()) {
            $this->line('       <fg=gray>↳ Install spatie/laravel-activitylog and run its migrations to enable.</>');
        }

        $this->line('');
        $this->info('Database connections');
        $this->line(str_repeat('─', 38));

        $allowed = (array) config('dblens.connections', []);
        if (empty($allowed)) {
            $allowed = [config('database.default')];
            $this->line('   <fg=gray>(no explicit dblens.connections — using default: '.$allowed[0].')</>');
        }

        foreach ($allowed as $name) {
            try {
                DB::connection($name)->getPdo();
                $driver = DB::connection($name)->getDriverName();
                $this->check("{$name} ({$driver})", true);
            } catch (\Throwable $e) {
                $this->check($name, false, $e->getMessage());
            }
        }

        $path = config('dblens.viewer.path', 'dblens');
        $this->line('');
        $this->info("✓ Done. Open: /{$path}");
        return self::SUCCESS;
    }

    protected function check(string $label, bool $ok, ?string $hint = null): void
    {
        $icon = $ok ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $this->line("   {$icon} {$label}");
        if (! $ok && $hint) {
            $this->line("       <fg=yellow>↳ {$hint}</>");
        }
    }

    protected function task(string $label, \Closure $cb): void
    {
        $this->output->write("   <fg=cyan>…</> {$label} ");
        $ok = false;
        try {
            $ok = (bool) $cb();
        } catch (\Throwable $e) {
            $this->output->writeln('<fg=red>FAIL</> '.$e->getMessage());
            return;
        }
        $this->output->writeln($ok ? '<fg=green>OK</>' : '<fg=red>FAIL</>');
    }
}
