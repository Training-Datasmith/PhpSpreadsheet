<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

class Conditional_Data_Bar
{
    private ?bool $show_value = null;
    private ?Conditional_Format_Value_Object $minimum_conditional_format_value_object = null;
    private ?Conditional_Format_Value_Object $maximum_conditional_format_value_object = null;
    private string $color = '';
    private ?Conditional_Formatting_Rule_Extension $conditional_formatting_rule_ext = null;
    public function get_show_value(): ?bool
    {
        return $this->show_value;
    }
    public function set_show_value(bool $show_value): self
    {
        $this->show_value = $show_value;
        return $this;
    }
    public function get_minimum_conditional_format_value_object(): ?Conditional_Format_Value_Object
    {
        return $this->minimum_conditional_format_value_object;
    }
    public function set_minimum_conditional_format_value_object(Conditional_Format_Value_Object $minimum_conditional_format_value_object): self
    {
        $this->minimum_conditional_format_value_object = $minimum_conditional_format_value_object;
        return $this;
    }
    public function get_maximum_conditional_format_value_object(): ?Conditional_Format_Value_Object
    {
        return $this->maximum_conditional_format_value_object;
    }
    public function set_maximum_conditional_format_value_object(Conditional_Format_Value_Object $maximum_conditional_format_value_object): self
    {
        $this->maximum_conditional_format_value_object = $maximum_conditional_format_value_object;
        return $this;
    }
    public function get_color(): string
    {
        return $this->color;
    }
    public function set_color(string $color): self
    {
        $this->color = $color;
        return $this;
    }
    public function get_conditional_formatting_rule_ext(): ?Conditional_Formatting_Rule_Extension
    {
        return $this->conditional_formatting_rule_ext;
    }
    public function set_conditional_formatting_rule_ext(Conditional_Formatting_Rule_Extension $conditional_formatting_rule_ext): self
    {
        $this->conditional_formatting_rule_ext = $conditional_formatting_rule_ext;
        return $this;
    }
}