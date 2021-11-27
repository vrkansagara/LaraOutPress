<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use Vrkansagara\LaraOutPress\Middleware\AfterMiddleware;

class ServiceProvider extends SupportServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/laraoutpress.php';
        $this->mergeConfigFrom($configPath, 'laraoutpress');
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/../config/laraoutpress.php';
        $this->publishes([$configPath => $this->getConfigPath()], 'config');
        $this->registerMiddleware(AfterMiddleware::class);
    }

    /**
     * Get the active router.
     *
     * @return Router
     */
    protected function getRouter()
    {
        return $this->app['router'];
    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath()
    {
        return config_path('laraoutpress.php');
    }

    /**
     * Publish the config file
     *
     * @param  string $configPath
     */
    protected function publishConfig($configPath)
    {
        $this->publishes([$configPath => config_path('laraoutpress.php')], 'config');
    }

    /**
     * Register the LaraOutPress Middleware
     *
     * @param  string $middleware
     */
    protected function registerMiddleware($middleware)
    {
        $kernel = $this->app[Kernel::class];
        $kernel->pushMiddleware($middleware);
    }
}
