<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use DateTime;
use DateTimeZone;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Internal\Wildcard_Match;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Cell_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column\Rule;
use Stringable;
use Throwable;
class Auto_Filter implements Stringable
{
    /**
     * Autofilter Range.
     */
    private string $range;
    /**
     * Autofilter Column Ruleset.
     *
     * @var AutoFilter\Column[]
     */
    private array $columns = [];
    private bool $evaluated = false;
    public function get_evaluated(): bool
    {
        return $this->evaluated;
    }
    public function set_evaluated(bool $value): void
    {
        $this->evaluated = $value;
    }
    /**
     * Create a new AutoFilter.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range
     *            A simple string containing a Cell range like 'A1:E10' is permitted
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange object.
     */
    public function __construct(
        Address_Range|string|array $range = '',
        /**
         * Autofilter Worksheet.
         */
        private ?Worksheet $work_sheet = null
    )
    {
        if ($range !== '') {
            [, $range] = Worksheet::extract_sheet_title(Validations::validate_cell_range($range), true);
        }
        $this->range = $range ?? '';
    }
    public function __destruct()
    {
        $this->work_sheet = null;
    }
    /**
     * Get AutoFilter Parent Worksheet.
     */
    public function get_parent(): null|Worksheet
    {
        return $this->work_sheet;
    }
    /**
     * Set AutoFilter Parent Worksheet.
     *
     * @return $this
     */
    public function set_parent(?Worksheet $worksheet = null): static
    {
        $this->evaluated = false;
        $this->work_sheet = $worksheet;
        return $this;
    }
    /**
     * Get AutoFilter Range.
     */
    public function get_range(): string
    {
        return $this->range;
    }
    /**
     * Set AutoFilter Cell Range.
     *
     * @param AddressRange<CellRange>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range
     *            A simple string containing a Cell range like 'A1:E10' or a Cell address like 'A1' is permitted
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange object.
     */
    public function set_range(Address_Range|string|array $range = ''): self
    {
        $this->evaluated = false;
        // extract coordinate
        if ($range !== '') {
            [, $range] = Worksheet::extract_sheet_title(Validations::validate_cell_range($range), true);
        }
        if (empty($range)) {
            //    Discard all column rules
            $this->columns = [];
            $this->range = '';
            return $this;
        }
        if (ctype_digit($range) || ctype_alpha($range)) {
            throw new Exception("{$range} is an invalid range for AutoFilter");
        }
        $this->range = $range;
        //    Discard any column rules that are no longer valid within this range
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        foreach ($this->columns as $key => $value) {
            $col_index = Coordinate::column_index_from_string($key);
            if ($range_start[0] > $col_index || $range_end[0] < $col_index) {
                unset($this->columns[$key]);
            }
        }
        return $this;
    }
    public function set_range_to_max_row(): self
    {
        $this->evaluated = false;
        if ($this->work_sheet !== null) {
            $thisrange = $this->range;
            $range = (string) preg_replace('/\d+$/', (string) $this->work_sheet->get_highest_row(), $thisrange);
            if ($range !== $thisrange) {
                $this->set_range($range);
            }
        }
        return $this;
    }
    /**
     * Get all AutoFilter Columns.
     *
     * @return AutoFilter\Column[]
     */
    public function get_columns(): array
    {
        return $this->columns;
    }
    /**
     * Validate that the specified column is in the AutoFilter range.
     *
     * @param string $column Column name (e.g. A)
     *
     * @return int The column offset within the autofilter range
     */
    public function test_column_in_range(string $column): int
    {
        if (empty($this->range)) {
            throw new Exception('No autofilter range is defined.');
        }
        $column_index = Coordinate::column_index_from_string($column);
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        if ($range_start[0] > $column_index || $range_end[0] < $column_index) {
            throw new Exception('Column is outside of current autofilter range.');
        }
        return $column_index - $range_start[0];
    }
    /**
     * Get a specified AutoFilter Column Offset within the defined AutoFilter range.
     *
     * @param string $column Column name (e.g. A)
     *
     * @return int The offset of the specified column within the autofilter range
     */
    public function get_column_offset(string $column): int
    {
        return $this->test_column_in_range($column);
    }
    /**
     * Get a specified AutoFilter Column.
     *
     * @param string $column Column name (e.g. A)
     */
    public function get_column(string $column): Auto_Filter\Column
    {
        $this->test_column_in_range($column);
        if (!isset($this->columns[$column])) {
            $this->columns[$column] = new Auto_Filter\Column($column, $this);
        }
        return $this->columns[$column];
    }
    /**
     * Get a specified AutoFilter Column by its offset.
     *
     * @param int $columnOffset Column offset within range (starting from 0)
     */
    public function get_column_by_offset(int $column_offset): Auto_Filter\Column
    {
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        $p_column = Coordinate::string_from_column_index($range_start[0] + $column_offset);
        return $this->get_column($p_column);
    }
    /**
     * Set AutoFilter.
     *
     * @param AutoFilter\Column|string $columnObjectOrString
     *            A simple string containing a Column ID like 'A' is permitted
     *
     * @return $this
     */
    public function set_column(Auto_Filter\Column|string $column_object_or_string): static
    {
        $this->evaluated = false;
        if (is_string($column_object_or_string) && !empty($column_object_or_string)) {
            $column = $column_object_or_string;
        } elseif ($column_object_or_string instanceof Auto_Filter\Column) {
            $column = $column_object_or_string->get_column_index();
        } else {
            throw new Exception('Column is not within the autofilter range.');
        }
        $this->test_column_in_range($column);
        if (is_string($column_object_or_string)) {
            $this->columns[$column_object_or_string] = new Auto_Filter\Column($column_object_or_string, $this);
        } else {
            $column_object_or_string->set_parent($this);
            $this->columns[$column] = $column_object_or_string;
        }
        ksort($this->columns);
        return $this;
    }
    /**
     * Clear a specified AutoFilter Column.
     *
     * @param string $column Column name (e.g. A)
     *
     * @return $this
     */
    public function clear_column(string $column): static
    {
        $this->evaluated = false;
        $this->test_column_in_range($column);
        if (isset($this->columns[$column])) {
            unset($this->columns[$column]);
        }
        return $this;
    }
    /**
     * Shift an AutoFilter Column Rule to a different column.
     *
     * Note: This method bypasses validation of the destination column to ensure it is within this AutoFilter range.
     *        Nor does it verify whether any column rule already exists at $toColumn, but will simply override any existing value.
     *        Use with caution.
     *
     * @param string $fromColumn Column name (e.g. A)
     * @param string $toColumn Column name (e.g. B)
     *
     * @return $this
     */
    public function shift_column(string $from_column, string $to_column): static
    {
        $this->evaluated = false;
        $from_column = strtoupper($from_column);
        $to_column = strtoupper($to_column);
        if (isset($this->columns[$from_column])) {
            $this->columns[$from_column]->set_parent();
            $this->columns[$from_column]->set_column_index($to_column);
            $this->columns[$to_column] = $this->columns[$from_column];
            $this->columns[$to_column]->set_parent($this);
            unset($this->columns[$from_column]);
            ksort($this->columns);
        }
        return $this;
    }
    /**
     * Test if cell value is in the defined set of values.
     *
     * @param array{blanks: bool, filterValues: array<string,array<string,string>>} $dataSet
     */
    protected static function filter_test_in_simple_data_set(mixed $cell_value, array $data_set): bool
    {
        $data_set_values = $data_set['filterValues'];
        $blanks = $data_set['blanks'];
        if ($cell_value === '' || $cell_value === null) {
            return $blanks;
        }
        return in_array($cell_value, $data_set_values);
    }
    /**
     * Test if cell value is in the defined set of Excel date values.
     *
     * @param array{blanks: bool, filterValues: array<string,array<string,string>>} $dataSet
     */
    protected static function filter_test_in_date_group_set(mixed $cell_value, array $data_set): bool
    {
        $date_set = $data_set['filterValues'];
        $blanks = $data_set['blanks'];
        if ($cell_value === '' || $cell_value === null) {
            return $blanks;
        }
        $time_zone = new DateTimeZone('UTC');
        if (is_numeric($cell_value)) {
            try {
                $date_time = Date::excel_to_date_time_object((float) $cell_value, $time_zone);
            } catch (Throwable) {
                return false;
            }
            $cell_value = (float) $cell_value;
            if ($cell_value < 1) {
                //    Just the time part
                $dt_val = $date_time->format('His');
                $date_set = $date_set['time'];
            } elseif ($cell_value == floor($cell_value)) {
                //    Just the date part
                $dt_val = $date_time->format('Ymd');
                $date_set = $date_set['date'];
            } else {
                //    date and time parts
                $dt_val = $date_time->format('YmdHis');
                $date_set = $date_set['dateTime'];
            }
            foreach ($date_set as $date_value) {
                //    Use of substr to extract value at the appropriate group level
                if (str_starts_with($dt_val, $date_value)) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Test if cell value is within a set of values defined by a ruleset.
     *
     * @param mixed[][] $ruleSet
     */
    protected static function filter_test_in_custom_data_set(mixed $cell_value, array $rule_set): bool
    {
        $data_set = $rule_set['filterRules'];
        $join = $rule_set['join'];
        $custom_rule_for_blanks = $rule_set['customRuleForBlanks'] ?? false;
        if (!$custom_rule_for_blanks) {
            //    Blank cells are always ignored, so return a FALSE
            if ($cell_value === '' || $cell_value === null) {
                return false;
            }
        }
        $return_val = $join == Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_AND;
        foreach ($data_set as $rule) {
            /** @var string[] $rule */
            $rule_value = $rule['value'];
            $rule_operator = $rule['operator'];
            /** @var string */
            $cell_value_string = $cell_value ?? '';
            $ret_val = false;
            if (is_numeric($rule_value)) {
                //    Numeric values are tested using the appropriate operator
                $numeric_test = is_numeric($cell_value);
                switch ($rule_operator) {
                    case Rule::AUTOFILTER_COLUMN_RULE_EQUAL:
                        $ret_val = $numeric_test && $cell_value == $rule_value;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_NOTEQUAL:
                        $ret_val = !$numeric_test || $cell_value != $rule_value;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_GREATERTHAN:
                        $ret_val = $numeric_test && $cell_value > $rule_value;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL:
                        $ret_val = $numeric_test && $cell_value >= $rule_value;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_LESSTHAN:
                        $ret_val = $numeric_test && $cell_value < $rule_value;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_LESSTHANOREQUAL:
                        $ret_val = $numeric_test && $cell_value <= $rule_value;
                        break;
                }
            } elseif ($rule_value == '') {
                $ret_val = match ($rule_operator) {
                    Rule::AUTOFILTER_COLUMN_RULE_EQUAL => $cell_value === '' || $cell_value === null,
                    Rule::AUTOFILTER_COLUMN_RULE_NOTEQUAL => $cell_value != '',
                    default => true,
                };
            } else {
                //    String values are always tested for equality, factoring in for wildcards (hence a regexp test)
                switch ($rule_operator) {
                    case Rule::AUTOFILTER_COLUMN_RULE_EQUAL:
                        $ret_val = (bool) preg_match('/^' . $rule_value . '$/i', $cell_value_string);
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_NOTEQUAL:
                        $ret_val = !(bool) preg_match('/^' . $rule_value . '$/i', $cell_value_string);
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_GREATERTHAN:
                        $ret_val = strcasecmp($cell_value_string, $rule_value) > 0;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL:
                        $ret_val = strcasecmp($cell_value_string, $rule_value) >= 0;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_LESSTHAN:
                        $ret_val = strcasecmp($cell_value_string, $rule_value) < 0;
                        break;
                    case Rule::AUTOFILTER_COLUMN_RULE_LESSTHANOREQUAL:
                        $ret_val = strcasecmp($cell_value_string, $rule_value) <= 0;
                        break;
                }
            }
            //    If there are multiple conditions, then we need to test both using the appropriate join operator
            switch ($join) {
                case Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_OR:
                    $return_val = $return_val || $ret_val;
                    //    Break as soon as we have a TRUE match for OR joins,
                    //        to avoid unnecessary additional code execution
                    if ($return_val) {
                        return $return_val;
                    }
                    break;
                case Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_AND:
                    $return_val = $return_val && $ret_val;
                    break;
            }
        }
        return $return_val;
    }
    /**
     * Test if cell date value is matches a set of values defined by a set of months.
     *
     * @param mixed[] $monthSet
     */
    protected static function filter_test_in_period_date_set(mixed $cell_value, array $month_set): bool
    {
        //    Blank cells are always ignored, so return a FALSE
        if ($cell_value === '' || $cell_value === null) {
            return false;
        }
        if (is_numeric($cell_value)) {
            try {
                $date_object = Date::excel_to_date_time_object((float) $cell_value, new DateTimeZone('UTC'));
            } catch (Throwable) {
                return false;
            }
            $date_value = (int) $date_object->format('m');
            if (in_array($date_value, $month_set)) {
                return true;
            }
        }
        return false;
    }
    private static function make_date_object(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): DateTime
    {
        $base_date = new DateTime();
        $base_date->set_date($year, $month, $day);
        $base_date->set_time($hour, $minute, $second);
        return $base_date;
    }
    private const DATE_FUNCTIONS = [Rule::AUTOFILTER_RULETYPE_DYNAMIC_LASTMONTH => 'dynamicLastMonth', Rule::AUTOFILTER_RULETYPE_DYNAMIC_LASTQUARTER => 'dynamicLastQuarter', Rule::AUTOFILTER_RULETYPE_DYNAMIC_LASTWEEK => 'dynamicLastWeek', Rule::AUTOFILTER_RULETYPE_DYNAMIC_LASTYEAR => 'dynamicLastYear', Rule::AUTOFILTER_RULETYPE_DYNAMIC_NEXTMONTH => 'dynamicNextMonth', Rule::AUTOFILTER_RULETYPE_DYNAMIC_NEXTQUARTER => 'dynamicNextQuarter', Rule::AUTOFILTER_RULETYPE_DYNAMIC_NEXTWEEK => 'dynamicNextWeek', Rule::AUTOFILTER_RULETYPE_DYNAMIC_NEXTYEAR => 'dynamicNextYear', Rule::AUTOFILTER_RULETYPE_DYNAMIC_THISMONTH => 'dynamicThisMonth', Rule::AUTOFILTER_RULETYPE_DYNAMIC_THISQUARTER => 'dynamicThisQuarter', Rule::AUTOFILTER_RULETYPE_DYNAMIC_THISWEEK => 'dynamicThisWeek', Rule::AUTOFILTER_RULETYPE_DYNAMIC_THISYEAR => 'dynamicThisYear', Rule::AUTOFILTER_RULETYPE_DYNAMIC_TODAY => 'dynamicToday', Rule::AUTOFILTER_RULETYPE_DYNAMIC_TOMORROW => 'dynamicTomorrow', Rule::AUTOFILTER_RULETYPE_DYNAMIC_YEARTODATE => 'dynamicYearToDate', Rule::AUTOFILTER_RULETYPE_DYNAMIC_YESTERDAY => 'dynamicYesterday'];
    /** @return array{DateTime, DateTime} */
    private static function dynamic_last_month(): array
    {
        $maxval = new DateTime();
        $year = (int) $maxval->format('Y');
        $month = (int) $maxval->format('m');
        $maxval->set_date($year, $month, 1);
        $maxval->set_time(0, 0, 0);
        $val = clone $maxval;
        $val->modify('-1 month');
        return [$val, $maxval];
    }
    private static function first_day_of_quarter(): DateTime
    {
        $val = new DateTime();
        $year = (int) $val->format('Y');
        $month = (int) $val->format('m');
        $month = 3 * intdiv($month - 1, 3) + 1;
        $val->set_date($year, $month, 1);
        $val->set_time(0, 0, 0);
        return $val;
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_last_quarter(): array
    {
        $maxval = self::first_day_of_quarter();
        $val = clone $maxval;
        $val->modify('-3 months');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_last_week(): array
    {
        $val = new DateTime();
        $val->set_time(0, 0, 0);
        $day_of_week = (int) $val->format('w');
        // Sunday is 0
        $subtract = $day_of_week + 7;
        // revert to prior Sunday
        $val->modify("-{$subtract} days");
        $maxval = clone $val;
        $maxval->modify('+7 days');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_last_year(): array
    {
        $val = new DateTime();
        $year = (int) $val->format('Y');
        $val = self::make_date_object($year - 1, 1, 1);
        $maxval = self::make_date_object($year, 1, 1);
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_next_month(): array
    {
        $val = new DateTime();
        $year = (int) $val->format('Y');
        $month = (int) $val->format('m');
        $val->set_date($year, $month, 1);
        $val->set_time(0, 0, 0);
        $val->modify('+1 month');
        $maxval = clone $val;
        $maxval->modify('+1 month');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_next_quarter(): array
    {
        $val = self::first_day_of_quarter();
        $val->modify('+3 months');
        $maxval = clone $val;
        $maxval->modify('+3 months');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_next_week(): array
    {
        $val = new DateTime();
        $val->set_time(0, 0, 0);
        $day_of_week = (int) $val->format('w');
        // Sunday is 0
        $add = 7 - $day_of_week;
        // move to next Sunday
        $val->modify("+{$add} days");
        $maxval = clone $val;
        $maxval->modify('+7 days');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_next_year(): array
    {
        $val = new DateTime();
        $year = (int) $val->format('Y');
        $val = self::make_date_object($year + 1, 1, 1);
        $maxval = self::make_date_object($year + 2, 1, 1);
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_this_month(): array
    {
        $base_date = new DateTime();
        $base_date->set_time(0, 0, 0);
        $year = (int) $base_date->format('Y');
        $month = (int) $base_date->format('m');
        $val = self::make_date_object($year, $month, 1);
        $maxval = clone $val;
        $maxval->modify('+1 month');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_this_quarter(): array
    {
        $val = self::first_day_of_quarter();
        $maxval = clone $val;
        $maxval->modify('+3 months');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_this_week(): array
    {
        $val = new DateTime();
        $val->set_time(0, 0, 0);
        $day_of_week = (int) $val->format('w');
        // Sunday is 0
        $subtract = $day_of_week;
        // revert to Sunday
        $val->modify("-{$subtract} days");
        $maxval = clone $val;
        $maxval->modify('+7 days');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_this_year(): array
    {
        $val = new DateTime();
        $year = (int) $val->format('Y');
        $val = self::make_date_object($year, 1, 1);
        $maxval = self::make_date_object($year + 1, 1, 1);
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_today(): array
    {
        $val = new DateTime();
        $val->set_time(0, 0, 0);
        $maxval = clone $val;
        $maxval->modify('+1 day');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_tomorrow(): array
    {
        $val = new DateTime();
        $val->set_time(0, 0, 0);
        $val->modify('+1 day');
        $maxval = clone $val;
        $maxval->modify('+1 day');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_year_to_date(): array
    {
        $maxval = new DateTime();
        $maxval->set_time(0, 0, 0);
        $val = self::make_date_object((int) $maxval->format('Y'), 1, 1);
        $maxval->modify('+1 day');
        return [$val, $maxval];
    }
    /** @return array{DateTime, DateTime} */
    private static function dynamic_yesterday(): array
    {
        $maxval = new DateTime();
        $maxval->set_time(0, 0, 0);
        $val = clone $maxval;
        $val->modify('-1 day');
        return [$val, $maxval];
    }
    /**
     * Convert a dynamic rule daterange to a custom filter range expression for ease of calculation.
     *
     * @return mixed[]
     */
    private function dynamic_filter_date_range(string $dynamic_rule_type, Auto_Filter\Column &$filter_column): array
    {
        $rule_values = [];
        $call_back = [self::class, self::DATE_FUNCTIONS[$dynamic_rule_type]];
        // What if not found?
        //    Calculate start/end dates for the required date range based on current date
        //    Val is lowest permitted value.
        //    Maxval is greater than highest permitted value
        $val = $maxval = 0;
        if (is_callable($call_back)) {
            //* @phpstan-ignore-line
            [$val, $maxval] = $call_back();
        }
        $val = Date::date_time_to_excel($val);
        $maxval = Date::date_time_to_excel($maxval);
        //    Set the filter column rule attributes ready for writing
        $filter_column->set_attributes(['val' => $val, 'maxVal' => $maxval]);
        //    Set the rules for identifying rows for hide/show
        $rule_values[] = ['operator' => Rule::AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL, 'value' => $val];
        $rule_values[] = ['operator' => Rule::AUTOFILTER_COLUMN_RULE_LESSTHAN, 'value' => $maxval];
        return ['method' => 'filterTestInCustomDataSet', 'arguments' => ['filterRules' => $rule_values, 'join' => Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_AND]];
    }
    /**
     * Apply the AutoFilter rules to the AutoFilter Range.
     */
    private function calculate_top_ten_value(string $column_id, int $start_row, int $end_row, ?string $rule_type, mixed $rule_value): mixed
    {
        $range = $column_id . $start_row . ':' . $column_id . $end_row;
        $ret_val = null;
        if ($this->work_sheet !== null) {
            $data_values = Functions::flatten_array($this->work_sheet->range_to_array($range, null, true, false));
            $data_values = array_filter($data_values);
            if ($rule_type == Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_TOP) {
                rsort($data_values);
            } else {
                sort($data_values);
            }
            if (is_numeric($rule_value)) {
                $rule_value = (int) $rule_value;
            }
            if ($rule_value === null || is_int($rule_value)) {
                $slice = array_slice($data_values, 0, $rule_value);
                $ret_val = array_pop($slice);
            }
        }
        return $ret_val;
    }
    /**
     * Apply the AutoFilter rules to the AutoFilter Range.
     *
     * @return $this
     */
    public function show_hide_rows(): static
    {
        if ($this->work_sheet === null) {
            return $this;
        }
        [$range_start, $range_end] = Coordinate::range_boundaries($this->range);
        //    The heading row should always be visible
        $this->work_sheet->get_row_dimension($range_start[1])->set_visible(true);
        $column_filter_tests = [];
        foreach ($this->columns as $column_id => $filter_column) {
            $rules = $filter_column->get_rules();
            switch ($filter_column->get_filter_type()) {
                case Auto_Filter\Column::AUTOFILTER_FILTERTYPE_FILTER:
                    $rule_type = null;
                    $rule_values = [];
                    //    Build a list of the filter value selections
                    foreach ($rules as $rule) {
                        $rule_type = $rule->get_rule_type();
                        $rule_values[] = $rule->get_value();
                    }
                    //    Test if we want to include blanks in our filter criteria
                    $blanks = false;
                    $rule_data_set = array_filter($rule_values);
                    if (count($rule_values) != count($rule_data_set)) {
                        $blanks = true;
                    }
                    if ($rule_type == Rule::AUTOFILTER_RULETYPE_FILTER) {
                        //    Filter on absolute values
                        $column_filter_tests[$column_id] = ['method' => 'filterTestInSimpleDataSet', 'arguments' => ['filterValues' => $rule_data_set, 'blanks' => $blanks]];
                    } elseif ($rule_type !== null) {
                        //    Filter on date group values
                        $arguments = ['date' => [], 'time' => [], 'dateTime' => []];
                        foreach ($rule_data_set as $rule_value) {
                            if (!is_array($rule_value)) {
                                continue;
                            }
                            $date = $time = '';
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_YEAR]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_YEAR] !== '') {
                                $date .= sprintf('%04d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_YEAR]);
                            }
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MONTH]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MONTH] != '') {
                                $date .= sprintf('%02d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MONTH]);
                            }
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_DAY]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_DAY] !== '') {
                                $date .= sprintf('%02d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_DAY]);
                            }
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_HOUR]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_HOUR] !== '') {
                                $time .= sprintf('%02d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_HOUR]);
                            }
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MINUTE]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MINUTE] !== '') {
                                $time .= sprintf('%02d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_MINUTE]);
                            }
                            if (isset($rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_SECOND]) && $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_SECOND] !== '') {
                                $time .= sprintf('%02d', $rule_value[Rule::AUTOFILTER_RULETYPE_DATEGROUP_SECOND]);
                            }
                            $date_time = $date . $time;
                            $arguments['date'][] = $date;
                            $arguments['time'][] = $time;
                            $arguments['dateTime'][] = $date_time;
                        }
                        //    Remove empty elements
                        $arguments['date'] = array_filter($arguments['date']);
                        $arguments['time'] = array_filter($arguments['time']);
                        $arguments['dateTime'] = array_filter($arguments['dateTime']);
                        $column_filter_tests[$column_id] = ['method' => 'filterTestInDateGroupSet', 'arguments' => ['filterValues' => $arguments, 'blanks' => $blanks]];
                    }
                    break;
                case Auto_Filter\Column::AUTOFILTER_FILTERTYPE_CUSTOMFILTER:
                    $custom_rule_for_blanks = true;
                    $rule_values = [];
                    //    Build a list of the filter value selections
                    foreach ($rules as $rule) {
                        $rule_value = $rule->get_value();
                        if (!is_array($rule_value) && !is_numeric($rule_value)) {
                            //    Convert to a regexp allowing for regexp reserved characters, wildcards and escaped wildcards
                            $rule_value = Wildcard_Match::wildcard($rule_value);
                            if (trim($rule_value) == '') {
                                $custom_rule_for_blanks = true;
                                $rule_value = trim($rule_value);
                            }
                        }
                        $rule_values[] = ['operator' => $rule->get_operator(), 'value' => $rule_value];
                    }
                    $join = $filter_column->get_join();
                    $column_filter_tests[$column_id] = ['method' => 'filterTestInCustomDataSet', 'arguments' => ['filterRules' => $rule_values, 'join' => $join, 'customRuleForBlanks' => $custom_rule_for_blanks]];
                    break;
                case Auto_Filter\Column::AUTOFILTER_FILTERTYPE_DYNAMICFILTER:
                    $rule_values = [];
                    foreach ($rules as $rule) {
                        //    We should only ever have one Dynamic Filter Rule anyway
                        $dynamic_rule_type = $rule->get_grouping();
                        if ($dynamic_rule_type == Rule::AUTOFILTER_RULETYPE_DYNAMIC_ABOVEAVERAGE || $dynamic_rule_type == Rule::AUTOFILTER_RULETYPE_DYNAMIC_BELOWAVERAGE) {
                            //    Number (Average) based
                            //    Calculate the average
                            $average_formula = '=AVERAGE(' . $column_id . ($range_start[1] + 1) . ':' . $column_id . $range_end[1] . ')';
                            $average = Calculation::get_instance($this->work_sheet->get_parent())->calculate_formula($average_formula, null, $this->work_sheet->get_cell('A1'));
                            while (is_array($average)) {
                                $average = array_pop($average);
                            }
                            //    Set above/below rule based on greaterThan or LessTan
                            $operator = $dynamic_rule_type === Rule::AUTOFILTER_RULETYPE_DYNAMIC_ABOVEAVERAGE ? Rule::AUTOFILTER_COLUMN_RULE_GREATERTHAN : Rule::AUTOFILTER_COLUMN_RULE_LESSTHAN;
                            $rule_values[] = ['operator' => $operator, 'value' => $average];
                            $column_filter_tests[$column_id] = ['method' => 'filterTestInCustomDataSet', 'arguments' => ['filterRules' => $rule_values, 'join' => Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_OR]];
                        } else if ($dynamic_rule_type[0] == 'M' || $dynamic_rule_type[0] == 'Q') {
                            $period_type = '';
                            $period = 0;
                            //    Month or Quarter
                            sscanf($dynamic_rule_type, '%[A-Z]%d', $period_type, $period);
                            if ($period_type == 'M') {
                                $rule_values = [$period];
                            } else {
                                /** @var int $period */
                                --$period;
                                $period_end = (1 + $period) * 3;
                                $period_start = 1 + $period * 3;
                                $rule_values = range($period_start, $period_end);
                            }
                            $column_filter_tests[$column_id] = ['method' => 'filterTestInPeriodDateSet', 'arguments' => $rule_values];
                            $filter_column->set_attributes([]);
                        } else {
                            //    Date Range
                            $column_filter_tests[$column_id] = $this->dynamic_filter_date_range($dynamic_rule_type, $filter_column);
                            break;
                        }
                    }
                    break;
                case Auto_Filter\Column::AUTOFILTER_FILTERTYPE_TOPTENFILTER:
                    $rule_values = [];
                    $data_row_count = $range_end[1] - $range_start[1];
                    $topten_rule_type = null;
                    $rule_value = 0;
                    $rule_operator = null;
                    foreach ($rules as $rule) {
                        //    We should only ever have one Dynamic Filter Rule anyway
                        $topten_rule_type = $rule->get_grouping();
                        $rule_value = $rule->get_value();
                        $rule_operator = $rule->get_operator();
                    }
                    if (is_numeric($rule_value) && $rule_operator === Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_PERCENT) {
                        $rule_value = (int) floor((float) $rule_value * ($data_row_count / 100));
                    }
                    if (!is_array($rule_value) && $rule_value < 1) {
                        $rule_value = 1;
                    }
                    if (!is_array($rule_value) && $rule_value > 500) {
                        $rule_value = 500;
                    }
                    /** @var float|int|string */
                    $max_val = $this->calculate_top_ten_value($column_id, $range_start[1] + 1, (int) $range_end[1], $topten_rule_type, $rule_value);
                    $operator = $topten_rule_type == Rule::AUTOFILTER_COLUMN_RULE_TOPTEN_TOP ? Rule::AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL : Rule::AUTOFILTER_COLUMN_RULE_LESSTHANOREQUAL;
                    $rule_values[] = ['operator' => $operator, 'value' => $max_val];
                    $column_filter_tests[$column_id] = ['method' => 'filterTestInCustomDataSet', 'arguments' => ['filterRules' => $rule_values, 'join' => Auto_Filter\Column::AUTOFILTER_COLUMN_JOIN_OR]];
                    $filter_column->set_attributes(['maxVal' => $max_val]);
                    break;
            }
        }
        $range_end[1] = $this->auto_extend_range($range_start[1], $range_end[1]);
        //    Execute the column tests for each row in the autoFilter range to determine show/hide,
        for ($row = $range_start[1] + 1; $row <= $range_end[1]; ++$row) {
            $result = true;
            foreach ($column_filter_tests as $column_id => $column_filter_test) {
                $cell_value = $this->work_sheet->get_cell($column_id . $row)->get_calculated_value();
                //    Execute the filter test
                /** @var callable */
                $temp = [self::class, $column_filter_test['method']];
                /** @var bool */
                $result = call_user_func_array($temp, [$cell_value, $column_filter_test['arguments']]);
                //    If filter test has resulted in FALSE, exit the loop straightaway rather than running any more tests
                if (!$result) {
                    break;
                }
            }
            //    Set show/hide for the row based on the result of the autoFilter result
            //    If the RowDimension object has not been allocated yet and the row should be visible,
            //    then we can avoid any operation since the rows are visible by default (saves a lot of memory)
            if ($result === false || $this->work_sheet->row_dimension_exists((int) $row)) {
                $this->work_sheet->get_row_dimension((int) $row)->set_visible($result)->set_visible_after_filter($result);
            }
        }
        $this->evaluated = true;
        return $this;
    }
    /**
     * Magic Range Auto-sizing.
     * For a single row rangeSet, we follow MS Excel rules, and search for the first empty row to determine our range.
     */
    public function auto_extend_range(int $start_row, int $end_row): int
    {
        if ($start_row === $end_row && $this->work_sheet !== null) {
            try {
                $row_iterator = $this->work_sheet->get_row_iterator($start_row + 1);
            } catch (Exception) {
                // If there are no rows below $startRow
                return $start_row;
            }
            foreach ($row_iterator as $row) {
                if ($row->is_empty(Cell_Iterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | Cell_Iterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL) === true) {
                    return $row->get_row_index() - 1;
                }
            }
        }
        return $end_row;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                if ($key === 'workSheet') {
                    //    Detach from worksheet
                    $this->{$key} = null;
                } else {
                    $this->{$key} = clone $value;
                }
            } elseif (is_array($value) && $key == 'columns') {
                //    The columns array of \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet\AutoFilter objects
                $this->{$key} = [];
                foreach ($value as $k => $v) {
                    $this->{$key}[$k] = clone $v;
                    //* @phpstan-ignore-line
                    // attach the new cloned Column to this new cloned Autofilter object
                    $this->{$key}[$k]->set_parent($this);
                    //* @phpstan-ignore-line
                }
            } else {
                $this->{$key} = $value;
            }
        }
    }
    /**
     * toString method replicates previous behavior by returning the range if object is
     * referenced as a property of its parent.
     */
    public function __toString(): string
    {
        return $this->range;
    }
}