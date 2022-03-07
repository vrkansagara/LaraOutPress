<?php

declare(strict_types=1);

namespace Vrkansagara\LaraOutPress\Middleware;

use Closure;
use Illuminate\Support\Facades\Request;
use Vrkansagara\LaraOutPress\LaraOutPress;

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
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->isCurrentRouteAllowedToCompress($request)) {
            return $next($request);
        }
        if (! $this->laraOutPress->isEnabled()) {
            return $next($request);
        }
        
        if ($request->expectsJson()) {
            return $next($request);
        }

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
        $whiteSpaceRules = [
            '/(\s)+/s' => '\\1',// shorten multiple whitespace sequences
            "#>\s+<#" => ">\n<",  // Strip excess whitespace using new line
            "#\n\s+<#" => "\n<",// strip excess whitespace using new line
            '/\>[^\S ]+/s' => '>',
            // Strip all whitespaces after tags, except space
            '/[^\S ]+\</s' => '<',// strip whitespaces before tags, except space
            /**
             * '/\s+     # Match one or more whitespace characters
             * (?!       # but only if it is impossible to match...
             * [^<>]*   # any characters except angle brackets
             * >        # followed by a closing bracket.
             * )         # End of lookahead
             * /x',
             */

            //Remove all whitespaces except content between html tags.
            //MOST DANGEROUS
            //            '/\s+(?![^<>]*>)/x' => '',
        ];
        $commentRules = [
            "/<!--.*?-->/ms" => '',// Remove all html comment.,
        ];
        $replaceWords = [
            //OldWord will be replaced by the NewWord
            // OldWord <-> NewWord DO NOT REMOVE THIS LINE. {REFERENCE LINE}
            //'/\bOldWord\b/i' =>'NewWord'
        ];
        $allRules = array_merge(
            $replaceWords,
            $commentRules,
            $whiteSpaceRules
        );
        $buffer = $this->compressJscript($buffer);
        $buffer = preg_replace(
            array_keys($allRules),
            array_values($allRules),
            $buffer
        );
        $this->bufferNewSize = strlen($buffer);

        if ($isDebug) {
            $old = $this->formatSizeUnits($this->bufferOldSize);
            $new = $this->formatSizeUnits($this->bufferNewSize);
            $percent = round(
                ($this->bufferNewSize / $this->bufferOldSize) * 100,
                2
            );
            $buffer
                .= <<< EOF
<span>
Before : $old<br>
After  : $new <br>
Reduce : $percent%<br>
</span>
EOF;
        }
        $response->setContent($buffer);

        ini_set('pcre.recursion_limit', '16777');

        ini_set(
            'zlib.output_compression',
            '4096'
        ); // Some browser cant get content type.
        ini_set('zlib.output_compression_level', '-1'); // Let server decide.

        return $response;
    }

    /**
     * This method will no longer support.
     *
     * @note Code arked as @deprecated but for reference only.
     * @param $buffer
     * @return null|string|string[] Compressed output
     * @deprecated
     *
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
        // We are going to check if processing has working
        if ($new_buffer === null) {
            $new_buffer = $buffer;
        }

        return $new_buffer;
    }

    public function formatSizeUnits($size)
    {
        $base = log($size) / log(1024);
        $suffix = ['', 'KB', 'MB', 'GB', 'TB'];
        $f_base = floor($base);

        return round(pow(1024, $base - floor($base)), 2) . $suffix[$f_base];
    }

    public function compressJscript($buffer)
    {
        // JavaScript compressor by John Elliot <jj5@jj5.net>
        $replace = [
            '#\'([^\n\']*?)/\*([^\n\']*)\'#' => "'\1/'+\'\'+'*\2'",
            // remove comments from ' strings
            '#\"([^\n\"]*?)/\*([^\n\"]*)\"#' => '"\1/"+\'\'+"*\2"',
            // remove comments from " strings
            '#/\*.*?\*/#s' => "",// strip C style comments
            '#[\r\n]+#' => "\n",
            // remove blank lines and \r's
            '#\n([ \t]*//.*?\n)*#s' => "\n",
            // strip line comments (whole line only)
            '#([^\\])//([^\'"\n]*)\n#s' => "\\1\n",
            // strip line comments
            // (that aren't possibly in strings or regex's)
            '#\n\s+#' => "\n",// strip excess whitespace
            '#\s+\n#' => "\n",// strip excess whitespace
            '#(//[^\n]*\n)#s' => "\\1\n",
            // extra line feed after any comments left
            // (important given later replacements)
            '#/([\'"])\+\'\'\+([\'"])\*#' => "/*"
            // restore comments in strings
        ];
        $script = preg_replace(array_keys($replace), $replace, $buffer);
        $replace = [
            "&&\n" => "&&",
            "||\n" => "||",
            "(\n" => "(",
            ")\n" => ")",
            "[\n" => "[",
            "]\n" => "]",
            "+\n" => "+",
            ",\n" => ",",
            "?\n" => "?",
            ":\n" => ":",
            ";\n" => ";",
            "{\n" => "{",
            //  "}\n"  => "}", (because I forget to put semicolons after function assignments)
            "\n]" => "]",
            "\n)" => ")",
            "\n}" => "}",
            "\n\n" => "\n",
        ];
        $script = str_replace(array_keys($replace), $replace, $script);

        return trim($script);
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
