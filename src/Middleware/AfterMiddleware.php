<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Vrkansagara\LaraOutPress\LaraOutPress;

/**
 * @copyright  Copyright (c) 2015-2024 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
class AfterMiddleware
{
    /**
     * Whitespace-collapsing rules applied to the final HTML buffer.
     *
     * Declared once as a class constant instead of being rebuilt on every
     * request, since the rule set never changes at runtime.
     *
     * @var array<string, string>
     */
    private const WHITESPACE_RULES = [
        '/(\s)+/s' => '\\1', // shorten multiple whitespace sequences
        "#>\s+<#" => ">\n<", // strip excess whitespace using new line
        "#\n\s+<#" => "\n<", // strip excess whitespace using new line
        '/\>[^\S ]+/s' => '>', // strip all whitespace after tags, except space
        '/[^\S ]+\</s' => '<', // strip whitespace before tags, except space
    ];

    /**
     * Rule that strips HTML comments from the final buffer.
     *
     * @var array<string, string>
     */
    private const COMMENT_RULES = [
        "/<!--.*?-->/ms" => '',
    ];

    /**
     * JavaScript minifier rules, first pass (regex-based).
     *
     * Credit: JavaScript compressor by John Elliot <jj5@jj5.net>.
     *
     * @var array<string, string>
     */
    private const JS_REGEX_RULES = [
        '#\'([^\n\']*?)/\*([^\n\']*)\'#' => "'\1/'+''+'*\2'", // remove comments from ' strings
        '#\"([^\n\"]*?)/\*([^\n\"]*)\"#' => '"\1/"+\'\'+"*\2"', // remove comments from " strings
        '#/\*.*?\*/#s' => '', // strip C style comments
        '#[\r\n]+#' => "\n", // remove blank lines and \r's
        '#\n([ \t]*//.*?\n)*#s' => "\n", // strip line comments (whole line only)
        '#([^\\])//([^\'"\n]*)\n#s' => "\\1\n", // strip line comments not inside strings/regexes
        '#\n\s+#' => "\n", // strip excess whitespace
        '#\s+\n#' => "\n", // strip excess whitespace
        '#(//[^\n]*\n)#s' => "\\1\n", // extra line feed after any comments left
        '#/([\'"])\+\'\'\+([\'"])\*#' => '/*', // restore comments in strings
    ];

    /**
     * JavaScript minifier rules, second pass (literal string replacement).
     *
     * @var array<string, string>
     */
    private const JS_LITERAL_RULES = [
        "&&\n" => '&&',
        "||\n" => '||',
        "(\n" => '(',
        ")\n" => ')',
        "[\n" => '[',
        "]\n" => ']',
        "+\n" => '+',
        ",\n" => ',',
        "?\n" => '?',
        ":\n" => ':',
        ";\n" => ';',
        "{\n" => '{',
        "\n]" => ']',
        "\n)" => ')',
        "\n}" => '}',
        "\n\n" => "\n",
    ];

    /** @var int Size in bytes of the response body before compression. */
    public $bufferOldSize;

    /** @var int Size in bytes of the response body after compression. */
    public $bufferNewSize;

    /** @var int 1 when the request ran with debug reporting enabled. */
    public $debug;

    /** @var LaraOutPress */
    protected $laraOutPress;

    /**
     * @param LaraOutPress $laraOutPress
     */
    public function __construct(LaraOutPress $laraOutPress)
    {
        $this->laraOutPress = $laraOutPress;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->shouldCompress($request)) {
            return $next($request);
        }

        $iniOverrides = $this->applyPerformanceIniOverrides();

        $config = $this->laraOutPress->getConfig();
        $isDebug = (bool) $config['debug'];
        $appEnvironment = (string) getenv('APP_ENV');
        $targetEnvironment = $this->resolveTargetEnvironment($config['target_environment'], $appEnvironment);

        /** @var Response $response */
        $response = $next($request);

        $rawContent = $response->getContent();

        // getContent() returns false for responses that don't buffer their
        // body in memory (e.g. BinaryFileResponse used by file/Excel
        // downloads). Those responses also throw on setContent(), so they
        // must never enter the compression pipeline at all.
        if ($rawContent === false) {
            $this->restoreIniOverrides($iniOverrides);

            return $response;
        }

        $buffer = $rawContent;
        $bufferOldSize = strlen($buffer);

        if ($isDebug) {
            $this->debug = 1;
            $this->bufferOldSize = $bufferOldSize;
        }

        if (! in_array($appEnvironment, $targetEnvironment, true)) {
            $this->restoreIniOverrides($iniOverrides);

            return $response;
        }

        $buffer = $this->compressBuffer($buffer);
        $bufferNewSize = strlen($buffer);
        $this->bufferNewSize = $bufferNewSize;

        if ($isDebug) {
            $buffer .= $this->renderDebugBanner($bufferOldSize, $bufferNewSize);
        }

        $response->setContent($buffer);

        // setContent() does not touch the Content-Length header. If anything
        // upstream (the app, a debug toolbar, a proxy) already set it based
        // on the pre-compression body, leaving it stale here can make the
        // browser/webserver truncate the response at the old byte count —
        // right around where trailing widgets (e.g. a debug toolbar injected
        // just before </body>) live. Let the server recompute it instead.
        $response->headers->remove('Content-Length');

        $this->restoreIniOverrides($iniOverrides);

        return $response;
    }

    /**
     * Whether the current request is eligible for compression at all,
     * independent of which target environment is configured.
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldCompress($request): bool
    {
        if (! $this->laraOutPress->isEnabled()) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        return $this->isCurrentRouteAllowedToCompress($request);
    }

    /**
     * Snapshot the ini settings this middleware is about to change, then
     * apply the overrides that make compression behave predictably across
     * browsers and servers.
     *
     * @return array<string, string|false> Snapshot to pass to {@see restoreIniOverrides()}.
     */
    protected function applyPerformanceIniOverrides(): array
    {
        $snapshot = [
            'pcre.recursion_limit' => ini_get('pcre.recursion_limit'),
            'zlib.output_compression' => ini_get('zlib.output_compression'),
            'zlib.output_compression_level' => ini_get('zlib.output_compression_level'),
        ];

        ini_set('pcre.recursion_limit', '16777');
        // Some browsers can't get the content type when zlib compression is left to its default.
        ini_set('zlib.output_compression', '4096');
        // Let the server decide the compression level.
        ini_set('zlib.output_compression_level', '-1');

        return $snapshot;
    }

    /**
     * Restore ini settings captured by {@see applyPerformanceIniOverrides()}.
     *
     * @param array<string, string|false> $snapshot
     * @return void
     */
    protected function restoreIniOverrides(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            ini_set($key, $value);
        }
    }

    /**
     * Pattern matching a full <script>...</script> block, capturing its
     * inner content for isolated JS minification.
     *
     * @var string
     */
    private const SCRIPT_BLOCK_PATTERN = '#(<script\b[^>]*>)(.*?)(</script>)#is';

    /**
     * Run the HTML/JS compression pipeline over a response buffer.
     *
     * <script> contents are minified in isolation and never exposed to the
     * HTML comment/whitespace rules below: a literal "-->" or "//" inside
     * script data (e.g. a debug toolbar dumping a query string or stack
     * trace) would otherwise be mistaken for the end of an HTML comment,
     * silently deleting real markup that follows it.
     *
     * @param string $buffer
     * @return string
     */
    protected function compressBuffer(string $buffer): string
    {
        $buffer = (string) preg_replace_callback(
            self::SCRIPT_BLOCK_PATTERN,
            function (array $matches): string {
                return $matches[1] . $this->compressJscript($matches[2]) . $matches[3];
            },
            $buffer
        );

        $allRules = self::COMMENT_RULES + self::WHITESPACE_RULES;

        $compressed = preg_replace(array_keys($allRules), array_values($allRules), $buffer);

        return $compressed ?? $buffer;
    }

    /**
     * Build the fixed, bottom-of-page debug banner showing the before/after
     * size and the percentage of payload saved.
     *
     * @param int $oldSize
     * @param int $newSize
     * @return string
     */
    protected function renderDebugBanner(int $oldSize, int $newSize): string
    {
        $old = LaraOutPress::formatSizeUnits($oldSize);
        $new = LaraOutPress::formatSizeUnits($newSize);
        $percent = $oldSize > 0 ? 100 - round(($newSize * 100) / $oldSize, 2) : 0;

        return <<<HTML
<p style="
  font-size: 200%;
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  z-index: 100;
  background: white;
  color: green;
  font-weight: bold;
  text-align: center;
"><span style="color: red;">&nbsp;Before : {$old}</span>
<span style="color: green;">&nbsp;After  : {$new} </span>
<span style="color: blue;">&nbsp;Reduce : {$percent}%</span> reduce network load on each request for this route.</p>
HTML;
    }

    /**
     * This method will no longer support.
     *
     * @note Code marked as @deprecated but kept for reference only.
     * @param string $buffer
     * @return string Compressed output
     * @deprecated
     */
    public static function compress($buffer)
    {
        /**
         * To remove useless whitespace from generated HTML, except for Javascript.
         * [Regex Source]
         * https://github.com/bcit-ci/codeigniter/wiki/compress-html-output
         * http://stackoverflow.com/questions/5312349/minifying-final-html-output-using-regular-expressions-with-codeigniter
         * %# Collapse ws everywhere but in blacklisted elements.
         * (?>             # Match all whitespaces other than single space.
         * [^\S ]\s*     # Either one [\t\r\n\f\v] and zero or more ws,
         * | \s{2,}        # or two or more consecutive-any-whitespace.
         * ) # Note: The remaining regex consumes no text at all...
         * (?=             # Ensure we are not in a blacklist tag.
         * (?:           # Begin (unnecessary) group.
         * (?:         # Zero or more of...
         * [^<]++    # Either one or more non-"<"
         * | <         # or a < starting a non-blacklist tag.
         * (?!/?(?:textarea|pre)\b)
         * )*+         # (This could be "unroll-the-loop"ified.)
         * )             # End (unnecessary) group.
         * (?:           # Begin alternation group.
         * <           # Either a blacklist start tag.
         * (?>textarea|pre)\b
         * | \z          # or end of file.
         * )             # End alternation group.
         * )  # If we made it here, we are not in a blacklist tag.
         * %ix
         */
        $regexRemoveWhiteSpace
            = '%(?>[^\S ]\s*| \s{2,})(?=(?:(?:[^<]++| <(?!/?(?:textarea|pre)\b))*+)(?:<(?>textarea|pre)\b|\z))%ix';
        $new_buffer = preg_replace($regexRemoveWhiteSpace, '', $buffer);

        return $new_buffer ?? $buffer;
    }

    /**
     * Minify inline JavaScript: strip comments and collapse whitespace left
     * behind by the strip, without touching string/regex literals.
     *
     * @param string $buffer
     * @return string
     */
    public function compressJscript($buffer)
    {
        $script = preg_replace(array_keys(self::JS_REGEX_RULES), self::JS_REGEX_RULES, $buffer);
        $script = str_replace(array_keys(self::JS_LITERAL_RULES), self::JS_LITERAL_RULES, $script ?? $buffer);

        return trim($script);
    }

    /**
     * Is current route allow to compress based on library configuration
     *
     * @param \Illuminate\Http\Request $request
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

        return true;
    }

    /**
     * Resolve the configured target_environment list, falling back to the
     * current app environment when the config is empty or contains an
     * unresolved placeholder (e.g. a literal "${APP_ENV}" from a .env file,
     * which dotenv does not expand).
     *
     * @param string|null $configuredTargetEnvironment
     * @param string $appEnvironment
     * @return array<int, string>
     */
    protected function resolveTargetEnvironment($configuredTargetEnvironment, string $appEnvironment): array
    {
        $configuredTargetEnvironment = trim((string) $configuredTargetEnvironment);

        if ($configuredTargetEnvironment === '' || str_contains($configuredTargetEnvironment, '${')) {
            return [$appEnvironment];
        }

        return explode(',', $configuredTargetEnvironment);
    }
}
