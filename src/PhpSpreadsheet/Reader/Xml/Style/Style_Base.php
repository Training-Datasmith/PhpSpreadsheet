<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Simple_Xml_Element;
abstract class Style_Base
{
    /** @param string[] $styleList */
    protected static function identify_fixed_style_value(array $style_list, string &$style_attribute_value): bool
    {
        $return_value = false;
        $style_attribute_value = strtolower($style_attribute_value);
        foreach ($style_list as $style) {
            if ($style_attribute_value == strtolower($style)) {
                $style_attribute_value = $style;
                $return_value = true;
                break;
            }
        }
        return $return_value;
    }
    protected static function get_attributes(?Simple_Xml_Element $simple, string $node): Simple_Xml_Element
    {
        return $simple === null ? new Simple_Xml_Element('<xml></xml>') : $simple->attributes($node) ?? new Simple_Xml_Element('<xml></xml>');
    }
}