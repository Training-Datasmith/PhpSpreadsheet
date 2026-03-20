<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Internal;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Excel_Array_Pseudo_Functions
{
    public static function single(string $cell_reference, Cell $cell): mixed
    {
        $worksheet = $cell->get_worksheet();
        [$reference_worksheet_name, $reference_cell_coordinate] = Worksheet::extract_sheet_title($cell_reference, true, true);
        if (preg_match('/^([$]?[a-z]{1,3})([$]?([0-9]{1,7})):([$]?[a-z]{1,3})([$]?([0-9]{1,7}))$/i', "{$reference_cell_coordinate}", $matches) === 1) {
            $our_row = $cell->get_row();
            $first_row = (int) $matches[3];
            $last_row = (int) $matches[6];
            if ($our_row < $first_row || $our_row > $last_row || $matches[1] !== $matches[4]) {
                return Excel_Error::VALUE();
            }
            $reference_cell_coordinate = $matches[1] . $our_row;
        }
        $reference_cell = $reference_worksheet_name === '' ? $worksheet->get_cell((string) $reference_cell_coordinate) : $worksheet->get_parent_or_throw()->get_sheet_by_name_or_throw((string) $reference_worksheet_name)->get_cell((string) $reference_cell_coordinate);
        $result = $reference_cell->get_calculated_value();
        while (is_array($result)) {
            $result = array_shift($result);
        }
        return $result;
    }
    /** @return array<mixed>|string */
    public static function anchor_array(string $cell_reference, Cell $cell): array|string
    {
        //$coordinate = $cell->getCoordinate();
        $worksheet = $cell->get_worksheet();
        [$reference_worksheet_name, $reference_cell_coordinate] = Worksheet::extract_sheet_title($cell_reference, true, true);
        $reference_cell = $reference_worksheet_name === '' ? $worksheet->get_cell((string) $reference_cell_coordinate) : $worksheet->get_parent_or_throw()->get_sheet_by_name_or_throw((string) $reference_worksheet_name)->get_cell((string) $reference_cell_coordinate);
        // We should always use the sizing for the array formula range from the referenced cell formula
        //$referenceRange = null;
        /*if ($referenceCell->isFormula() && $referenceCell->isArrayFormula()) {
              $referenceRange = $referenceCell->arrayFormulaRange();
          }*/
        $calc_engine = Calculation::get_instance($worksheet->get_parent());
        $result = $calc_engine->calculate_cell_value($reference_cell, false);
        if (!is_array($result)) {
            return Excel_Error::REF();
        }
        // Ensure that our array result dimensions match the specified array formula range dimensions,
        //    from the referenced cell, expanding or shrinking it as necessary.
        /*$result = Functions::resizeMatrix(
              $result,
              ...Coordinate::rangeDimension($referenceRange ?? $coordinate)
          );*/
        // Set the result for our target cell (with spillage)
        // But if we do write it, we get problems with #SPILL! Errors if the spreadsheet is saved
        // TODO How are we going to identify and handle a #SPILL! or a #CALC! error?
        //        IOFactory::setLoading(true);
        //        $worksheet->fromArray(
        //            $result,
        //            null,
        //            $coordinate,
        //            true
        //        );
        //        IOFactory::setLoading(true);
        // Calculate the array formula range that we should set for our target, based on our target cell coordinate
        //        [$col, $row] = Coordinate::indexesFromString($coordinate);
        //        $row += count($result) - 1;
        //        $col = Coordinate::stringFromColumnIndex($col + count($result[0]) - 1);
        //        $arrayFormulaRange = "{$coordinate}:{$col}{$row}";
        //        $formulaAttributes = ['t' => 'array', 'ref' => $arrayFormulaRange];
        // Using fromArray() would reset the value for this cell with the calculation result
        //      as well as updating the spillage cells,
        //  so we need to restore this cell to its formula value, attributes, and datatype
        //        $cell = $worksheet->getCell($coordinate);
        //        $cell->setValueExplicit($value, DataType::TYPE_FORMULA, true, $arrayFormulaRange);
        //        $cell->setFormulaAttributes($formulaAttributes);
        //        $cell->updateInCollection();
        return $result;
    }
}