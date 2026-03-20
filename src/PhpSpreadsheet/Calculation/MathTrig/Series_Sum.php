<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
class Series_Sum
{
    use Array_Enabled;
    /**
     * SERIESSUM.
     *
     * Returns the sum of a power series
     *
     * @param mixed $x Input value
     * @param mixed $n Initial power
     * @param mixed $m Step
     * @param mixed[] $args An array of coefficients for the Data Series
     *
     * @return array<mixed>|float|int|string The result, or a string containing an error
     */
    public static function evaluate(mixed $x, mixed $n, mixed $m, ...$args): array|string|float|int
    {
        if (is_array($x) || is_array($n) || is_array($m)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 3, $x, $n, $m, ...$args);
        }
        try {
            $x = Helpers::validate_numeric_null_substitution($x, 0);
            $n = Helpers::validate_numeric_null_substitution($n, 0);
            $m = Helpers::validate_numeric_null_substitution($m, 0);
            // Loop through arguments
            $a_args = Functions::flatten_array($args);
            $return_value = 0;
            $i = 0;
            foreach ($a_args as $argx) {
                if ($argx !== null) {
                    $arg = Helpers::validate_numeric_null_substitution($argx, 0);
                    $return_value += $arg * $x ** ($n + $m * $i);
                    ++$i;
                }
            }
        } catch (Exception $e) {
            return $e->get_message();
        }
        return $return_value;
    }
}