<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
class Function_Prefix
{
    public const XLFNREGEXP = '/(?:_xlfn\.)?((?:_xlws\.)?\b(' . 'beta[.]dist' . '|beta[.]inv' . '|binom[.]dist' . '|binom[.]inv' . '|ceiling[.]precise' . '|chisq[.]dist' . '|chisq[.]dist[.]rt' . '|chisq[.]inv' . '|chisq[.]inv[.]rt' . '|chisq[.]test' . '|confidence[.]norm' . '|confidence[.]t' . '|covariance[.]p' . '|covariance[.]s' . '|erf[.]precise' . '|erfc[.]precise' . '|expon[.]dist' . '|f[.]dist' . '|f[.]dist[.]rt' . '|f[.]inv' . '|f[.]inv[.]rt' . '|f[.]test' . '|floor[.]precise' . '|gamma[.]dist' . '|gamma[.]inv' . '|gammaln[.]precise' . '|lognorm[.]dist' . '|lognorm[.]inv' . '|mode[.]mult' . '|mode[.]sngl' . '|negbinom[.]dist' . '|networkdays[.]intl' . '|norm[.]dist' . '|norm[.]inv' . '|norm[.]s[.]dist' . '|norm[.]s[.]inv' . '|percentile[.]exc' . '|percentile[.]inc' . '|percentrank[.]exc' . '|percentrank[.]inc' . '|poisson[.]dist' . '|quartile[.]exc' . '|quartile[.]inc' . '|rank[.]avg' . '|rank[.]eq' . '|stdev[.]p' . '|stdev[.]s' . '|t[.]dist' . '|t[.]dist[.]2t' . '|t[.]dist[.]rt' . '|t[.]inv' . '|t[.]inv[.]2t' . '|t[.]test' . '|var[.]p' . '|var[.]s' . '|weibull[.]dist' . '|z[.]test' . '|base' . '|acot' . '|acoth' . '|arabic' . '|averageifs' . '|binom[.]dist[.]range' . '|bitand' . '|bitlshift' . '|bitor' . '|bitrshift' . '|bitxor' . '|ceiling[.]math' . '|combina' . '|cot' . '|coth' . '|csc' . '|csch' . '|days' . '|dbcs' . '|decimal' . '|encodeurl' . '|filterxml' . '|floor[.]math' . '|formulatext' . '|gamma' . '|gauss' . '|ifna' . '|imcosh' . '|imcot' . '|imcsc' . '|imcsch' . '|imsec' . '|imsech' . '|imsinh' . '|imtan' . '|isformula' . '|iso[.]ceiling' . '|isoweeknum' . '|munit' . '|numbervalue' . '|pduration' . '|permutationa' . '|phi' . '|rri' . '|sec' . '|sech' . '|sheet' . '|sheets' . '|skew[.]p' . '|unichar' . '|unicode' . '|webservice' . '|xor' . '|forecast[.]et2' . '|forecast[.]ets[.]confint' . '|forecast[.]ets[.]seasonality' . '|forecast[.]ets[.]stat' . '|forecast[.]linear' . '|switch' . '|concat' . '|ifs' . '|maxifs' . '|minifs' . '|textjoin' . '|anchorarray' . '|arraytotext' . '|bycol' . '|byrow' . '|call' . '|choosecols' . '|chooserows' . '|drop' . '|expand' . '|filter' . '|groupby' . '|hstack' . '|isomitted' . '|lambda' . '|let' . '|makearray' . '|map' . '|randarray' . '|reduce' . '|register[.]id' . '|scan' . '|sequence' . '|single' . '|sort' . '|sortby' . '|take' . '|textafter' . '|textbefore' . '|textjoin' . '|textsplit' . '|tocol' . '|torow' . '|unique' . '|valuetotext' . '|vstack' . '|wrapcols' . '|wraprows' . '|xlookup' . '|xmatch' . '))\s*\(/Umui';
    public const XLWSREGEXP = '/(?<!_xlws\.)(' . 'filter' . '|sort' . ')\s*\(/mui';
    /**
     * Prefix function name in string with _xlfn. where required.
     */
    protected static function add_xlfn_prefix(string $function_string): string
    {
        return Preg::replace(self::XLFNREGEXP, '_xlfn.$1(', $function_string);
    }
    /**
     * Prefix function name in string with _xlws. where required.
     */
    protected static function add_xlws_prefix(string $function_string): string
    {
        return Preg::replace(self::XLWSREGEXP, '_xlws.$1(', $function_string);
    }
    /**
     * Prefix function name in string with _xlfn. where required.
     */
    public static function add_function_prefix(string $function_string): string
    {
        $function_string = Preg::replace_callback(Calculation::CALCULATION_REGEXP_CELLREF_SPILL, fn(array $matches): string => 'ANCHORARRAY(' . substr((string) $matches[0], 0, -1) . ')', $function_string);
        return self::add_xlws_prefix(self::add_xlfn_prefix($function_string));
    }
    /**
     * Prefix function name in string with _xlfn. where required.
     * Leading character, expected to be equals sign, is stripped.
     */
    public static function add_function_prefix_strip_equals(string $function_string): string
    {
        $function_string = Preg::replace(['/\b(CEILING|FLOOR)[.]ODS\s*[(]/', '/\b(CEILING|FLOOR)[.]XCL\s*[(]/'], ['$1.MATH(', '$1('], $function_string);
        return self::add_function_prefix(substr($function_string, 1));
    }
}