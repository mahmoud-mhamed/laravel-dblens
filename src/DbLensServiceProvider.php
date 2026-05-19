<?php

namespace MahmoudMhamed\DbLens;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MahmoudMhamed\DbLens\Console\Commands\DbLensInstallCommand;
use MahmoudMhamed\DbLens\Http\Middleware\AuthorizeDbLens;
use MahmoudMhamed\DbLens\Http\Middleware\DbLensThrottle;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\RowEditor;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Services\Exporter;
use MahmoudMhamed\DbLens\Services\Importer;
use MahmoudMhamed\DbLens\Services\ModelCastResolver;
use MahmoudMhamed\DbLens\Services\TableEditor;

class DbLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/dblens.php', 'dblens');

        $this->app->singleton(ConnectionManager::class, fn () => new ConnectionManager());
        $this->app->singleton(SchemaInspector::class, fn ($app) => new SchemaInspector($app->make(ConnectionManager::class)));
        $this->app->singleton(QueryRunner::class, fn ($app) => new QueryRunner($app->make(ConnectionManager::class)));
        $this->app->singleton(RowEditor::class, fn ($app) => new RowEditor($app->make(ConnectionManager::class), $app->make(SchemaInspector::class)));
        $this->app->singleton(TableEditor::class, fn ($app) => new TableEditor($app->make(ConnectionManager::class)));
        $this->app->singleton(Exporter::class, fn ($app) => new Exporter($app->make(ConnectionManager::class), $app->make(SchemaInspector::class)));
        $this->app->singleton(Importer::class, fn ($app) => new Importer($app->make(ConnectionManager::class), $app->make(SchemaInspector::class)));
        $this->app->singleton(ModelCastResolver::class, fn () => new ModelCastResolver());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/dblens.php' => config_path('dblens.php'),
        ], 'dblens-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/dblens'),
        ], 'dblens-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dblens');

        $this->registerDefaultGate();

        if ($this->isEnabledForEnvironment() && config('dblens.viewer.enabled', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                DbLensInstallCommand::class,
            ]);
        }
    }

    protected function isEnabledForEnvironment(): bool
    {
        if ($this->app->environment('local')) {
            return (bool) config('dblens.enable_local', true);
        }
        return (bool) config('dblens.enable_production', false);
    }

    protected function registerDefaultGate(): void
    {
        if (Gate::has('viewDbLens')) {
            return;
        }
        Gate::define('viewDbLens', function ($user = null) {
            return $this->app->environment('local');
        });
    }

    protected function registerRoutes(): void
    {
        $router = $this->app['router'];

        $middleware = (array) config('dblens.viewer.middleware', ['web']);
        $middleware[] = AuthorizeDbLens::class;

        if (config('dblens.throttle.enabled', true)) {
            $router->aliasMiddleware('dblens.throttle', DbLensThrottle::class);
            $middleware[] = 'dblens.throttle';
        }

        $router->middlewareGroup('dblens', $middleware);

        $router->group([
            'prefix' => config('dblens.viewer.path', 'dblens'),
            'domain' => config('dblens.viewer.domain'),
            'middleware' => 'dblens',
            'as' => 'dblens.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }
}
