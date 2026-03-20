<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Math_Trig;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Statistical;
use Php_Office\Php_Spreadsheet\Cell\Cell;
class Subtotal
{
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    protected static function filter_hidden_args(Cell $cell_reference, array $args): array
    {
        return array_filter($args, function ($index) use ($cell_reference): bool {
            $explode_array = explode('.', $index);
            $row = $explode_array[1] ?? '';
            if (!is_numeric($row)) {
                return true;
            }
            return $cell_reference->get_worksheet()->get_row_dimension((int) $row)->get_visible();
        }, ARRAY_FILTER_USE_KEY);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    protected static function filter_filtered_args(Cell $cell_reference, array $args): array
    {
        return array_filter($args, function ($index) use ($cell_reference): bool {
            $explode_array = explode('.', $index);
            $row = $explode_array[1] ?? '';
            return is_numeric($row) ? $cell_reference->get_worksheet()->get_row_dimension((int) $row)->get_visible_after_filter() : true;
        }, ARRAY_FILTER_USE_KEY);
    }
    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    protected static function filter_formula_args(Cell $cell_reference, array $args): array
    {
        return array_filter($args, function ($index) use ($cell_reference): bool {
            $explode_array = explode('.', $index);
            $row = $explode_array[1] ?? '';
            $column = $explode_array[2] ?? '';
            $ret_val = true;
            if ($cell_reference->get_worksheet()->cell_exists($column . $row)) {
                //take this cell out if it contains the SUBTOTAL or AGGREGATE functions in a formula
                $is_formula = $cell_reference->get_worksheet()->get_cell($column . $row)->is_formula();
                $cell_formula = !preg_match('/^=.*\b(SUBTOTAL|AGGREGATE)\s*\(/i', $cell_reference->get_worksheet()->get_cell($column . $row)->get_value_string());
                $ret_val = !$is_formula || $cell_formula;
            }
            return $ret_val;
        }, ARRAY_FILTER_USE_KEY);
    }
    /**
     * @var array<int, callable>
     */
    private const CALL_FUNCTIONS = [
        1 => [Statistical\Averages::class, 'average'],
        // 1 and 101
        [Statistical\Counts::class, 'COUNT'],
        // 2 and 102
        [Statistical\Counts::class, 'COUNTA'],
        // 3 and 103
        [Statistical\Maximum::class, 'max'],
        // 4 and 104
        [Statistical\Minimum::class, 'min'],
        // 5 and 105
        [Operations::class, 'product'],
        // 6 and 106
        [Statistical\Standard_Deviations::class, 'STDEV'],
        // 7 and 107
        [Statistical\Standard_Deviations::class, 'STDEVP'],
        // 8 and 108
        [Sum::class, 'sumIgnoringStrings'],
        // 9 and 109
        [Statistical\Variances::class, 'VAR'],
        // 10 and 110
        [Statistical\Variances::class, 'VARP'],
    ];
    /**
     * SUBTOTAL.
     *
     * Returns a subtotal in a list or database.
     *
     * @param mixed $functionType
     *            A number 1 to 11 that specifies which function to
     *                    use in calculating subtotals within a range
     *                    list
     *            Numbers 101 to 111 shadow the functions of 1 to 11
     *                    but ignore any values in the range that are
     *                    in hidden rows
     * @param mixed[] $args A mixed data series of values
     */
    public static function evaluate(mixed $function_type, ...$args): float|int|string
    {
        /** @var Cell */
        $cell_reference = array_pop($args);
        $b_args = Functions::flatten_array_indexed($args);
        $a_args = [];
        // int keys must come before string keys for PHP 8.0+
        // Otherwise, PHP thinks positional args follow keyword
        //    in the subsequent call to call_user_func_array.
        // Fortunately, order of args is unimportant to Subtotal.
        foreach ($b_args as $key => $value) {
            if (is_int($key)) {
                $a_args[$key] = $value;
            }
        }
        foreach ($b_args as $key => $value) {
            if (!is_int($key)) {
                $a_args[$key] = $value;
            }
        }
        try {
            $subtotal = (int) Helpers::validate_numeric_null_bool($function_type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // Calculate
        if ($subtotal > 100) {
            $a_args = self::filter_hidden_args($cell_reference, $a_args);
            $subtotal -= 100;
        } else {
            $a_args = self::filter_filtered_args($cell_reference, $a_args);
        }
        $a_args = self::filter_formula_args($cell_reference, $a_args);
        if (array_key_exists($subtotal, self::CALL_FUNCTIONS)) {
            $call = self::CALL_FUNCTIONS[$subtotal];
            return call_user_func_array($call, $a_args);
            //* @phpstan-ignore-line
        }
        return Excel_Error::VALUE();
    }
}