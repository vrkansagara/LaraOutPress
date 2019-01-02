<?php

namespace Vrkansagara\LaraOutPress;

use Illuminate\Contracts\Foundation\Application;

/**
 * @copyright  Copyright (c) 2015-2019 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
class LaraOutPress
{

    /**
     * The Laravel application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * Normalized Laravel Version
     *
     * @var string
     */
    protected $version;

    /**
     * True when this is a Lumen application
     *
     * @var bool
     */
    protected $is_lumen = false;

    /**
     * True when enabled, false disabled an null for still unknown
     *
     * @var bool
     */
    protected $enabled = null;



    /**
     * @param Application $app
     */
    public function __construct($app = null)
    {
        if (!$app) {
            $app = app();   //Fallback when $app is not given
        }
        $this->app = $app;
        $this->version = $app->version();
        $this->is_lumen = str_contains($this->version, 'Lumen');
    }

    /**
     * Check if the LaraOutPress is enabled
     * @return boolean
     */
    public function isEnabled()
    {
        if ($this->enabled === null) {
            $config = $this->app['config'];
            $configEnabled = value($config->get('laraoutpress.enabled'));
            $this->enabled = ( $configEnabled && !$this->app->runningInConsole() ) ? $configEnabled : false;
        }
        return $this->enabled;
    }


}