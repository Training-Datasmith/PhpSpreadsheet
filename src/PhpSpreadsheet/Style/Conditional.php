<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\I_Comparable;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Color_Scale;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Data_Bar;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Icon_Set;
class Conditional implements I_Comparable
{
    // Condition types
    public const CONDITION_NONE = 'none';
    public const CONDITION_BEGINSWITH = 'beginsWith';
    public const CONDITION_CELLIS = 'cellIs';
    public const CONDITION_COLORSCALE = 'colorScale';
    public const CONDITION_CONTAINSBLANKS = 'containsBlanks';
    public const CONDITION_CONTAINSERRORS = 'containsErrors';
    public const CONDITION_CONTAINSTEXT = 'containsText';
    public const CONDITION_DATABAR = 'dataBar';
    public const CONDITION_ENDSWITH = 'endsWith';
    public const CONDITION_EXPRESSION = 'expression';
    public const CONDITION_NOTCONTAINSBLANKS = 'notContainsBlanks';
    public const CONDITION_NOTCONTAINSERRORS = 'notContainsErrors';
    public const CONDITION_NOTCONTAINSTEXT = 'notContainsText';
    public const CONDITION_TIMEPERIOD = 'timePeriod';
    public const CONDITION_DUPLICATES = 'duplicateValues';
    public const CONDITION_UNIQUE = 'uniqueValues';
    public const CONDITION_ICONSET = 'iconSet';
    private const CONDITION_TYPES = [self::CONDITION_BEGINSWITH, self::CONDITION_CELLIS, self::CONDITION_COLORSCALE, self::CONDITION_CONTAINSBLANKS, self::CONDITION_CONTAINSERRORS, self::CONDITION_CONTAINSTEXT, self::CONDITION_DATABAR, self::CONDITION_DUPLICATES, self::CONDITION_ENDSWITH, self::CONDITION_EXPRESSION, self::CONDITION_NONE, self::CONDITION_NOTCONTAINSBLANKS, self::CONDITION_NOTCONTAINSERRORS, self::CONDITION_NOTCONTAINSTEXT, self::CONDITION_TIMEPERIOD, self::CONDITION_UNIQUE, self::CONDITION_ICONSET];
    // Operator types
    public const OPERATOR_NONE = '';
    public const OPERATOR_BEGINSWITH = 'beginsWith';
    public const OPERATOR_ENDSWITH = 'endsWith';
    public const OPERATOR_EQUAL = 'equal';
    public const OPERATOR_GREATERTHAN = 'greaterThan';
    public const OPERATOR_GREATERTHANOREQUAL = 'greaterThanOrEqual';
    public const OPERATOR_LESSTHAN = 'lessThan';
    public const OPERATOR_LESSTHANOREQUAL = 'lessThanOrEqual';
    public const OPERATOR_NOTEQUAL = 'notEqual';
    public const OPERATOR_CONTAINSTEXT = 'containsText';
    public const OPERATOR_NOTCONTAINS = 'notContains';
    public const OPERATOR_BETWEEN = 'between';
    public const OPERATOR_NOTBETWEEN = 'notBetween';
    public const TIMEPERIOD_TODAY = 'today';
    public const TIMEPERIOD_YESTERDAY = 'yesterday';
    public const TIMEPERIOD_TOMORROW = 'tomorrow';
    public const TIMEPERIOD_LAST_7_DAYS = 'last7Days';
    public const TIMEPERIOD_LAST_WEEK = 'lastWeek';
    public const TIMEPERIOD_THIS_WEEK = 'thisWeek';
    public const TIMEPERIOD_NEXT_WEEK = 'nextWeek';
    public const TIMEPERIOD_LAST_MONTH = 'lastMonth';
    public const TIMEPERIOD_THIS_MONTH = 'thisMonth';
    public const TIMEPERIOD_NEXT_MONTH = 'nextMonth';
    /**
     * Condition type.
     */
    private string $condition_type = self::CONDITION_NONE;
    /**
     * Operator type.
     */
    private string $operator_type = self::OPERATOR_NONE;
    /**
     * Text.
     */
    private string $text = '';
    /**
     * Stop on this condition, if it matches.
     */
    private bool $stop_if_true = false;
    /**
     * Condition.
     *
     * @var (bool|float|int|string)[]
     */
    private array $condition = [];
    private ?Conditional_Data_Bar $data_bar = null;
    private ?Conditional_Color_Scale $color_scale = null;
    private ?Conditional_Icon_Set $icon_set = null;
    private Style $style;
    private bool $no_format_set = false;
    private int $priority = 0;
    /**
     * Create a new Conditional.
     */
    public function __construct()
    {
        // Initialise values
        $this->style = new Style(false, true);
    }
    public function get_priority(): int
    {
        return $this->priority;
    }
    public function set_priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    public function get_no_format_set(): bool
    {
        return $this->no_format_set;
    }
    public function set_no_format_set(bool $no_format_set): self
    {
        $this->no_format_set = $no_format_set;
        return $this;
    }
    /**
     * Get Condition type.
     */
    public function get_condition_type(): string
    {
        return $this->condition_type;
    }
    /**
     * Set Condition type.
     *
     * @param string $type Condition type, see self::CONDITION_*
     *
     * @return $this
     */
    public function set_condition_type(string $type): static
    {
        $this->condition_type = $type;
        return $this;
    }
    /**
     * Get Operator type.
     */
    public function get_operator_type(): string
    {
        return $this->operator_type;
    }
    /**
     * Set Operator type.
     *
     * @param string $type Conditional operator type, see self::OPERATOR_*
     *
     * @return $this
     */
    public function set_operator_type(string $type): static
    {
        $this->operator_type = $type;
        return $this;
    }
    /**
     * Get text.
     */
    public function get_text(): string
    {
        return $this->text;
    }
    /**
     * Set text.
     *
     * @return $this
     */
    public function set_text(string $text): static
    {
        $this->text = $text;
        return $this;
    }
    /**
     * Get StopIfTrue.
     */
    public function get_stop_if_true(): bool
    {
        return $this->stop_if_true;
    }
    /**
     * Set StopIfTrue.
     *
     * @return $this
     */
    public function set_stop_if_true(bool $stop_if_true): static
    {
        $this->stop_if_true = $stop_if_true;
        return $this;
    }
    /**
     * Get Conditions.
     *
     * @return (bool|float|int|string)[]
     */
    public function get_conditions(): array
    {
        return $this->condition;
    }
    /**
     * Set Conditions.
     *
     * @param bool|(bool|float|int|string)[]|float|int|string $conditions Condition
     *
     * @return $this
     */
    public function set_conditions($conditions): static
    {
        if (!is_array($conditions)) {
            $conditions = [$conditions];
        }
        $this->condition = $conditions;
        return $this;
    }
    /**
     * Add Condition.
     *
     * @param bool|float|int|string $condition Condition
     *
     * @return $this
     */
    public function add_condition($condition): static
    {
        $this->condition[] = $condition;
        return $this;
    }
    /**
     * Get Style.
     */
    public function get_style(mixed $cell_data = null): Style
    {
        if ($this->condition_type === self::CONDITION_COLORSCALE && $cell_data !== null && $this->color_scale !== null && is_numeric($cell_data)) {
            $style = new Style(isConditional: true);
            $style->get_fill()->set_fill_type(Fill::FILL_SOLID);
            $style->get_fill()->get_start_color()->set_argb($this->color_scale->get_color_for_value((float) $cell_data));
            return $style;
        }
        return $this->style;
    }
    /**
     * Set Style.
     *
     * @return $this
     */
    public function set_style(Style $style): static
    {
        $this->style = $style;
        return $this;
    }
    public function get_data_bar(): ?Conditional_Data_Bar
    {
        return $this->data_bar;
    }
    public function set_data_bar(Conditional_Data_Bar $data_bar): static
    {
        $this->data_bar = $data_bar;
        return $this;
    }
    public function get_color_scale(): ?Conditional_Color_Scale
    {
        return $this->color_scale;
    }
    public function set_color_scale(Conditional_Color_Scale $color_scale): static
    {
        $this->color_scale = $color_scale;
        return $this;
    }
    public function get_icon_set(): ?Conditional_Icon_Set
    {
        return $this->icon_set;
    }
    public function set_icon_set(Conditional_Icon_Set $icon_set): static
    {
        $this->icon_set = $icon_set;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->condition_type . $this->operator_type . implode(';', $this->condition) . $this->style->get_hash_code() . self::class);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                $this->{$key} = clone $value;
            } else {
                $this->{$key} = $value;
            }
        }
    }
    /**
     * Verify if param is valid condition type.
     */
    public static function is_valid_condition_type(string $type): bool
    {
        return in_array($type, self::CONDITION_TYPES);
    }
}