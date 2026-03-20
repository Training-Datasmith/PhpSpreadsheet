<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
abstract class Defined_Name
{
    protected const REGEXP_IDENTIFY_FORMULA = '[^_\p{N}\p{L}:, \$\'!]';
    /**
     * Worksheet on which the defined name can be resolved.
     */
    protected ?Worksheet $worksheet;
    /**
     * Value of the named object.
     */
    protected string $value;
    /**
     * Scope.
     */
    protected ?Worksheet $scope;
    /**
     * Whether this is a named range or a named formula.
     */
    protected bool $is_formula;
    /**
     * Create a new Defined Name.
     */
    public function __construct(
        /**
         * Name.
         */
        protected string $name,
        ?Worksheet $worksheet = null,
        ?string $value = null,
        /**
         * Is the defined named local? (i.e. can only be used on $this->worksheet).
         */
        protected bool $local_only = false,
        ?Worksheet $scope = null
    )
    {
        if ($worksheet === null) {
            $worksheet = $scope;
        }
        $this->worksheet = $worksheet;
        $this->value = (string) $value;
        // If local only, then the scope will be set to worksheet unless a scope is explicitly set
        $this->scope = $this->local_only === true ? $scope ?? $worksheet : null;
        // If the range string contains characters that aren't associated with the range definition (A-Z,1-9
        //      for cell references, and $, or the range operators (colon comma or space), quotes and ! for
        //      worksheet names
        //  then this is treated as a named formula, and not a named range
        $this->is_formula = self::test_if_formula($this->value);
    }
    public function __destruct()
    {
        $this->worksheet = null;
        $this->scope = null;
    }
    /**
     * Create a new defined name, either a range or a formula.
     */
    public static function create_instance(string $name, ?Worksheet $worksheet = null, ?string $value = null, bool $local_only = false, ?Worksheet $scope = null): self
    {
        $value = (string) $value;
        $is_formula = self::test_if_formula($value);
        if ($is_formula) {
            return new Named_Formula($name, $worksheet, $value, $local_only, $scope);
        }
        return new Named_Range($name, $worksheet, $value, $local_only, $scope);
    }
    public static function test_if_formula(string $value): bool
    {
        if (str_starts_with($value, '=')) {
            $value = substr($value, 1);
        }
        if (is_numeric($value)) {
            return true;
        }
        $seg_matcher = false;
        foreach (explode("'", $value) as $sub_val) {
            //    Only test in alternate array entries (the non-quoted blocks)
            $seg_matcher = $seg_matcher === false;
            if ($seg_matcher && preg_match('/' . self::REGEXP_IDENTIFY_FORMULA . '/miu', $sub_val)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Get name.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Set name.
     */
    public function set_name(string $name): self
    {
        if (!empty($name)) {
            // Old title
            $old_title = $this->name;
            // Re-attach
            if ($this->worksheet !== null) {
                $this->worksheet->get_parent_or_throw()->remove_named_range($this->name, $this->worksheet);
            }
            $this->name = $name;
            if ($this->worksheet !== null) {
                $this->worksheet->get_parent_or_throw()->add_defined_name($this);
            }
            if ($this->worksheet !== null) {
                // New title
                $new_title = $this->name;
                Reference_Helper::get_instance()->update_named_formulae($this->worksheet->get_parent_or_throw(), $old_title, $new_title);
            }
        }
        return $this;
    }
    /**
     * Get worksheet.
     */
    public function get_worksheet(): ?Worksheet
    {
        return $this->worksheet;
    }
    /**
     * Set worksheet.
     */
    public function set_worksheet(?Worksheet $worksheet): self
    {
        $this->worksheet = $worksheet;
        return $this;
    }
    /**
     * Get range or formula value.
     */
    public function get_value(): string
    {
        return $this->value;
    }
    /**
     * Set range or formula  value.
     */
    public function set_value(string $value): self
    {
        $this->value = $value;
        return $this;
    }
    /**
     * Get localOnly.
     */
    public function get_local_only(): bool
    {
        return $this->local_only;
    }
    /**
     * Set localOnly.
     */
    public function set_local_only(bool $local_scope): self
    {
        $this->local_only = $local_scope;
        $this->scope = $local_scope ? $this->worksheet : null;
        return $this;
    }
    /**
     * Get scope.
     */
    public function get_scope(): ?Worksheet
    {
        return $this->scope;
    }
    /**
     * Set scope.
     */
    public function set_scope(?Worksheet $worksheet): self
    {
        $this->scope = $worksheet;
        $this->local_only = $worksheet !== null;
        return $this;
    }
    /**
     * Identify whether this is a named range or a named formula.
     */
    public function is_formula(): bool
    {
        return $this->is_formula;
    }
    /**
     * Resolve a named range to a regular cell range or formula.
     */
    public static function resolve_name(string $defined_name, Worksheet $worksheet, string $sheet_name = ''): ?self
    {
        if ($sheet_name === '') {
            $worksheet2 = $worksheet;
        } else {
            $worksheet2 = $worksheet->get_parent_or_throw()->get_sheet_by_name($sheet_name);
            if ($worksheet2 === null) {
                return null;
            }
        }
        return $worksheet->get_parent_or_throw()->get_defined_name($defined_name, $worksheet2);
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
}