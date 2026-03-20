<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalculationException;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Collection\Cells;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Date as SharedDate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Cell_Style_Assessor;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Stringable;
class Cell implements Stringable
{
    /**
     * Value binder to use.
     */
    private static ?I_Value_Binder $value_binder = null;
    /**
     *    Calculated value of the cell (used for caching)
     *    This returns the value last calculated by MS Excel or whichever spreadsheet program was used to
     *        create the original spreadsheet file.
     *    Note that this value is not guaranteed to reflect the actual calculated value because it is
     *        possible that auto-calculation was disabled in the original spreadsheet, and underlying data
     *        values used by the formula have changed since it was last calculated.
     *
     * @var mixed
     */
    private $calculated_value;
    /**
     * Type of the cell data.
     */
    private string $data_type;
    /**
     * The collection of cells that this cell belongs to (i.e. The Cell Collection for the parent Worksheet).
     */
    private ?Cells $parent;
    /**
     * Index to the cellXf reference for the styling of this cell.
     */
    private int $xf_index = 0;
    /**
     * Attributes of the formula.
     *
     * @var null|array<string, string>
     */
    private ?array $formula_attributes = null;
    private readonly Ignored_Errors $ignored_errors;
    /**
     * Update the cell into the cell collection.
     *
     * @throws SpreadsheetException
     */
    public function update_in_collection(): self
    {
        $parent = $this->parent;
        if ($parent === null) {
            throw new Spreadsheet_Exception('Cannot update when cell is not bound to a worksheet');
        }
        $parent->update($this);
        return $this;
    }
    public function detach(): void
    {
        $this->parent = null;
    }
    public function attach(Cells $parent): void
    {
        $this->parent = $parent;
    }
    /**
     * Create a new Cell.
     *
     * @throws SpreadsheetException
     */
    public function __construct(
        /**
         * Value of the cell.
         */
        private mixed $value,
        ?string $data_type,
        Worksheet $worksheet
    )
    {
        // Set worksheet cache
        $this->parent = $worksheet->get_cell_collection();
        // Set datatype?
        if ($data_type !== null) {
            if ($data_type == Data_Type::TYPE_STRING2) {
                $data_type = Data_Type::TYPE_STRING;
            }
            $this->data_type = $data_type;
        } else {
            $value_binder = $worksheet->get_parent()?->get_value_binder() ?? self::get_value_binder();
            if ($value_binder->bind_value($this, $this->value) === false) {
                throw new Spreadsheet_Exception('Value could not be bound to cell.');
            }
        }
        $this->ignored_errors = new Ignored_Errors();
    }
    /**
     * Get cell coordinate column.
     *
     * @throws SpreadsheetException
     */
    public function get_column(): string
    {
        $parent = $this->parent;
        if ($parent === null) {
            throw new Spreadsheet_Exception('Cannot get column when cell is not bound to a worksheet');
        }
        return $parent->get_current_column();
    }
    /**
     * Get cell coordinate row.
     *
     * @throws SpreadsheetException
     */
    public function get_row(): int
    {
        $parent = $this->parent;
        if ($parent === null) {
            throw new Spreadsheet_Exception('Cannot get row when cell is not bound to a worksheet');
        }
        return $parent->get_current_row();
    }
    /**
     * Get cell coordinate.
     *
     * @throws SpreadsheetException
     */
    public function get_coordinate(): string
    {
        $parent = $this->parent;
        if ($parent !== null) {
            $coordinate = $parent->get_current_coordinate();
        } else {
            $coordinate = null;
        }
        if ($coordinate === null) {
            throw new Spreadsheet_Exception('Coordinate no longer exists');
        }
        return $coordinate;
    }
    /**
     * Get cell value.
     */
    public function get_value(): mixed
    {
        return $this->value;
    }
    public function get_value_string(): string
    {
        return String_Helper::convert_to_string($this->value, false);
    }
    /**
     * Get cell value with formatting.
     */
    public function get_formatted_value(): string
    {
        $current_calendar = Shared_Date::get_excel_calendar();
        Shared_Date::set_excel_calendar($this->get_worksheet()->get_parent()?->get_excel_calendar());
        $formatted_value = Number_Format::to_formatted_string($this->get_calculated_value_string(), (string) $this->get_style()->get_number_format()->get_format_code(true));
        Shared_Date::set_excel_calendar($current_calendar);
        return $formatted_value;
    }
    protected static function update_if_cell_is_table_header(?Worksheet $work_sheet, self $cell, mixed $old_value, mixed $new_value): void
    {
        $old_value = String_Helper::convert_to_string($old_value, false);
        $new_value = String_Helper::convert_to_string($new_value, false);
        if (String_Helper::str_to_lower($old_value) === String_Helper::str_to_lower($new_value) || $work_sheet === null) {
            return;
        }
        foreach ($work_sheet->get_table_collection() as $table) {
            /** @var Table $table */
            if ($cell->is_in_range($table->get_range())) {
                $range_rows_columns = Coordinate::get_range_boundaries($table->get_range());
                if ($cell->get_row() === (int) $range_rows_columns[0][1]) {
                    Table\Column::update_structured_references($work_sheet, $old_value, $new_value);
                }
                return;
            }
        }
    }
    /**
     * Set cell value.
     *
     *    Sets the value for a cell, automatically determining the datatype using the value binder
     *
     * @param mixed $value Value
     * @param null|IValueBinder $binder Value Binder to override the currently set Value Binder
     *
     * @throws SpreadsheetException
     */
    public function set_value(mixed $value, ?I_Value_Binder $binder = null): self
    {
        if ($this->had_hyperlink) {
            $this->clear_hyperlink();
        }
        // Cells?->Worksheet?->Spreadsheet
        $binder ??= $this->parent?->get_parent()?->get_parent()?->get_value_binder() ?? self::get_value_binder();
        if (!$binder->bind_value($this, $value)) {
            throw new Spreadsheet_Exception('Value could not be bound to cell.');
        }
        return $this;
    }
    private bool $had_hyperlink = false;
    /** @internal */
    public function set_had_hyperlink(bool $had_hyperlink): void
    {
        $this->had_hyperlink = $had_hyperlink;
    }
    private function clear_hyperlink(): void
    {
        $worksheet = $this->get_worksheet_or_null();
        if ($worksheet !== null) {
            $coordinate = $this->get_coordinate();
            $worksheet->set_hyperlink($coordinate);
        }
        $this->had_hyperlink = false;
    }
    /**
     * Set the value for a cell, with the explicit data type passed to the method (bypassing any use of the value binder).
     *
     * @param mixed $value Value
     * @param string $dataType Explicit data type, see DataType::TYPE_*
     *        This parameter is currently optional (default = string).
     *        Omitting it is ***DEPRECATED***, and the default will be removed in a future release.
     *        Note that PhpSpreadsheet does not validate that the value and datatype are consistent, in using this
     *             method, then it is your responsibility as an end-user developer to validate that the value and
     *             the datatype match.
     *       If you do mismatch value and datatype, then the value you enter may be changed to match the datatype
     *          that you specify.
     *
     * @throws SpreadsheetException
     */
    public function set_value_explicit(mixed $value, string $data_type = Data_Type::TYPE_STRING): self
    {
        if ($this->had_hyperlink) {
            $this->clear_hyperlink();
        }
        $old_value = $this->value;
        $quote_prefix = false;
        // set the value according to data type
        switch ($data_type) {
            case Data_Type::TYPE_NULL:
                $this->value = null;
                break;
            case Data_Type::TYPE_STRING2:
                $data_type = Data_Type::TYPE_STRING;
            // no break
            case Data_Type::TYPE_STRING:
                // Synonym for string
                if (is_string($value) && strlen($value) > 1 && $value[0] === '=') {
                    $quote_prefix = true;
                }
            // no break
            case Data_Type::TYPE_INLINE:
                // Rich text
                $value2 = String_Helper::convert_to_string($value, true);
                // Cells?->Worksheet?->Spreadsheet
                $binder = $this->parent?->get_parent()?->get_parent()?->get_value_binder();
                $preserve_cr = false;
                if ($binder !== null && method_exists($binder, 'getPreserveCr')) {
                    /** @var bool */
                    $preserve_cr = $binder->get_preserve_cr();
                }
                $this->value = Data_Type::check_string($value instanceof Rich_Text ? $value : $value2, $preserve_cr);
                break;
            case Data_Type::TYPE_NUMERIC:
                if ($value !== null && !is_bool($value) && !is_numeric($value)) {
                    throw new Spreadsheet_Exception('Invalid numeric value for datatype Numeric');
                }
                $this->value = 0 + $value;
                break;
            case Data_Type::TYPE_FORMULA:
                $this->value = String_Helper::convert_to_string($value, true);
                break;
            case Data_Type::TYPE_BOOL:
                $this->value = (bool) $value;
                break;
            case Data_Type::TYPE_ISO_DATE:
                $this->value = Shared_Date::convert_iso_date($value);
                $data_type = Data_Type::TYPE_NUMERIC;
                break;
            case Data_Type::TYPE_DRAWING_IN_CELL:
                if ($value instanceof Base_Drawing) {
                    $this->value = $value;
                } else {
                    throw new Spreadsheet_Exception('Item is not a drawing');
                }
                break;
            case Data_Type::TYPE_ERROR:
                $this->value = Data_Type::check_error_code($value);
                break;
            default:
                throw new Spreadsheet_Exception('Invalid datatype: ' . $data_type);
        }
        // set the datatype
        $this->data_type = $data_type;
        $this->update_in_collection();
        $cell_coordinate = $this->get_coordinate();
        self::update_if_cell_is_table_header($this->get_parent()?->get_parent(), $this, $old_value, $value);
        $worksheet = $this->get_worksheet();
        $spreadsheet = $worksheet->get_parent();
        if (isset($spreadsheet) && $spreadsheet->get_index($worksheet, true) >= 0) {
            $original_selected = $worksheet->get_selected_cells();
            $active_sheet_index = $spreadsheet->get_active_sheet_index();
            $style = $this->get_style();
            $old_quote_prefix = $style->get_quote_prefix();
            if ($old_quote_prefix !== $quote_prefix) {
                $style->set_quote_prefix($quote_prefix);
            }
            $worksheet->set_selected_cells($original_selected);
            if ($active_sheet_index >= 0) {
                $spreadsheet->set_active_sheet_index($active_sheet_index);
            }
        }
        return $this->get_parent()?->get($cell_coordinate) ?? $this;
    }
    public const CALCULATE_DATE_TIME_ASIS = 0;
    public const CALCULATE_DATE_TIME_FLOAT = 1;
    public const CALCULATE_TIME_FLOAT = 2;
    private static int $calculate_date_time_type = self::CALCULATE_DATE_TIME_ASIS;
    public static function get_calculate_date_time_type(): int
    {
        return self::$calculate_date_time_type;
    }
    /** @throws CalculationException */
    public static function set_calculate_date_time_type(int $calculate_date_time_type): void
    {
        self::$calculate_date_time_type = match ($calculate_date_time_type) {
            self::CALCULATE_DATE_TIME_ASIS, self::CALCULATE_DATE_TIME_FLOAT, self::CALCULATE_TIME_FLOAT => $calculate_date_time_type,
            default => throw new Calculation_Exception("Invalid value {$calculate_date_time_type} for calculated date time type"),
        };
    }
    /**
     * Convert date, time, or datetime from int to float if desired.
     */
    private function convert_date_time_int(mixed $result): mixed
    {
        if (is_int($result)) {
            if (self::$calculate_date_time_type === self::CALCULATE_TIME_FLOAT) {
                if (Shared_Date::is_date_time($this, $result, false)) {
                    $result = (float) $result;
                }
            } elseif (self::$calculate_date_time_type === self::CALCULATE_DATE_TIME_FLOAT) {
                if (Shared_Date::is_date_time($this, $result, true)) {
                    $result = (float) $result;
                }
            }
        }
        return $result;
    }
    /**
     * Get calculated cell value converted to string.
     */
    public function get_calculated_value_string(): string
    {
        $value = $this->get_calculated_value();
        while (is_array($value)) {
            $value = array_shift($value);
        }
        return String_Helper::convert_to_string($value, false);
    }
    /**
     * Get calculated cell value.
     *
     * @param bool $resetLog Whether the calculation engine logger should be reset or not
     *
     * @throws CalculationException
     */
    public function get_calculated_value(bool $reset_log = true): mixed
    {
        $title = 'unknown';
        $old_attributes = $this->formula_attributes;
        $old_attributes_t = $old_attributes['t'] ?? '';
        $coordinate = $this->get_coordinate();
        $old_attributes_ref = $old_attributes['ref'] ?? $coordinate;
        $original_value = $this->value;
        $original_data_type = $this->data_type;
        $this->formula_attributes = [];
        $spill = false;
        if ($this->data_type === Data_Type::TYPE_FORMULA) {
            try {
                $current_calendar = Shared_Date::get_excel_calendar();
                Shared_Date::set_excel_calendar($this->get_worksheet()->get_parent()?->get_excel_calendar());
                $thisworksheet = $this->get_worksheet();
                $index = $thisworksheet->get_parent_or_throw()->get_active_sheet_index();
                $selected = $thisworksheet->get_selected_cells();
                $title = $thisworksheet->get_title();
                $calculation = Calculation::get_instance($thisworksheet->get_parent());
                $result = $calculation->calculate_cell_value($this, $reset_log);
                $result = $this->convert_date_time_int($result);
                $thisworksheet->set_selected_cells($selected);
                $thisworksheet->get_parent_or_throw()->set_active_sheet_index($index);
                if (is_array($result) && $calculation->get_instance_array_return_type() !== Calculation::RETURN_ARRAY_AS_ARRAY) {
                    while (is_array($result)) {
                        $result = array_shift($result);
                    }
                }
                if (!is_array($result) && $calculation->get_instance_array_return_type() === Calculation::RETURN_ARRAY_AS_ARRAY && $old_attributes_t === 'array' && ($old_attributes_ref === $coordinate || $old_attributes_ref === "{$coordinate}:{$coordinate}")) {
                    $result = [$result];
                }
                // if return_as_array for formula like '=sheet!cell'
                if (is_array($result) && count($result) === 1) {
                    $result_key = array_keys($result)[0];
                    $result_value = $result[$result_key];
                    if (is_int($result_key) && is_array($result_value) && count($result_value) === 1) {
                        $result_key2 = array_keys($result_value)[0];
                        $result_value2 = $result_value[$result_key2];
                        if (is_string($result_key2) && !is_array($result_value2) && preg_match('/[a-zA-Z]{1,3}/', $result_key2) === 1) {
                            $result = $result_value2;
                        }
                    }
                }
                $new_column = $this->get_column();
                if (is_array($result)) {
                    $result = self::convert_special_array($result);
                    $this->formula_attributes['t'] = 'array';
                    $this->formula_attributes['ref'] = $max_coordinate = $coordinate;
                    $new_row = $row = $this->get_row();
                    $column = $this->get_column();
                    foreach ($result as $result_row) {
                        if (is_array($result_row)) {
                            $new_column = $column;
                            foreach ($result_row as $result_value) {
                                if ($row !== $new_row || $column !== $new_column) {
                                    $max_coordinate = $new_column . $new_row;
                                    if ($thisworksheet->get_cell($new_column . $new_row)->get_value() !== null) {
                                        if (!Coordinate::coordinate_is_inside_range($old_attributes_ref, $new_column . $new_row)) {
                                            $spill = true;
                                            break;
                                        }
                                    }
                                }
                                /** @var string $newColumn */
                                String_Helper::string_increment($new_column);
                            }
                            ++$new_row;
                        } else {
                            if ($row !== $new_row || $column !== $new_column) {
                                $max_coordinate = $new_column . $new_row;
                                if ($thisworksheet->get_cell($new_column . $new_row)->get_value() !== null) {
                                    if (!Coordinate::coordinate_is_inside_range($old_attributes_ref, $new_column . $new_row)) {
                                        $spill = true;
                                    }
                                }
                            }
                            String_Helper::string_increment($new_column);
                        }
                        if ($spill) {
                            break;
                        }
                    }
                    if (!$spill) {
                        $this->formula_attributes['ref'] .= ":{$max_coordinate}";
                    }
                    $thisworksheet->get_cell($column . $row);
                }
                if (is_array($result)) {
                    if ($old_attributes !== null && $calculation->get_instance_array_return_type() === Calculation::RETURN_ARRAY_AS_ARRAY) {
                        if ($old_attributes_t === 'array') {
                            $thisworksheet = $this->get_worksheet();
                            $coordinate = $this->get_coordinate();
                            $ref = $old_attributes_ref;
                            if (preg_match('/^([A-Z]{1,3})([0-9]{1,7})(:([A-Z]{1,3})([0-9]{1,7}))?$/', $ref, $matches) === 1) {
                                if (isset($matches[3])) {
                                    $min_col = $matches[1];
                                    $min_row = (int) $matches[2];
                                    $max_col = $matches[4];
                                    String_Helper::string_increment($max_col);
                                    $max_row = (int) $matches[5];
                                    for ($row = $min_row; $row <= $max_row; ++$row) {
                                        for ($col = $min_col; $col !== $max_col; String_Helper::string_increment($col)) {
                                            /** @var string $col */
                                            if ("{$col}{$row}" !== $coordinate) {
                                                $thisworksheet->get_cell("{$col}{$row}")->set_value(null);
                                            }
                                        }
                                    }
                                }
                            }
                            $thisworksheet->get_cell($coordinate);
                        }
                    }
                }
                if ($spill) {
                    $result = Excel_Error::SPILL();
                }
                if (is_array($result)) {
                    $new_row = $row = $this->get_row();
                    $new_column = $column = $this->get_column();
                    foreach ($result as $result_row) {
                        if (is_array($result_row)) {
                            $new_column = $column;
                            foreach ($result_row as $result_value) {
                                if ($row !== $new_row || $column !== $new_column) {
                                    $thisworksheet->get_cell($new_column . $new_row)->set_value($result_value);
                                }
                                String_Helper::string_increment($new_column);
                            }
                            ++$new_row;
                        } else {
                            if ($row !== $new_row || $column !== $new_column) {
                                $thisworksheet->get_cell($new_column . $new_row)->set_value($result_row);
                            }
                            String_Helper::string_increment($new_column);
                        }
                    }
                    $thisworksheet->get_cell($column . $row);
                    $this->value = $original_value;
                    $this->data_type = $original_data_type;
                }
            } catch (Spreadsheet_Exception $ex) {
                Shared_Date::set_excel_calendar($current_calendar);
                if ($ex->get_message() === 'Unable to access External Workbook' && $this->calculated_value !== null) {
                    return $this->calculated_value;
                    // Fallback for calculations referencing external files.
                } elseif (preg_match('/[Uu]ndefined (name|offset: 2|array key 2)/', $ex->get_message()) === 1) {
                    return Excel_Error::NAME();
                }
                throw new Calculation_Exception($title . '!' . $this->get_coordinate() . ' -> ' . $ex->get_message(), $ex->get_code(), $ex);
            }
            Shared_Date::set_excel_calendar($current_calendar);
            if ($result === Functions::NOT_YET_IMPLEMENTED) {
                $this->formula_attributes = $old_attributes;
                return $this->calculated_value;
                // Fallback if calculation engine does not support the formula.
            }
            return $result;
        }
        if ($this->value instanceof Rich_Text) {
            return $this->value->get_plain_text();
        }
        return $this->convert_date_time_int($this->value);
    }
    /**
     * Convert array like the following (preserve values, lose indexes):
     * [
     *   rowNumber1 => [colLetter1 => value, colLetter2 => value ...],
     *   rowNumber2 => [colLetter1 => value, colLetter2 => value ...],
     *   ...
     * ].
     *
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    private static function convert_special_array(array $array): array
    {
        $new_array = [];
        foreach ($array as $row_index => $row) {
            if (!is_int($row_index) || $row_index <= 0 || !is_array($row)) {
                return $array;
            }
            $keys = array_keys($row);
            $key0 = $keys[0] ?? '';
            if (!is_string($key0)) {
                return $array;
            }
            $new_array[] = array_values($row);
        }
        return $new_array;
    }
    /**
     * Set old calculated value (cached).
     *
     * @param mixed $originalValue Value
     */
    public function set_calculated_value(mixed $original_value, bool $try_numeric = true): self
    {
        if ($original_value !== null) {
            $this->calculated_value = $try_numeric && is_numeric($original_value) ? 0 + $original_value : $original_value;
        }
        return $this->update_in_collection();
    }
    /**
     *    Get old calculated value (cached)
     *    This returns the value last calculated by MS Excel or whichever spreadsheet program was used to
     *        create the original spreadsheet file.
     *    Note that this value is not guaranteed to reflect the actual calculated value because it is
     *        possible that auto-calculation was disabled in the original spreadsheet, and underlying data
     *        values used by the formula have changed since it was last calculated.
     */
    public function get_old_calculated_value(): mixed
    {
        return $this->calculated_value;
    }
    /**
     * Get cell data type.
     */
    public function get_data_type(): string
    {
        return $this->data_type;
    }
    /**
     * Set cell data type.
     *
     * @param string $dataType see DataType::TYPE_*
     */
    public function set_data_type(string $data_type): self
    {
        $this->set_value_explicit($this->value, $data_type);
        return $this;
    }
    /**
     * Identify if the cell contains a formula.
     */
    public function is_formula(): bool
    {
        return $this->data_type === Data_Type::TYPE_FORMULA && $this->get_style()->get_quote_prefix() === false;
    }
    /**
     *    Does this cell contain Data validation rules?
     *
     * @throws SpreadsheetException
     */
    public function has_data_validation(): bool
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot check for data validation when cell is not bound to a worksheet');
        }
        return $this->get_worksheet()->data_validation_exists($this->get_coordinate());
    }
    /**
     * Get Data validation rules.
     *
     * @throws SpreadsheetException
     */
    public function get_data_validation(): Data_Validation
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot get data validation for cell that is not bound to a worksheet');
        }
        return $this->get_worksheet()->get_data_validation($this->get_coordinate());
    }
    /**
     * Set Data validation rules.
     *
     * @throws SpreadsheetException
     */
    public function set_data_validation(?Data_Validation $data_validation = null): self
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot set data validation for cell that is not bound to a worksheet');
        }
        $this->get_worksheet()->set_data_validation($this->get_coordinate(), $data_validation);
        return $this->update_in_collection();
    }
    /**
     * Does this cell contain valid value?
     */
    public function has_valid_value(): bool
    {
        $validator = new Data_Validator();
        return $validator->is_valid($this);
    }
    /**
     * Does this cell contain a Hyperlink?
     *
     * @throws SpreadsheetException
     */
    public function has_hyperlink(): bool
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot check for hyperlink when cell is not bound to a worksheet');
        }
        return $this->get_worksheet()->hyperlink_exists($this->get_coordinate());
    }
    /**
     * Get Hyperlink.
     *
     * @throws SpreadsheetException
     */
    public function get_hyperlink(): Hyperlink
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot get hyperlink for cell that is not bound to a worksheet');
        }
        return $this->get_worksheet()->get_hyperlink($this->get_coordinate());
    }
    /**
     * Set Hyperlink.
     *
     * @throws SpreadsheetException
     */
    public function set_hyperlink(?Hyperlink $hyperlink = null): self
    {
        if (!isset($this->parent)) {
            throw new Spreadsheet_Exception('Cannot set hyperlink for cell that is not bound to a worksheet');
        }
        $this->get_worksheet()->set_hyperlink($this->get_coordinate(), $hyperlink);
        return $this->update_in_collection();
    }
    /**
     * Get cell collection.
     */
    public function get_parent(): ?Cells
    {
        return $this->parent;
    }
    /**
     * Get parent worksheet.
     *
     * @throws SpreadsheetException
     */
    public function get_worksheet(): Worksheet
    {
        $parent = $this->parent;
        if ($parent !== null) {
            $worksheet = $parent->get_parent();
        } else {
            $worksheet = null;
        }
        if ($worksheet === null) {
            throw new Spreadsheet_Exception('Worksheet no longer exists');
        }
        return $worksheet;
    }
    public function get_worksheet_or_null(): ?Worksheet
    {
        $parent = $this->parent;
        if ($parent !== null) {
            return $parent->get_parent();
        }
        return null;
    }
    /**
     * Is this cell in a merge range.
     */
    public function is_in_merge_range(): bool
    {
        return (bool) $this->get_merge_range();
    }
    /**
     * Is this cell the master (top left cell) in a merge range (that holds the actual data value).
     */
    public function is_merge_range_value_cell(): bool
    {
        if ($merge_range = $this->get_merge_range()) {
            $merge_range = Coordinate::split_range($merge_range);
            [$start_cell] = $merge_range[0];
            return $this->get_coordinate() === $start_cell;
        }
        return false;
    }
    /**
     * If this cell is in a merge range, then return the range.
     *
     * @return false|string
     */
    public function get_merge_range()
    {
        foreach ($this->get_worksheet()->get_merge_cells() as $merge_range) {
            if ($this->is_in_range($merge_range)) {
                return $merge_range;
            }
        }
        return false;
    }
    /**
     * Get cell style.
     */
    public function get_style(): Style
    {
        return $this->get_worksheet()->get_style($this->get_coordinate());
    }
    /**
     * Get cell style.
     */
    public function get_applied_style(): Style
    {
        if ($this->get_worksheet()->conditional_styles_exists($this->get_coordinate()) === false) {
            return $this->get_style();
        }
        $range = $this->get_worksheet()->get_conditional_range($this->get_coordinate());
        if ($range === null) {
            return $this->get_style();
        }
        $matcher = new Cell_Style_Assessor($this, $range);
        return $matcher->match_conditions($this->get_worksheet()->get_conditional_styles($this->get_coordinate()));
    }
    /**
     * Re-bind parent.
     */
    public function rebind_parent(Worksheet $parent): self
    {
        $this->parent = $parent->get_cell_collection();
        return $this->update_in_collection();
    }
    /**
     *    Is cell in a specific range?
     *
     * @param string $range Cell range (e.g. A1:A1)
     */
    public function is_in_range(string $range): bool
    {
        [$range_start, $range_end] = Coordinate::range_boundaries($range);
        // Translate properties
        $my_column = Coordinate::column_index_from_string($this->get_column());
        $my_row = $this->get_row();
        // Verify if cell is in range
        return $range_start[0] <= $my_column && $range_end[0] >= $my_column && $range_start[1] <= $my_row && $range_end[1] >= $my_row;
    }
    /**
     * Compare 2 cells.
     *
     * @param Cell $a Cell a
     * @param Cell $b Cell b
     *
     * @return int Result of comparison (always -1 or 1, never zero!)
     */
    public static function compare_cells(self $a, self $b): int
    {
        if ($a->get_row() < $b->get_row()) {
            return -1;
        }
        if ($a->get_row() > $b->get_row()) {
            return 1;
        }
        if (Coordinate::column_index_from_string($a->get_column()) < Coordinate::column_index_from_string($b->get_column())) {
            return -1;
        }
        return 1;
    }
    /**
     * Get value binder to use.
     */
    public static function get_value_binder(): I_Value_Binder
    {
        if (self::$value_binder === null) {
            self::$value_binder = new Default_Value_Binder();
        }
        return self::$value_binder;
    }
    /**
     * Set value binder to use.
     */
    public static function set_value_binder(I_Value_Binder $binder): void
    {
        self::$value_binder = $binder;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $property_name => $property_value) {
            if (is_object($property_value) && $property_name !== 'parent') {
                $this->{$property_name} = clone $property_value;
            } else {
                $this->{$property_name} = $property_value;
            }
        }
    }
    /**
     * Get index to cellXf.
     */
    public function get_xf_index(): int
    {
        return $this->xf_index;
    }
    /**
     * Set index to cellXf.
     */
    public function set_xf_index(int $index_value): self
    {
        $this->xf_index = $index_value;
        return $this->update_in_collection();
    }
    /**
     * Set the formula attributes.
     *
     * @param null|array<string, string> $attributes
     */
    public function set_formula_attributes(?array $attributes): self
    {
        $this->formula_attributes = $attributes;
        return $this;
    }
    /**
     * Get the formula attributes.
     *
     * @return null|array<string, string>
     */
    public function get_formula_attributes(): mixed
    {
        return $this->formula_attributes;
    }
    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        $ret_val = $this->value;
        return String_Helper::convert_to_string($ret_val, false);
    }
    public function get_ignored_errors(): Ignored_Errors
    {
        return $this->ignored_errors;
    }
    public function is_locked(): bool
    {
        $protected = $this->parent?->get_parent()?->get_protection()?->get_sheet();
        if ($protected !== true) {
            return false;
        }
        $locked = $this->get_style()->get_protection()->get_locked();
        return $locked !== Protection::PROTECTION_UNPROTECTED;
    }
    public function is_hidden_on_formula_bar(): bool
    {
        if ($this->get_data_type() !== Data_Type::TYPE_FORMULA) {
            return false;
        }
        $protected = $this->parent?->get_parent()?->get_protection()?->get_sheet();
        if ($protected !== true) {
            return false;
        }
        $hidden = $this->get_style()->get_protection()->get_hidden();
        return $hidden !== Protection::PROTECTION_UNPROTECTED;
    }
}