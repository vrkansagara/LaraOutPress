<?php

/**
 * @copyright  Copyright (c) 2015-2023 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */

/**
 * Helper file
 */

if (! function_exists('usage')) {
    /**
     * print usage for the performance
     */
    function usage()
    {
        echo PHP_EOL . sprintf(
            "Script execution complete in %2.3f milliseconds",
            floor((microtime(true) - START_TIME) * 1000)
        );


        echo PHP_EOL . sprintf(
            "Current memeory usage %s",
            getCurrentMemoryUsage(memory_get_usage(true))
        ) . PHP_EOL;
    }
}

if (! function_exists('getCurrentMemoryUsage')) {
    /**
     * @usage echo getCurrentMemoryUsage(memory_get_usage(true)); // 123 kb
     * @param $size
     * @return string
     */
    function getCurrentMemoryUsage($size): string
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }
}

if (! function_exists('formatSizeUnits')) {
    /**
     * Get formatted size string
     *
     * @param int | float $size
     * @return string
     */
    function formatSizeUnits(int|float $size = 0): string
    {
        $base = log($size) / log(1024);
        return round(pow(1024, $base - floor($base)), 2) . ['', 'KB', 'MB', 'GB', 'TB'][floor($base)];
    }
}
