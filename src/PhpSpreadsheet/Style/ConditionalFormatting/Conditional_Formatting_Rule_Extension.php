<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Style\Conditional;
use Simple_Xml_Element;
class Conditional_Formatting_Rule_Extension
{
    public const CONDITION_EXTENSION_DATABAR = 'dataBar';
    private string $id;
    private Conditional_Data_Bar_Extension $data_bar;
    /** @var string Sequence of References */
    private string $sqref = '';
    /**
     * ConditionalFormattingRuleExtension constructor.
     */
    public function __construct(
        ?string $id = null,
        /** @var string Conditional Formatting Rule */
        private string $cf_rule = self::CONDITION_EXTENSION_DATABAR
    )
    {
        if (null === $id) {
            $this->id = '{' . $this->generate_uuid() . '}';
        } else {
            $this->id = $id;
        }
    }
    private function generate_uuid(): string
    {
        $chars = mb_str_split('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx', 1, 'UTF-8');
        foreach ($chars as $i => $char) {
            if ($char === 'x') {
                $chars[$i] = dechex(random_int(0, 15));
            } elseif ($char === 'y') {
                $chars[$i] = dechex(random_int(8, 11));
            }
        }
        return implode('', $chars);
    }
    /** @return mixed[] */
    public static function parse_ext_lst_xml(?Simple_Xml_Element $ext_lst_xml): array
    {
        $conditional_formatting_rule_extensions = [];
        $conditional_formatting_rule_extension_xml = null;
        if ($ext_lst_xml instanceof Simple_Xml_Element) {
            foreach (count($ext_lst_xml) > 0 ? $ext_lst_xml : [$ext_lst_xml] as $ext_lst) {
                //this uri is conditionalFormattings
                //https://docs.microsoft.com/en-us/openspecs/office_standards/ms-xlsx/07d607af-5618-4ca2-b683-6a78dc0d9627
                if (isset($ext_lst->ext['uri']) && (string) $ext_lst->ext['uri'] === '{78C0D931-6437-407d-A8EE-F0AAD7539E65}') {
                    $conditional_formatting_rule_extension_xml = $ext_lst->ext;
                }
            }
            if ($conditional_formatting_rule_extension_xml) {
                $ns = $conditional_formatting_rule_extension_xml->get_namespaces(true);
                $ext_formattings_xml = $conditional_formatting_rule_extension_xml->children($ns['x14']);
                foreach ($ext_formattings_xml->children($ns['x14']) as $ext_formatting_xml) {
                    $ext_cf_rule_xml = $ext_formatting_xml->cf_rule;
                    $attributes = $ext_cf_rule_xml->attributes();
                    if (!$attributes) {
                        continue;
                    }
                    if ((string) $attributes->type !== Conditional::CONDITION_DATABAR) {
                        continue;
                    }
                    $ext_formatting_rule_obj = new self((string) $attributes->id);
                    $ext_formatting_rule_obj->set_sqref((string) $ext_formatting_xml->children($ns['xm'])->sqref);
                    $conditional_formatting_rule_extensions[$ext_formatting_rule_obj->get_id()] = $ext_formatting_rule_obj;
                    $ext_data_bar_obj = new Conditional_Data_Bar_Extension();
                    $ext_formatting_rule_obj->set_data_bar_ext($ext_data_bar_obj);
                    $data_bar_xml = $ext_cf_rule_xml->data_bar;
                    self::parse_ext_data_bar_attributes_from_xml($ext_data_bar_obj, $data_bar_xml);
                    self::parse_ext_data_bar_element_children_from_xml($ext_data_bar_obj, $data_bar_xml, $ns);
                }
            }
        }
        return $conditional_formatting_rule_extensions;
    }
    private static function parse_ext_data_bar_attributes_from_xml(Conditional_Data_Bar_Extension $ext_data_bar_obj, Simple_Xml_Element $data_bar_xml): void
    {
        $data_bar_attribute = $data_bar_xml->attributes();
        if ($data_bar_attribute === null) {
            return;
        }
        if ($data_bar_attribute->min_length) {
            $ext_data_bar_obj->set_min_length((int) $data_bar_attribute->min_length);
        }
        if ($data_bar_attribute->max_length) {
            $ext_data_bar_obj->set_max_length((int) $data_bar_attribute->max_length);
        }
        if ($data_bar_attribute->border) {
            $ext_data_bar_obj->set_border((bool) (string) $data_bar_attribute->border);
        }
        if ($data_bar_attribute->gradient) {
            $ext_data_bar_obj->set_gradient((bool) (string) $data_bar_attribute->gradient);
        }
        if ($data_bar_attribute->direction) {
            $ext_data_bar_obj->set_direction((string) $data_bar_attribute->direction);
        }
        if ($data_bar_attribute->negative_bar_border_color_same_as_positive) {
            $ext_data_bar_obj->set_negative_bar_border_color_same_as_positive((bool) (string) $data_bar_attribute->negative_bar_border_color_same_as_positive);
        }
        if ($data_bar_attribute->axis_position) {
            $ext_data_bar_obj->set_axis_position((string) $data_bar_attribute->axis_position);
        }
    }
    /** @param string[] $ns */
    private static function parse_ext_data_bar_element_children_from_xml(Conditional_Data_Bar_Extension $ext_data_bar_obj, Simple_Xml_Element $data_bar_xml, array $ns): void
    {
        if ($data_bar_xml->border_color) {
            $attributes = $data_bar_xml->border_color->attributes();
            if ($attributes !== null) {
                $ext_data_bar_obj->set_border_color((string) $attributes['rgb']);
            }
        }
        if ($data_bar_xml->negative_fill_color) {
            $attributes = $data_bar_xml->negative_fill_color->attributes();
            if ($attributes !== null) {
                $ext_data_bar_obj->set_negative_fill_color((string) $attributes['rgb']);
            }
        }
        if ($data_bar_xml->negative_border_color) {
            $attributes = $data_bar_xml->negative_border_color->attributes();
            if ($attributes !== null) {
                $ext_data_bar_obj->set_negative_border_color((string) $attributes['rgb']);
            }
        }
        if ($data_bar_xml->axis_color) {
            $axis_color_attr = $data_bar_xml->axis_color->attributes();
            if ($axis_color_attr !== null) {
                $ext_data_bar_obj->set_axis_color((string) $axis_color_attr['rgb'], (string) $axis_color_attr['theme'], (string) $axis_color_attr['tint']);
            }
        }
        $cfvo_index = 0;
        foreach ($data_bar_xml->cfvo as $cfvo) {
            $f = (string) $cfvo->children($ns['xm'])->f;
            $attributes = $cfvo->attributes();
            if (!$attributes) {
                continue;
            }
            if ($cfvo_index === 0) {
                $ext_data_bar_obj->set_minimum_conditional_format_value_object(new Conditional_Format_Value_Object((string) $attributes['type'], null, empty($f) ? null : $f));
            }
            if ($cfvo_index === 1) {
                $ext_data_bar_obj->set_maximum_conditional_format_value_object(new Conditional_Format_Value_Object((string) $attributes['type'], null, empty($f) ? null : $f));
            }
            ++$cfvo_index;
        }
    }
    public function get_id(): string
    {
        return $this->id;
    }
    public function set_id(string $id): self
    {
        $this->id = $id;
        return $this;
    }
    public function get_cf_rule(): string
    {
        return $this->cf_rule;
    }
    public function set_cf_rule(string $cf_rule): self
    {
        $this->cf_rule = $cf_rule;
        return $this;
    }
    public function get_data_bar_ext(): Conditional_Data_Bar_Extension
    {
        return $this->data_bar;
    }
    public function set_data_bar_ext(Conditional_Data_Bar_Extension $data_bar): self
    {
        $this->data_bar = $data_bar;
        return $this;
    }
    public function get_sqref(): string
    {
        return $this->sqref;
    }
    public function set_sqref(string $sqref): self
    {
        $this->sqref = $sqref;
        return $this;
    }
}