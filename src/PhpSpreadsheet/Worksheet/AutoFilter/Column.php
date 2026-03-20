<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Worksheet\Auto_Filter;
class Column
{
    public const AUTOFILTER_FILTERTYPE_FILTER = 'filters';
    public const AUTOFILTER_FILTERTYPE_CUSTOMFILTER = 'customFilters';
    //    Supports no more than 2 rules, with an And/Or join criteria
    //        if more than 1 rule is defined
    public const AUTOFILTER_FILTERTYPE_DYNAMICFILTER = 'dynamicFilter';
    //    Even though the filter rule is constant, the filtered data can vary
    //        e.g. filtered by date = TODAY
    public const AUTOFILTER_FILTERTYPE_TOPTENFILTER = 'top10';
    /**
     * Types of autofilter rules.
     *
     * @var string[]
     */
    private static array $filter_types = [
        //    Currently we're not handling
        //        colorFilter
        //        extLst
        //        iconFilter
        self::AUTOFILTER_FILTERTYPE_FILTER,
        self::AUTOFILTER_FILTERTYPE_CUSTOMFILTER,
        self::AUTOFILTER_FILTERTYPE_DYNAMICFILTER,
        self::AUTOFILTER_FILTERTYPE_TOPTENFILTER,
    ];
    // Multiple Rule Connections
    public const AUTOFILTER_COLUMN_JOIN_AND = 'and';
    public const AUTOFILTER_COLUMN_JOIN_OR = 'or';
    /**
     * Join options for autofilter rules.
     *
     * @var string[]
     */
    private static array $rule_joins = [self::AUTOFILTER_COLUMN_JOIN_AND, self::AUTOFILTER_COLUMN_JOIN_OR];
    /**
     * Autofilter Column Filter Type.
     */
    private string $filter_type = self::AUTOFILTER_FILTERTYPE_FILTER;
    /**
     * Autofilter Multiple Rules And/Or.
     */
    private string $join = self::AUTOFILTER_COLUMN_JOIN_OR;
    /**
     * Autofilter Column Rules.
     *
     * @var Column\Rule[]
     */
    private array $ruleset = [];
    /**
     * Autofilter Column Dynamic Attributes.
     *
     * @var (float|int|string)[]
     */
    private array $attributes = [];
    /**
     * Create a new Column.
     *
     * @param string $columnIndex Column (e.g. A)
     * @param ?AutoFilter $parent Autofilter for this column
     */
    public function __construct(private string $column_index, private ?Auto_Filter $parent = null)
    {
    }
    public function set_evaluated_false(): void
    {
        if ($this->parent !== null) {
            $this->parent->set_evaluated(false);
        }
    }
    /**
     * Get AutoFilter column index as string eg: 'A'.
     */
    public function get_column_index(): string
    {
        return $this->column_index;
    }
    /**
     * Set AutoFilter column index as string eg: 'A'.
     *
     * @param string $column Column (e.g. A)
     *
     * @return $this
     */
    public function set_column_index(string $column): static
    {
        $this->set_evaluated_false();
        // Uppercase coordinate
        $column = strtoupper($column);
        if ($this->parent !== null) {
            $this->parent->test_column_in_range($column);
        }
        $this->column_index = $column;
        return $this;
    }
    /**
     * Get this Column's AutoFilter Parent.
     */
    public function get_parent(): ?Auto_Filter
    {
        return $this->parent;
    }
    /**
     * Set this Column's AutoFilter Parent.
     *
     * @return $this
     */
    public function set_parent(?Auto_Filter $parent = null): static
    {
        $this->set_evaluated_false();
        $this->parent = $parent;
        return $this;
    }
    /**
     * Get AutoFilter Type.
     */
    public function get_filter_type(): string
    {
        return $this->filter_type;
    }
    /**
     * Set AutoFilter Type.
     *
     * @return $this
     */
    public function set_filter_type(string $filter_type): static
    {
        $this->set_evaluated_false();
        if (!in_array($filter_type, self::$filter_types)) {
            throw new Php_Spreadsheet_Exception('Invalid filter type for column AutoFilter.');
        }
        if ($filter_type === self::AUTOFILTER_FILTERTYPE_CUSTOMFILTER && count($this->ruleset) > 2) {
            throw new Php_Spreadsheet_Exception('No more than 2 rules are allowed in a Custom Filter');
        }
        $this->filter_type = $filter_type;
        return $this;
    }
    /**
     * Get AutoFilter Multiple Rules And/Or Join.
     */
    public function get_join(): string
    {
        return $this->join;
    }
    /**
     * Set AutoFilter Multiple Rules And/Or.
     *
     * @param string $join And/Or
     *
     * @return $this
     */
    public function set_join(string $join): static
    {
        $this->set_evaluated_false();
        // Lowercase And/Or
        $join = strtolower($join);
        if (!in_array($join, self::$rule_joins)) {
            throw new Php_Spreadsheet_Exception('Invalid rule connection for column AutoFilter.');
        }
        $this->join = $join;
        return $this;
    }
    /**
     * Set AutoFilter Attributes.
     *
     * @param (float|int|string)[] $attributes
     *
     * @return $this
     */
    public function set_attributes(array $attributes): static
    {
        $this->set_evaluated_false();
        $this->attributes = $attributes;
        return $this;
    }
    /**
     * Set An AutoFilter Attribute.
     *
     * @param string $name Attribute Name
     * @param float|int|string $value Attribute Value
     *
     * @return $this
     */
    public function set_attribute(string $name, $value): static
    {
        $this->set_evaluated_false();
        $this->attributes[$name] = $value;
        return $this;
    }
    /**
     * Get AutoFilter Column Attributes.
     *
     * @return (float|int|string)[]
     */
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    /**
     * Get specific AutoFilter Column Attribute.
     *
     * @param string $name Attribute Name
     */
    public function get_attribute(string $name): null|float|int|string
    {
        return $this->attributes[$name] ?? null;
    }
    public function rule_count(): int
    {
        return count($this->ruleset);
    }
    /**
     * Get all AutoFilter Column Rules.
     *
     * @return Column\Rule[]
     */
    public function get_rules(): array
    {
        return $this->ruleset;
    }
    /**
     * Get a specified AutoFilter Column Rule.
     *
     * @param int $index Rule index in the ruleset array
     */
    public function get_rule(int $index): Column\Rule
    {
        if (!isset($this->ruleset[$index])) {
            $this->ruleset[$index] = new Column\Rule($this);
        }
        return $this->ruleset[$index];
    }
    /**
     * Create a new AutoFilter Column Rule in the ruleset.
     */
    public function create_rule(): Column\Rule
    {
        $this->set_evaluated_false();
        if ($this->filter_type === self::AUTOFILTER_FILTERTYPE_CUSTOMFILTER && count($this->ruleset) >= 2) {
            throw new Php_Spreadsheet_Exception('No more than 2 rules are allowed in a Custom Filter');
        }
        $this->ruleset[] = new Column\Rule($this);
        return end($this->ruleset);
    }
    /**
     * Add a new AutoFilter Column Rule to the ruleset.
     *
     * @return $this
     */
    public function add_rule(Column\Rule $rule): static
    {
        $this->set_evaluated_false();
        $rule->set_parent($this);
        $this->ruleset[] = $rule;
        return $this;
    }
    /**
     * Delete a specified AutoFilter Column Rule
     * If the number of rules is reduced to 1, then we reset And/Or logic to Or.
     *
     * @param int $index Rule index in the ruleset array
     *
     * @return $this
     */
    public function delete_rule(int $index): static
    {
        $this->set_evaluated_false();
        if (isset($this->ruleset[$index])) {
            unset($this->ruleset[$index]);
            //    If we've just deleted down to a single rule, then reset And/Or joining to Or
            if (count($this->ruleset) <= 1) {
                $this->set_join(self::AUTOFILTER_COLUMN_JOIN_OR);
            }
        }
        return $this;
    }
    /**
     * Delete all AutoFilter Column Rules.
     *
     * @return $this
     */
    public function clear_rules(): static
    {
        $this->set_evaluated_false();
        $this->ruleset = [];
        $this->set_join(self::AUTOFILTER_COLUMN_JOIN_OR);
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        /** @var Column\Rule[] $value */
        foreach ($vars as $key => $value) {
            if ($key === 'parent') {
                // Detach from autofilter parent
                $this->parent = null;
            } elseif ($key === 'ruleset') {
                // The columns array of \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet\AutoFilter objects
                $this->ruleset = [];
                foreach ($value as $k => $v) {
                    $cloned = clone $v;
                    $cloned->set_parent($this);
                    // attach the new cloned Rule to this new cloned Autofilter Cloned object
                    $this->ruleset[$k] = $cloned;
                }
            } else {
                $this->{$key} = $value;
            }
        }
    }
}