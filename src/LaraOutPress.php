<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress;

use Illuminate\Contracts\Foundation\Application;

/**
 * @copyright  Copyright (c) 2015-2024 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
class LaraOutPress
{
    /**
     * The Laravel application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected Application $app;

    /**
     * Normalized Laravel Version
     *
     * @var string
     */
    protected string $version;

    /**
     * True when enabled, false disabled an null for still unknown
     *
     * @var bool
     */
    protected $enabled;


    /**
     * @var null
     */
    protected $config;

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     */
    public function setVersion($version): void
    {
        $this->version = $version;
    }

    /**
     * @return \Illuminate\Foundation\Application
     */
    public function getApp(): Application
    {
        return $this->app;
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    public function setApp($app): void
    {
        $this->app = $app;
    }


    /**
     * @param Application $app
     */
    public function __construct($app = null)
    {
        if (! $app) {
            $app = app();   //Fallback when $app is not given
        }
        $this->setApp($app);
        $this->setConfig();
        $this->setEnabled();
        $this->setVersion($app->version());
    }

    /**
     * @return null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param null $config
     */
    public function setConfig()
    {
        $applicationConfig = $this->app['config'];
        $this->config = $applicationConfig->get('laraoutpress');
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool
     */
    public function setEnabled(): bool
    {
        if ($this->enabled === null) {
            $config = $this->config;
            $configEnabled = value($config['enabled']);
            $this->enabled = ($configEnabled && ! $this->app->runningInConsole()) ? $configEnabled : false;
        }
        return $this->enabled;
    }

    /**
     * Get formatted size string
     *
     * @param float $size
     * @return string
     */
    public static function formatSizeUnits($size = 0): string
    {
        $base = log($size) / log(1024);
        return round(pow(1024, $base - floor($base)), 2) . ['', 'KB', 'MB', 'GB', 'TB'][floor($base)];
    }
}
