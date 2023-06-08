<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Middleware;

use Closure;
use Illuminate\Http\Response;
use Vrkansagara\LaraOutPress\HtmlCompressor;
use Vrkansagara\LaraOutPress\LaraOutPress;

include_once __DIR__ . '/../helpers.php';


/**
 * @copyright  Copyright (c) 2015-2023 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
class AfterMiddleware
{
    /** * @var $bufferOldSize int */
    public $bufferOldSize;

    /** * @var $bufferNewSize int */
    public $bufferNewSize;


    /** * @var LaraOutPress */
    protected $laraOutPress;

    /**
     * Create a new middleware instance.
     * AfterMiddleware constructor.
     *
     * @param LaraOutPress $laraOutPress
     */
    public function __construct(LaraOutPress $laraOutPress)
    {
        $this->laraOutPress = $laraOutPress;
    }


    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     *
     * @return Response
     */
    public function handle($request, Closure $next): Response
    {
        # Priority :- 1 Is enable ?
        if (! $this->laraOutPress->isEnabled()) {
            return $next($request);
        }

        # Priority :- 1 Does response expect Json ?
        if ($request->expectsJson()) {
            return $next($request);
        }

        # Priority :- 3 Current route is belong to exclude(s) ?
        if (! $this->isCurrentRouteAllowedToCompress($request)) {
            return $next($request);
        }

        // If priority and module status is enable then lest start processing the request.

        $iniData  = [];
        $iniData['pcre.recursion_limit'] = ini_get('pcre.recursion_limit');
        $iniData['zlib.output_compression'] = ini_get('zlib.output_compression');
        $iniData['zlib.output_compression_level'] = ini_get('zlib.output_compression_level');

        ini_set('pcre.recursion_limit', '16777');
        // Some browser cant get content type.
        ini_set('zlib.output_compression', '4096');
        // Let server decide.
        ini_set('zlib.output_compression_level', '-1');

        $config = $this->laraOutPress->getConfig();
        $isDebug = $config['debug'];
        $targetEnvironment = explode(',', $config['target_environment']);
        $appEnvironment = getenv('APP_ENV');

        $response = $next($request);

        $buffer = $response->getContent();

        if ($isDebug) {
            $this->debug = 1;
            $this->bufferOldSize = strlen($buffer);
        }

        if (! in_array($appEnvironment, $targetEnvironment)) {
            return $next($request);
        }


        $htmlCom = new HtmlCompressor();
        $buffer = $htmlCom->init($buffer);

        $this->bufferNewSize = strlen($buffer);

        if ($isDebug) {
            $buffer .= debugMessage($this->bufferOldSize, $this->bufferNewSize);
        }
        $response->setContent($buffer);

        ini_set('pcre.recursion_limit', $iniData['pcre.recursion_limit']);
        ini_set('zlib.output_compression', $iniData['zlib.output_compression']);
        ini_set('zlib.output_compression_level', $iniData['zlib.output_compression_level']);

        return $response;
    }



    /**
     * Is current route allow to compress based on library configuration
     *
     * @return bool
     */
    public function isCurrentRouteAllowedToCompress(\Illuminate\Http\Request $request)
    {
        $config = $this->laraOutPress->getConfig();

        if (! is_array($config['exclude_routes'])) {
            // If configuration has no route(s) data or is empty then we will not compress any data
            return false;
        }

        if ($request->is($config['exclude_routes'])) {
            return false;
        }

        foreach ($config['exclude_routes'] as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->fullUrlIs($path) || $request->is($path)) {
                return true;
            }
        }

        return true;
    }
}
