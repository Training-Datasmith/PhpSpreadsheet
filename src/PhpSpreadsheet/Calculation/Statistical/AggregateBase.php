<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
abstract class Aggregate_Base
{
    /**
     * MS Excel does not count Booleans if passed as cell values, but they are counted if passed as literals.
     * OpenOffice Calc always counts Booleans.
     * Gnumeric never counts Booleans.
     */
    protected static function test_accepted_boolean(mixed $arg, mixed $k): mixed
    {
        if (!is_bool($arg)) {
            return $arg;
        }
        if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_GNUMERIC) {
            return $arg;
        }
        if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
            return (int) $arg;
        }
        if (!Functions::is_cell_value($k)) {
            return (int) $arg;
        }
        /*if (
              (is_bool($arg)) &&
              ((!Functions::isCellValue($k) && (Functions::getCompatibilityMode() === Functions::COMPATIBILITY_EXCEL)) ||
                  (Functions::getCompatibilityMode() === Functions::COMPATIBILITY_OPENOFFICE))
          ) {
              $arg = (int) $arg;
          }*/
        return $arg;
    }
    protected static function is_accepted_countable(mixed $arg, mixed $k, bool $count_null = false): bool
    {
        if ($count_null && $arg === null && !Functions::is_cell_value($k) && Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_GNUMERIC) {
            return true;
        }
        if (!is_numeric($arg)) {
            return false;
        }
        if (!is_string($arg)) {
            return true;
        }
        if (!Functions::is_cell_value($k) && Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
            return true;
        }
        if (!Functions::is_cell_value($k) && Functions::get_compatibility_mode() !== Functions::COMPATIBILITY_GNUMERIC) {
            return true;
        }
        return false;
    }
}