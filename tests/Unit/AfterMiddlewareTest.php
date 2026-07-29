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

    public function testScriptContentWithLiteralCommentCloserDoesNotCorruptSurroundingHtml()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');

        // Guards against a real (if narrow) corruption risk: a <script> block
        // whose JSON payload legitimately contains a literal "-->" (e.g. a
        // debug toolbar dumping collected SQL, request data, or a stack
        // trace). The non-greedy HTML comment regex must not treat that
        // embedded "-->" as an earlier comment's real closing marker, which
        // would delete everything in between, including the script tag
        // itself.
        $original = '<html><body>'
            . '<p>Page content</p>'
            . '<!-- a real comment -->'
            . '<div id="widget"><script>var x = {"note": "weird --> data"};</script></div>'
            . '</body></html>';
        $response = new Response($original);

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertStringContainsString('Page content', $result->getContent());
        $this->assertStringContainsString('id="widget"', $result->getContent());
        $this->assertStringContainsString('<script>', $result->getContent());
        $this->assertStringNotContainsString('a real comment', $result->getContent());
        putenv('APP_ENV');
    }

    public function testStaleContentLengthHeaderIsRemovedAfterCompression()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/home', 'GET');

        // Reproduces GitHub issue #37: a debug toolbar (e.g. barryvdh/laravel-debugbar)
        // sets Content-Length based on the response it rendered, before this
        // middleware shrinks the body further via whitespace/comment
        // stripping. setContent() does not touch Content-Length, so a stale,
        // larger value left in place tells the browser/webserver the body is
        // longer than it actually is — which can truncate the response right
        // around trailing content like a toolbar injected before </body>.
        $original = "<html>\n  <body>\n    <p>content</p>\n  </body>\n</html>";
        $response = new Response($original);
        $response->headers->set('Content-Length', (string) strlen($original));

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertLessThan(strlen($original), strlen($result->getContent()));
        $this->assertFalse($result->headers->has('Content-Length'));
    }

    public function testNonHtmlResponseIsPassedThroughUntouched()
    {
        putenv('APP_ENV=testing');

        $laraOutPress = $this->makeLaraOutPress(true, $this->baseConfig([
            'target_environment' => 'testing',
        ]));
        $middleware = new AfterMiddleware($laraOutPress);

        $request = Request::create('/invoice.pdf', 'GET');

        // Reproduces GitHub issue #35: barryvdh/laravel-dompdf's stream()
        // returns a plain Response (not JSON, not a BinaryFileResponse) whose
        // body is raw PDF binary with Content-Type: application/pdf. Running
        // HTML whitespace/comment regexes over PDF bytes corrupts the
        // byte-offset-dependent xref table, so the file renders blank.
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
            . "xref\n0 4\n0000000000 65535 f \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n178\n%%EOF";
        $response = new Response($pdfBytes, 200, ['Content-Type' => 'application/pdf']);

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($pdfBytes, $result->getContent());
    }
}
