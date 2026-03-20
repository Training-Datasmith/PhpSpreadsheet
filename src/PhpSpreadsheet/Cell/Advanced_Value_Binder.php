<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Formatted_Number;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
class Advanced_Value_Binder extends Default_Value_Binder implements I_Value_Binder
{
    /**
     * Bind value to a cell.
     *
     * @param Cell $cell Cell to bind value to
     * @param mixed $value Value to bind in cell
     */
    public function bind_value(Cell $cell, mixed $value = null): bool
    {
        if ($value === null) {
            return parent::bind_value($cell, $value);
        }
        if (is_string($value)) {
            // sanitize UTF-8 strings
            $value = String_Helper::sanitize_utf8($value);
        }
        // Find out data type
        $data_type = parent::data_type_for_value($value);
        // Style logic - strings
        if ($data_type === Data_Type::TYPE_STRING && is_string($value)) {
            //    Test for booleans using locale-setting
            if (String_Helper::str_to_upper($value) === Calculation::get_true()) {
                $cell->set_value_explicit(true, Data_Type::TYPE_BOOL);
                return true;
            }
            //    Test for booleans using locale-setting
            if (String_Helper::str_to_upper($value) === Calculation::get_false()) {
                $cell->set_value_explicit(false, Data_Type::TYPE_BOOL);
                return true;
            }
            // Check for fractions
            if (preg_match('~^([+-]?)\s*(\d+)\s*/\s*(\d+)$~', $value, $matches)) {
                return $this->set_proper_fraction($matches, $cell);
            }
            // Check for fractions
            if (preg_match('~^([+-]?)(\d+)\s+(\d+)\s*/\s*(\d+)$~', $value, $matches)) {
                return $this->set_improper_fraction($matches, $cell);
            }
            $decimal_separator_no_preg = String_Helper::get_decimal_separator();
            $decimal_separator = preg_quote($decimal_separator_no_preg, '/');
            $thousands_separator = preg_quote(String_Helper::get_thousands_separator(), '/');
            // Check for percentage
            if (preg_match('/^\-?\d*' . $decimal_separator . '?\d*\s?\%$/', (string) preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', $value))) {
                return $this->set_percentage((string) preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', $value), $cell);
            }
            // Check for currency
            if (preg_match(Formatted_Number::currency_matcher_regexp(), (string) preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', $value), $matches, PREG_UNMATCHED_AS_NULL)) {
                // Convert value to number
                $sign = ($matches['PrefixedSign'] ?? $matches['PrefixedSign2'] ?? $matches['PostfixedSign']) ?? null;
                $currency_code = $matches['PrefixedCurrency'] ?? $matches['PostfixedCurrency'] ?? '';
                /** @var string */
                $temp = str_replace([$decimal_separator_no_preg, $currency_code, ' ', '-'], ['.', '', '', ''], (string) preg_replace('/(\d)' . $thousands_separator . '(\d)/u', '$1$2', $value));
                $value = (float) ($sign . trim($temp));
                return $this->set_currency($value, $cell, $currency_code);
            }
            // Check for time without seconds e.g. '9:45', '09:45'
            if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d$/', $value)) {
                return $this->set_time_hours_minutes($value, $cell);
            }
            // Check for time with seconds '9:45:59', '09:45:59'
            if (preg_match('/^(\d|[0-1]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
                return $this->set_time_hours_minutes_seconds($value, $cell);
            }
            // Check for datetime, e.g. '2008-12-31', '2008-12-31 15:59', '2008-12-31 15:59:10'
            if (($d = Date::string_to_excel($value)) !== false) {
                // Convert value to number
                $cell->set_value_explicit($d, Data_Type::TYPE_NUMERIC);
                // Determine style. Either there is a time part or not. Look for ':'
                if (str_contains($value, ':')) {
                    $format_code = 'yyyy-mm-dd h:mm';
                } else {
                    $format_code = 'yyyy-mm-dd';
                }
                $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code($format_code);
                return true;
            }
            // Check for newline character "\n"
            if (str_contains($value, "\n")) {
                $cell->set_value_explicit($value, Data_Type::TYPE_STRING);
                // Set style
                $cell->get_worksheet()->get_style($cell->get_coordinate())->get_alignment()->set_wrap_text(true);
                return true;
            }
        }
        // Not bound yet? Use parent...
        return parent::bind_value($cell, $value);
    }
    /** @param array{0: string, 1: ?string, 2: numeric-string, 3: numeric-string, 4: numeric-string} $matches */
    protected function set_improper_fraction(array $matches, Cell $cell): bool
    {
        // Convert value to number
        $value = $matches[2] + $matches[3] / $matches[4];
        if ($matches[1] === '-') {
            $value = 0 - $value;
        }
        $cell->set_value_explicit((float) $value, Data_Type::TYPE_NUMERIC);
        // Build the number format mask based on the size of the matched values
        $dividend = str_repeat('?', strlen($matches[3]));
        $divisor = str_repeat('?', strlen($matches[4]));
        $fraction_mask = "# {$dividend}/{$divisor}";
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code($fraction_mask);
        return true;
    }
    /** @param array{0: string, 1: ?string, 2: numeric-string, 3: numeric-string} $matches */
    protected function set_proper_fraction(array $matches, Cell $cell): bool
    {
        // Convert value to number
        $value = $matches[2] / $matches[3];
        if ($matches[1] === '-') {
            $value = 0 - $value;
        }
        $cell->set_value_explicit((float) $value, Data_Type::TYPE_NUMERIC);
        // Build the number format mask based on the size of the matched values
        $dividend = str_repeat('?', strlen($matches[2]));
        $divisor = str_repeat('?', strlen($matches[3]));
        $fraction_mask = "{$dividend}/{$divisor}";
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code($fraction_mask);
        return true;
    }
    protected function set_percentage(string $value, Cell $cell): bool
    {
        // Convert value to number
        $value = (float) str_replace('%', '', $value) / 100;
        $cell->set_value_explicit($value, Data_Type::TYPE_NUMERIC);
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code(Number_Format::FORMAT_PERCENTAGE_00);
        return true;
    }
    protected function set_currency(float $value, Cell $cell, string $currency_code): bool
    {
        $cell->set_value_explicit($value, Data_Type::TYPE_NUMERIC);
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code(str_replace('$', '[$' . $currency_code . ']', Number_Format::FORMAT_CURRENCY_USD));
        return true;
    }
    protected function set_time_hours_minutes(string $value, Cell $cell): bool
    {
        // Convert value to number
        [$hours, $minutes] = explode(':', $value);
        $hours = (int) $hours;
        $minutes = (int) $minutes;
        $days = $hours / 24 + $minutes / 1440;
        $cell->set_value_explicit($days, Data_Type::TYPE_NUMERIC);
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code(Number_Format::FORMAT_DATE_TIME3);
        return true;
    }
    protected function set_time_hours_minutes_seconds(string $value, Cell $cell): bool
    {
        // Convert value to number
        [$hours, $minutes, $seconds] = explode(':', $value);
        $hours = (int) $hours;
        $minutes = (int) $minutes;
        $seconds = (int) $seconds;
        $days = $hours / 24 + $minutes / 1440 + $seconds / 86400;
        $cell->set_value_explicit($days, Data_Type::TYPE_NUMERIC);
        // Set style
        $cell->get_worksheet()->get_style($cell->get_coordinate())->get_number_format()->set_format_code(Number_Format::FORMAT_DATE_TIME4);
        return true;
    }
}