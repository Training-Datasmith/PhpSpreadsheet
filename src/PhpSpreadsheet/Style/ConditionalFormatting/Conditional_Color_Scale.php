<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Calculation\Statistical\Percentiles;
use Php_Office\Php_Spreadsheet\Style\Color;
class Conditional_Color_Scale
{
    private ?Conditional_Format_Value_Object $minimum_conditional_format_value_object = null;
    private ?Conditional_Format_Value_Object $midpoint_conditional_format_value_object = null;
    private ?Conditional_Format_Value_Object $maximum_conditional_format_value_object = null;
    private ?Color $minimum_color = null;
    private ?Color $midpoint_color = null;
    private ?Color $maximum_color = null;
    private ?string $sqref = null;
    /** @var mixed[] */
    private array $value_array = [];
    private float $min_value = 0;
    private float $max_value = 0;
    private float $mid_value = 0;
    private ?\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet = null;
    public function get_minimum_conditional_format_value_object(): ?Conditional_Format_Value_Object
    {
        return $this->minimum_conditional_format_value_object;
    }
    public function set_minimum_conditional_format_value_object(Conditional_Format_Value_Object $minimum_conditional_format_value_object): self
    {
        $this->minimum_conditional_format_value_object = $minimum_conditional_format_value_object;
        return $this;
    }
    public function get_midpoint_conditional_format_value_object(): ?Conditional_Format_Value_Object
    {
        return $this->midpoint_conditional_format_value_object;
    }
    public function set_midpoint_conditional_format_value_object(Conditional_Format_Value_Object $midpoint_conditional_format_value_object): self
    {
        $this->midpoint_conditional_format_value_object = $midpoint_conditional_format_value_object;
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
    public function get_minimum_color(): ?Color
    {
        return $this->minimum_color;
    }
    public function set_minimum_color(Color $minimum_color): self
    {
        $this->minimum_color = $minimum_color;
        return $this;
    }
    public function get_midpoint_color(): ?Color
    {
        return $this->midpoint_color;
    }
    public function set_midpoint_color(Color $midpoint_color): self
    {
        $this->midpoint_color = $midpoint_color;
        return $this;
    }
    public function get_maximum_color(): ?Color
    {
        return $this->maximum_color;
    }
    public function set_maximum_color(Color $maximum_color): self
    {
        $this->maximum_color = $maximum_color;
        return $this;
    }
    public function get_sq_ref(): ?string
    {
        return $this->sqref;
    }
    public function set_sq_ref(string $sqref, \Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): self
    {
        $this->sqref = $sqref;
        $this->worksheet = $worksheet;
        return $this;
    }
    public function set_scale_array(): self
    {
        if ($this->sqref !== null && $this->worksheet !== null) {
            $values = $this->worksheet->ranges_to_array($this->sqref, null, true, true, true);
            $this->value_array = [];
            foreach ($values as $value) {
                /** @var array<float|int|string> $value */
                foreach ($value as $v) {
                    $this->value_array[] = (float) $v;
                }
            }
            $this->prepare_color_scale();
        }
        return $this;
    }
    public function get_color_for_value(float $value): string
    {
        if ($this->minimum_color === null || $this->midpoint_color === null || $this->maximum_color === null) {
            return 'FF000000';
        }
        $min_color = $this->minimum_color->get_argb();
        $mid_color = $this->midpoint_color->get_argb();
        $max_color = $this->maximum_color->get_argb();
        if ($min_color === null || $mid_color === null || $max_color === null) {
            return 'FF000000';
        }
        if ($value <= $this->min_value) {
            return $min_color;
        }
        if ($value >= $this->max_value) {
            return $max_color;
        }
        if ($value == $this->mid_value) {
            return $mid_color;
        }
        if ($value < $this->mid_value) {
            $blend = ($value - $this->min_value) / ($this->mid_value - $this->min_value);
            $alpha1 = hexdec(substr($min_color, 0, 2));
            $alpha2 = hexdec(substr($mid_color, 0, 2));
            $red1 = hexdec(substr($min_color, 2, 2));
            $red2 = hexdec(substr($mid_color, 2, 2));
            $green1 = hexdec(substr($min_color, 4, 2));
            $green2 = hexdec(substr($mid_color, 4, 2));
            $blue1 = hexdec(substr($min_color, 6, 2));
            $blue2 = hexdec(substr($mid_color, 6, 2));
            return strtoupper(dechex((int) ($alpha2 * $blend + $alpha1 * (1 - $blend))) . '' . dechex((int) ($red2 * $blend + $red1 * (1 - $blend))) . '' . dechex((int) ($green2 * $blend + $green1 * (1 - $blend))) . '' . dechex((int) ($blue2 * $blend + $blue1 * (1 - $blend))));
        }
        $blend = ($value - $this->mid_value) / ($this->max_value - $this->mid_value);
        $alpha1 = hexdec(substr($mid_color, 0, 2));
        $alpha2 = hexdec(substr($max_color, 0, 2));
        $red1 = hexdec(substr($mid_color, 2, 2));
        $red2 = hexdec(substr($max_color, 2, 2));
        $green1 = hexdec(substr($mid_color, 4, 2));
        $green2 = hexdec(substr($max_color, 4, 2));
        $blue1 = hexdec(substr($mid_color, 6, 2));
        $blue2 = hexdec(substr($max_color, 6, 2));
        return strtoupper(dechex((int) ($alpha2 * $blend + $alpha1 * (1 - $blend))) . '' . dechex((int) ($red2 * $blend + $red1 * (1 - $blend))) . '' . dechex((int) ($green2 * $blend + $green1 * (1 - $blend))) . '' . dechex((int) ($blue2 * $blend + $blue1 * (1 - $blend))));
    }
    private function get_limit_value(string $type, float $value = 0, float $formula = 0): float
    {
        if (count($this->value_array) === 0) {
            return 0;
        }
        switch ($type) {
            case 'min':
                /** @var float|int */
                $temp = min($this->value_array);
                return (float) $temp;
            case 'max':
                /** @var float|int */
                $temp = max($this->value_array);
                return (float) $temp;
            case 'percentile':
                return (float) Percentiles::PERCENTILE($this->value_array, $value / 100);
            case 'formula':
                return $formula;
            case 'percent':
                /** @var float|int */
                $min = min($this->value_array);
                $min = (float) $min;
                /** @var float|int */
                $max = max($this->value_array);
                $max = (float) $max;
                return $min + $value / 100 * ($max - $min);
            default:
                return 0;
        }
    }
    /**
     * Prepares color scale for execution, see the first if for variables that must be set beforehand.
     */
    public function prepare_color_scale(): self
    {
        if ($this->minimum_conditional_format_value_object !== null && $this->maximum_conditional_format_value_object !== null && $this->minimum_color !== null && $this->maximum_color !== null) {
            if ($this->midpoint_conditional_format_value_object !== null && $this->midpoint_conditional_format_value_object->get_type() !== 'None') {
                $this->min_value = $this->get_limit_value($this->minimum_conditional_format_value_object->get_type(), (float) $this->minimum_conditional_format_value_object->get_value(), (float) $this->minimum_conditional_format_value_object->get_cell_formula());
                $this->mid_value = $this->get_limit_value($this->midpoint_conditional_format_value_object->get_type(), (float) $this->midpoint_conditional_format_value_object->get_value(), (float) $this->midpoint_conditional_format_value_object->get_cell_formula());
                $this->max_value = $this->get_limit_value($this->maximum_conditional_format_value_object->get_type(), (float) $this->maximum_conditional_format_value_object->get_value(), (float) $this->maximum_conditional_format_value_object->get_cell_formula());
            } else {
                $this->min_value = $this->get_limit_value($this->minimum_conditional_format_value_object->get_type(), (float) $this->minimum_conditional_format_value_object->get_value(), (float) $this->minimum_conditional_format_value_object->get_cell_formula());
                $this->max_value = $this->get_limit_value($this->maximum_conditional_format_value_object->get_type(), (float) $this->maximum_conditional_format_value_object->get_value(), (float) $this->maximum_conditional_format_value_object->get_cell_formula());
                $this->mid_value = $this->min_value + $this->max_value / 2;
                $blend = 0.5;
                $min_color = $this->minimum_color->get_argb();
                $max_color = $this->maximum_color->get_argb();
                if ($min_color !== null && $max_color !== null) {
                    $alpha1 = hexdec(substr($min_color, 0, 2));
                    $alpha2 = hexdec(substr($max_color, 0, 2));
                    $red1 = hexdec(substr($min_color, 2, 2));
                    $red2 = hexdec(substr($max_color, 2, 2));
                    $green1 = hexdec(substr($min_color, 4, 2));
                    $green2 = hexdec(substr($max_color, 4, 2));
                    $blue1 = hexdec(substr($min_color, 6, 2));
                    $blue2 = hexdec(substr($max_color, 6, 2));
                    $this->midpoint_color = new Color(strtoupper(dechex((int) ($alpha2 * $blend + $alpha1 * (1 - $blend))) . '' . dechex((int) ($red2 * $blend + $red1 * (1 - $blend))) . '' . dechex((int) ($green2 * $blend + $green1 * (1 - $blend))) . '' . dechex((int) ($blue2 * $blend + $blue1 * (1 - $blend)))));
                } else {
                    $this->midpoint_color = null;
                }
            }
        }
        return $this;
    }
    /**
     * Checks that all needed color scale data is in place.
     */
    public function color_scale_ready_for_use(): bool
    {
        if ($this->minimum_color === null || $this->midpoint_color === null || $this->maximum_color === null) {
            return false;
        }
        return true;
    }
}