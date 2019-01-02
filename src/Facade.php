<?php
namespace Vrkansagara\LaraOutPress;

/**
 * @copyright  Copyright (c) 2015-2019 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return LaraOutPress::class;
    }
}