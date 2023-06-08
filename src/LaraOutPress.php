<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress;

use Illuminate\Contracts\Foundation\Application;

/**
 * @copyright  Copyright (c) 2015-2023 Vallabh Kansagara <vrkansagara@gmail.com>
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
    protected bool $enabled = false;


    /**
     * @var null
     */
    protected mixed $config;

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
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param mixed $config
     */
    public function setConfig(): void
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
        $config = $this->config;
        $configEnabled = value($config['enabled']);
        if ($this->app->runningInConsole()) {
            $this->enabled = false;
        } else {
            $this->enabled = $configEnabled;
        }
        return $this->enabled;
    }
}
