<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Simple_Xml_Element;
class Number_Format extends Style_Base
{
    /** @return mixed[] */
    public function parse_style(Simple_Xml_Element $style_attributes): array
    {
        $style = [];
        $from_formats = ['\-', '\ '];
        $to_formats = ['-', ' '];
        foreach ($style_attributes as $style_attribute_value) {
            $style_attribute_value = str_replace($from_formats, $to_formats, (string) $style_attribute_value);
            switch ($style_attribute_value) {
                case 'Short Date':
                    $style_attribute_value = 'dd/mm/yyyy';
                    break;
            }
            if ($style_attribute_value > '') {
                $style['numberFormat']['formatCode'] = $style_attribute_value;
            }
        }
        return $style;
    }
}