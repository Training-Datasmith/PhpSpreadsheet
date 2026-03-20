<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Php_Office\Php_Spreadsheet\Style\Font as FontUnderline;
use Simple_Xml_Element;
class Font extends Style_Base
{
    protected const UNDERLINE_STYLES = [Font_Underline::UNDERLINE_NONE, Font_Underline::UNDERLINE_DOUBLE, Font_Underline::UNDERLINE_DOUBLEACCOUNTING, Font_Underline::UNDERLINE_SINGLE, Font_Underline::UNDERLINE_SINGLEACCOUNTING];
    /**
     * @param mixed[][] $style
     *
     * @return mixed[][]
     */
    protected function parse_underline(array $style, string $style_attribute_value): array
    {
        if (self::identify_fixed_style_value(self::UNDERLINE_STYLES, $style_attribute_value)) {
            $style['font']['underline'] = $style_attribute_value;
        }
        return $style;
    }
    /**
     * @param mixed[][] $style
     *
     * @return mixed[][]
     */
    protected function parse_vertical_align(array $style, string $style_attribute_value): array
    {
        if ($style_attribute_value == 'Superscript') {
            $style['font']['superscript'] = true;
        }
        if ($style_attribute_value == 'Subscript') {
            $style['font']['subscript'] = true;
        }
        return $style;
    }
    /** @return mixed[] */
    public function parse_style(Simple_Xml_Element $style_attributes): array
    {
        $style = [];
        foreach ($style_attributes as $style_attribute_key => $style_attribute_value) {
            $style_attribute_value = (string) $style_attribute_value;
            switch ($style_attribute_key) {
                case 'FontName':
                    $style['font']['name'] = $style_attribute_value;
                    break;
                case 'Size':
                    $style['font']['size'] = $style_attribute_value;
                    break;
                case 'Color':
                    /** @var string[][][] $style */
                    $style['font']['color']['rgb'] = substr($style_attribute_value, 1);
                    break;
                case 'Bold':
                    $style['font']['bold'] = $style_attribute_value === '1';
                    break;
                case 'Italic':
                    $style['font']['italic'] = $style_attribute_value === '1';
                    break;
                case 'Underline':
                    $style = $this->parse_underline($style, $style_attribute_value);
                    break;
                case 'VerticalAlign':
                    $style = $this->parse_vertical_align($style, $style_attribute_value);
                    break;
            }
        }
        return $style;
    }
}