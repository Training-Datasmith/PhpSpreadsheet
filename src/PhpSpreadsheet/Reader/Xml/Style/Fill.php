<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Php_Office\Php_Spreadsheet\Style\Fill as FillStyles;
use Simple_Xml_Element;
class Fill extends Style_Base
{
    public const FILL_MAPPINGS = ['fillType' => [
        'solid' => Fill_Styles::FILL_SOLID,
        'gray75' => Fill_Styles::FILL_PATTERN_DARKGRAY,
        'gray50' => Fill_Styles::FILL_PATTERN_MEDIUMGRAY,
        'gray25' => Fill_Styles::FILL_PATTERN_LIGHTGRAY,
        'gray125' => Fill_Styles::FILL_PATTERN_GRAY125,
        'gray0625' => Fill_Styles::FILL_PATTERN_GRAY0625,
        'horzstripe' => Fill_Styles::FILL_PATTERN_DARKHORIZONTAL,
        // horizontal stripe
        'vertstripe' => Fill_Styles::FILL_PATTERN_DARKVERTICAL,
        // vertical stripe
        'reversediagstripe' => Fill_Styles::FILL_PATTERN_DARKUP,
        // reverse diagonal stripe
        'diagstripe' => Fill_Styles::FILL_PATTERN_DARKDOWN,
        // diagonal stripe
        'diagcross' => Fill_Styles::FILL_PATTERN_DARKGRID,
        // diagoanl crosshatch
        'thickdiagcross' => Fill_Styles::FILL_PATTERN_DARKTRELLIS,
        // thick diagonal crosshatch
        'thinhorzstripe' => Fill_Styles::FILL_PATTERN_LIGHTHORIZONTAL,
        'thinvertstripe' => Fill_Styles::FILL_PATTERN_LIGHTVERTICAL,
        'thinreversediagstripe' => Fill_Styles::FILL_PATTERN_LIGHTUP,
        'thindiagstripe' => Fill_Styles::FILL_PATTERN_LIGHTDOWN,
        'thinhorzcross' => Fill_Styles::FILL_PATTERN_LIGHTGRID,
        // thin horizontal crosshatch
        'thindiagcross' => Fill_Styles::FILL_PATTERN_LIGHTTRELLIS,
    ]];
    /** @return mixed[] */
    public function parse_style(Simple_Xml_Element $style_attributes): array
    {
        $style = [];
        foreach ($style_attributes as $style_attribute_key => $style_attribute_valuex) {
            $style_attribute_value = (string) $style_attribute_valuex;
            switch ($style_attribute_key) {
                case 'Color':
                    $style['fill']['endColor']['rgb'] = substr($style_attribute_value, 1);
                    $style['fill']['startColor']['rgb'] = substr($style_attribute_value, 1);
                    break;
                case 'PatternColor':
                    $style['fill']['startColor']['rgb'] = substr($style_attribute_value, 1);
                    break;
                case 'Pattern':
                    $lc_style_attribute_value = strtolower($style_attribute_value);
                    $style['fill']['fillType'] = self::FILL_MAPPINGS['fillType'][$lc_style_attribute_value] ?? Fill_Styles::FILL_NONE;
                    break;
            }
        }
        return $style;
    }
}