<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Style\Font;
class Axis_Text extends Properties
{
    private ?int $rotation = null;
    private Font $font;
    public function __construct()
    {
        parent::__construct();
        $this->font = new Font();
        $this->font->set_size(null, true);
    }
    public function set_rotation(?int $rotation): self
    {
        $this->rotation = $rotation;
        return $this;
    }
    public function get_rotation(): ?int
    {
        return $this->rotation;
    }
    public function get_fill_color_object(): Chart_Color
    {
        $fill_color = $this->font->get_chart_color();
        if ($fill_color === null) {
            $fill_color = new Chart_Color();
            $this->font->set_chart_color_from_object($fill_color);
        }
        return $fill_color;
    }
    public function get_font(): Font
    {
        return $this->font;
    }
    public function set_font(Font $font): self
    {
        $this->font = $font;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        parent::__clone();
        $this->font = clone $this->font;
    }
}