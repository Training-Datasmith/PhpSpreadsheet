<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Php_Office\Php_Spreadsheet\Style\Alignment as AlignmentStyles;
use Simple_Xml_Element;
class Alignment extends Style_Base
{
    protected const VERTICAL_ALIGNMENT_STYLES = [Alignment_Styles::VERTICAL_BOTTOM, Alignment_Styles::VERTICAL_TOP, Alignment_Styles::VERTICAL_CENTER, Alignment_Styles::VERTICAL_JUSTIFY];
    protected const HORIZONTAL_ALIGNMENT_STYLES = [Alignment_Styles::HORIZONTAL_GENERAL, Alignment_Styles::HORIZONTAL_LEFT, Alignment_Styles::HORIZONTAL_RIGHT, Alignment_Styles::HORIZONTAL_CENTER, Alignment_Styles::HORIZONTAL_CENTER_CONTINUOUS, Alignment_Styles::HORIZONTAL_JUSTIFY];
    /** @return mixed[] */
    public function parse_style(Simple_Xml_Element $style_attributes): array
    {
        $style = [];
        foreach ($style_attributes as $style_attribute_key => $style_attribute_value) {
            $style_attribute_value = (string) $style_attribute_value;
            switch ($style_attribute_key) {
                case 'Vertical':
                    if (self::identify_fixed_style_value(self::VERTICAL_ALIGNMENT_STYLES, $style_attribute_value)) {
                        $style['alignment']['vertical'] = $style_attribute_value;
                    }
                    break;
                case 'Horizontal':
                    if (self::identify_fixed_style_value(self::HORIZONTAL_ALIGNMENT_STYLES, $style_attribute_value)) {
                        $style['alignment']['horizontal'] = $style_attribute_value;
                    }
                    break;
                case 'WrapText':
                    $style['alignment']['wrapText'] = true;
                    break;
                case 'Rotate':
                    $style['alignment']['textRotation'] = $style_attribute_value;
                    break;
                case 'Indent':
                    $style['alignment']['indent'] = $style_attribute_value;
                    break;
                case 'ReadingOrder':
                    if ($style_attribute_value === 'RightToLeft') {
                        $style['alignment']['readOrder'] = Alignment_Styles::READORDER_RTL;
                    } elseif ($style_attribute_value === 'LeftToRight') {
                        $style['alignment']['readOrder'] = Alignment_Styles::READORDER_LTR;
                    }
                    break;
            }
        }
        return $style;
    }
}