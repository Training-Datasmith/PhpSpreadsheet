<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Database;

use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Statistical\Minimum;
class D_Min extends Database_Abstract
{
    /**
     * DMIN.
     *
     * Returns the smallest number in a column of a list or database that matches conditions you that
     * specify.
     *
     * Excel Function:
     *        DMIN(database,field,criteria)
     *
     * @param mixed[] $database The range of cells that makes up the list or database.
     *                                        A database is a list of related data in which rows of related
     *                                        information are records, and columns of data are fields. The
     *                                        first row of the list contains labels for each column.
     * @param null|array<mixed>|int|string $field Indicates which column is used in the function. Enter the
     *                                        column label enclosed between double quotation marks, such as
     *                                        "Age" or "Yield," or a number (without quotation marks) that
     *                                        represents the position of the column within the list: 1 for
     *                                        the first column, 2 for the second column, and so on.
     * @param mixed[][] $criteria The range of cells that contains the conditions you specify.
     *                                        You can use any range for the criteria argument, as long as it
     *                                        includes at least one column label and at least one cell below
     *                                        the column label in which you specify a condition for the
     *                                        column.
     */
    public static function evaluate(array $database, array|null|int|string $field, array $criteria, bool $return_error = true): float|string|null
    {
        $field = self::field_extract($database, $field);
        if ($field === null) {
            return $return_error ? Excel_Error::VALUE() : null;
        }
        return Minimum::min(self::get_filtered_column($database, $field, $criteria));
    }
}