<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPressTest\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use Vrkansagara\LaraOutPress\LaraOutPress;
use Vrkansagara\LaraOutPress\Middleware\AfterMiddleware;

class AfterMiddlewareTest extends TestCase
{
    private function makeLaraOutPress(bool $enabled, array $config): LaraOutPress
    {
        $laraOutPress = $this->createMock(LaraOutPress::class);
        $laraOutPress->method('isEnabled')->willReturn($enabled);
        $laraOutPress->method('getConfig')->willReturn($config);

        return $laraOutPress;
    }

    private function baseConfig(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'debug' => false,
            'target_environment' => 'testing',
            'exclude_routes' => ['api/*'],
        ], $overrides);
    }

    public function testNextIsCalledExactlyOnceWhenEnvironmentDoesNotMatch()
    {
        putenv('APP_ENV=production');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $response = new Response('<html>  <body>hi</body>  </html>');

        $calls = 0;
        $next = function ($req) use (&$calls, $response) {
            $calls++;
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame(1, $calls, '$next() must be invoked exactly once per request');
        $this->assertSame($response, $result);
        putenv('APP_ENV');
    }

    public function testResponseIsCompressedWhenEnvironmentMatches()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $original = "<html>\n  <body>\n    hi   there\n  </body>\n</html>";
        $response = new Response($original);

        $calls = 0;
        $next = function ($req) use (&$calls, $response) {
            $calls++;
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame(1, $calls);
        $this->assertNotSame($original, $result->getContent());
        $this->assertLessThan(strlen($original), strlen($result->getContent()));
        putenv('APP_ENV');
    }

    public function testUnresolvedEnvPlaceholderFallsBackToCurrentEnvironment()
    {
        putenv('APP_ENV=local');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => '${APP_ENV}',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $original = "<html>\n  <body>\n    hi   there\n  </body>\n</html>";
        $response = new Response($original);

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertNotSame($original, $result->getContent());
        $this->assertLessThan(strlen($original), strlen($result->getContent()));
        putenv('APP_ENV');
    }

    public function testEmptyTargetEnvironmentFallsBackToCurrentEnvironment()
    {
        putenv('APP_ENV=local');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => '',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $original = "<html>\n  <body>\n    hi   there\n  </body>\n</html>";
        $response = new Response($original);

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertNotSame($original, $result->getContent());
        $this->assertLessThan(strlen($original), strlen($result->getContent()));
        putenv('APP_ENV');
    }

    public function testDisabledModuleSkipsProcessing()
    {
        $laraOutPress = $this->makeLaraOutPress(false, $this->baseConfig());
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $response = new Response('<html></html>');

        $calls = 0;
        $next = function ($req) use (&$calls, $response) {
            $calls++;
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame(1, $calls);
        $this->assertSame($response, $result);
    }

    public function testJsonRequestsAreSkipped()
    {
        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig());
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $request->headers->set('Accept', 'application/json');
        $response = new Response('{"foo":"bar"}');

        $calls = 0;
        $next = function ($req) use (&$calls, $response) {
            $calls++;
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame(1, $calls);
        $this->assertSame($response, $result);
    }

    public function testExcludedRouteIsSkipped()
    {
        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'exclude_routes' => ['api/*'],
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/api/users', 'GET');
        $response = new Response('{"foo":"bar"}');

        $calls = 0;
        $next = function ($req) use (&$calls, $response) {
            $calls++;
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame(1, $calls);
        $this->assertSame($response, $result);
    }

    public function testDebugModeAppendsStatsWithoutDivisionByZeroOnEmptyBody()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'debug' => true,
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $response = new Response('');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertStringContainsString('Reduce : 0%', $result->getContent());
        putenv('APP_ENV');
    }

    public function testDebugModeAppendsBeforeAfterStats()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'debug' => true,
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');
        $response = new Response("<html>\n  <body>    hi   </body>\n</html>");

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertStringContainsString('Before :', $result->getContent());
        $this->assertStringContainsString('After  :', $result->getContent());
        putenv('APP_ENV');
    }

    public function testIsCurrentRouteAllowedToCompressReturnsFalseForExcludedRoute()
    {
        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'exclude_routes' => ['api/*'],
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/api/users', 'GET');

        $this->assertFalse($middleware->isCurrentRouteAllowedToCompress($request));
    }

    public function testIsCurrentRouteAllowedToCompressReturnsTrueForNonExcludedRoute()
    {
        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'exclude_routes' => ['api/*'],
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');

        $this->assertTrue($middleware->isCurrentRouteAllowedToCompress($request));
    }

    public function testIsCurrentRouteAllowedToCompressReturnsFalseWhenExcludeRoutesNotArray()
    {
        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'exclude_routes' => null,
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');

        $this->assertFalse($middleware->isCurrentRouteAllowedToCompress($request));
    }

    public function testCompressJscriptStripsComments()
    {
        $middleware = new AfterMiddleware($this->makeLaraOutPress(true, $this->baseConfig()));

        $script = "var a = 1; /* comment */\nvar b = 2; // line comment\n";
        $result = $middleware->compressJscript($script);

        $this->assertStringNotContainsString('/* comment */', $result);
    }

    public function testBinaryFileResponseIsPassedThroughUntouched()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/export.xlsx', 'GET');

        $tmpFile = tempnam(sys_get_temp_dir(), 'laraoutpress-test');
        file_put_contents($tmpFile, 'binary excel payload');
        $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($tmpFile);

        $next = function ($req) use ($response) {
            return $response;
        };

        // BinaryFileResponse::getContent() returns false and setContent()
        // throws; the middleware must detect this and return the response
        // untouched instead of crashing (see GitHub issue #40).
        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);

        unlink($tmpFile);
        putenv('APP_ENV');
    }
}
