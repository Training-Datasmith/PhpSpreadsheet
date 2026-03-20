<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

class Conditional_Format_Value_Object
{
    /**
     * For icon sets, determines whether this threshold value uses the greater
     * than or equal to operator. False indicates 'greater than' is used instead
     * of 'greater than or equal to'.
     */
    private ?bool $greater_than_or_equal = null;
    public function __construct(private string $type, private null|float|int|string $value = null, private ?string $cell_formula = null)
    {
    }
    public function get_type(): string
    {
        return $this->type;
    }
    public function set_type(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    public function get_value(): null|float|int|string
    {
        return $this->value;
    }
    public function set_value(null|float|int|string $value): self
    {
        $this->value = $value;
        return $this;
    }
    public function get_cell_formula(): ?string
    {
        return $this->cell_formula;
    }
    public function set_cell_formula(?string $cell_formula): self
    {
        $this->cell_formula = $cell_formula;
        return $this;
    }
    public function get_greater_than_or_equal(): ?bool
    {
        return $this->greater_than_or_equal;
    }
    public function set_greater_than_or_equal(?bool $greater_than_or_equal): self
    {
        $this->greater_than_or_equal = $greater_than_or_equal;
        return $this;
    }
}