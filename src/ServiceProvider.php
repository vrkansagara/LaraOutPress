<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use Vrkansagara\LaraOutPress\Middleware\AfterMiddleware;

/**
 * @copyright  Copyright (c) 2015-2023 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
class ServiceProvider extends SupportServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected bool $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $configPath = __DIR__ . '/../config/laraoutpress.php';
        $this->mergeConfigFrom($configPath, 'laraoutpress');
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laraoutpress.php' => config_path('laraoutpress.php'),
        ]);
        $this->registerMiddleware(AfterMiddleware::class);
    }

    /**
     * Get the active router.
     *
     * @return Router
     */
    protected function getRouter(): Router
    {
        return $this->app['router'];
    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath(): string
    {
        return config_path('laraoutpress.php');
    }

    /**
     * Publish the config file
     *
     * @param  string $configPath
     */
    protected function publishConfig($configPath): void
    {
        $this->publishes([$configPath => config_path('laraoutpress.php')], 'config');
    }

    /**
     * Register the LaraOutPress Middleware
     *
     * @param  string $middleware
     */
    protected function registerMiddleware($middleware): void
    {
        $kernel = $this->app[Kernel::class];
        $kernel->pushMiddleware($middleware);
    }
}
