<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml;

use Php_Office\Php_Spreadsheet\Style\Protection;
use Simple_Xml_Element;
class Style
{
    /**
     * Formats.
     *
     * @var mixed[]
     */
    protected array $styles = [];
    /**
     * @param string[] $namespaces
     *
     * @return mixed[]
     */
    public function parse_styles(Simple_Xml_Element $xml, array $namespaces): array
    {
        $children = $xml->children('urn:schemas-microsoft-com:office:spreadsheet');
        $styles_xml = $children->Styles[0];
        if (!isset($styles_xml)) {
            return [];
        }
        $alignment_style_parser = new Style\Alignment();
        $border_style_parser = new Style\Border();
        $font_style_parser = new Style\Font();
        $fill_style_parser = new Style\Fill();
        $number_format_style_parser = new Style\Number_Format();
        foreach ($styles_xml as $style) {
            $style_ss = self::get_attributes($style, $namespaces['ss']);
            $style_id = (string) $style_ss['ID'];
            $this->styles[$style_id] = $this->styles['Default'] ?? [];
            $alignment = $border = $font = $fill = $number_format = $protection = [];
            foreach ($style as $style_type => $style_datax) {
                $style_data = self::get_sxml($style_datax);
                $style_attributes = $style_data->attributes($namespaces['ss']);
                switch ($style_type) {
                    case 'Alignment':
                        if ($style_attributes) {
                            $alignment = $alignment_style_parser->parse_style($style_attributes);
                        }
                        break;
                    case 'Borders':
                        $border = $border_style_parser->parse_style($style_data, $namespaces);
                        break;
                    case 'Font':
                        if ($style_attributes) {
                            $font = $font_style_parser->parse_style($style_attributes);
                        }
                        break;
                    case 'Interior':
                        if ($style_attributes) {
                            $fill = $fill_style_parser->parse_style($style_attributes);
                        }
                        break;
                    case 'NumberFormat':
                        if ($style_attributes) {
                            $number_format = $number_format_style_parser->parse_style($style_attributes);
                        }
                        break;
                    case 'Protection':
                        $locked = $hidden = null;
                        $style_attributes_p = array_key_exists('x', $namespaces) ? $style_data->attributes($namespaces['x']) : [];
                        if (isset($style_attributes['Protected'])) {
                            $locked = (bool) (string) $style_attributes['Protected'] ? Protection::PROTECTION_PROTECTED : Protection::PROTECTION_UNPROTECTED;
                        }
                        if (isset($style_attributes_p['HideFormula'])) {
                            $hidden = (bool) (string) $style_attributes_p['HideFormula'] ? Protection::PROTECTION_PROTECTED : Protection::PROTECTION_UNPROTECTED;
                        }
                        if ($locked !== null || $hidden !== null) {
                            $protection['protection'] = [];
                            if ($locked !== null) {
                                $protection['protection']['locked'] = $locked;
                            }
                            if ($hidden !== null) {
                                $protection['protection']['hidden'] = $hidden;
                            }
                        }
                        break;
                }
            }
            $this->styles[$style_id] = array_merge($alignment, $border, $font, $fill, $number_format, $protection);
        }
        return $this->styles;
    }
    private static function get_attributes(?Simple_Xml_Element $simple, string $node): Simple_Xml_Element
    {
        return $simple === null ? new Simple_Xml_Element('<xml></xml>') : $simple->attributes($node) ?? new Simple_Xml_Element('<xml></xml>');
    }
    private static function get_sxml(?Simple_Xml_Element $simple): Simple_Xml_Element
    {
        return $simple ?? new Simple_Xml_Element('<xml></xml>');
    }
}