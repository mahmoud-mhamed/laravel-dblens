<?php

namespace MahmoudMhamed\DbLens;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MahmoudMhamed\DbLens\Console\Commands\DbLensActivityLogInstallCommand;
use MahmoudMhamed\DbLens\Console\Commands\DbLensInstallCommand;
use MahmoudMhamed\DbLens\Console\Commands\DbLensMakeMigrationCommand;
use MahmoudMhamed\DbLens\Http\Middleware\AuthorizeDbLens;
use MahmoudMhamed\DbLens\Http\Middleware\DbLensThrottle;
use MahmoudMhamed\DbLens\Services\ActivityContext;
use MahmoudMhamed\DbLens\Services\ActivityLogger;
use MahmoudMhamed\DbLens\Services\TenancyManager;
use MahmoudMhamed\DbLens\Services\ConnectionManager;
use MahmoudMhamed\DbLens\Services\QueryRunner;
use MahmoudMhamed\DbLens\Services\RowEditor;
use MahmoudMhamed\DbLens\Services\SchemaInspector;
use MahmoudMhamed\DbLens\Services\ErViewStorage;
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
        $this->app->singleton(TableEditor::class, fn ($app) => new TableEditor(
            $app->make(ConnectionManager::class),
            $app->make(ActivityLogger::class),
            $app->make(SchemaInspector::class),
        ));
        $this->app->singleton(Exporter::class, fn ($app) => new Exporter($app->make(ConnectionManager::class), $app->make(SchemaInspector::class)));
        $this->app->singleton(Importer::class, fn ($app) => new Importer($app->make(ConnectionManager::class), $app->make(SchemaInspector::class)));
        $this->app->singleton(ModelCastResolver::class, fn () => new ModelCastResolver());
        $this->app->singleton(ErViewStorage::class, fn () => new ErViewStorage());
        $this->app->singleton(ActivityContext::class, fn () => new ActivityContext());
        $this->app->singleton(ActivityLogger::class, fn ($app) => new ActivityLogger($app->make(ActivityContext::class)));
        $this->app->singleton(TenancyManager::class, fn () => new TenancyManager());
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

        if (config('dblens.activity_log.enrich.performance_data', true)) {
            $this->app->make(ActivityContext::class)->startCollecting();
        }

        $this->registerDefaultGate();

        if ($this->isEnabledForEnvironment() && config('dblens.viewer.enabled', true)) {
            $this->registerRoutes();
            $this->registerConnectionErrorRenderer();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                DbLensInstallCommand::class,
                DbLensMakeMigrationCommand::class,
                DbLensActivityLogInstallCommand::class,
            ]);
        }
    }

    /**
     * Render driver/connection failures from dblens routes as a friendly page
     * instead of Laravel's stack-trace error screen.
     */
    protected function registerConnectionErrorRenderer(): void
    {
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        if (! method_exists($handler, 'renderable')) return;

        $prefix = trim((string) config('dblens.viewer.path', 'dblens'), '/');

        $handler->renderable(function (\Throwable $e, $request) use ($prefix) {
            if (! $request->is($prefix.'*')) return null;
            $isConnError = $e instanceof \Illuminate\Database\QueryException
                        || $e instanceof \PDOException
                        || ($e->getPrevious() instanceof \PDOException)
                        || ($e instanceof \InvalidArgumentException && str_starts_with($e->getMessage(), 'DbLens: driver '))
                        || preg_match('/Database connection \[.+\] not configured|Driver \[.+\] not supported/i', $e->getMessage());
            if (! $isConnError) return null;

            $connection = $request->route('connection');
            $connections = $this->app->make(ConnectionManager::class)->available();

            return response()->view('dblens::connection-error', [
                'connection' => $connection,
                'connections' => $connections,
                'database' => null,
                'tables' => [],
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'config' => $connection ? config("database.connections.{$connection}", []) : [],
            ], 503);
        });
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

        $middleware = $this->resolveViewerMiddleware();
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

    /**
     * Build the viewer middleware stack.
     *
     * When `dblens.tenancy.identification_middleware` is configured (typically
     * stancl/tenancy's `InitializeTenancyByDomain` + `PreventAccessFromCentralDomains`),
     * those classes are spliced in just before the first `auth` / `auth:*` entry
     * so the auth guard resolves against the tenant database — not the central one.
     * If no `auth` entry exists, the tenancy middleware is appended.
     *
     * @return array<int,string>
     */
    protected function resolveViewerMiddleware(): array
    {
        $middleware = array_values((array) config('dblens.viewer.middleware', ['web']));

        $tenancy = array_values(array_filter(
            (array) config('dblens.tenancy.identification_middleware', []),
            fn ($m) => is_string($m) && $m !== ''
        ));
        if (empty($tenancy)) {
            return $middleware;
        }

        $authIndex = null;
        foreach ($middleware as $i => $m) {
            if (! is_string($m)) continue;
            if ($m === 'auth' || str_starts_with($m, 'auth:')) {
                $authIndex = $i;
                break;
            }
        }

        if ($authIndex === null) {
            return array_merge($middleware, $tenancy);
        }

        array_splice($middleware, $authIndex, 0, $tenancy);
        return $middleware;
    }
}
