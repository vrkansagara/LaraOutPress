<?php
/**
 * @copyright  Copyright (c) 2015-2016 Vallabh Kansagara <vrkansagara@gmail.com>
 * @license    https://opensource.org/licenses/BSD-3-Clause New BSD License
 */
namespace Vrkansagara\Http\Middleware;

use Closure;

class AfterMiddleware {

  public $bufferOldSize;
  public $bufferNewSize;
  public $debug = 0;

  /**
   * Handle an incoming request.
   *
   * @param \Illuminate\Http\Request $request
   * @param \Closure                 $next
   *
   * @return mixed
   */
  public function handle($request, Closure $next) {
    $response            = $next($request);
    $buffer              = $response->getContent();
    $this->bufferOldSize = strlen($buffer);
    $whiteSpaceRules     = array(
//            '/\>[^\S ]+/s' => '>',// Strip all whitespaces after tags, except space
//            '/[^\S ]+\</s' => '<',// strip whitespaces before tags, except space
            '/(\s)+/s'          => '\\1',// shorten multiple whitespace sequences
      /**
       * '/\s+     # Match one or more whitespace characters
       * (?!       # but only if it is impossible to match...
       * [^<>]*   # any characters except angle brackets
       * >        # followed by a closing bracket.
       * )         # End of lookahead
       * /x',
       */
            '/\s+(?![^<>]*>)/x' => '', //Remove all whitespaces except content between html tags.
    );
    $commentRules        = array(
            "/<!--.*?-->/ms" => '',// Remove all html comment.,
    );
    $replaceWords        = array(
      //OldWord will be replaced by the NewWord
//              '/\bOldWord\b/i' =>'NewWord' // OldWord <-> NewWord DO NOT REMOVE THIS LINE. {REFERENCE LINE}
    );
    $mergeCss            = array();
    $allRules            = array_merge(
            $mergeCss,
            $replaceWords,
            $commentRules,
            $whiteSpaceRules
    );
    $buffer              = preg_replace(array_keys($allRules), array_values($allRules), $buffer);
    $this->bufferNewSize = strlen($buffer);
    if ($this->debug) {
      $old     = $this->formatSizeUnits($this->bufferOldSize);
      $new     = $this->formatSizeUnits($this->bufferNewSize);
      $percent = round(($this->bufferNewSize / $this->bufferOldSize) * 100, 2);
      $buffer  .= <<< EOF
<span>
Before : $old<br>
After  : $new <br>
Reduce : $percent%<br>
</span>
EOF;
    }
    $response->setContent($buffer);
    ini_set('pcre.recursion_limit', '16777');
    ini_set('zlib.output_compression', 'On'); // If you like to enable GZip, too!

    return $response;
  }

  /**
   * This method will no longer support.
   *
   * @note Code will be healed even after marked as @deprecated for further reference.
   * @deprecated
   *
   * @param $buffer
   *
   * @return null|string|string[] Compressed output
   */
  public static function compress($buffer) {
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
    $regexRemoveWhiteSpace = '%(?>[^\S ]\s*| \s{2,})(?=(?:(?:[^<]++| <(?!/?(?:textarea|pre)\b))*+)(?:<(?>textarea|pre)\b|\z))%ix';
    $new_buffer            = preg_replace($regexRemoveWhiteSpace, '', $buffer);
    // We are going to check if processing has working
    if ($new_buffer === null) {
      $new_buffer = $buffer;
    }

    return $new_buffer;
  }

  function formatSizeUnits($size){
    $base = log($size) / log(1024);
    $suffix = array('', 'KB', 'MB', 'GB', 'TB');
    $f_base = floor($base);
    return round(pow(1024, $base - floor($base)), 2) . $suffix[$f_base];
  }
}