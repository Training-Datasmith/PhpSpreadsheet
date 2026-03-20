<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

use Composer\Pcre\Preg;
// many pregs in this program use u modifier, which has side-effects which make it unsuitable for this
use Php_Office\Php_Spreadsheet\Calculation\Engine\Branch_Pruner;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Cyclic_Reference_Stack;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Logger;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Operands;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Token\Stack;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Named_Range;
use Php_Office\Php_Spreadsheet\Reference_Helper;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Reflection_Class_Constant;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;
use TypeError;
class Calculation extends Calculation_Locale
{
    /** Constants                */
    /** Regular Expressions        */
    //    Numeric operand
    public const CALCULATION_REGEXP_NUMBER = '[-+]?\d*\.?\d+(e[-+]?\d+)?';
    //    String operand
    public const CALCULATION_REGEXP_STRING = '"(?:[^"]|"")*"';
    //    Opening bracket
    public const CALCULATION_REGEXP_OPENBRACE = '\(';
    //    Function (allow for the old @ symbol that could be used to prefix a function, but we'll ignore it)
    public const CALCULATION_REGEXP_FUNCTION = '@?(?:_xlfn\.)?(?:_xlws\.)?((?:__xludf\.)?[\p{L}][\p{L}\p{N}\.]*)[\s]*\(';
    //    Cell reference, with or without a sheet reference)
    public const CALCULATION_REGEXP_CELLREF = '((([^\s,!&%^\/\*\+<>=:`-]*)|(\'(?:[^\']|\'[^!])+?\')|(\"(?:[^\"]|\"[^!])+?\"))!)?\$?\b([a-z]{1,3})\$?(\d{1,7})(?![\w.])';
    // Used only to detect spill operator #
    public const CALCULATION_REGEXP_CELLREF_SPILL = '/' . self::CALCULATION_REGEXP_CELLREF . '#/i';
    //    Cell reference (with or without a sheet reference) ensuring absolute/relative
    public const CALCULATION_REGEXP_CELLREF_RELATIVE = '((([^\s\(,!&%^\/\*\+<>=:`-]*)|(\'(?:[^\']|\'[^!])+?\')|(\"(?:[^\"]|\"[^!])+?\"))!)?(\$?\b[a-z]{1,3})(\$?\d{1,7})(?![\w.])';
    public const CALCULATION_REGEXP_COLUMN_RANGE = '(((([^\s\(,!&%^\/\*\+<>=:`-]*)|(\'(?:[^\']|\'[^!])+?\')|(\".(?:[^\"]|\"[^!])?\"))!)?(\$?[a-z]{1,3})):(?![.*])';
    public const CALCULATION_REGEXP_ROW_RANGE = '(((([^\s\(,!&%^\/\*\+<>=:`-]*)|(\'(?:[^\']|\'[^!])+?\')|(\"(?:[^\"]|\"[^!])+?\"))!)?(\$?[1-9][0-9]{0,6})):(?![.*])';
    //    Cell reference (with or without a sheet reference) ensuring absolute/relative
    //    Cell ranges ensuring absolute/relative
    public const CALCULATION_REGEXP_COLUMNRANGE_RELATIVE = '(\$?[a-z]{1,3}):(\$?[a-z]{1,3})';
    public const CALCULATION_REGEXP_ROWRANGE_RELATIVE = '(\$?\d{1,7}):(\$?\d{1,7})';
    //    Defined Names: Named Range of cells, or Named Formulae
    public const CALCULATION_REGEXP_DEFINEDNAME = '((([^\s,!&%^\/\*\+<>=-]*)|(\'(?:[^\']|\'[^!])+?\')|(\"(?:[^\"]|\"[^!])+?\"))!)?([_\p{L}][_\p{L}\p{N}\.]*)';
    // Structured Reference (Fully Qualified and Unqualified)
    public const CALCULATION_REGEXP_STRUCTURED_REFERENCE = '([\p{L}_\\\\][\p{L}\p{N}\._]+)?(\[(?:[^\d\]+-])?)';
    //    Error
    public const CALCULATION_REGEXP_ERROR = '\#[A-Z][A-Z0_\/]*[!\?]?';
    /** constants */
    public const RETURN_ARRAY_AS_ERROR = 'error';
    public const RETURN_ARRAY_AS_VALUE = 'value';
    public const RETURN_ARRAY_AS_ARRAY = 'array';
    /** Preferable to use instance variable instanceArrayReturnType rather than this static property. */
    private static string $return_array_as_type = self::RETURN_ARRAY_AS_VALUE;
    /** Preferable to use this instance variable rather than static returnArrayAsType */
    private ?string $instance_array_return_type = null;
    /**
     * Instance of this class.
     */
    private static ?Calculation $instance = null;
    /**
     * Calculation cache.
     *
     * @var mixed[]
     */
    private array $calculation_cache = [];
    /**
     * Calculation cache enabled.
     */
    private bool $calculation_cache_enabled = true;
    private Branch_Pruner $branch_pruner;
    protected bool $branch_pruning_enabled = true;
    /**
     * List of operators that can be used within formulae
     * The true/false value indicates whether it is a binary operator or a unary operator.
     */
    private const CALCULATION_OPERATORS = ['+' => true, '-' => true, '*' => true, '/' => true, '^' => true, '&' => true, '%' => false, '~' => false, '>' => true, '<' => true, '=' => true, '>=' => true, '<=' => true, '<>' => true, '∩' => true, '∪' => true, ':' => true];
    /**
     * List of binary operators (those that expect two operands).
     */
    private const BINARY_OPERATORS = ['+' => true, '-' => true, '*' => true, '/' => true, '^' => true, '&' => true, '>' => true, '<' => true, '=' => true, '>=' => true, '<=' => true, '<>' => true, '∩' => true, '∪' => true, ':' => true];
    /**
     * The debug log generated by the calculation engine.
     */
    private readonly Logger $debug_log;
    private bool $suppress_formula_errors = false;
    private bool $processing_anchor_array = false;
    /**
     * Error message for any error that was raised/thrown by the calculation engine.
     */
    public ?string $formula_error = null;
    /**
     * An array of the nested cell references accessed by the calculation engine, used for the debug log.
     */
    private readonly Cyclic_Reference_Stack $cyclic_reference_stack;
    /** @var mixed[] */
    private array $cell_stack = [];
    /**
     * Current iteration counter for cyclic formulae
     * If the value is 0 (or less) then cyclic formulae will throw an exception,
     * otherwise they will iterate to the limit defined here before returning a result.
     */
    private int $cyclic_formula_counter = 1;
    private string $cyclic_formula_cell = '';
    /**
     * Number of iterations for cyclic formulae.
     */
    public int $cyclic_formula_count = 1;
    /**
     * Excel constant string translations to their PHP equivalents
     * Constant conversion from text name/value to actual (datatyped) value.
     */
    private const EXCEL_CONSTANTS = ['TRUE' => true, 'FALSE' => false, 'NULL' => null];
    public static function key_in_excel_constants(string $key): bool
    {
        return array_key_exists($key, self::EXCEL_CONSTANTS);
    }
    public static function get_excel_constants(string $key): bool|null
    {
        return self::EXCEL_CONSTANTS[$key];
    }
    /**
     *    Internal functions used for special control purposes.
     *
     * @var array<string, array<string, array<string>|string>>
     */
    private static array $control_functions = ['MKMATRIX' => ['argumentCount' => '*', 'functionCall' => [Internal\Make_Matrix::class, 'make']], 'NAME.ERROR' => ['argumentCount' => '*', 'functionCall' => [Excel_Error::class, 'NAME']], 'WILDCARDMATCH' => ['argumentCount' => '2', 'functionCall' => [Internal\Wildcard_Match::class, 'compare']]];
    public function __construct(
        /**
         * Instance of the spreadsheet this Calculation Engine is using.
         */
        private readonly ?Spreadsheet $spreadsheet = null
    )
    {
        $this->cyclic_reference_stack = new Cyclic_Reference_Stack();
        $this->debug_log = new Logger($this->cyclic_reference_stack);
        $this->branch_pruner = new Branch_Pruner($this->branch_pruning_enabled);
    }
    /**
     * Get an instance of this class.
     *
     * @param ?Spreadsheet $spreadsheet Injected spreadsheet for working with a PhpSpreadsheet Spreadsheet object,
     *                                    or NULL to create a standalone calculation engine
     */
    public static function get_instance(?Spreadsheet $spreadsheet = null): self
    {
        if ($spreadsheet !== null) {
            return $spreadsheet->get_calculation_engine();
        }
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Intended for use only via a destructor.
     *
     * @internal
     */
    public static function get_instance_or_null(?Spreadsheet $spreadsheet = null): ?self
    {
        if ($spreadsheet !== null) {
            return $spreadsheet->get_calculation_engine_or_null();
        }
        return null;
    }
    /**
     * Flush the calculation cache for any existing instance of this class
     *        but only if a Calculation instance exists.
     */
    public function flush_instance(): void
    {
        $this->clear_calculation_cache();
        $this->branch_pruner->clear_branch_store();
    }
    /**
     * Get the Logger for this calculation engine instance.
     */
    public function get_debug_log(): Logger
    {
        return $this->debug_log;
    }
    /**
     * __clone implementation. Cloning should not be allowed in a Singleton!
     */
    final public function __clone()
    {
        throw new Exception('Cloning the calculation engine is not allowed!');
    }
    /**
     * Set the Array Return Type (Array or Value of first element in the array).
     *
     * @param string $returnType Array return type
     *
     * @return bool Success or failure
     */
    public static function set_array_return_type(string $return_type): bool
    {
        if ($return_type == self::RETURN_ARRAY_AS_VALUE || $return_type == self::RETURN_ARRAY_AS_ERROR || $return_type == self::RETURN_ARRAY_AS_ARRAY) {
            self::$return_array_as_type = $return_type;
            return true;
        }
        return false;
    }
    /**
     * Return the Array Return Type (Array or Value of first element in the array).
     *
     * @return string $returnType Array return type
     */
    public static function get_array_return_type(): string
    {
        return self::$return_array_as_type;
    }
    /**
     * Set the Instance Array Return Type (Array or Value of first element in the array).
     *
     * @param string $returnType Array return type
     *
     * @return bool Success or failure
     */
    public function set_instance_array_return_type(string $return_type): bool
    {
        if ($return_type == self::RETURN_ARRAY_AS_VALUE || $return_type == self::RETURN_ARRAY_AS_ERROR || $return_type == self::RETURN_ARRAY_AS_ARRAY) {
            $this->instance_array_return_type = $return_type;
            return true;
        }
        return false;
    }
    /**
     * Return the Array Return Type (Array or Value of first element in the array).
     *
     * @return string $returnType Array return type for instance if non-null, otherwise static property
     */
    public function get_instance_array_return_type(): string
    {
        return $this->instance_array_return_type ?? self::$return_array_as_type;
    }
    /**
     * Is calculation caching enabled?
     */
    public function get_calculation_cache_enabled(): bool
    {
        return $this->calculation_cache_enabled;
    }
    /**
     * Enable/disable calculation cache.
     */
    public function set_calculation_cache_enabled(bool $calculation_cache_enabled): self
    {
        $this->calculation_cache_enabled = $calculation_cache_enabled;
        $this->clear_calculation_cache();
        return $this;
    }
    /**
     * Enable calculation cache.
     */
    public function enable_calculation_cache(): void
    {
        $this->set_calculation_cache_enabled(true);
    }
    /**
     * Disable calculation cache.
     */
    public function disable_calculation_cache(): void
    {
        $this->set_calculation_cache_enabled(false);
    }
    /**
     * Clear calculation cache.
     */
    public function clear_calculation_cache(): void
    {
        $this->calculation_cache = [];
    }
    /**
     * Clear calculation cache for a specified worksheet.
     */
    public function clear_calculation_cache_for_worksheet(string $worksheet_name): void
    {
        if (isset($this->calculation_cache[$worksheet_name])) {
            unset($this->calculation_cache[$worksheet_name]);
        }
    }
    /**
     * Rename calculation cache for a specified worksheet.
     */
    public function rename_calculation_cache_for_worksheet(string $from_worksheet_name, string $to_worksheet_name): void
    {
        if (isset($this->calculation_cache[$from_worksheet_name])) {
            $this->calculation_cache[$to_worksheet_name] =& $this->calculation_cache[$from_worksheet_name];
            unset($this->calculation_cache[$from_worksheet_name]);
        }
    }
    public function get_branch_pruning_enabled(): bool
    {
        return $this->branch_pruning_enabled;
    }
    public function set_branch_pruning_enabled(mixed $enabled): self
    {
        $this->branch_pruning_enabled = (bool) $enabled;
        $this->branch_pruner = new Branch_Pruner($this->branch_pruning_enabled);
        return $this;
    }
    public function enable_branch_pruning(): void
    {
        $this->set_branch_pruning_enabled(true);
    }
    public function disable_branch_pruning(): void
    {
        $this->set_branch_pruning_enabled(false);
    }
    /**
     * Wrap string values in quotes.
     */
    public static function wrap_result(mixed $value): mixed
    {
        if (is_string($value)) {
            //    Error values cannot be "wrapped"
            if (Preg::is_match('/^' . self::CALCULATION_REGEXP_ERROR . '$/i', $value, $match)) {
                //    Return Excel errors "as is"
                return $value;
            }
            //    Return strings wrapped in quotes
            return self::FORMULA_STRING_QUOTE . $value . self::FORMULA_STRING_QUOTE;
        }
        if (is_float($value) && (is_nan($value) || is_infinite($value))) {
            //    Convert numeric errors to NaN error
            return Excel_Error::NAN();
        }
        return $value;
    }
    /**
     * Remove quotes used as a wrapper to identify string values.
     */
    public static function unwrap_result(mixed $value): mixed
    {
        if (is_string($value)) {
            if (isset($value[0]) && $value[0] == self::FORMULA_STRING_QUOTE && substr($value, -1) == self::FORMULA_STRING_QUOTE) {
                return substr($value, 1, -1);
            }
            //    Convert numeric errors to NAN error
        } elseif (is_float($value) && (is_nan($value) || is_infinite($value))) {
            return Excel_Error::NAN();
        }
        return $value;
    }
    /**
     * Calculate cell value (using formula from a cell ID)
     * Retained for backward compatibility.
     *
     * @param ?Cell $cell Cell to calculate
     */
    public function calculate(?Cell $cell = null): mixed
    {
        try {
            return $this->calculate_cell_value($cell);
        } catch (\Exception $e) {
            throw new Exception($e->get_message());
        }
    }
    /**
     * Calculate the value of a cell formula.
     *
     * @param ?Cell $cell Cell to calculate
     * @param bool $resetLog Flag indicating whether the debug log should be reset or not
     */
    public function calculate_cell_value(?Cell $cell = null, bool $reset_log = true): mixed
    {
        if ($cell === null) {
            return null;
        }
        if ($reset_log) {
            //    Initialise the logging settings if requested
            $this->formula_error = null;
            $this->debug_log->clear_log();
            $this->cyclic_reference_stack->clear();
            $this->cyclic_formula_counter = 1;
        }
        //    Execute the calculation for the cell formula
        $this->cell_stack[] = ['sheet' => $cell->get_worksheet()->get_title(), 'cell' => $cell->get_coordinate()];
        $cell_address_attempted = false;
        $cell_address = null;
        try {
            $value = $cell->get_value();
            if (is_string($value) && $cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                $value = Preg::replace_callback(self::CALCULATION_REGEXP_CELLREF_SPILL, fn(array $matches): string => 'ANCHORARRAY(' . substr((string) $matches[0], 0, -1) . ')', $value);
            }
            $result = self::unwrap_result($this->_calculate_formula_value($value, $cell->get_coordinate(), $cell));
            //* @phpstan-ignore-line
            if ($this->spreadsheet === null) {
                throw new Exception('null spreadsheet in calculateCellValue');
            }
            $cell_address_attempted = true;
            $cell_address = array_pop($this->cell_stack);
            if ($cell_address === null) {
                throw new Exception('null cellAddress in calculateCellValue');
            }
            /** @var array{sheet: string, cell: string} $cellAddress */
            $test_sheet = $this->spreadsheet->get_sheet_by_name($cell_address['sheet']);
            if ($test_sheet === null) {
                throw new Exception('worksheet not found in calculateCellValue');
            }
            $test_sheet->get_cell($cell_address['cell']);
        } catch (\Exception $e) {
            if (!$cell_address_attempted) {
                $cell_address = array_pop($this->cell_stack);
            }
            if ($this->spreadsheet !== null && is_array($cell_address) && array_key_exists('sheet', $cell_address)) {
                $sheet_name = $cell_address['sheet'] ?? null;
                $test_sheet = is_string($sheet_name) ? $this->spreadsheet->get_sheet_by_name($sheet_name) : null;
                if ($test_sheet !== null && array_key_exists('cell', $cell_address)) {
                    /** @var array{cell: string} $cellAddress */
                    $test_sheet->get_cell($cell_address['cell']);
                }
            }
            throw new Exception($e->get_message(), $e->get_code(), $e);
        }
        if (is_array($result) && $this->get_instance_array_return_type() !== self::RETURN_ARRAY_AS_ARRAY) {
            $test_result = Functions::flatten_array($result);
            if ($this->get_instance_array_return_type() == self::RETURN_ARRAY_AS_ERROR) {
                return Excel_Error::VALUE();
            }
            $result = array_shift($test_result);
        }
        if ($result === null && $cell->get_worksheet()->get_sheet_view()->get_show_zeros()) {
            return 0;
        }
        if (is_float($result) && (is_nan($result) || is_infinite($result))) {
            return Excel_Error::NAN();
        }
        return $result;
    }
    /**
     * Validate and parse a formula string.
     *
     * @param string $formula Formula to parse
     *
     * @return array<mixed>|bool
     */
    public function parse_formula(string $formula): array|bool
    {
        $formula = Preg::replace_callback(self::CALCULATION_REGEXP_CELLREF_SPILL, fn(array $matches): string => 'ANCHORARRAY(' . substr((string) $matches[0], 0, -1) . ')', $formula);
        //    Basic validation that this is indeed a formula
        //    We return an empty array if not
        $formula = trim($formula);
        if (!isset($formula[0]) || $formula[0] != '=') {
            return [];
        }
        $formula = ltrim(substr($formula, 1));
        if (!isset($formula[0])) {
            return [];
        }
        //    Parse the formula and return the token stack
        return $this->internal_parse_formula($formula);
    }
    /**
     * Calculate the value of a formula.
     *
     * @param string $formula Formula to parse
     * @param ?string $cellID Address of the cell to calculate
     * @param ?Cell $cell Cell to calculate
     */
    public function calculate_formula(string $formula, ?string $cell_id = null, ?Cell $cell = null): mixed
    {
        //    Initialise the logging settings
        $this->formula_error = null;
        $this->debug_log->clear_log();
        $this->cyclic_reference_stack->clear();
        $reset_cache = $this->get_calculation_cache_enabled();
        if ($this->spreadsheet !== null && $cell_id === null && $cell === null) {
            $cell_id = 'A1';
            $cell = $this->spreadsheet->get_active_sheet()->get_cell($cell_id);
        } else {
            //    Disable calculation cacheing because it only applies to cell calculations, not straight formulae
            //    But don't actually flush any cache
            $this->calculation_cache_enabled = false;
        }
        //    Execute the calculation
        try {
            $result = self::unwrap_result($this->_calculate_formula_value($formula, $cell_id, $cell));
        } catch (\Exception $e) {
            throw new Exception($e->get_message());
        }
        if ($this->spreadsheet === null) {
            //    Reset calculation cacheing to its previous state
            $this->calculation_cache_enabled = $reset_cache;
        }
        return $result;
    }
    public function get_value_from_cache(string $cell_reference, mixed &$cell_value): bool
    {
        $this->debug_log->write_debug_log('Testing cache value for cell %s', $cell_reference);
        // Is calculation cacheing enabled?
        // If so, is the required value present in calculation cache?
        if ($this->calculation_cache_enabled && isset($this->calculation_cache[$cell_reference])) {
            $this->debug_log->write_debug_log('Retrieving value for cell %s from cache', $cell_reference);
            // Return the cached result
            $cell_value = $this->calculation_cache[$cell_reference];
            return true;
        }
        return false;
    }
    public function save_value_to_cache(string $cell_reference, mixed $cell_value): void
    {
        if ($this->calculation_cache_enabled) {
            $this->calculation_cache[$cell_reference] = $cell_value;
        }
    }
    /**
     * Parse a cell formula and calculate its value.
     *
     * @param string $formula The formula to parse and calculate
     * @param ?string $cellID The ID (e.g. A3) of the cell that we are calculating
     * @param ?Cell $cell Cell to calculate
     * @param bool $ignoreQuotePrefix If set to true, evaluate the formyla even if the referenced cell is quote prefixed
     */
    public function _calculate_formula_value(string $formula, ?string $cell_id = null, ?Cell $cell = null, bool $ignore_quote_prefix = false): mixed
    {
        $cell_value = null;
        //  Quote-Prefixed cell values cannot be formulae, but are treated as strings
        if ($cell !== null && $ignore_quote_prefix === false && $cell->get_style()->get_quote_prefix() === true) {
            return self::wrap_result($formula);
        }
        // https://www.reddit.com/r/excel/comments/chr41y/cmd_formula_stopped_working_since_last_update/
        if (preg_match('/^=\s*cmd\s*\|/miu', $formula) !== 0) {
            return Excel_Error::REF();
            // returns #BLOCKED in newer versions
        }
        //    Basic validation that this is indeed a formula
        //    We simply return the cell value if not
        $formula = trim($formula);
        if ($formula === '' || $formula[0] !== '=') {
            return self::wrap_result($formula);
        }
        $formula = ltrim(substr($formula, 1));
        if (!isset($formula[0])) {
            return self::wrap_result($formula);
        }
        $p_cell_parent = $cell !== null ? $cell->get_worksheet() : null;
        $ws_title = $p_cell_parent !== null ? $p_cell_parent->get_title() : "\x00Wrk";
        $ws_cell_reference = $ws_title . '!' . $cell_id;
        if ($cell_id !== null && $this->get_value_from_cache($ws_cell_reference, $cell_value)) {
            return $cell_value;
        }
        $this->debug_log->write_debug_log('Evaluating formula for cell %s', $ws_cell_reference);
        if ($ws_title[0] !== "\x00" && $this->cyclic_reference_stack->on_stack($ws_cell_reference)) {
            if ($this->cyclic_formula_count <= 0) {
                $this->cyclic_formula_cell = '';
                return $this->raise_formula_error('Cyclic Reference in Formula');
            }
            if ($this->cyclic_formula_cell === $ws_cell_reference) {
                ++$this->cyclic_formula_counter;
                if ($this->cyclic_formula_counter >= $this->cyclic_formula_count) {
                    $this->cyclic_formula_cell = '';
                    return $cell_value;
                }
            } elseif ($this->cyclic_formula_cell == '') {
                if ($this->cyclic_formula_counter >= $this->cyclic_formula_count) {
                    return $cell_value;
                }
                $this->cyclic_formula_cell = $ws_cell_reference;
            }
        }
        $this->debug_log->write_debug_log('Formula for cell %s is %s', $ws_cell_reference, $formula);
        //    Parse the formula onto the token stack and calculate the value
        $this->cyclic_reference_stack->push($ws_cell_reference);
        $cell_value = $this->process_token_stack($this->internal_parse_formula($formula, $cell), $cell_id, $cell);
        $this->cyclic_reference_stack->pop();
        // Save to calculation cache
        if ($cell_id !== null) {
            $this->save_value_to_cache($ws_cell_reference, $cell_value);
        }
        //    Return the calculated value
        return $cell_value;
    }
    /**
     * Ensure that paired matrix operands are both matrices and of the same size.
     *
     * @param mixed $operand1 First matrix operand
     *
     * @param-out mixed[] $operand1
     *
     * @param mixed $operand2 Second matrix operand
     *
     * @param-out mixed[] $operand2
     *
     * @param int $resize Flag indicating whether the matrices should be resized to match
     *                                        and (if so), whether the smaller dimension should grow or the
     *                                        larger should shrink.
     *                                            0 = no resize
     *                                            1 = shrink to fit
     *                                            2 = extend to fit
     *
     * @return mixed[]
     */
    public static function check_matrix_operands(mixed &$operand1, mixed &$operand2, int $resize = 1): array
    {
        //    Examine each of the two operands, and turn them into an array if they aren't one already
        //    Note that this function should only be called if one or both of the operand is already an array
        if (!is_array($operand1)) {
            if (is_array($operand2)) {
                [$matrix_rows, $matrix_columns] = self::get_matrix_dimensions($operand2);
                $operand1 = array_fill(0, $matrix_rows, array_fill(0, $matrix_columns, $operand1));
                $resize = 0;
            } else {
                $operand1 = [$operand1];
                $operand2 = [$operand2];
            }
        } elseif (!is_array($operand2)) {
            [$matrix_rows, $matrix_columns] = self::get_matrix_dimensions($operand1);
            $operand2 = array_fill(0, $matrix_rows, array_fill(0, $matrix_columns, $operand2));
            $resize = 0;
        }
        [$matrix1Rows, $matrix1Columns] = self::get_matrix_dimensions($operand1);
        [$matrix2Rows, $matrix2Columns] = self::get_matrix_dimensions($operand2);
        if ($resize === 3) {
            $resize = 2;
        } elseif ($matrix1Rows == $matrix2Columns && $matrix2Rows == $matrix1Columns) {
            $resize = 1;
        }
        if ($resize == 2) {
            //    Given two matrices of (potentially) unequal size, convert the smaller in each dimension to match the larger
            self::resize_matrices_extend($operand1, $operand2, $matrix1Rows, $matrix1Columns, $matrix2Rows, $matrix2Columns);
        } elseif ($resize == 1) {
            //    Given two matrices of (potentially) unequal size, convert the larger in each dimension to match the smaller
            /** @var mixed[][] $operand1 */
            /** @var mixed[][] $operand2 */
            self::resize_matrices_shrink($operand1, $operand2, $matrix1Rows, $matrix1Columns, $matrix2Rows, $matrix2Columns);
        }
        [$matrix1Rows, $matrix1Columns] = self::get_matrix_dimensions($operand1);
        [$matrix2Rows, $matrix2Columns] = self::get_matrix_dimensions($operand2);
        return [$matrix1Rows, $matrix1Columns, $matrix2Rows, $matrix2Columns];
    }
    /**
     * Read the dimensions of a matrix, and re-index it with straight numeric keys starting from row 0, column 0.
     *
     * @param mixed[] $matrix matrix operand
     *
     * @return int[] An array comprising the number of rows, and number of columns
     */
    public static function get_matrix_dimensions(array &$matrix): array
    {
        $matrix_rows = count($matrix);
        $matrix_columns = 0;
        foreach ($matrix as $row_key => $row_value) {
            if (!is_array($row_value)) {
                $matrix[$row_key] = [$row_value];
                $matrix_columns = max(1, $matrix_columns);
            } else {
                $matrix[$row_key] = array_values($row_value);
                $matrix_columns = max(count($row_value), $matrix_columns);
            }
        }
        $matrix = array_values($matrix);
        return [$matrix_rows, $matrix_columns];
    }
    /**
     * Ensure that paired matrix operands are both matrices of the same size.
     *
     * @param mixed[][] $matrix1 First matrix operand
     * @param mixed[][] $matrix2 Second matrix operand
     * @param int $matrix1Rows Row size of first matrix operand
     * @param int $matrix1Columns Column size of first matrix operand
     * @param int $matrix2Rows Row size of second matrix operand
     * @param int $matrix2Columns Column size of second matrix operand
     */
    private static function resize_matrices_shrink(array &$matrix1, array &$matrix2, int $matrix1Rows, int $matrix1Columns, int $matrix2Rows, int $matrix2Columns): void
    {
        if ($matrix2Columns < $matrix1Columns || $matrix2Rows < $matrix1Rows) {
            if ($matrix2Rows < $matrix1Rows) {
                for ($i = $matrix2Rows; $i < $matrix1Rows; ++$i) {
                    unset($matrix1[$i]);
                }
            }
            if ($matrix2Columns < $matrix1Columns) {
                for ($i = 0; $i < $matrix1Rows; ++$i) {
                    for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
                        unset($matrix1[$i][$j]);
                    }
                }
            }
        }
        if ($matrix1Columns < $matrix2Columns || $matrix1Rows < $matrix2Rows) {
            if ($matrix1Rows < $matrix2Rows) {
                for ($i = $matrix1Rows; $i < $matrix2Rows; ++$i) {
                    unset($matrix2[$i]);
                }
            }
            if ($matrix1Columns < $matrix2Columns) {
                for ($i = 0; $i < $matrix2Rows; ++$i) {
                    for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
                        unset($matrix2[$i][$j]);
                    }
                }
            }
        }
    }
    /**
     * Ensure that paired matrix operands are both matrices of the same size.
     *
     * @param mixed[] $matrix1 First matrix operand
     * @param mixed[] $matrix2 Second matrix operand
     * @param int $matrix1Rows Row size of first matrix operand
     * @param int $matrix1Columns Column size of first matrix operand
     * @param int $matrix2Rows Row size of second matrix operand
     * @param int $matrix2Columns Column size of second matrix operand
     */
    private static function resize_matrices_extend(array &$matrix1, array &$matrix2, int $matrix1Rows, int $matrix1Columns, int $matrix2Rows, int $matrix2Columns): void
    {
        if ($matrix2Columns < $matrix1Columns || $matrix2Rows < $matrix1Rows) {
            if ($matrix2Columns < $matrix1Columns) {
                for ($i = 0; $i < $matrix2Rows; ++$i) {
                    /** @var mixed[][] $matrix2 */
                    $x = $matrix2Columns === 1 ? $matrix2[$i][0] : null;
                    for ($j = $matrix2Columns; $j < $matrix1Columns; ++$j) {
                        $matrix2[$i][$j] = $x;
                    }
                }
            }
            if ($matrix2Rows < $matrix1Rows) {
                $x = $matrix2Rows === 1 ? $matrix2[0] : array_fill(0, $matrix2Columns, null);
                for ($i = $matrix2Rows; $i < $matrix1Rows; ++$i) {
                    $matrix2[$i] = $x;
                }
            }
        }
        if ($matrix1Columns < $matrix2Columns || $matrix1Rows < $matrix2Rows) {
            if ($matrix1Columns < $matrix2Columns) {
                for ($i = 0; $i < $matrix1Rows; ++$i) {
                    /** @var mixed[][] $matrix1 */
                    $x = $matrix1Columns === 1 ? $matrix1[$i][0] : null;
                    for ($j = $matrix1Columns; $j < $matrix2Columns; ++$j) {
                        $matrix1[$i][$j] = $x;
                    }
                }
            }
            if ($matrix1Rows < $matrix2Rows) {
                $x = $matrix1Rows === 1 ? $matrix1[0] : array_fill(0, $matrix2Columns, null);
                for ($i = $matrix1Rows; $i < $matrix2Rows; ++$i) {
                    $matrix1[$i] = $x;
                }
            }
        }
    }
    /**
     * Format details of an operand for display in the log (based on operand type).
     *
     * @param mixed $value First matrix operand
     */
    private function show_value(mixed $value): mixed
    {
        if ($this->debug_log->get_write_debug_log()) {
            $test_array = Functions::flatten_array($value);
            if (count($test_array) == 1) {
                $value = array_pop($test_array);
            }
            if (is_array($value)) {
                $return_matrix = [];
                $pad = $rpad = ', ';
                foreach ($value as $row) {
                    if (is_array($row)) {
                        $return_matrix[] = implode($pad, array_map($this->show_value(...), $row));
                        $rpad = '; ';
                    } else {
                        $return_matrix[] = $this->show_value($row);
                    }
                }
                return '{ ' . implode($rpad, $return_matrix) . ' }';
            }
            if (is_string($value) && trim($value, self::FORMULA_STRING_QUOTE) == $value) {
                return self::FORMULA_STRING_QUOTE . $value . self::FORMULA_STRING_QUOTE;
            }
            if (is_bool($value)) {
                return $value ? self::$locale_boolean['TRUE'] : self::$locale_boolean['FALSE'];
            }
            if ($value === null) {
                return self::$locale_boolean['NULL'];
            }
        }
        return Functions::flatten_single_value($value);
    }
    /**
     * Format type and details of an operand for display in the log (based on operand type).
     *
     * @param mixed $value First matrix operand
     */
    private function show_type_details(mixed $value): ?string
    {
        if ($this->debug_log->get_write_debug_log()) {
            $test_array = Functions::flatten_array($value);
            if (count($test_array) == 1) {
                $value = array_pop($test_array);
            }
            if ($value === null) {
                return 'a NULL value';
            }
            if (is_float($value)) {
                $type_string = 'a floating point number';
            } elseif (is_int($value)) {
                $type_string = 'an integer number';
            } elseif (is_bool($value)) {
                $type_string = 'a boolean';
            } elseif (is_array($value)) {
                $type_string = 'a matrix';
            } else {
                /** @var string $value */
                if ($value == '') {
                    return 'an empty string';
                }
                /** @var string $value */
                if ($value[0] == '#') {
                    return 'a ' . $value . ' error';
                }
                $type_string = 'a string';
            }
            return $type_string . ' with a value of ' . String_Helper::convert_to_string($this->show_value($value));
        }
        return null;
    }
    private const MATRIX_REPLACE_FROM = [self::FORMULA_OPEN_MATRIX_BRACE, ';', self::FORMULA_CLOSE_MATRIX_BRACE];
    private const MATRIX_REPLACE_TO = ['MKMATRIX(MKMATRIX(', '),MKMATRIX(', '))'];
    /**
     * @return false|string False indicates an error
     */
    private function convert_matrix_references(string $formula): false|string
    {
        //    Convert any Excel matrix references to the MKMATRIX() function
        if (str_contains($formula, self::FORMULA_OPEN_MATRIX_BRACE)) {
            //    If there is the possibility of braces within a quoted string, then we don't treat those as matrix indicators
            if (str_contains($formula, self::FORMULA_STRING_QUOTE)) {
                //    So instead we skip replacing in any quoted strings by only replacing in every other array element after we've exploded
                //        the formula
                $temp = explode(self::FORMULA_STRING_QUOTE, $formula);
                //    Open and Closed counts used for trapping mismatched braces in the formula
                $open_count = $close_count = 0;
                $not_within_quotes = false;
                foreach ($temp as &$value) {
                    //    Only count/replace in alternating array entries
                    $not_within_quotes = $not_within_quotes === false;
                    if ($not_within_quotes === true) {
                        $open_count += substr_count($value, self::FORMULA_OPEN_MATRIX_BRACE);
                        $close_count += substr_count($value, self::FORMULA_CLOSE_MATRIX_BRACE);
                        $value = str_replace(self::MATRIX_REPLACE_FROM, self::MATRIX_REPLACE_TO, $value);
                    }
                }
                unset($value);
                //    Then rebuild the formula string
                $formula = implode(self::FORMULA_STRING_QUOTE, $temp);
            } else {
                //    If there's no quoted strings, then we do a simple count/replace
                $open_count = substr_count($formula, self::FORMULA_OPEN_MATRIX_BRACE);
                $close_count = substr_count($formula, self::FORMULA_CLOSE_MATRIX_BRACE);
                $formula = str_replace(self::MATRIX_REPLACE_FROM, self::MATRIX_REPLACE_TO, $formula);
            }
            //    Trap for mismatched braces and trigger an appropriate error
            if ($open_count < $close_count) {
                if ($open_count > 0) {
                    return $this->raise_formula_error("Formula Error: Mismatched matrix braces '}'");
                }
                return $this->raise_formula_error("Formula Error: Unexpected '}' encountered");
            }
            //    Trap for mismatched braces and trigger an appropriate error
            if ($open_count > $close_count) {
                if ($close_count > 0) {
                    return $this->raise_formula_error("Formula Error: Mismatched matrix braces '{'");
                }
                return $this->raise_formula_error("Formula Error: Unexpected '{' encountered");
            }
        }
        return $formula;
    }
    /**
     *    Comparison (Boolean) Operators.
     *    These operators work on two values, but always return a boolean result.
     */
    private const COMPARISON_OPERATORS = ['>' => true, '<' => true, '=' => true, '>=' => true, '<=' => true, '<>' => true];
    /**
     *    Operator Precedence.
     *    This list includes all valid operators, whether binary (including boolean) or unary (such as %).
     *    Array key is the operator, the value is its precedence.
     */
    private const OPERATOR_PRECEDENCE = [
        ':' => 9,
        //    Range
        '∩' => 8,
        //    Intersect
        '∪' => 7,
        //    Union
        '~' => 6,
        //    Negation
        '%' => 5,
        //    Percentage
        '^' => 4,
        //    Exponentiation
        '*' => 3,
        '/' => 3,
        //    Multiplication and Division
        '+' => 2,
        '-' => 2,
        //    Addition and Subtraction
        '&' => 1,
        //    Concatenation
        '>' => 0,
        '<' => 0,
        '=' => 0,
        '>=' => 0,
        '<=' => 0,
        '<>' => 0,
    ];
    /** @param string[] $matches */
    private static function union_for_comma(array $matches): string
    {
        return $matches[1] . str_replace(',', '∪', $matches[2]);
    }
    private const CELL_OR_CELLRANGE_OR_DEFINED_NAME = '(?:' . self::CALCULATION_REGEXP_CELLREF . '(?::' . self::CALCULATION_REGEXP_CELLREF . ')?' . '|' . self::CALCULATION_REGEXP_DEFINEDNAME . ')';
    public const UNIONABLE_COMMAS = '/((?:[,(]|^)\s*)' . '([(]' . self::CELL_OR_CELLRANGE_OR_DEFINED_NAME . '(?:\s*,\s*' . self::CELL_OR_CELLRANGE_OR_DEFINED_NAME . ')+' . '\s*[)])/i';
    // optional whitespace, end paren
    /**
     * @return array<int, mixed>|false
     */
    private function internal_parse_formula(string $formula, ?Cell $cell = null): bool|array
    {
        if (($formula = $this->convert_matrix_references(trim($formula))) === false) {
            return false;
        }
        $old_formula = $formula;
        $formula = Preg::replace_callback(self::UNIONABLE_COMMAS, self::union_for_comma(...), $formula);
        // @phpstan-ignore-line
        if ($old_formula !== $formula) {
            $this->debug_log->write_debug_log('Reformulated as %s', $formula);
        }
        $php_spreadsheet_functions =& self::get_functions_address();
        //    If we're using cell caching, then $pCell may well be flushed back to the cache (which detaches the parent worksheet),
        //        so we store the parent worksheet so that we can re-attach it when necessary
        $p_cell_parent = $cell !== null ? $cell->get_worksheet() : null;
        $regexp_match_string = '/^((?<string>' . self::CALCULATION_REGEXP_STRING . ')|(?<function>' . self::CALCULATION_REGEXP_FUNCTION . ')|(?<cellRef>' . self::CALCULATION_REGEXP_CELLREF . ')|(?<colRange>' . self::CALCULATION_REGEXP_COLUMN_RANGE . ')|(?<rowRange>' . self::CALCULATION_REGEXP_ROW_RANGE . ')|(?<number>' . self::CALCULATION_REGEXP_NUMBER . ')|(?<openBrace>' . self::CALCULATION_REGEXP_OPENBRACE . ')|(?<structuredReference>' . self::CALCULATION_REGEXP_STRUCTURED_REFERENCE . ')|(?<definedName>' . self::CALCULATION_REGEXP_DEFINEDNAME . ')|(?<error>' . self::CALCULATION_REGEXP_ERROR . '))/sui';
        //    Start with initialisation
        $index = 0;
        $stack = new Stack($this->branch_pruner);
        $output = [];
        $expecting_operator = false;
        //    We use this test in syntax-checking the expression to determine when a
        //        - is a negation or + is a positive operator rather than an operation
        $expecting_operand = false;
        //    We use this test in syntax-checking the expression to determine whether an operand
        //        should be null in a function call
        //    The guts of the lexical parser
        //    Loop through the formula extracting each operator and operand in turn
        while (true) {
            // Branch pruning: we adapt the output item to the context (it will
            // be used to limit its computation)
            $this->branch_pruner->initialise_for_loop();
            $op_character = $formula[$index];
            //    Get the first character of the value at the current index position
            if ($op_character === "\xe2") {
                // intersection or union
                $op_character .= $formula[++$index];
                $op_character .= $formula[++$index];
            }
            // Check for two-character operators (e.g. >=, <=, <>)
            if (isset(self::COMPARISON_OPERATORS[$op_character]) && strlen($formula) > $index && isset($formula[$index + 1], self::COMPARISON_OPERATORS[$formula[$index + 1]])) {
                $op_character .= $formula[++$index];
            }
            //    Find out if we're currently at the beginning of a number, variable, cell/row/column reference,
            //         function, defined name, structured reference, parenthesis, error or operand
            $is_operand_or_function = (bool) preg_match($regexp_match_string, substr($formula, $index), $match);
            $expecting_operator_copy = $expecting_operator;
            if ($op_character === '-' && !$expecting_operator) {
                //    Is it a negation instead of a minus?
                //    Put a negation on the stack
                $stack->push('Unary Operator', '~');
                ++$index;
                //        and drop the negation symbol
            } elseif ($op_character === '%' && $expecting_operator) {
                //    Put a percentage on the stack
                $stack->push('Unary Operator', '%');
                ++$index;
            } elseif ($op_character === '+' && !$expecting_operator) {
                //    Positive (unary plus rather than binary operator plus) can be discarded?
                ++$index;
                //    Drop the redundant plus symbol
            } elseif ($op_character === '~' && !$is_operand_or_function) {
                //    We have to explicitly deny a tilde, union or intersect because they are legal
                return $this->raise_formula_error("Formula Error: Illegal character '~'");
                //        on the stack but not in the input expression
            } elseif ((isset(self::CALCULATION_OPERATORS[$op_character]) || $is_operand_or_function) && $expecting_operator) {
                //    Are we putting an operator on the stack?
                while (self::swap_operands($stack, $op_character)) {
                    $output[] = $stack->pop();
                    //    Swap operands and higher precedence operators from the stack to the output
                }
                //    Finally put our current operator onto the stack
                $stack->push('Binary Operator', $op_character);
                ++$index;
                $expecting_operator = false;
            } elseif ($op_character === ')' && $expecting_operator) {
                //    Are we expecting to close a parenthesis?
                $expecting_operand = false;
                while (($o2 = $stack->pop()) && $o2['value'] !== '(') {
                    //    Pop off the stack back to the last (
                    $output[] = $o2;
                }
                $d = $stack->last(2);
                // Branch pruning we decrease the depth whether is it a function
                // call or a parenthesis
                $this->branch_pruner->decrement_depth();
                if (is_array($d) && preg_match('/^' . self::CALCULATION_REGEXP_FUNCTION . '$/miu', String_Helper::convert_to_string($d['value']), $matches)) {
                    //    Did this parenthesis just close a function?
                    try {
                        $this->branch_pruner->closing_brace($d['value']);
                    } catch (Exception $e) {
                        return $this->raise_formula_error($e->get_message(), $e->get_code(), $e);
                    }
                    $function_name = $matches[1];
                    //    Get the function name
                    $d = $stack->pop();
                    $argument_count = $d['value'] ?? 0;
                    //    See how many arguments there were (argument count is the next value stored on the stack)
                    $output[] = $d;
                    //    Dump the argument count on the output
                    $output[] = $stack->pop();
                    //    Pop the function and push onto the output
                    if (isset(self::$control_functions[$function_name])) {
                        $expected_argument_count = self::$control_functions[$function_name]['argumentCount'];
                    } elseif (isset($php_spreadsheet_functions[$function_name])) {
                        $expected_argument_count = $php_spreadsheet_functions[$function_name]['argumentCount'];
                    } else {
                        // did we somehow push a non-function on the stack? this should never happen
                        return $this->raise_formula_error('Formula Error: Internal error, non-function on stack');
                    }
                    //    Check the argument count
                    $argument_count_error = false;
                    $expected_argument_count_string = null;
                    if (is_numeric($expected_argument_count)) {
                        if ($expected_argument_count < 0) {
                            if ($argument_count > abs($expected_argument_count + 0)) {
                                $argument_count_error = true;
                                $expected_argument_count_string = 'no more than ' . abs($expected_argument_count + 0);
                            }
                        } else if ($argument_count != $expected_argument_count) {
                            $argument_count_error = true;
                            $expected_argument_count_string = $expected_argument_count;
                        }
                    } elseif (is_string($expected_argument_count) && $expected_argument_count !== '*') {
                        if (!Preg::is_match('/(\d*)([-+,])(\d*)/', $expected_argument_count, $arg_match)) {
                            $arg_match = ['', '', '', ''];
                        }
                        switch ($arg_match[2]) {
                            case '+':
                                if ($argument_count < $arg_match[1]) {
                                    $argument_count_error = true;
                                    $expected_argument_count_string = $arg_match[1] . ' or more ';
                                }
                                break;
                            case '-':
                                if ($argument_count < $arg_match[1] || $argument_count > $arg_match[3]) {
                                    $argument_count_error = true;
                                    $expected_argument_count_string = 'between ' . $arg_match[1] . ' and ' . $arg_match[3];
                                }
                                break;
                            case ',':
                                if ($argument_count != $arg_match[1] && $argument_count != $arg_match[3]) {
                                    $argument_count_error = true;
                                    $expected_argument_count_string = 'either ' . $arg_match[1] . ' or ' . $arg_match[3];
                                }
                                break;
                        }
                    }
                    if ($argument_count_error) {
                        /** @var int $argumentCount */
                        return $this->raise_formula_error("Formula Error: Wrong number of arguments for {$function_name}() function: {$argument_count} given, " . $expected_argument_count_string . ' expected');
                    }
                }
                ++$index;
            } elseif ($op_character === ',') {
                // Is this the separator for function arguments?
                try {
                    $this->branch_pruner->argument_separator();
                } catch (Exception $e) {
                    return $this->raise_formula_error($e->get_message(), $e->get_code(), $e);
                }
                while (($o2 = $stack->pop()) && $o2['value'] !== '(') {
                    //    Pop off the stack back to the last (
                    $output[] = $o2;
                    // pop the argument expression stuff and push onto the output
                }
                //    If we've a comma when we're expecting an operand, then what we actually have is a null operand;
                //        so push a null onto the stack
                if ($expecting_operand || !$expecting_operator) {
                    $output[] = $stack->get_stack_item('Empty Argument', null, 'NULL');
                }
                // make sure there was a function
                $d = $stack->last(2);
                /** @var string */
                $temp = $d['value'] ?? '';
                if (!preg_match('/^' . self::CALCULATION_REGEXP_FUNCTION . '$/miu', $temp, $matches)) {
                    // Can we inject a dummy function at this point so that the braces at least have some context
                    //     because at least the braces are paired up (at this stage in the formula)
                    // MS Excel allows this if the content is cell references; but doesn't allow actual values,
                    //    but at this point, we can't differentiate (so allow both)
                    //return $this->raiseFormulaError('Formula Error: Unexpected ,');
                    $stack->push('Binary Operator', '∪');
                    ++$index;
                    $expecting_operator = false;
                    continue;
                }
                /** @var array<string, int> $d */
                $d = $stack->pop();
                ++$d['value'];
                // increment the argument count
                $stack->push_stack_item($d);
                $stack->push('Brace', '(');
                // put the ( back on, we'll need to pop back to it again
                $expecting_operator = false;
                $expecting_operand = true;
                ++$index;
            } elseif ($op_character === '(' && !$expecting_operator) {
                // Branch pruning: we go deeper
                $this->branch_pruner->increment_depth();
                $stack->push('Brace', '(');
                ++$index;
            } elseif ($is_operand_or_function && !$expecting_operator_copy) {
                // do we now have a function/variable/number?
                $expecting_operator = true;
                $expecting_operand = false;
                $val = $match[1] ?? '';
                //* @phpstan-ignore-line
                $length = strlen($val);
                if (preg_match('/^' . self::CALCULATION_REGEXP_FUNCTION . '$/miu', $val, $matches)) {
                    // $val is known to be valid unicode from statement above, so Preg::replace is okay even with u modifier
                    $val = Preg::replace('/\s/u', '', $val);
                    if (isset($php_spreadsheet_functions[strtoupper($matches[1])]) || isset(self::$control_functions[strtoupper($matches[1])])) {
                        // it's a function
                        $val_to_upper = strtoupper($val);
                    } else {
                        $val_to_upper = 'NAME.ERROR(';
                    }
                    // here $matches[1] will contain values like "IF"
                    // and $val "IF("
                    $this->branch_pruner->function_call($val_to_upper);
                    $stack->push('Function', $val_to_upper);
                    // tests if the function is closed right after opening
                    $ax = preg_match('/^\s*\)/u', substr($formula, $index + $length));
                    if ($ax) {
                        $stack->push('Operand Count for Function ' . $val_to_upper . ')', 0);
                        $expecting_operator = true;
                    } else {
                        $stack->push('Operand Count for Function ' . $val_to_upper . ')', 1);
                        $expecting_operator = false;
                    }
                    $stack->push('Brace', '(');
                } elseif (preg_match('/^' . self::CALCULATION_REGEXP_CELLREF . '$/miu', $val, $matches)) {
                    //    Watch for this case-change when modifying to allow cell references in different worksheets...
                    //    Should only be applied to the actual cell column, not the worksheet name
                    //    If the last entry on the stack was a : operator, then we have a cell range reference
                    $test_prev_op = $stack->last(1);
                    if ($test_prev_op !== null && $test_prev_op['value'] === ':') {
                        //    If we have a worksheet reference, then we're playing with a 3D reference
                        if ($matches[2] === '') {
                            //    Otherwise, we 'inherit' the worksheet reference from the start cell reference
                            //    The start of the cell range reference should be the last entry in $output
                            $range_start_cell_ref = $output[count($output) - 1]['value'] ?? '';
                            if ($range_start_cell_ref === ':') {
                                // Do we have chained range operators?
                                $range_start_cell_ref = $output[count($output) - 2]['value'] ?? '';
                            }
                            /** @var string $rangeStartCellRef */
                            preg_match('/^' . self::CALCULATION_REGEXP_CELLREF . '$/miu', $range_start_cell_ref, $range_start_matches);
                            if (array_key_exists(2, $range_start_matches)) {
                                if ($range_start_matches[2] > '') {
                                    $val = $range_start_matches[2] . '!' . $val;
                                }
                            } else {
                                $val = Excel_Error::REF();
                            }
                        } else {
                            $range_start_cell_ref = $output[count($output) - 1]['value'] ?? '';
                            if ($range_start_cell_ref === ':') {
                                // Do we have chained range operators?
                                $range_start_cell_ref = $output[count($output) - 2]['value'] ?? '';
                            }
                            /** @var string $rangeStartCellRef */
                            preg_match('/^' . self::CALCULATION_REGEXP_CELLREF . '$/miu', $range_start_cell_ref, $range_start_matches);
                            if (isset($range_start_matches[2]) && $range_start_matches[2] !== $matches[2]) {
                                return $this->raise_formula_error('3D Range references are not yet supported');
                            }
                        }
                    } elseif (!str_contains($val, '!') && $p_cell_parent !== null) {
                        $worksheet = $p_cell_parent->get_title();
                        $val = "'{$worksheet}'!{$val}";
                    }
                    // unescape any apostrophes or double quotes in worksheet name
                    $val = str_replace(["''", '""'], ["'", '"'], $val);
                    $output_item = $stack->get_stack_item('Cell Reference', $val, $val);
                    $output[] = $output_item;
                } elseif (preg_match('/^' . self::CALCULATION_REGEXP_STRUCTURED_REFERENCE . '$/miu', $val, $matches)) {
                    try {
                        $structured_reference = Operands\Structured_Reference::from_parser($formula, $index, $matches);
                    } catch (Exception $e) {
                        return $this->raise_formula_error($e->get_message(), $e->get_code(), $e);
                    }
                    $val = $structured_reference->value();
                    $length = strlen($val);
                    $output_item = $stack->get_stack_item(Operands\Structured_Reference::NAME, $structured_reference);
                    $output[] = $output_item;
                    $expecting_operator = true;
                } else {
                    // it's a variable, constant, string, number or boolean
                    $locale_constant = false;
                    $stack_item_type = 'Value';
                    $stack_item_reference = null;
                    //    If the last entry on the stack was a : operator, then we may have a row or column range reference
                    $test_prev_op = $stack->last(1);
                    if ($test_prev_op !== null && $test_prev_op['value'] === ':') {
                        $stack_item_type = 'Cell Reference';
                        if (!is_numeric($val) && (ctype_alpha($val) === false || strlen($val) > 3) && preg_match('/^' . self::CALCULATION_REGEXP_DEFINEDNAME . '$/mui', $val) !== false && ($this->spreadsheet === null || $this->spreadsheet->get_named_range($val) !== null)) {
                            $named_range = $this->spreadsheet === null ? null : $this->spreadsheet->get_named_range($val);
                            if ($named_range !== null) {
                                $stack_item_type = 'Defined Name';
                                $address = str_replace('$', '', $named_range->get_value());
                                $stack_item_reference = $val;
                                if (str_contains($address, ':')) {
                                    // We'll need to manipulate the stack for an actual named range rather than a named cell
                                    $from_to = explode(':', $address);
                                    $to = array_pop($from_to);
                                    foreach ($from_to as $from) {
                                        $output[] = $stack->get_stack_item($stack_item_type, $from, $stack_item_reference);
                                        $output[] = $stack->get_stack_item('Binary Operator', ':');
                                    }
                                    $address = $to;
                                }
                                $val = $address;
                            }
                        } elseif ($val === Excel_Error::REF()) {
                            $stack_item_reference = $val;
                        } else {
                            /** @var non-empty-string $startRowColRef */
                            $start_row_col_ref = $output[count($output) - 1]['value'] ?? '';
                            [$range_ws1, $start_row_col_ref] = Worksheet::extract_sheet_title($start_row_col_ref, true);
                            $range_sheet_ref = $range_ws1;
                            if ($range_ws1 !== '') {
                                $range_ws1 .= '!';
                            }
                            if (str_starts_with($range_sheet_ref, "'")) {
                                $range_sheet_ref = Worksheet::un_apostrophize_title($range_sheet_ref);
                            }
                            [$range_ws2, $val] = Worksheet::extract_sheet_title($val, true);
                            if ($range_ws2 !== '') {
                                $range_ws2 .= '!';
                            } else {
                                $range_ws2 = $range_ws1;
                            }
                            $ref_sheet = $p_cell_parent;
                            if ($p_cell_parent !== null && $range_sheet_ref !== '' && $range_sheet_ref !== $p_cell_parent->get_title()) {
                                $ref_sheet = $p_cell_parent->get_parent_or_throw()->get_sheet_by_name($range_sheet_ref);
                            }
                            if (ctype_digit($val) && $val <= Address_Range::MAX_ROW) {
                                //    Row range
                                $stack_item_type = 'Row Reference';
                                $valx = $val;
                                $end_row_col_ref = $ref_sheet !== null ? $ref_sheet->get_highest_data_column($valx) : Address_Range::MAX_COLUMN;
                                //    Max 16,384 columns for Excel2007
                                $val = "{$range_ws2}{$end_row_col_ref}{$val}";
                            } elseif (ctype_alpha($val) && strlen($val) <= 3) {
                                //    Column range
                                $stack_item_type = 'Column Reference';
                                $end_row_col_ref = $ref_sheet !== null ? $ref_sheet->get_highest_data_row($val) : Address_Range::MAX_ROW;
                                //    Max 1,048,576 rows for Excel2007
                                $val = "{$range_ws2}{$val}{$end_row_col_ref}";
                            }
                            $stack_item_reference = $val;
                        }
                    } elseif ($op_character === self::FORMULA_STRING_QUOTE) {
                        //    UnEscape any quotes within the string
                        $val = self::wrap_result(str_replace('""', self::FORMULA_STRING_QUOTE, String_Helper::convert_to_string(self::unwrap_result($val))));
                    } elseif (isset(self::EXCEL_CONSTANTS[trim(strtoupper($val))])) {
                        $stack_item_type = 'Constant';
                        $excel_constant = trim(strtoupper($val));
                        $val = self::EXCEL_CONSTANTS[$excel_constant];
                        $stack_item_reference = $excel_constant;
                    } elseif (($locale_constant = array_search(trim(strtoupper($val)), self::$locale_boolean)) !== false) {
                        $stack_item_type = 'Constant';
                        $val = self::EXCEL_CONSTANTS[$locale_constant];
                        $stack_item_reference = $locale_constant;
                    } elseif (preg_match('/^' . self::CALCULATION_REGEXP_ROW_RANGE . '/miu', substr($formula, $index), $row_range_reference)) {
                        $val = $row_range_reference[1];
                        $length = strlen($row_range_reference[1]);
                        $stack_item_type = 'Row Reference';
                        // unescape any apostrophes or double quotes in worksheet name
                        $val = str_replace(["''", '""'], ["'", '"'], $val);
                        $column = 'A';
                        if ($test_prev_op !== null && $test_prev_op['value'] === ':' && $p_cell_parent !== null) {
                            $column = $p_cell_parent->get_highest_data_column($val);
                        }
                        $val = "{$row_range_reference[2]}{$column}{$row_range_reference[7]}";
                        $stack_item_reference = $val;
                    } elseif (preg_match('/^' . self::CALCULATION_REGEXP_COLUMN_RANGE . '/miu', substr($formula, $index), $column_range_reference)) {
                        $val = $column_range_reference[1];
                        $length = strlen($val);
                        $stack_item_type = 'Column Reference';
                        // unescape any apostrophes or double quotes in worksheet name
                        $val = str_replace(["''", '""'], ["'", '"'], $val);
                        $row = '1';
                        if ($test_prev_op !== null && $test_prev_op['value'] === ':' && $p_cell_parent !== null) {
                            $row = $p_cell_parent->get_highest_data_row($val);
                        }
                        $val = "{$val}{$row}";
                        $stack_item_reference = $val;
                    } elseif (preg_match('/^' . self::CALCULATION_REGEXP_DEFINEDNAME . '.*/miu', $val, $match)) {
                        $stack_item_type = 'Defined Name';
                        $stack_item_reference = $val;
                    } elseif (is_numeric($val)) {
                        if (str_contains($val, '.') || stripos($val, 'e') !== false || $val > PHP_INT_MAX || $val < -PHP_INT_MAX) {
                            $val = (float) $val;
                        } else {
                            $val = (int) $val;
                        }
                    }
                    $details = $stack->get_stack_item($stack_item_type, $val, $stack_item_reference);
                    if ($locale_constant) {
                        $details['localeValue'] = $locale_constant;
                    }
                    $output[] = $details;
                }
                $index += $length;
            } elseif ($op_character === '$') {
                // absolute row or column range
                ++$index;
            } elseif ($op_character === ')') {
                // miscellaneous error checking
                if ($expecting_operand) {
                    $output[] = $stack->get_stack_item('Empty Argument', null, 'NULL');
                    $expecting_operand = false;
                    $expecting_operator = true;
                } else {
                    return $this->raise_formula_error("Formula Error: Unexpected ')'");
                }
            } elseif (isset(self::CALCULATION_OPERATORS[$op_character]) && !$expecting_operator) {
                return $this->raise_formula_error("Formula Error: Unexpected operator '{$op_character}'");
            } else {
                // I don't even want to know what you did to get here
                return $this->raise_formula_error('Formula Error: An unexpected error occurred');
            }
            //    Test for end of formula string
            if ($index == strlen($formula)) {
                //    Did we end with an operator?.
                //    Only valid for the % unary operator
                if (isset(self::CALCULATION_OPERATORS[$op_character]) && $op_character != '%') {
                    return $this->raise_formula_error("Formula Error: Operator '{$op_character}' has no operands");
                }
                break;
            }
            //    Ignore white space
            while ($formula[$index] === "\n" || $formula[$index] === "\r") {
                ++$index;
            }
            if ($formula[$index] === ' ') {
                while ($formula[$index] === ' ') {
                    ++$index;
                }
                //    If we're expecting an operator, but only have a space between the previous and next operands (and both are
                //        Cell References, Defined Names or Structured References) then we have an INTERSECTION operator
                $count_output_minus1 = count($output) - 1;
                if ($expecting_operator && array_key_exists($count_output_minus1, $output) && is_array($output[$count_output_minus1]) && array_key_exists('type', $output[$count_output_minus1]) && (preg_match('/^' . self::CALCULATION_REGEXP_CELLREF . '.*/miu', substr($formula, $index), $match) && $output[$count_output_minus1]['type'] === 'Cell Reference' || preg_match('/^' . self::CALCULATION_REGEXP_DEFINEDNAME . '.*/miu', substr($formula, $index), $match) && ($output[$count_output_minus1]['type'] === 'Defined Name' || $output[$count_output_minus1]['type'] === 'Value') || preg_match('/^' . self::CALCULATION_REGEXP_STRUCTURED_REFERENCE . '.*/miu', substr($formula, $index), $match) && ($output[$count_output_minus1]['type'] === Operands\Structured_Reference::NAME || $output[$count_output_minus1]['type'] === 'Value'))) {
                    while (self::swap_operands($stack, $op_character)) {
                        $output[] = $stack->pop();
                        //    Swap operands and higher precedence operators from the stack to the output
                    }
                    $stack->push('Binary Operator', '∩');
                    //    Put an Intersect Operator on the stack
                    $expecting_operator = false;
                }
            }
        }
        while (($op = $stack->pop()) !== null) {
            // pop everything off the stack and push onto output
            if ($op['value'] == '(') {
                return $this->raise_formula_error("Formula Error: Expecting ')'");
                // if there are any opening braces on the stack, then braces were unbalanced
            }
            $output[] = $op;
        }
        return $output;
    }
    /** @param mixed[] $operandData */
    private static function data_test_reference(array &$operand_data): mixed
    {
        $operand = $operand_data['value'];
        if ($operand_data['reference'] === null && is_array($operand)) {
            $r_keys = array_keys($operand);
            $row_key = array_shift($r_keys);
            if (is_array($operand[$row_key]) === false) {
                $operand_data['value'] = $operand[$row_key];
                return $operand[$row_key];
            }
            $c_keys = array_keys(array_keys($operand[$row_key]));
            $col_key = array_shift($c_keys);
            if (ctype_upper("{$col_key}")) {
                $operand_data['reference'] = $col_key . $row_key;
            }
        }
        return $operand;
    }
    private static int $match_index8 = 8;
    private static int $match_index9 = 9;
    private static int $match_index10 = 10;
    /**
     * @param array<mixed>|false $tokens
     *
     * @return array<int, mixed>|false|string
     */
    private function process_token_stack(false|array $tokens, ?string $cell_id = null, ?Cell $cell = null)
    {
        if ($tokens === false) {
            return false;
        }
        $php_spreadsheet_functions =& self::get_functions_address();
        //    If we're using cell caching, then $pCell may well be flushed back to the cache (which detaches the parent cell collection),
        //        so we store the parent cell collection so that we can re-attach it when necessary
        $p_cell_worksheet = $cell !== null ? $cell->get_worksheet() : null;
        $original_coordinate = $cell?->get_coordinate();
        $p_cell_parent = $cell !== null ? $cell->get_parent() : null;
        $stack = new Stack($this->branch_pruner);
        // Stores branches that have been pruned
        $faked_for_branch_pruning = [];
        // help us to know when pruning ['branchTestId' => true/false]
        $branch_store = [];
        //    Loop through each token in turn
        foreach ($tokens as $token_idx => $token_data) {
            /** @var mixed[] $tokenData */
            $this->processing_anchor_array = false;
            if ($token_data['type'] === 'Cell Reference' && isset($tokens[$token_idx + 1]) && $tokens[$token_idx + 1]['type'] === 'Operand Count for Function ANCHORARRAY()') {
                //* @phpstan-ignore-line
                $this->processing_anchor_array = true;
            }
            $token = $token_data['value'];
            // Branch pruning: skip useless resolutions
            /** @var ?string */
            $store_key = $token_data['storeKey'] ?? null;
            if ($this->branch_pruning_enabled && isset($token_data['onlyIf'])) {
                /** @var string */
                $only_if_store_key = $token_data['onlyIf'];
                $store_value = $branch_store[$only_if_store_key] ?? null;
                $store_value_as_bool = $store_value === null ? true : (bool) Functions::flatten_single_value($store_value);
                if (is_array($store_value)) {
                    $wrapped_item = end($store_value);
                    $store_value = is_array($wrapped_item) ? end($wrapped_item) : $wrapped_item;
                }
                if ((isset($store_value) || $token_data['reference'] === 'NULL') && (!$store_value_as_bool || Information\Error_Value::is_error($store_value) || $store_value === 'Pruned branch')) {
                    // If branching value is not true, we don't need to compute
                    /** @var string $onlyIfStoreKey */
                    if (!isset($faked_for_branch_pruning['onlyIf-' . $only_if_store_key])) {
                        /** @var string $token */
                        $stack->push('Value', 'Pruned branch (only if ' . $only_if_store_key . ') ' . $token);
                        $faked_for_branch_pruning['onlyIf-' . $only_if_store_key] = true;
                    }
                    if (isset($store_key)) {
                        // We are processing an if condition
                        // We cascade the pruning to the depending branches
                        $branch_store[$store_key] = 'Pruned branch';
                        $faked_for_branch_pruning['onlyIfNot-' . $store_key] = true;
                        $faked_for_branch_pruning['onlyIf-' . $store_key] = true;
                    }
                    continue;
                }
            }
            if ($this->branch_pruning_enabled && isset($token_data['onlyIfNot'])) {
                /** @var string */
                $only_if_not_store_key = $token_data['onlyIfNot'];
                $store_value = $branch_store[$only_if_not_store_key] ?? null;
                $store_value_as_bool = $store_value === null ? true : (bool) Functions::flatten_single_value($store_value);
                if (is_array($store_value)) {
                    $wrapped_item = end($store_value);
                    $store_value = is_array($wrapped_item) ? end($wrapped_item) : $wrapped_item;
                }
                if ((isset($store_value) || $token_data['reference'] === 'NULL') && ($store_value_as_bool || Information\Error_Value::is_error($store_value) || $store_value === 'Pruned branch')) {
                    // If branching value is true, we don't need to compute
                    if (!isset($faked_for_branch_pruning['onlyIfNot-' . $only_if_not_store_key])) {
                        /** @var string $token */
                        $stack->push('Value', 'Pruned branch (only if not ' . $only_if_not_store_key . ') ' . $token);
                        $faked_for_branch_pruning['onlyIfNot-' . $only_if_not_store_key] = true;
                    }
                    if (isset($store_key)) {
                        // We are processing an if condition
                        // We cascade the pruning to the depending branches
                        $branch_store[$store_key] = 'Pruned branch';
                        $faked_for_branch_pruning['onlyIfNot-' . $store_key] = true;
                        $faked_for_branch_pruning['onlyIf-' . $store_key] = true;
                    }
                    continue;
                }
            }
            if ($token instanceof Operands\Structured_Reference) {
                if ($cell === null) {
                    return $this->raise_formula_error('Structured References must exist in a Cell context');
                }
                try {
                    $cell_range = $token->parse($cell);
                    if (str_contains($cell_range, ':')) {
                        $this->debug_log->write_debug_log('Evaluating Structured Reference %s as Cell Range %s', $token->value(), $cell_range);
                        $range_value = self::get_instance($cell->get_worksheet()->get_parent())->_calculate_formula_value("={$cell_range}", $cell_range, $cell);
                        $stack->push('Value', $range_value);
                        $this->debug_log->write_debug_log('Evaluated Structured Reference %s as value %s', $token->value(), $this->show_value($range_value));
                    } else {
                        $this->debug_log->write_debug_log('Evaluating Structured Reference %s as Cell %s', $token->value(), $cell_range);
                        $cell_value = $cell->get_worksheet()->get_cell($cell_range)->get_calculated_value(false);
                        $stack->push('Cell Reference', $cell_value, $cell_range);
                        $this->debug_log->write_debug_log('Evaluated Structured Reference %s as value %s', $token->value(), $this->show_value($cell_value));
                    }
                } catch (Exception $e) {
                    if ($e->get_code() === Exception::CALCULATION_ENGINE_PUSH_TO_STACK) {
                        $stack->push('Error', Excel_Error::REF());
                        $this->debug_log->write_debug_log('Evaluated Structured Reference %s as error value %s', $token->value(), Excel_Error::REF());
                    } else {
                        return $this->raise_formula_error($e->get_message(), $e->get_code(), $e);
                    }
                }
            } elseif (!is_numeric($token) && !is_object($token) && isset($token, self::BINARY_OPERATORS[$token])) {
                //* @phpstan-ignore-line
                // if the token is a binary operator, pop the top two values off the stack, do the operation, and push the result back on the stack
                //    We must have two operands, error if we don't
                $operand2Data = $stack->pop();
                if ($operand2Data === null) {
                    return $this->raise_formula_error('Internal error - Operand value missing from stack');
                }
                $operand1Data = $stack->pop();
                if ($operand1Data === null) {
                    return $this->raise_formula_error('Internal error - Operand value missing from stack');
                }
                $operand1 = self::data_test_reference($operand1Data);
                $operand2 = self::data_test_reference($operand2Data);
                //    Log what we're doing
                if ($token == ':') {
                    $this->debug_log->write_debug_log('Evaluating Range %s %s %s', $this->show_value($operand1Data['reference']), $token, $this->show_value($operand2Data['reference']));
                } else {
                    $this->debug_log->write_debug_log('Evaluating %s %s %s', $this->show_value($operand1), $token, $this->show_value($operand2));
                }
                //    Process the operation in the appropriate manner
                switch ($token) {
                    // Comparison (Boolean) Operators
                    case '>':
                    // Greater than
                    case '<':
                    // Less than
                    case '>=':
                    // Greater than or Equal to
                    case '<=':
                    // Less than or Equal to
                    case '=':
                    // Equality
                    case '<>':
                        // Inequality
                        $result = $this->execute_binary_comparison_operation($operand1, $operand2, (string) $token, $stack);
                        if (isset($store_key)) {
                            $branch_store[$store_key] = $result;
                        }
                        break;
                    // Binary Operators
                    case ':':
                        // Range
                        if ($operand1Data['type'] === 'Error') {
                            $stack->push($operand1Data['type'], $operand1Data['value']);
                            break;
                        }
                        if ($operand2Data['type'] === 'Error') {
                            $stack->push($operand2Data['type'], $operand2Data['value']);
                            break;
                        }
                        if ($operand1Data['type'] === 'Defined Name') {
                            /** @var array{reference: string} $operand1Data */
                            if (preg_match('/$' . self::CALCULATION_REGEXP_DEFINEDNAME . '^/mui', $operand1Data['reference']) !== false && $this->spreadsheet !== null) {
                                /** @var string[] $operand1Data */
                                $defined_name = $this->spreadsheet->get_named_range($operand1Data['reference']);
                                if ($defined_name !== null) {
                                    $operand1Data['reference'] = $operand1Data['value'] = str_replace('$', '', $defined_name->get_value());
                                }
                            }
                        }
                        /** @var array{reference?: ?string} $operand1Data */
                        if (str_contains($operand1Data['reference'] ?? '', '!')) {
                            [$sheet1, $operand1Data['reference']] = Worksheet::extract_sheet_title($operand1Data['reference'], true, true);
                        } else {
                            $sheet1 = $p_cell_worksheet !== null ? $p_cell_worksheet->get_title() : '';
                        }
                        //$sheet1 ??= ''; // phpstan level 10 says this is unneeded
                        /** @var string */
                        $op2ref = $operand2Data['reference'];
                        [$sheet2, $operand2Data['reference']] = Worksheet::extract_sheet_title($op2ref, true, true);
                        if (empty($sheet2)) {
                            $sheet2 = $sheet1;
                        }
                        if ($sheet1 === $sheet2) {
                            /** @var array{reference: ?string, value: string|string[]} $operand1Data */
                            if ($operand1Data['reference'] === null && $cell !== null) {
                                if (is_array($operand1Data['value'])) {
                                    $operand1Data['reference'] = $cell->get_coordinate();
                                } elseif (trim($operand1Data['value']) != '' && is_numeric($operand1Data['value'])) {
                                    $operand1Data['reference'] = $cell->get_column() . $operand1Data['value'];
                                } elseif (trim($operand1Data['value']) == '') {
                                    $operand1Data['reference'] = $cell->get_coordinate();
                                } else {
                                    $operand1Data['reference'] = $operand1Data['value'] . $cell->get_row();
                                }
                            }
                            /** @var array{reference: ?string, value: string|string[]} $operand2Data */
                            if ($operand2Data['reference'] === null && $cell !== null) {
                                if (is_array($operand2Data['value'])) {
                                    $operand2Data['reference'] = $cell->get_coordinate();
                                } elseif (trim($operand2Data['value']) != '' && is_numeric($operand2Data['value'])) {
                                    $operand2Data['reference'] = $cell->get_column() . $operand2Data['value'];
                                } elseif (trim($operand2Data['value']) == '') {
                                    $operand2Data['reference'] = $cell->get_coordinate();
                                } else {
                                    $operand2Data['reference'] = $operand2Data['value'] . $cell->get_row();
                                }
                            }
                            $o_data = array_merge(explode(':', $operand1Data['reference'] ?? ''), explode(':', $operand2Data['reference'] ?? ''));
                            $o_col = $o_row = [];
                            $break_needed = false;
                            foreach ($o_data as $o_datum) {
                                try {
                                    $o_cr = Coordinate::coordinate_from_string($o_datum);
                                    $o_col[] = Coordinate::column_index_from_string($o_cr[0]) - 1;
                                    $o_row[] = $o_cr[1];
                                } catch (\Exception) {
                                    $stack->push('Error', Excel_Error::REF());
                                    $break_needed = true;
                                    break;
                                }
                            }
                            if ($break_needed) {
                                break;
                            }
                            $cell_ref = Coordinate::string_from_column_index(min($o_col) + 1) . min($o_row) . ':' . Coordinate::string_from_column_index(max($o_col) + 1) . max($o_row);
                            // @phpstan-ignore-line
                            if ($p_cell_parent !== null && $this->spreadsheet !== null) {
                                $cell_value = $this->extract_cell_range($cell_ref, $this->spreadsheet->get_sheet_by_name($sheet1), false);
                            } else {
                                return $this->raise_formula_error('Unable to access Cell Reference');
                            }
                            $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($cell_value));
                            $stack->push('Cell Reference', $cell_value, $cell_ref);
                        } else {
                            $this->debug_log->write_debug_log('Evaluation Result is a #REF! Error');
                            $stack->push('Error', Excel_Error::REF());
                        }
                        break;
                    case '+':
                    //    Addition
                    case '-':
                    //    Subtraction
                    case '*':
                    //    Multiplication
                    case '/':
                    //    Division
                    case '^':
                        //    Exponential
                        $result = $this->execute_numeric_binary_operation($operand1, $operand2, $token, $stack);
                        if (isset($store_key)) {
                            $branch_store[$store_key] = $result;
                        }
                        break;
                    case '&':
                        //    Concatenation
                        //    If either of the operands is a matrix, we need to treat them both as matrices
                        //        (converting the other operand to a matrix if need be); then perform the required
                        //        matrix operation
                        $operand1 = self::bool_to_string($operand1);
                        $operand2 = self::bool_to_string($operand2);
                        if (is_array($operand1) || is_array($operand2)) {
                            if (is_string($operand1)) {
                                $operand1 = self::unwrap_result($operand1);
                            }
                            if (is_string($operand2)) {
                                $operand2 = self::unwrap_result($operand2);
                            }
                            //    Ensure that both operands are arrays/matrices
                            [$rows, $columns] = self::check_matrix_operands($operand1, $operand2, 2);
                            for ($row = 0; $row < $rows; ++$row) {
                                for ($column = 0; $column < $columns; ++$column) {
                                    /** @var mixed[][] $operand1 */
                                    $op1x = self::bool_to_string($operand1[$row][$column]);
                                    /** @var mixed[][] $operand2 */
                                    $op2x = self::bool_to_string($operand2[$row][$column]);
                                    if (Information\Error_Value::is_error($op1x)) {
                                        // no need to do anything
                                    } elseif (Information\Error_Value::is_error($op2x)) {
                                        $operand1[$row][$column] = $op2x;
                                    } else {
                                        /** @var string $op1x */
                                        $operand1[$row][$column] = String_Helper::substring($op1x . $op2x, 0, Data_Type::MAX_STRING_LENGTH);
                                    }
                                }
                            }
                            $result = $operand1;
                        } else if (Information\Error_Value::is_error($operand1)) {
                            $result = $operand1;
                        } elseif (Information\Error_Value::is_error($operand2)) {
                            $result = $operand2;
                        } else {
                            $result = str_replace('""', self::FORMULA_STRING_QUOTE, self::unwrap_result($operand1) . self::unwrap_result($operand2));
                            //* @phpstan-ignore-line
                            $result = String_Helper::substring($result, 0, Data_Type::MAX_STRING_LENGTH);
                            $result = self::FORMULA_STRING_QUOTE . $result . self::FORMULA_STRING_QUOTE;
                        }
                        $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($result));
                        $stack->push('Value', $result);
                        if (isset($store_key)) {
                            $branch_store[$store_key] = $result;
                        }
                        break;
                    case '∩':
                        //    Intersect
                        /** @var mixed[][] $operand1 */
                        /** @var mixed[][] $operand2 */
                        $row_intersect = array_intersect_key($operand1, $operand2);
                        $cell_intersect = $o_col = $o_row = [];
                        foreach (array_keys($row_intersect) as $row) {
                            $o_row[] = $row;
                            foreach ($row_intersect[$row] as $col => $data) {
                                $o_col[] = Coordinate::column_index_from_string($col) - 1;
                                $cell_intersect[$row] = array_intersect_key($operand1[$row], $operand2[$row]);
                            }
                        }
                        if (count(Functions::flatten_array($cell_intersect)) === 0) {
                            $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($cell_intersect));
                            $stack->push('Error', Excel_Error::null());
                        } else {
                            $cell_ref = Coordinate::string_from_column_index(min($o_col) + 1) . min($o_row) . ':' . Coordinate::string_from_column_index(max($o_col) + 1) . max($o_row);
                            // @phpstan-ignore-line
                            $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($cell_intersect));
                            $stack->push('Value', $cell_intersect, $cell_ref);
                        }
                        break;
                    case '∪':
                        //    union
                        /** @var mixed[][] $operand1 */
                        /** @var mixed[][] $operand2 */
                        $cell_union = array_merge($operand1, $operand2);
                        $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($cell_union));
                        $stack->push('Value', $cell_union, 'A1');
                        break;
                }
            } elseif ($token === '~' || $token === '%') {
                // if the token is a unary operator, pop one value off the stack, do the operation, and push it back on
                if (($arg = $stack->pop()) === null) {
                    return $this->raise_formula_error('Internal error - Operand value missing from stack');
                }
                $arg = $arg['value'];
                if ($token === '~') {
                    $this->debug_log->write_debug_log('Evaluating Negation of %s', $this->show_value($arg));
                    $multiplier = -1;
                } else {
                    $this->debug_log->write_debug_log('Evaluating Percentile of %s', $this->show_value($arg));
                    $multiplier = 0.01;
                }
                if (is_array($arg)) {
                    $operand2 = $multiplier;
                    $result = $arg;
                    [$rows, $columns] = self::check_matrix_operands($result, $operand2, 0);
                    for ($row = 0; $row < $rows; ++$row) {
                        for ($column = 0; $column < $columns; ++$column) {
                            /** @var mixed[][] $result */
                            if (self::is_numeric_or_bool($result[$row][$column])) {
                                /** @var float|int|numeric-string */
                                $temp = $result[$row][$column];
                                $result[$row][$column] = $temp * $multiplier;
                            } else {
                                $result[$row][$column] = self::make_error($result[$row][$column]);
                            }
                        }
                    }
                    $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($result));
                    $stack->push('Value', $result);
                    if (isset($store_key)) {
                        $branch_store[$store_key] = $result;
                    }
                } else {
                    $this->execute_numeric_binary_operation($multiplier, $arg, '*', $stack);
                }
            } elseif (Preg::is_match('/^' . self::CALCULATION_REGEXP_CELLREF . '$/i', String_Helper::convert_to_string($token ?? ''), $matches)) {
                $cell_ref = null;
                /* Phpstan says matches[8/9/10] is never set,
                      and code coverage report seems to confirm.
                      regex101.com confirms - only 7 capturing groups.
                      My theory is that this code expected regexp to
                      match cell *or* cellRange, but it does not
                      match the latter. Retain the code for now in case
                      we do want to add the range match later.
                      Probably delete this block later.
                      Until delete happens, turn code coverage off.
                   */
                if (isset($matches[self::$match_index8])) {
                    // @codeCoverageIgnoreStart
                    if ($cell === null) {
                        // We can't access the range, so return a REF error
                        $cell_value = Excel_Error::REF();
                    } else {
                        $cell_ref = $matches[6] . $matches[7] . ':' . $matches[self::$match_index9] . $matches[self::$match_index10];
                        $matches[2] = (string) $matches[2];
                        if ($matches[2] > '') {
                            $matches[2] = trim($matches[2], "\"'");
                            if (str_contains($matches[2], '[') || str_contains($matches[2], ']')) {
                                //    It's a Reference to an external spreadsheet (not currently supported)
                                return $this->raise_formula_error('Unable to access External Workbook');
                            }
                            $matches[2] = trim($matches[2], "\"'");
                            $this->debug_log->write_debug_log('Evaluating Cell Range %s in worksheet %s', $cell_ref, $matches[2]);
                            if ($p_cell_parent !== null && $this->spreadsheet !== null) {
                                $cell_value = $this->extract_cell_range($cell_ref, $this->spreadsheet->get_sheet_by_name($matches[2]), false);
                            } else {
                                return $this->raise_formula_error('Unable to access Cell Reference');
                            }
                            $this->debug_log->write_debug_log('Evaluation Result for cells %s in worksheet %s is %s', $cell_ref, $matches[2], $this->show_type_details($cell_value));
                        } else {
                            $this->debug_log->write_debug_log('Evaluating Cell Range %s in current worksheet', $cell_ref);
                            if ($p_cell_parent !== null) {
                                $cell_value = $this->extract_cell_range($cell_ref, $p_cell_worksheet, false);
                            } else {
                                return $this->raise_formula_error('Unable to access Cell Reference');
                            }
                            $this->debug_log->write_debug_log('Evaluation Result for cells %s is %s', $cell_ref, $this->show_type_details($cell_value));
                        }
                    }
                    // @codeCoverageIgnoreEnd
                } else if ($cell === null) {
                    // We can't access the cell, so return a REF error
                    $cell_value = Excel_Error::REF();
                } else {
                    $cell_ref = $matches[6] . $matches[7];
                    $matches[2] = (string) $matches[2];
                    if ($matches[2] > '') {
                        $matches[2] = trim($matches[2], "\"'");
                        if (str_contains($matches[2], '[') || str_contains($matches[2], ']')) {
                            //    It's a Reference to an external spreadsheet (not currently supported)
                            return $this->raise_formula_error('Unable to access External Workbook');
                        }
                        $this->debug_log->write_debug_log('Evaluating Cell %s in worksheet %s', $cell_ref, $matches[2]);
                        if ($p_cell_parent !== null && $this->spreadsheet !== null) {
                            $cell_sheet = $this->spreadsheet->get_sheet_by_name($matches[2]);
                            if ($cell_sheet && !$cell_sheet->cell_exists($cell_ref)) {
                                try {
                                    $cell_sheet->set_cell_value($cell_ref, null);
                                } catch (Spreadsheet_Exception) {
                                    // do nothing
                                }
                            }
                            if ($cell_sheet && $cell_sheet->cell_exists($cell_ref)) {
                                $cell_value = $this->extract_cell_range($cell_ref, $this->spreadsheet->get_sheet_by_name($matches[2]), false);
                                $cell->attach($p_cell_parent);
                            } else {
                                $cell_ref = $cell_sheet !== null ? "'{$matches[2]}'!{$cell_ref}" : $cell_ref;
                                $cell_value = $cell_sheet !== null ? null : Excel_Error::REF();
                            }
                        } else {
                            return $this->raise_formula_error('Unable to access Cell Reference');
                        }
                        $this->debug_log->write_debug_log('Evaluation Result for cell %s in worksheet %s is %s', $cell_ref, $matches[2], $this->show_type_details($cell_value));
                    } else {
                        $this->debug_log->write_debug_log('Evaluating Cell %s in current worksheet', $cell_ref);
                        if ($p_cell_parent !== null && $p_cell_parent->has($cell_ref)) {
                            $cell_value = $this->extract_cell_range($cell_ref, $p_cell_worksheet, false);
                            $cell->attach($p_cell_parent);
                        } else {
                            $cell_value = null;
                        }
                        $this->debug_log->write_debug_log('Evaluation Result for cell %s is %s', $cell_ref, $this->show_type_details($cell_value));
                    }
                }
                if ($this->get_instance_array_return_type() === self::RETURN_ARRAY_AS_ARRAY && !$this->processing_anchor_array && is_array($cell_value)) {
                    while (is_array($cell_value)) {
                        $cell_value = array_shift($cell_value);
                    }
                    if (is_string($cell_value)) {
                        $cell_value = Preg::replace('/"/', '""', $cell_value);
                    }
                    $this->debug_log->write_debug_log('Scalar Result for cell %s is %s', $cell_ref, $this->show_type_details($cell_value));
                }
                $this->processing_anchor_array = false;
                $stack->push('Cell Value', $cell_value, $cell_ref);
                if (isset($store_key)) {
                    $branch_store[$store_key] = $cell_value;
                }
            } elseif (preg_match('/^' . self::CALCULATION_REGEXP_FUNCTION . '$/miu', String_Helper::convert_to_string($token ?? ''), $matches)) {
                // if the token is a function, pop arguments off the stack, hand them to the function, and push the result back on
                if ($cell !== null && $p_cell_parent !== null) {
                    $cell->attach($p_cell_parent);
                }
                $function_name = $matches[1];
                /** @var array<string, int> $argCount */
                $arg_count = $stack->pop();
                $arg_count = $arg_count['value'];
                if ($function_name !== 'MKMATRIX') {
                    $this->debug_log->write_debug_log('Evaluating Function %s() with %s argument%s', self::locale_func($function_name), $arg_count == 0 ? 'no' : $arg_count, $arg_count == 1 ? '' : 's');
                }
                if (isset($php_spreadsheet_functions[$function_name]) || isset(self::$control_functions[$function_name])) {
                    // function
                    $pass_by_reference = false;
                    $pass_cell_reference = false;
                    $function_call = null;
                    if (isset($php_spreadsheet_functions[$function_name])) {
                        $function_call = $php_spreadsheet_functions[$function_name]['functionCall'];
                        $pass_by_reference = isset($php_spreadsheet_functions[$function_name]['passByReference']);
                        $pass_cell_reference = isset($php_spreadsheet_functions[$function_name]['passCellReference']);
                    } elseif (isset(self::$control_functions[$function_name])) {
                        $function_call = self::$control_functions[$function_name]['functionCall'];
                        $pass_by_reference = isset(self::$control_functions[$function_name]['passByReference']);
                        $pass_cell_reference = isset(self::$control_functions[$function_name]['passCellReference']);
                    }
                    // get the arguments for this function
                    $args = $arg_array_vals = [];
                    $empty_arguments = [];
                    for ($i = 0; $i < $arg_count; ++$i) {
                        $arg = $stack->pop();
                        $a = $arg_count - $i - 1;
                        if ($pass_by_reference && isset($php_spreadsheet_functions[$function_name]['passByReference'][$a]) && $php_spreadsheet_functions[$function_name]['passByReference'][$a]) {
                            /** @var mixed[] $arg */
                            if ($arg['reference'] === null) {
                                $next_arg = $cell_id;
                                if ($function_name === 'ISREF' && ($arg['type'] ?? '') === 'Value') {
                                    if (array_key_exists('value', $arg)) {
                                        $arg_value = $arg['value'];
                                        if (is_scalar($arg_value)) {
                                            $next_arg = $arg_value;
                                        } elseif (empty($arg_value)) {
                                            $next_arg = '';
                                        }
                                    }
                                } elseif (($arg['type'] ?? '') === 'Error') {
                                    $arg_value = $arg['value'];
                                    if (is_scalar($arg_value)) {
                                        $next_arg = $arg_value;
                                    } elseif (empty($arg_value)) {
                                        $next_arg = '';
                                    }
                                }
                                $args[] = $next_arg;
                                if ($function_name !== 'MKMATRIX') {
                                    $arg_array_vals[] = $this->show_value($cell_id);
                                }
                            } else {
                                $args[] = $arg['reference'];
                                if ($function_name !== 'MKMATRIX') {
                                    $arg_array_vals[] = $this->show_value($arg['reference']);
                                }
                            }
                        } else {
                            /** @var mixed[] $arg */
                            if ($arg['type'] === 'Empty Argument' && in_array($function_name, ['MIN', 'MINA', 'MAX', 'MAXA', 'IF'], true)) {
                                $empty_arguments[] = false;
                                $args[] = $arg['value'] = 0;
                                $this->debug_log->write_debug_log('Empty Argument reevaluated as 0');
                            } else {
                                $empty_arguments[] = $arg['type'] === 'Empty Argument';
                                $args[] = self::unwrap_result($arg['value']);
                            }
                            if ($function_name !== 'MKMATRIX') {
                                $arg_array_vals[] = $this->show_value($arg['value']);
                            }
                        }
                    }
                    //    Reverse the order of the arguments
                    krsort($args);
                    krsort($empty_arguments);
                    if ($arg_count > 0 && is_array($function_call)) {
                        /** @var string[] */
                        $function_call_copy = $function_call;
                        $args = $this->add_default_argument_values($function_call_copy, $args, $empty_arguments);
                    }
                    if ($pass_by_reference && $arg_count == 0) {
                        $args[] = $cell_id;
                        $arg_array_vals[] = $this->show_value($cell_id);
                    }
                    if ($function_name !== 'MKMATRIX') {
                        if ($this->debug_log->get_write_debug_log()) {
                            krsort($arg_array_vals);
                            $this->debug_log->write_debug_log('Evaluating %s ( %s )', self::locale_func($function_name), implode(self::$locale_argument_separator . ' ', Functions::flatten_array($arg_array_vals)));
                        }
                    }
                    //    Process the argument with the appropriate function call
                    if ($p_cell_worksheet !== null && $original_coordinate !== null) {
                        $p_cell_worksheet->get_cell($original_coordinate);
                    }
                    /** @var array<string>|string $functionCall */
                    $args = $this->add_cell_reference($args, $pass_cell_reference, $function_call, $cell);
                    if (!is_array($function_call)) {
                        foreach ($args as &$arg) {
                            $arg = Functions::flatten_single_value($arg);
                        }
                        unset($arg);
                    }
                    /** @var callable $functionCall */
                    try {
                        $result = call_user_func_array($function_call, $args);
                    } catch (TypeError $e) {
                        if (!$this->suppress_formula_errors) {
                            throw $e;
                        }
                        $result = false;
                    }
                    if ($function_name !== 'MKMATRIX') {
                        $this->debug_log->write_debug_log('Evaluation Result for %s() function call is %s', self::locale_func($function_name), $this->show_type_details($result));
                    }
                    $stack->push('Value', self::wrap_result($result));
                    if (isset($store_key)) {
                        $branch_store[$store_key] = $result;
                    }
                }
            } else if (isset(self::EXCEL_CONSTANTS[strtoupper($token ?? '')])) {
                $excel_constant = strtoupper("{$token}");
                $stack->push('Constant Value', self::EXCEL_CONSTANTS[$excel_constant]);
                if (isset($store_key)) {
                    $branch_store[$store_key] = self::EXCEL_CONSTANTS[$excel_constant];
                }
                $this->debug_log->write_debug_log('Evaluating Constant %s as %s', $excel_constant, $this->show_type_details(self::EXCEL_CONSTANTS[$excel_constant]));
            } elseif (is_numeric($token) || $token === null || is_bool($token) || $token == '' || $token[0] == self::FORMULA_STRING_QUOTE || $token[0] == '#') {
                //* @phpstan-ignore-line
                /** @var array{type: string, reference: ?string} $tokenData */
                $stack->push($token_data['type'], $token, $token_data['reference']);
                if (isset($store_key)) {
                    $branch_store[$store_key] = $token;
                }
            } elseif (preg_match('/^' . self::CALCULATION_REGEXP_DEFINEDNAME . '$/miu', $token, $matches)) {
                // if the token is a named range or formula, evaluate it and push the result onto the stack
                $defined_name = $matches[6];
                if (str_starts_with($defined_name, '_xleta')) {
                    return Functions::NOT_YET_IMPLEMENTED;
                }
                if ($cell === null || $p_cell_worksheet === null) {
                    return $this->raise_formula_error("undefined name '{$token}'");
                }
                $specified_worksheet = trim($matches[2], "'");
                $this->debug_log->write_debug_log('Evaluating Defined Name %s', $defined_name);
                $named_range = Defined_Name::resolve_name($defined_name, $p_cell_worksheet, $specified_worksheet);
                // If not Defined Name, try as Table.
                if ($named_range === null && $this->spreadsheet !== null) {
                    $table = $this->spreadsheet->get_table_by_name($defined_name);
                    if ($table !== null) {
                        $table_range = Coordinate::get_range_boundaries($table->get_range());
                        if ($table->get_show_header_row()) {
                            ++$table_range[0][1];
                        }
                        if ($table->get_show_totals_row()) {
                            --$table_range[1][1];
                        }
                        $table_range_string = '$' . $table_range[0][0] . '$' . $table_range[0][1] . ':' . '$' . $table_range[1][0] . '$' . $table_range[1][1];
                        $named_range = new Named_Range($defined_name, $table->get_worksheet(), $table_range_string);
                    }
                }
                if ($named_range === null) {
                    $result = Excel_Error::NAME();
                    $stack->push('Error', $result);
                    $this->debug_log->write_debug_log("Error {$result}");
                } else {
                    $result = $this->evaluate_defined_name($cell, $named_range, $p_cell_worksheet, $stack, $specified_worksheet !== '');
                }
                if (isset($store_key)) {
                    $branch_store[$store_key] = $result;
                }
            } else {
                return $this->raise_formula_error("undefined name '{$token}'");
            }
        }
        // when we're out of tokens, the stack should have a single element, the final result
        if ($stack->count() != 1) {
            return $this->raise_formula_error('internal error');
        }
        /** @var array<string, array<int, mixed>|false|string> */
        $output = $stack->pop();
        return $output['value'];
    }
    private function validate_binary_operand(mixed &$operand, Stack &$stack): bool
    {
        if (is_array($operand)) {
            if (count($operand, COUNT_RECURSIVE) - count($operand) == 1) {
                do {
                    $operand = array_pop($operand);
                } while (is_array($operand));
            }
        }
        //    Numbers, matrices and booleans can pass straight through, as they're already valid
        if (is_string($operand)) {
            //    We only need special validations for the operand if it is a string
            //    Start by stripping off the quotation marks we use to identify true excel string values internally
            if ($operand > '' && $operand[0] == self::FORMULA_STRING_QUOTE) {
                $operand = String_Helper::convert_to_string(self::unwrap_result($operand));
            }
            //    If the string is a numeric value, we treat it as a numeric, so no further testing
            if (!is_numeric($operand)) {
                //    If not a numeric, test to see if the value is an Excel error, and so can't be used in normal binary operations
                if ($operand > '' && $operand[0] == '#') {
                    $stack->push('Value', $operand);
                    $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($operand));
                    return false;
                }
                //    If not a numeric, test to see if the value is an Excel error, and so can't be used in normal binary operations
                if (Engine\Formatted_Number::convert_to_number_if_formatted($operand) === false) {
                    //    If not a numeric, a fraction or a percentage, then it's a text string, and so can't be used in mathematical binary operations
                    $stack->push('Error', '#VALUE!');
                    $this->debug_log->write_debug_log('Evaluation Result is a %s', $this->show_type_details('#VALUE!'));
                    return false;
                }
            }
        }
        //    return a true if the value of the operand is one that we can use in normal binary mathematical operations
        return true;
    }
    /** @return mixed[] */
    private function execute_array_comparison(mixed $operand1, mixed $operand2, string $operation, Stack &$stack, bool $recursing_arrays): array
    {
        $result = [];
        if (!is_array($operand2) && is_array($operand1)) {
            // Operand 1 is an array, Operand 2 is a scalar
            foreach ($operand1 as $x => $operand_data) {
                $this->debug_log->write_debug_log('Evaluating Comparison %s %s %s', $this->show_value($operand_data), $operation, $this->show_value($operand2));
                $this->execute_binary_comparison_operation($operand_data, $operand2, $operation, $stack);
                /** @var array<string, mixed> $r */
                $r = $stack->pop();
                $result[$x] = $r['value'];
            }
        } elseif (is_array($operand2) && !is_array($operand1)) {
            // Operand 1 is a scalar, Operand 2 is an array
            foreach ($operand2 as $x => $operand_data) {
                $this->debug_log->write_debug_log('Evaluating Comparison %s %s %s', $this->show_value($operand1), $operation, $this->show_value($operand_data));
                $this->execute_binary_comparison_operation($operand1, $operand_data, $operation, $stack);
                /** @var array<string, mixed> $r */
                $r = $stack->pop();
                $result[$x] = $r['value'];
            }
        } elseif (is_array($operand2) && is_array($operand1)) {
            // Operand 1 and Operand 2 are both arrays
            if (!$recursing_arrays) {
                self::check_matrix_operands($operand1, $operand2, 2);
            }
            foreach ($operand1 as $x => $operand_data) {
                $this->debug_log->write_debug_log('Evaluating Comparison %s %s %s', $this->show_value($operand_data), $operation, $this->show_value($operand2[$x]));
                $this->execute_binary_comparison_operation($operand_data, $operand2[$x], $operation, $stack, true);
                /** @var array<string, mixed> $r */
                $r = $stack->pop();
                $result[$x] = $r['value'];
            }
        } else {
            throw new Exception('Neither operand is an arra');
        }
        //    Log the result details
        $this->debug_log->write_debug_log('Comparison Evaluation Result is %s', $this->show_type_details($result));
        //    And push the result onto the stack
        $stack->push('Array', $result);
        return $result;
    }
    /** @return array<mixed>|bool|string */
    private function execute_binary_comparison_operation(mixed $operand1, mixed $operand2, string $operation, Stack &$stack, bool $recursing_arrays = false): array|bool|string
    {
        //    If we're dealing with matrix operations, we want a matrix result
        if (is_array($operand1) || is_array($operand2)) {
            return $this->execute_array_comparison($operand1, $operand2, $operation, $stack, $recursing_arrays);
        }
        $result = Binary_Comparison::compare($operand1, $operand2, $operation);
        //    Log the result details
        $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($result));
        //    And push the result onto the stack
        $stack->push('Value', $result);
        return $result;
    }
    private function execute_numeric_binary_operation(mixed $operand1, mixed $operand2, string $operation, Stack &$stack): mixed
    {
        //    Validate the two operands
        if ($this->validate_binary_operand($operand1, $stack) === false || $this->validate_binary_operand($operand2, $stack) === false) {
            return false;
        }
        if (Functions::get_compatibility_mode() != Functions::COMPATIBILITY_OPENOFFICE && (is_string($operand1) && !is_numeric($operand1) && $operand1 !== '' || is_string($operand2) && !is_numeric($operand2) && $operand2 !== '')) {
            $result = Excel_Error::VALUE();
        } elseif (is_array($operand1) || is_array($operand2)) {
            //    Ensure that both operands are arrays/matrices
            if (is_array($operand1)) {
                foreach ($operand1 as $key => $value) {
                    $operand1[$key] = Functions::flatten_array($value);
                }
            }
            if (is_array($operand2)) {
                foreach ($operand2 as $key => $value) {
                    $operand2[$key] = Functions::flatten_array($value);
                }
            }
            [$rows, $columns] = self::check_matrix_operands($operand1, $operand2, 3);
            for ($row = 0; $row < $rows; ++$row) {
                for ($column = 0; $column < $columns; ++$column) {
                    /** @var mixed[][] $operand1 */
                    if (($operand1[$row][$column] ?? null) === null) {
                        $operand1[$row][$column] = 0;
                    } elseif (!self::is_numeric_or_bool($operand1[$row][$column])) {
                        $operand1[$row][$column] = self::make_error($operand1[$row][$column]);
                        continue;
                    }
                    /** @var mixed[][] $operand2 */
                    if (($operand2[$row][$column] ?? null) === null) {
                        $operand2[$row][$column] = 0;
                    } elseif (!self::is_numeric_or_bool($operand2[$row][$column])) {
                        $operand1[$row][$column] = self::make_error($operand2[$row][$column]);
                        continue;
                    }
                    /** @var float|int */
                    $operand1Val = $operand1[$row][$column];
                    /** @var float|int */
                    $operand2Val = $operand2[$row][$column];
                    switch ($operation) {
                        case '+':
                            $operand1[$row][$column] = $operand1Val + $operand2Val;
                            break;
                        case '-':
                            $operand1[$row][$column] = $operand1Val - $operand2Val;
                            break;
                        case '*':
                            $operand1[$row][$column] = $operand1Val * $operand2Val;
                            break;
                        case '/':
                            if ($operand2Val == 0) {
                                $operand1[$row][$column] = Excel_Error::DIV0();
                            } else {
                                $operand1[$row][$column] = $operand1Val / $operand2Val;
                            }
                            break;
                        case '^':
                            $operand1[$row][$column] = $operand1Val ** $operand2Val;
                            break;
                        default:
                            throw new Exception('Unsupported numeric binary operation');
                    }
                }
            }
            $result = $operand1;
        } else {
            //    If we're dealing with non-matrix operations, execute the necessary operation
            /** @var float|int $operand1 */
            /** @var float|int $operand2 */
            switch ($operation) {
                //    Addition
                case '+':
                    $result = $operand1 + $operand2;
                    break;
                //    Subtraction
                case '-':
                    $result = $operand1 - $operand2;
                    break;
                //    Multiplication
                case '*':
                    $result = $operand1 * $operand2;
                    break;
                //    Division
                case '/':
                    if ($operand2 == 0) {
                        //    Trap for Divide by Zero error
                        $stack->push('Error', Excel_Error::DIV0());
                        $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details(Excel_Error::DIV0()));
                        return false;
                    }
                    $result = $operand1 / $operand2;
                    break;
                //    Power
                case '^':
                    $result = $operand1 ** $operand2;
                    break;
                default:
                    throw new Exception('Unsupported numeric binary operation');
            }
        }
        //    Log the result details
        $this->debug_log->write_debug_log('Evaluation Result is %s', $this->show_type_details($result));
        //    And push the result onto the stack
        $stack->push('Value', $result);
        return $result;
    }
    /**
     * Trigger an error, but nicely, if need be.
     *
     * @return false
     */
    protected function raise_formula_error(string $error_message, int $code = 0, ?Throwable $exception = null): bool
    {
        $this->formula_error = $error_message;
        $this->cyclic_reference_stack->clear();
        $suppress = $this->suppress_formula_errors;
        $suppressed = $suppress ? ' $suppressed' : '';
        $this->debug_log->write_debug_log("Raise Error{$suppressed} {$error_message}");
        if (!$suppress) {
            throw new Exception($error_message, $code, $exception);
        }
        return false;
    }
    /**
     * Extract range values.
     *
     * @param string $range String based range representation
     * @param ?Worksheet $worksheet Worksheet
     * @param bool $resetLog Flag indicating whether calculation log should be reset or not
     *
     * @return mixed[] Array of values in range if range contains more than one element. Otherwise, a single value is returned.
     */
    public function extract_cell_range(string &$range = 'A1', ?Worksheet $worksheet = null, bool $reset_log = true, bool $create_cell = false): array
    {
        // Return value
        /** @var mixed[][] */
        $return_value = [];
        if ($worksheet !== null) {
            $worksheet_name = $worksheet->get_title();
            if (str_contains($range, '!')) {
                [$worksheet_name, $range] = Worksheet::extract_sheet_title($range, true, true);
                $worksheet = $this->spreadsheet === null ? null : $this->spreadsheet->get_sheet_by_name($worksheet_name);
            }
            // Extract range
            $a_references = Coordinate::extract_all_cell_references_in_range($range);
            $range = "'" . $worksheet_name . "'" . '!' . $range;
            $current_col = '';
            $current_row = 0;
            if (!isset($a_references[1])) {
                //    Single cell in range
                sscanf($a_references[0], '%[A-Z]%d', $current_col, $current_row);
                /** @var string $currentCol */
                /** @var int $currentRow */
                if ($create_cell && $worksheet !== null && !$worksheet->cell_exists($a_references[0])) {
                    $worksheet->set_cell_value($a_references[0], null);
                }
                if ($worksheet !== null && $worksheet->cell_exists($a_references[0])) {
                    $temp = $worksheet->get_cell($a_references[0])->get_calculated_value($reset_log);
                    if ($this->get_instance_array_return_type() === self::RETURN_ARRAY_AS_ARRAY) {
                        while (is_array($temp)) {
                            $temp = array_shift($temp);
                        }
                    }
                    $return_value[$current_row][$current_col] = $temp;
                } else {
                    $return_value[$current_row][$current_col] = null;
                }
            } else {
                // Extract cell data for all cells in the range
                foreach ($a_references as $reference) {
                    // Extract range
                    sscanf($reference, '%[A-Z]%d', $current_col, $current_row);
                    /** @var string $currentCol */
                    /** @var int $currentRow */
                    if ($create_cell && $worksheet !== null && !$worksheet->cell_exists($reference)) {
                        $worksheet->set_cell_value($reference, null);
                    }
                    if ($worksheet !== null && $worksheet->cell_exists($reference)) {
                        $temp = $worksheet->get_cell($reference)->get_calculated_value($reset_log);
                        if ($this->get_instance_array_return_type() === self::RETURN_ARRAY_AS_ARRAY) {
                            while (is_array($temp)) {
                                $temp = array_shift($temp);
                            }
                        }
                        $return_value[$current_row][$current_col] = $temp;
                    } else {
                        $return_value[$current_row][$current_col] = null;
                    }
                }
            }
        }
        return $return_value;
    }
    /**
     * Extract range values.
     *
     * @param string $range String based range representation
     * @param null|Worksheet $worksheet Worksheet
     * @param bool $resetLog Flag indicating whether calculation log should be reset or not
     *
     * @return mixed[]|string Array of values in range if range contains more than one element. Otherwise, a single value is returned.
     */
    public function extract_named_range(string &$range = 'A1', ?Worksheet $worksheet = null, bool $reset_log = true): string|array
    {
        // Return value
        $return_value = [];
        if ($worksheet !== null) {
            if (str_contains($range, '!')) {
                [$worksheet_name, $range] = Worksheet::extract_sheet_title($range, true, true);
                $worksheet = $this->spreadsheet === null ? null : $this->spreadsheet->get_sheet_by_name($worksheet_name);
            }
            // Named range?
            $named_range = $worksheet === null ? null : Defined_Name::resolve_name($range, $worksheet);
            if ($named_range === null) {
                return Excel_Error::REF();
            }
            $worksheet = $named_range->get_worksheet();
            $range = $named_range->get_value();
            $split_range = Coordinate::split_range($range);
            //    Convert row and column references
            if ($worksheet !== null && ctype_alpha($split_range[0][0])) {
                $range = $split_range[0][0] . '1:' . $split_range[0][1] . $worksheet->get_highest_row();
            } elseif ($worksheet !== null && ctype_digit($split_range[0][0])) {
                $range = 'A' . $split_range[0][0] . ':' . $worksheet->get_highest_column() . $split_range[0][1];
            }
            // Extract range
            $a_references = Coordinate::extract_all_cell_references_in_range($range);
            if (!isset($a_references[1])) {
                //    Single cell (or single column or row) in range
                [$current_col, $current_row] = Coordinate::coordinate_from_string($a_references[0]);
                /** @var mixed[][] $returnValue */
                if ($worksheet !== null && $worksheet->cell_exists($a_references[0])) {
                    $return_value[$current_row][$current_col] = $worksheet->get_cell($a_references[0])->get_calculated_value($reset_log);
                } else {
                    $return_value[$current_row][$current_col] = null;
                }
            } else {
                // Extract cell data for all cells in the range
                foreach ($a_references as $reference) {
                    // Extract range
                    [$current_col, $current_row] = Coordinate::coordinate_from_string($reference);
                    if ($worksheet !== null && $worksheet->cell_exists($reference)) {
                        $return_value[$current_row][$current_col] = $worksheet->get_cell($reference)->get_calculated_value($reset_log);
                    } else {
                        $return_value[$current_row][$current_col] = null;
                    }
                }
            }
        }
        return $return_value;
    }
    /**
     * Is a specific function implemented?
     *
     * @param string $function Function Name
     */
    public function is_implemented(string $function): bool
    {
        $function = strtoupper($function);
        $php_spreadsheet_functions =& self::get_functions_address();
        $not_implemented = !isset($php_spreadsheet_functions[$function]) || is_array($php_spreadsheet_functions[$function]['functionCall']) && $php_spreadsheet_functions[$function]['functionCall'][1] === 'DUMMY';
        return !$not_implemented;
    }
    /**
     * Get a list of implemented Excel function names.
     *
     * @return string[]
     */
    public function get_implemented_function_names(): array
    {
        $return_value = [];
        $php_spreadsheet_functions =& self::get_functions_address();
        foreach ($php_spreadsheet_functions as $function_name => $function) {
            if ($this->is_implemented($function_name)) {
                $return_value[] = $function_name;
            }
        }
        return $return_value;
    }
    /**
     * @param string[] $functionCall
     * @param mixed[] $args
     * @param mixed[] $emptyArguments
     *
     * @return mixed[]
     */
    private function add_default_argument_values(array $function_call, array $args, array $empty_arguments): array
    {
        $reflector = new ReflectionMethod($function_call[0], $function_call[1]);
        $method_arguments = $reflector->get_parameters();
        if (count($method_arguments) > 0) {
            // Apply any defaults for empty argument values
            foreach ($empty_arguments as $argument_id => $is_argument_empty) {
                if ($is_argument_empty === true) {
                    $reflected_argument_id = count($args) - (int) $argument_id - 1;
                    if (!array_key_exists($reflected_argument_id, $method_arguments) || $method_arguments[$reflected_argument_id]->is_variadic()) {
                        break;
                    }
                    $args[$argument_id] = $this->get_argument_default_value($method_arguments[$reflected_argument_id]);
                }
            }
        }
        return $args;
    }
    private function get_argument_default_value(ReflectionParameter $method_argument): mixed
    {
        $default_value = null;
        if ($method_argument->is_default_value_available()) {
            $default_value = $method_argument->get_default_value();
            if ($method_argument->is_default_value_constant()) {
                $constant_name = $method_argument->get_default_value_constant_name() ?? '';
                // read constant value
                if (str_contains($constant_name, '::')) {
                    [$class_name, $constant_name] = explode('::', $constant_name);
                    /** @var class-string $className */
                    $constant_reflector = new Reflection_Class_Constant($class_name, $constant_name);
                    return $constant_reflector->get_value();
                }
                return constant($constant_name);
            }
        }
        return $default_value;
    }
    /**
     * Add cell reference if needed while making sure that it is the last argument.
     *
     * @param mixed[] $args
     * @param string|string[] $functionCall
     *
     * @return mixed[]
     */
    private function add_cell_reference(array $args, bool $pass_cell_reference, array|string $function_call, ?Cell $cell = null): array
    {
        if ($pass_cell_reference) {
            if (is_array($function_call)) {
                $class_name = $function_call[0];
                $method_name = $function_call[1];
                $reflection_method = new ReflectionMethod($class_name, $method_name);
                $argument_count = count($reflection_method->get_parameters());
                while (count($args) < $argument_count - 1) {
                    $args[] = null;
                }
            }
            $args[] = $cell;
        }
        return $args;
    }
    private function evaluate_defined_name(Cell $cell, Defined_Name $named_range, Worksheet $cell_worksheet, Stack $stack, bool $ignore_scope = false): mixed
    {
        $defined_name_scope = $named_range->get_scope();
        if ($defined_name_scope !== null && $defined_name_scope !== $cell_worksheet && !$ignore_scope) {
            // The defined name isn't in our current scope, so #REF
            $result = Excel_Error::REF();
            $stack->push('Error', $result, $named_range->get_name());
            return $result;
        }
        $defined_name_value = $named_range->get_value();
        $defined_name_type = $named_range->is_formula() ? 'Formula' : 'Range';
        if ($defined_name_type === 'Range') {
            if (Preg::is_match('/^(.*!)?(.*)$/', $defined_name_value, $matches)) {
                $matches2 = Preg::replace(['/ +/', '/,/'], [' ∩ ', ' ∪ '], trim((string) $matches[2]));
                $defined_name_value = $matches[1] . $matches2;
            }
        }
        $defined_name_worksheet = $named_range->get_worksheet();
        if ($defined_name_value[0] !== '=') {
            $defined_name_value = '=' . $defined_name_value;
        }
        $this->debug_log->write_debug_log('Defined Name is a %s with a value of %s', $defined_name_type, $defined_name_value);
        $original_coordinate = $cell->get_coordinate();
        $recursive_calculation_cell = $defined_name_type !== 'Formula' && $defined_name_worksheet !== null && $defined_name_worksheet !== $cell_worksheet ? $defined_name_worksheet->get_cell('A1') : $cell;
        $recursive_calculation_cell_address = $recursive_calculation_cell->get_coordinate();
        // Adjust relative references in ranges and formulae so that we execute the calculation for the correct rows and columns
        $defined_name_value = Reference_Helper::get_instance()->update_formula_references_any_worksheet($defined_name_value, Coordinate::column_index_from_string($cell->get_column()) - 1, $cell->get_row() - 1);
        $this->debug_log->write_debug_log('Value adjusted for relative references is %s', $defined_name_value);
        $recursive_calculator = new self($this->spreadsheet);
        $recursive_calculator->get_debug_log()->set_write_debug_log($this->get_debug_log()->get_write_debug_log());
        $recursive_calculator->get_debug_log()->set_echo_debug_log($this->get_debug_log()->get_echo_debug_log());
        $result = $recursive_calculator->_calculate_formula_value($defined_name_value, $recursive_calculation_cell_address, $recursive_calculation_cell, true);
        $cell_worksheet->get_cell($original_coordinate);
        if ($this->get_debug_log()->get_write_debug_log()) {
            $this->debug_log->merge_debug_log(array_slice($recursive_calculator->get_debug_log()->get_log(), 3));
            $this->debug_log->write_debug_log('Evaluation Result for Named %s %s is %s', $defined_name_type, $named_range->get_name(), $this->show_type_details($result));
        }
        $y = $named_range->get_worksheet()?->get_title();
        $x = $named_range->get_local_only();
        if ($x && $y !== null) {
            $stack->push('Defined Name', $result, "'{$y}'!" . $named_range->get_name());
        } else {
            $stack->push('Defined Name', $result, $named_range->get_name());
        }
        return $result;
    }
    public function set_suppress_formula_errors(bool $suppress_formula_errors): self
    {
        $this->suppress_formula_errors = $suppress_formula_errors;
        return $this;
    }
    public function get_suppress_formula_errors(): bool
    {
        return $this->suppress_formula_errors;
    }
    public static function bool_to_string(mixed $operand1): mixed
    {
        if (is_bool($operand1)) {
            $operand1 = $operand1 ? self::$locale_boolean['TRUE'] : self::$locale_boolean['FALSE'];
        } elseif ($operand1 === null) {
            $operand1 = '';
        }
        return $operand1;
    }
    private static function is_numeric_or_bool(mixed $operand): bool
    {
        return is_numeric($operand) || is_bool($operand);
    }
    private static function make_error(mixed $operand = ''): string
    {
        return is_string($operand) && Information\Error_Value::is_error($operand) ? $operand : Excel_Error::VALUE();
    }
    private static function swap_operands(Stack $stack, string $op_character): bool
    {
        $ret_val = false;
        if ($stack->count() > 0) {
            $o2 = $stack->last();
            if ($o2) {
                /** @var array{value: string} $o2 */
                if (isset(self::CALCULATION_OPERATORS[$o2['value']])) {
                    $ret_val = (self::OPERATOR_PRECEDENCE[$op_character] ?? 0) <= self::OPERATOR_PRECEDENCE[$o2['value']];
                }
            }
        }
        return $ret_val;
    }
    public function get_spreadsheet(): ?Spreadsheet
    {
        return $this->spreadsheet;
    }
}