<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

class Conditional_Data_Bar_Extension
{
    /** <dataBar> attributes */
    private int $min_length;
    private int $max_length;
    private ?bool $border = null;
    private ?bool $gradient = null;
    private ?string $direction = null;
    private ?bool $negative_bar_border_color_same_as_positive = null;
    private ?string $axis_position = null;
    // <dataBar> children
    private Conditional_Format_Value_Object $maximum_conditional_format_value_object;
    private Conditional_Format_Value_Object $minimum_conditional_format_value_object;
    private ?string $border_color = null;
    private ?string $negative_fill_color = null;
    private ?string $negative_border_color = null;
    /** @var array{rgb: ?string, theme: ?string, tint: ?string} */
    private array $axis_color = ['rgb' => null, 'theme' => null, 'tint' => null];
    /** @return mixed[] */
    public function get_xml_attributes(): array
    {
        $ret = [];
        foreach (['minLength', 'maxLength', 'direction', 'axisPosition'] as $attr_key) {
            if (null !== $this->{$attr_key}) {
                $ret[$attr_key] = $this->{$attr_key};
            }
        }
        foreach (['border', 'gradient', 'negativeBarBorderColorSameAsPositive'] as $attr_key) {
            if (null !== $this->{$attr_key}) {
                $ret[$attr_key] = $this->{$attr_key} ? '1' : '0';
            }
        }
        return $ret;
    }
    /** @return mixed[] */
    public function get_xml_elements(): array
    {
        $ret = [];
        $elms = ['borderColor', 'negativeFillColor', 'negativeBorderColor'];
        foreach ($elms as $elm_key) {
            if (null !== $this->{$elm_key}) {
                $ret[$elm_key] = ['rgb' => $this->{$elm_key}];
            }
        }
        foreach (array_filter($this->axis_color) as $attr_key => $axis_color_attr) {
            if (!isset($ret['axisColor'])) {
                $ret['axisColor'] = [];
            }
            $ret['axisColor'][$attr_key] = $axis_color_attr;
        }
        return $ret;
    }
    public function get_min_length(): int
    {
        return $this->min_length;
    }
    public function set_min_length(int $min_length): self
    {
        $this->min_length = $min_length;
        return $this;
    }
    public function get_max_length(): int
    {
        return $this->max_length;
    }
    public function set_max_length(int $max_length): self
    {
        $this->max_length = $max_length;
        return $this;
    }
    public function get_border(): ?bool
    {
        return $this->border;
    }
    public function set_border(bool $border): self
    {
        $this->border = $border;
        return $this;
    }
    public function get_gradient(): ?bool
    {
        return $this->gradient;
    }
    public function set_gradient(bool $gradient): self
    {
        $this->gradient = $gradient;
        return $this;
    }
    public function get_direction(): ?string
    {
        return $this->direction;
    }
    public function set_direction(string $direction): self
    {
        $this->direction = $direction;
        return $this;
    }
    public function get_negative_bar_border_color_same_as_positive(): ?bool
    {
        return $this->negative_bar_border_color_same_as_positive;
    }
    public function set_negative_bar_border_color_same_as_positive(bool $negative_bar_border_color_same_as_positive): self
    {
        $this->negative_bar_border_color_same_as_positive = $negative_bar_border_color_same_as_positive;
        return $this;
    }
    public function get_axis_position(): ?string
    {
        return $this->axis_position;
    }
    public function set_axis_position(string $axis_position): self
    {
        $this->axis_position = $axis_position;
        return $this;
    }
    public function get_maximum_conditional_format_value_object(): Conditional_Format_Value_Object
    {
        return $this->maximum_conditional_format_value_object;
    }
    public function set_maximum_conditional_format_value_object(Conditional_Format_Value_Object $maximum_conditional_format_value_object): self
    {
        $this->maximum_conditional_format_value_object = $maximum_conditional_format_value_object;
        return $this;
    }
    public function get_minimum_conditional_format_value_object(): Conditional_Format_Value_Object
    {
        return $this->minimum_conditional_format_value_object;
    }
    public function set_minimum_conditional_format_value_object(Conditional_Format_Value_Object $minimum_conditional_format_value_object): self
    {
        $this->minimum_conditional_format_value_object = $minimum_conditional_format_value_object;
        return $this;
    }
    public function get_border_color(): ?string
    {
        return $this->border_color;
    }
    public function set_border_color(string $border_color): self
    {
        $this->border_color = $border_color;
        return $this;
    }
    public function get_negative_fill_color(): ?string
    {
        return $this->negative_fill_color;
    }
    public function set_negative_fill_color(string $negative_fill_color): self
    {
        $this->negative_fill_color = $negative_fill_color;
        return $this;
    }
    public function get_negative_border_color(): ?string
    {
        return $this->negative_border_color;
    }
    public function set_negative_border_color(string $negative_border_color): self
    {
        $this->negative_border_color = $negative_border_color;
        return $this;
    }
    /** @return array{rgb: ?string, theme: ?string, tint: ?string} */
    public function get_axis_color(): array
    {
        return $this->axis_color;
    }
    public function set_axis_color(?string $rgb, ?string $theme = null, ?string $tint = null): self
    {
        $this->axis_color = ['rgb' => $rgb, 'theme' => $theme, 'tint' => $tint];
        return $this;
    }
}