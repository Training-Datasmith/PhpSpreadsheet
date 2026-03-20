<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Functions
{
    public const PRECISION = 8.88E-16;
    /**
     * 2 / PI.
     */
    public const M_2DIVPI = 0.6366197723675814;
    public const COMPATIBILITY_EXCEL = 'Excel';
    public const COMPATIBILITY_GNUMERIC = 'Gnumeric';
    public const COMPATIBILITY_OPENOFFICE = 'OpenOfficeCalc';
    /** Use of RETURNDATE_PHP_NUMERIC is discouraged - not 32-bit Y2038-safe, no timezone. */
    public const RETURNDATE_PHP_NUMERIC = 'P';
    /** Use of RETURNDATE_UNIX_TIMESTAMP is discouraged - not 32-bit Y2038-safe, no timezone. */
    public const RETURNDATE_UNIX_TIMESTAMP = 'P';
    public const RETURNDATE_PHP_OBJECT = 'O';
    public const RETURNDATE_PHP_DATETIME_OBJECT = 'O';
    public const RETURNDATE_EXCEL = 'E';
    public const NOT_YET_IMPLEMENTED = '#Not Yet Implemented';
    /**
     * Compatibility mode to use for error checking and responses.
     */
    protected static string $compatibility_mode = self::COMPATIBILITY_EXCEL;
    /**
     * Data Type to use when returning date values.
     */
    protected static string $return_date_type = self::RETURNDATE_EXCEL;
    /**
     * Set the Compatibility Mode.
     *
     * @param string $compatibilityMode Compatibility Mode
     *                                  Permitted values are:
     *                                      Functions::COMPATIBILITY_EXCEL        'Excel'
     *                                      Functions::COMPATIBILITY_GNUMERIC     'Gnumeric'
     *                                      Functions::COMPATIBILITY_OPENOFFICE   'OpenOfficeCalc'
     *
     * @return bool (Success or Failure)
     */
    public static function set_compatibility_mode(string $compatibility_mode): bool
    {
        if ($compatibility_mode == self::COMPATIBILITY_EXCEL || $compatibility_mode == self::COMPATIBILITY_GNUMERIC || $compatibility_mode == self::COMPATIBILITY_OPENOFFICE) {
            self::$compatibility_mode = $compatibility_mode;
            return true;
        }
        return false;
    }
    /**
     * Return the current Compatibility Mode.
     *
     * @return string Compatibility Mode
     *                Possible Return values are:
     *                    Functions::COMPATIBILITY_EXCEL        'Excel'
     *                    Functions::COMPATIBILITY_GNUMERIC     'Gnumeric'
     *                    Functions::COMPATIBILITY_OPENOFFICE   'OpenOfficeCalc'
     */
    public static function get_compatibility_mode(): string
    {
        return self::$compatibility_mode;
    }
    /**
     * Set the Return Date Format used by functions that return a date/time (Excel, PHP Serialized Numeric or PHP DateTime Object).
     *
     * @param string $returnDateType Return Date Format
     *                               Permitted values are:
     *                                   Functions::RETURNDATE_UNIX_TIMESTAMP       'P'
     *                                   Functions::RETURNDATE_PHP_DATETIME_OBJECT  'O'
     *                                   Functions::RETURNDATE_EXCEL                'E'
     *
     * @return bool Success or failure
     */
    public static function set_return_date_type(string $return_date_type): bool
    {
        if ($return_date_type == self::RETURNDATE_UNIX_TIMESTAMP || $return_date_type == self::RETURNDATE_PHP_DATETIME_OBJECT || $return_date_type == self::RETURNDATE_EXCEL) {
            self::$return_date_type = $return_date_type;
            return true;
        }
        return false;
    }
    /**
     * Return the current Return Date Format for functions that return a date/time (Excel, PHP Serialized Numeric or PHP Object).
     *
     * @return string Return Date Format
     *                Possible Return values are:
     *                    Functions::RETURNDATE_UNIX_TIMESTAMP         'P'
     *                    Functions::RETURNDATE_PHP_DATETIME_OBJECT    'O'
     *                    Functions::RETURNDATE_EXCEL            '     'E'
     */
    public static function get_return_date_type(): string
    {
        return self::$return_date_type;
    }
    /**
     * DUMMY.
     *
     * @return string #Not Yet Implemented
     */
    public static function DUMMY(): string
    {
        return self::NOT_YET_IMPLEMENTED;
    }
    public static function is_matrix_value(mixed $idx): bool
    {
        $idx = String_Helper::convert_to_string($idx);
        return substr_count($idx, '.') <= 1 || preg_match('/\.[A-Z]/', $idx) > 0;
    }
    public static function is_value(mixed $idx): bool
    {
        $idx = String_Helper::convert_to_string($idx);
        return substr_count($idx, '.') === 0;
    }
    public static function is_cell_value(mixed $idx): bool
    {
        $idx = String_Helper::convert_to_string($idx);
        return substr_count($idx, '.') > 1;
    }
    public static function if_condition(mixed $condition): string
    {
        $condition = self::flatten_single_value($condition);
        if ($condition === '' || $condition === null) {
            return '=""';
        }
        if (!is_string($condition) || !in_array($condition[0], ['>', '<', '='], true)) {
            $condition = self::operand_special_handling($condition);
            if (is_bool($condition)) {
                return '=' . ($condition ? 'TRUE' : 'FALSE');
            }
            if (!is_numeric($condition)) {
                if ($condition !== '""') {
                    // Not an empty string
                    // Escape any quotes in the string value
                    $condition = (string) preg_replace('/"/ui', '""', $condition);
                }
                $condition = Calculation::wrap_result(strtoupper($condition));
            }
            return str_replace('""""', '""', '=' . String_Helper::convert_to_string($condition));
        }
        $operator = $operand = '';
        if (1 === preg_match('/(=|<[>=]?|>=?)(.*)/', $condition, $matches)) {
            [, $operator, $operand] = $matches;
        }
        $operand = (string) self::operand_special_handling($operand);
        if (is_numeric(trim($operand, '"'))) {
            $operand = trim($operand, '"');
        } elseif (!is_numeric($operand) && $operand !== 'FALSE' && $operand !== 'TRUE') {
            $operand = str_replace('"', '""', $operand);
            $operand = Calculation::wrap_result(strtoupper($operand));
            $operand = String_Helper::convert_to_string($operand);
        }
        return str_replace('""""', '""', $operator . $operand);
    }
    private static function operand_special_handling(mixed $operand): bool|float|int|string
    {
        if (is_numeric($operand) || is_bool($operand)) {
            return $operand;
        }
        $operand = String_Helper::convert_to_string($operand);
        if (strtoupper($operand) === Calculation::get_true() || strtoupper($operand) === Calculation::get_false()) {
            return strtoupper($operand);
        }
        // Check for percentage
        if (preg_match('/^\-?\d*\.?\d*\s?\%$/', $operand)) {
            return (float) rtrim($operand, '%') / 100;
        }
        // Check for dates
        if (($date_value_operand = Date::string_to_excel($operand)) !== false) {
            return $date_value_operand;
        }
        return $operand;
    }
    /**
     * Convert a multi-dimensional array to a simple 1-dimensional array.
     *
     * @param mixed $array Array to be flattened
     *
     * @return array<mixed> Flattened array
     */
    public static function flatten_array(mixed $array): array
    {
        if (!is_array($array)) {
            return (array) $array;
        }
        $flattened = [];
        $stack = array_values($array);
        while (!empty($stack)) {
            $value = array_shift($stack);
            if (is_array($value)) {
                array_unshift($stack, ...array_values($value));
            } else {
                $flattened[] = $value;
            }
        }
        return $flattened;
    }
    /**
     * Convert a multi-dimensional array to a simple 1-dimensional array.
     * Same as above but argument is specified in ... format.
     *
     * @param mixed $array Array to be flattened
     *
     * @return array<mixed> Flattened array
     */
    public static function flatten_array2(mixed ...$array): array
    {
        $flattened = [];
        $stack = array_values($array);
        while (!empty($stack)) {
            $value = array_shift($stack);
            if (is_array($value)) {
                array_unshift($stack, ...array_values($value));
            } else {
                $flattened[] = $value;
            }
        }
        return $flattened;
    }
    public static function scalar(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        do {
            $value = array_pop($value);
        } while (is_array($value));
        return $value;
    }
    /**
     * Convert a multi-dimensional array to a simple 1-dimensional array, but retain an element of indexing.
     *
     * @param array|mixed $array Array to be flattened
     *
     * @return array<mixed> Flattened array
     */
    public static function flatten_array_indexed($array): array
    {
        if (!is_array($array)) {
            return (array) $array;
        }
        $array_values = [];
        foreach ($array as $k1 => $value) {
            if (is_array($value)) {
                foreach ($value as $k2 => $val) {
                    if (is_array($val)) {
                        foreach ($val as $k3 => $v) {
                            $array_values[$k1 . '.' . $k2 . '.' . $k3] = $v;
                        }
                    } else {
                        $array_values[$k1 . '.' . $k2] = $val;
                    }
                }
            } else {
                $array_values[$k1] = $value;
            }
        }
        return $array_values;
    }
    /**
     * Convert an array to a single scalar value by extracting the first element.
     *
     * @param mixed $value Array or scalar value
     */
    public static function flatten_single_value(mixed $value): mixed
    {
        while (is_array($value)) {
            $value = array_shift($value);
        }
        return $value;
    }
    public static function expand_defined_name(string $coordinate, Cell $cell): string
    {
        $worksheet = $cell->get_worksheet();
        $spreadsheet = $worksheet->get_parent_or_throw();
        // Uppercase coordinate
        $p_coordinatex = strtoupper($coordinate);
        // Eliminate leading equal sign
        $p_coordinatex = (string) preg_replace('/^=/', '', $p_coordinatex);
        $defined = $spreadsheet->get_defined_name($p_coordinatex, $worksheet);
        if ($defined !== null) {
            $worksheet2 = $defined->get_work_sheet();
            if (!$defined->is_formula() && $worksheet2 !== null) {
                $coordinate = "'" . $worksheet2->get_title() . "'!" . preg_replace('/^=/', '', str_replace('$', '', $defined->get_value()));
            }
        }
        return $coordinate;
    }
    public static function trim_trailing_range(string $coordinate): string
    {
        return (string) preg_replace('/:[\w\$]+$/', '', $coordinate);
    }
    public static function trim_sheet_from_cell_reference(string $coordinate): string
    {
        if (str_contains($coordinate, '!')) {
            return substr($coordinate, strrpos($coordinate, '!') + 1);
        }
        return $coordinate;
    }
    /** @param mixed[] $array */
    public static function convert_array_to_cell_range(array $array): string
    {
        $ret_val = '';
        $last_row = $last_column = $first_row = $first_column = 0;
        foreach ($array as $rowkey => $row) {
            if (!is_array($row) || !is_int($rowkey) || $rowkey < 1) {
                $first_row = 0;
                break;
            }
            if ($first_row > $rowkey || $first_row === 0) {
                $first_row = $rowkey;
            }
            if ($last_row < $rowkey) {
                $last_row = $rowkey;
            }
            foreach ($row as $colkey => $cell_value) {
                if (!preg_match('/^[A-Z]{1,3}$/', (string) $colkey)) {
                    $first_row = 0;
                    break 2;
                }
                $column = Coordinate::column_index_from_string($colkey);
                if ($first_column > $column || $first_column === 0) {
                    $first_column = $column;
                }
                if ($last_column < $column) {
                    $last_column = $column;
                }
            }
        }
        if ($first_row > 0 && $first_column > 0 && ($first_row !== $last_row || $first_column !== $last_column)) {
            return Coordinate::string_from_column_index($first_column) . $first_row . ':' . Coordinate::string_from_column_index($last_column) . $last_row;
        }
        return $ret_val;
    }
}