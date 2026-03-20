<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Selection
{
    use Array_Enabled;
    /**
     * CHOOSE.
     *
     * Uses lookup_value to return a value from the list of value arguments.
     * Use CHOOSE to select one of up to 254 values based on the lookup_value.
     *
     * Excel Function:
     *        =CHOOSE(index_num, value1, [value2], ...)
     *
     * @param mixed $chosenEntry The entry to select from the list (indexed from 1)
     * @param mixed ...$chooseArgs Data values
     *
     * @return mixed The selected value
     */
    public static function choose(mixed $chosen_entry, mixed ...$choose_args): mixed
    {
        if (is_array($chosen_entry)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $chosen_entry, ...$choose_args);
        }
        $entry_count = count($choose_args) - 1;
        if (is_numeric($chosen_entry)) {
            --$chosen_entry;
        } else {
            return Excel_Error::VALUE();
        }
        $chosen_entry = (int) floor($chosen_entry);
        if ($chosen_entry < 0 || $chosen_entry > $entry_count) {
            return Excel_Error::VALUE();
        }
        if (is_array($choose_args[$chosen_entry])) {
            return Functions::flatten_array($choose_args[$chosen_entry]);
        }
        return $choose_args[$chosen_entry];
    }
}