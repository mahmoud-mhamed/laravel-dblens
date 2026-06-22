<?php

namespace MahmoudMhamed\DbLens\Console\Commands;

use Illuminate\Console\Command;

class DbLensPublishConfigCommand extends Command
{
    protected $signature = 'dblens:publish-config
        {--force : Overwrite the existing config/dblens.php}';

    protected $description = 'Publish the DbLens config file to config/dblens.php';

    public function handle(): int
    {
        $published = 0 === $this->call('vendor:publish', [
            '--tag' => 'dblens-config',
            '--force' => (bool) $this->option('force'),
        ]);

        if (! $published) {
            $this->error('Failed to publish the DbLens config.');

            return self::FAILURE;
        }

        $this->info($this->option('force')
            ? 'DbLens config published (existing file overwritten): config/dblens.php'
            : 'DbLens config published: config/dblens.php (use --force to overwrite).');

        return self::SUCCESS;
    }
}
