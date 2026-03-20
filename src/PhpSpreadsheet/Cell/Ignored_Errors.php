<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

class Ignored_Errors
{
    private bool $number_stored_as_text = false;
    private bool $formula = false;
    private bool $formula_range = false;
    private bool $two_digit_text_year = false;
    private bool $eval_error = false;
    public function set_number_stored_as_text(bool $value): self
    {
        $this->number_stored_as_text = $value;
        return $this;
    }
    public function get_number_stored_as_text(): bool
    {
        return $this->number_stored_as_text;
    }
    public function set_formula(bool $value): self
    {
        $this->formula = $value;
        return $this;
    }
    public function get_formula(): bool
    {
        return $this->formula;
    }
    public function set_formula_range(bool $value): self
    {
        $this->formula_range = $value;
        return $this;
    }
    public function get_formula_range(): bool
    {
        return $this->formula_range;
    }
    public function set_two_digit_text_year(bool $value): self
    {
        $this->two_digit_text_year = $value;
        return $this;
    }
    public function get_two_digit_text_year(): bool
    {
        return $this->two_digit_text_year;
    }
    public function set_eval_error(bool $value): self
    {
        $this->eval_error = $value;
        return $this;
    }
    public function get_eval_error(): bool
    {
        return $this->eval_error;
    }
}