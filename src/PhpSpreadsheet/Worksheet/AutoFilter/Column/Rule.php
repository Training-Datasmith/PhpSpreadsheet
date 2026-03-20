<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter\Column;
class Rule
{
    public const AUTOFILTER_RULETYPE_FILTER = 'filter';
    public const AUTOFILTER_RULETYPE_DATEGROUP = 'dateGroupItem';
    public const AUTOFILTER_RULETYPE_CUSTOMFILTER = 'customFilter';
    public const AUTOFILTER_RULETYPE_DYNAMICFILTER = 'dynamicFilter';
    public const AUTOFILTER_RULETYPE_TOPTENFILTER = 'top10Filter';
    private const RULE_TYPES = [
        //    Currently we're not handling
        //        colorFilter
        //        extLst
        //        iconFilter
        self::AUTOFILTER_RULETYPE_FILTER,
        self::AUTOFILTER_RULETYPE_DATEGROUP,
        self::AUTOFILTER_RULETYPE_CUSTOMFILTER,
        self::AUTOFILTER_RULETYPE_DYNAMICFILTER,
        self::AUTOFILTER_RULETYPE_TOPTENFILTER,
    ];
    public const AUTOFILTER_RULETYPE_DATEGROUP_YEAR = 'year';
    public const AUTOFILTER_RULETYPE_DATEGROUP_MONTH = 'month';
    public const AUTOFILTER_RULETYPE_DATEGROUP_DAY = 'day';
    public const AUTOFILTER_RULETYPE_DATEGROUP_HOUR = 'hour';
    public const AUTOFILTER_RULETYPE_DATEGROUP_MINUTE = 'minute';
    public const AUTOFILTER_RULETYPE_DATEGROUP_SECOND = 'second';
    private const DATE_TIME_GROUPS = [self::AUTOFILTER_RULETYPE_DATEGROUP_YEAR, self::AUTOFILTER_RULETYPE_DATEGROUP_MONTH, self::AUTOFILTER_RULETYPE_DATEGROUP_DAY, self::AUTOFILTER_RULETYPE_DATEGROUP_HOUR, self::AUTOFILTER_RULETYPE_DATEGROUP_MINUTE, self::AUTOFILTER_RULETYPE_DATEGROUP_SECOND];
    public const AUTOFILTER_RULETYPE_DYNAMIC_YESTERDAY = 'yesterday';
    public const AUTOFILTER_RULETYPE_DYNAMIC_TODAY = 'today';
    public const AUTOFILTER_RULETYPE_DYNAMIC_TOMORROW = 'tomorrow';
    public const AUTOFILTER_RULETYPE_DYNAMIC_YEARTODATE = 'yearToDate';
    public const AUTOFILTER_RULETYPE_DYNAMIC_THISYEAR = 'thisYear';
    public const AUTOFILTER_RULETYPE_DYNAMIC_THISQUARTER = 'thisQuarter';
    public const AUTOFILTER_RULETYPE_DYNAMIC_THISMONTH = 'thisMonth';
    public const AUTOFILTER_RULETYPE_DYNAMIC_THISWEEK = 'thisWeek';
    public const AUTOFILTER_RULETYPE_DYNAMIC_LASTYEAR = 'lastYear';
    public const AUTOFILTER_RULETYPE_DYNAMIC_LASTQUARTER = 'lastQuarter';
    public const AUTOFILTER_RULETYPE_DYNAMIC_LASTMONTH = 'lastMonth';
    public const AUTOFILTER_RULETYPE_DYNAMIC_LASTWEEK = 'lastWeek';
    public const AUTOFILTER_RULETYPE_DYNAMIC_NEXTYEAR = 'nextYear';
    public const AUTOFILTER_RULETYPE_DYNAMIC_NEXTQUARTER = 'nextQuarter';
    public const AUTOFILTER_RULETYPE_DYNAMIC_NEXTMONTH = 'nextMonth';
    public const AUTOFILTER_RULETYPE_DYNAMIC_NEXTWEEK = 'nextWeek';
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_1 = 'M1';
    public const AUTOFILTER_RULETYPE_DYNAMIC_JANUARY = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_1;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_2 = 'M2';
    public const AUTOFILTER_RULETYPE_DYNAMIC_FEBRUARY = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_2;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_3 = 'M3';
    public const AUTOFILTER_RULETYPE_DYNAMIC_MARCH = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_3;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_4 = 'M4';
    public const AUTOFILTER_RULETYPE_DYNAMIC_APRIL = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_4;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_5 = 'M5';
    public const AUTOFILTER_RULETYPE_DYNAMIC_MAY = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_5;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_6 = 'M6';
    public const AUTOFILTER_RULETYPE_DYNAMIC_JUNE = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_6;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_7 = 'M7';
    public const AUTOFILTER_RULETYPE_DYNAMIC_JULY = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_7;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_8 = 'M8';
    public const AUTOFILTER_RULETYPE_DYNAMIC_AUGUST = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_8;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_9 = 'M9';
    public const AUTOFILTER_RULETYPE_DYNAMIC_SEPTEMBER = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_9;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_10 = 'M10';
    public const AUTOFILTER_RULETYPE_DYNAMIC_OCTOBER = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_10;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_11 = 'M11';
    public const AUTOFILTER_RULETYPE_DYNAMIC_NOVEMBER = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_11;
    public const AUTOFILTER_RULETYPE_DYNAMIC_MONTH_12 = 'M12';
    public const AUTOFILTER_RULETYPE_DYNAMIC_DECEMBER = self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_12;
    public const AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_1 = 'Q1';
    public const AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_2 = 'Q2';
    public const AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_3 = 'Q3';
    public const AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_4 = 'Q4';
    public const AUTOFILTER_RULETYPE_DYNAMIC_ABOVEAVERAGE = 'aboveAverage';
    public const AUTOFILTER_RULETYPE_DYNAMIC_BELOWAVERAGE = 'belowAverage';
    private const DYNAMIC_TYPES = [self::AUTOFILTER_RULETYPE_DYNAMIC_YESTERDAY, self::AUTOFILTER_RULETYPE_DYNAMIC_TODAY, self::AUTOFILTER_RULETYPE_DYNAMIC_TOMORROW, self::AUTOFILTER_RULETYPE_DYNAMIC_YEARTODATE, self::AUTOFILTER_RULETYPE_DYNAMIC_THISYEAR, self::AUTOFILTER_RULETYPE_DYNAMIC_THISQUARTER, self::AUTOFILTER_RULETYPE_DYNAMIC_THISMONTH, self::AUTOFILTER_RULETYPE_DYNAMIC_THISWEEK, self::AUTOFILTER_RULETYPE_DYNAMIC_LASTYEAR, self::AUTOFILTER_RULETYPE_DYNAMIC_LASTQUARTER, self::AUTOFILTER_RULETYPE_DYNAMIC_LASTMONTH, self::AUTOFILTER_RULETYPE_DYNAMIC_LASTWEEK, self::AUTOFILTER_RULETYPE_DYNAMIC_NEXTYEAR, self::AUTOFILTER_RULETYPE_DYNAMIC_NEXTQUARTER, self::AUTOFILTER_RULETYPE_DYNAMIC_NEXTMONTH, self::AUTOFILTER_RULETYPE_DYNAMIC_NEXTWEEK, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_1, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_2, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_3, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_4, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_5, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_6, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_7, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_8, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_9, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_10, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_11, self::AUTOFILTER_RULETYPE_DYNAMIC_MONTH_12, self::AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_1, self::AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_2, self::AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_3, self::AUTOFILTER_RULETYPE_DYNAMIC_QUARTER_4, self::AUTOFILTER_RULETYPE_DYNAMIC_ABOVEAVERAGE, self::AUTOFILTER_RULETYPE_DYNAMIC_BELOWAVERAGE];
    // Filter rule operators for filter and customFilter types.
    public const AUTOFILTER_COLUMN_RULE_EQUAL = 'equal';
    public const AUTOFILTER_COLUMN_RULE_NOTEQUAL = 'notEqual';
    public const AUTOFILTER_COLUMN_RULE_GREATERTHAN = 'greaterThan';
    public const AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL = 'greaterThanOrEqual';
    public const AUTOFILTER_COLUMN_RULE_LESSTHAN = 'lessThan';
    public const AUTOFILTER_COLUMN_RULE_LESSTHANOREQUAL = 'lessThanOrEqual';
    private const OPERATORS = [self::AUTOFILTER_COLUMN_RULE_EQUAL, self::AUTOFILTER_COLUMN_RULE_NOTEQUAL, self::AUTOFILTER_COLUMN_RULE_GREATERTHAN, self::AUTOFILTER_COLUMN_RULE_GREATERTHANOREQUAL, self::AUTOFILTER_COLUMN_RULE_LESSTHAN, self::AUTOFILTER_COLUMN_RULE_LESSTHANOREQUAL];
    public const AUTOFILTER_COLUMN_RULE_TOPTEN_BY_VALUE = 'byValue';
    public const AUTOFILTER_COLUMN_RULE_TOPTEN_PERCENT = 'byPercent';
    private const TOP_TEN_VALUE = [self::AUTOFILTER_COLUMN_RULE_TOPTEN_BY_VALUE, self::AUTOFILTER_COLUMN_RULE_TOPTEN_PERCENT];
    public const AUTOFILTER_COLUMN_RULE_TOPTEN_TOP = 'top';
    public const AUTOFILTER_COLUMN_RULE_TOPTEN_BOTTOM = 'bottom';
    private const TOP_TEN_TYPE = [self::AUTOFILTER_COLUMN_RULE_TOPTEN_TOP, self::AUTOFILTER_COLUMN_RULE_TOPTEN_BOTTOM];
    /**
     * Autofilter Rule Type.
     */
    private string $rule_type = self::AUTOFILTER_RULETYPE_FILTER;
    /**
     * Autofilter Rule Value.
     *
     * @var int|int[]|string|string[]
     */
    private $value = '';
    /**
     * Autofilter Rule Operator.
     */
    private string $operator = self::AUTOFILTER_COLUMN_RULE_EQUAL;
    /**
     * DateTimeGrouping Group Value.
     */
    private string $grouping = '';
    /**
     * Create a new Rule.
     */
    public function __construct(
        /**
         * Autofilter Column.
         */
        private ?Column $parent = null
    )
    {
    }
    private function set_evaluated_false(): void
    {
        if ($this->parent !== null) {
            $this->parent->set_evaluated_false();
        }
    }
    /**
     * Get AutoFilter Rule Type.
     */
    public function get_rule_type(): string
    {
        return $this->rule_type;
    }
    /**
     * Set AutoFilter Rule Type.
     *
     * @param string $ruleType see self::AUTOFILTER_RULETYPE_*
     *
     * @return $this
     */
    public function set_rule_type(string $rule_type): static
    {
        $this->set_evaluated_false();
        if (!in_array($rule_type, self::RULE_TYPES)) {
            throw new Php_Spreadsheet_Exception('Invalid rule type for column AutoFilter Rule.');
        }
        $this->rule_type = $rule_type;
        return $this;
    }
    /**
     * Get AutoFilter Rule Value.
     *
     * @return int|int[]|string|string[]
     */
    public function get_value()
    {
        return $this->value;
    }
    /**
     * Set AutoFilter Rule Value.
     *
     * @param int|int[]|string|string[] $value
     *
     * @return $this
     */
    public function set_value($value): static
    {
        $this->set_evaluated_false();
        if (is_array($value)) {
            $grouping = -1;
            foreach ($value as $key => $v) {
                //    Validate array entries
                if (!in_array($key, self::DATE_TIME_GROUPS)) {
                    //    Remove any invalid entries from the value array
                    unset($value[$key]);
                } else {
                    //    Work out what the dateTime grouping will be
                    $grouping = max($grouping, array_search($key, self::DATE_TIME_GROUPS));
                }
            }
            if (count($value) == 0) {
                throw new Php_Spreadsheet_Exception('Invalid rule value for column AutoFilter Rule.');
            }
            //    Set the dateTime grouping that we've anticipated
            //    I have no idea what Phpstan is complaining about below
            $this->set_grouping(self::DATE_TIME_GROUPS[$grouping]);
            // @phpstan-ignore-line
        }
        $this->value = $value;
        return $this;
    }
    /**
     * Get AutoFilter Rule Operator.
     */
    public function get_operator(): string
    {
        return $this->operator;
    }
    /**
     * Set AutoFilter Rule Operator.
     *
     * @param string $operator see self::AUTOFILTER_COLUMN_RULE_*
     *
     * @return $this
     */
    public function set_operator(string $operator): static
    {
        $this->set_evaluated_false();
        if (empty($operator)) {
            $operator = self::AUTOFILTER_COLUMN_RULE_EQUAL;
        }
        if (!in_array($operator, self::OPERATORS) && !in_array($operator, self::TOP_TEN_VALUE)) {
            throw new Php_Spreadsheet_Exception('Invalid operator for column AutoFilter Rule.');
        }
        $this->operator = $operator;
        return $this;
    }
    /**
     * Get AutoFilter Rule Grouping.
     */
    public function get_grouping(): string
    {
        return $this->grouping;
    }
    /**
     * Set AutoFilter Rule Grouping.
     *
     * @return $this
     */
    public function set_grouping(string $grouping): static
    {
        $this->set_evaluated_false();
        if (!in_array($grouping, self::DATE_TIME_GROUPS) && !in_array($grouping, self::DYNAMIC_TYPES) && !in_array($grouping, self::TOP_TEN_TYPE)) {
            throw new Php_Spreadsheet_Exception('Invalid grouping for column AutoFilter Rule.');
        }
        $this->grouping = $grouping;
        return $this;
    }
    /**
     * Set AutoFilter Rule.
     *
     * @param string $operator see self::AUTOFILTER_COLUMN_RULE_*
     * @param int|int[]|string|string[] $value
     *
     * @return $this
     */
    public function set_rule(string $operator, $value, ?string $grouping = null): static
    {
        $this->set_evaluated_false();
        $this->set_operator($operator);
        $this->set_value($value);
        //  Only set grouping if it's been passed in as a user-supplied argument,
        //      otherwise we're calculating it when we setValue() and don't want to overwrite that
        //      If the user supplies an argument for grouping, then on their own head be it
        if ($grouping !== null) {
            $this->set_grouping($grouping);
        }
        return $this;
    }
    /**
     * Get this Rule's AutoFilter Column Parent.
     */
    public function get_parent(): ?Column
    {
        return $this->parent;
    }
    /**
     * Set this Rule's AutoFilter Column Parent.
     *
     * @return $this
     */
    public function set_parent(?Column $parent = null): static
    {
        $this->set_evaluated_false();
        $this->parent = $parent;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                if ($key == 'parent') {
                    // this is only object
                    //    Detach from autofilter column parent
                    $this->{$key} = null;
                }
            } else {
                $this->{$key} = $value;
            }
        }
    }
}