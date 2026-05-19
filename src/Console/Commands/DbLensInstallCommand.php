<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;

class DbLensInstallCommand extends Command
{
    protected $signature = 'dblens:install {--force : Overwrite existing files}';
    protected $description = 'Publish DbLens config and views';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'dblens-config',
            '--force' => $this->option('force'),
        ]);
        $this->call('vendor:publish', [
            '--tag' => 'dblens-views',
            '--force' => $this->option('force'),
        ]);

        $this->info('DbLens installed. Visit /' . config('dblens.viewer.path', 'dblens'));
        return self::SUCCESS;
    }
}
