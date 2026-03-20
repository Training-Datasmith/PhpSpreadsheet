<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Styles as StyleReader;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Color_Scale;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Data_Bar;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Formatting_Rule_Extension;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Format_Value_Object;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Conditional_Icon_Set;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Icon_Set_Values;
use Php_Office\Php_Spreadsheet\Style\Style as Style;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
use stdClass;
class Conditional_Styles
{
    /** @var string[] */
    private array $ns;
    /** @param Style[] $dxfs */
    public function __construct(private readonly Worksheet $worksheet, private readonly Simple_Xml_Element $worksheet_xml, private array $dxfs, private readonly Style_Reader $style_reader)
    {
    }
    public function load(): void
    {
        $selected_cells = $this->worksheet->get_selected_cells();
        $this->set_conditional_styles($this->worksheet, $this->read_conditional_styles($this->worksheet_xml), $this->worksheet_xml->ext_lst);
        $this->worksheet->set_selected_cells($selected_cells);
    }
    public function load_from_ext(): void
    {
        $selected_cells = $this->worksheet->get_selected_cells();
        $this->ns = $this->worksheet_xml->get_namespaces(true);
        $this->set_conditionals_from_ext($this->read_conditionals_from_ext($this->worksheet_xml->ext_lst));
        $this->worksheet->set_selected_cells($selected_cells);
    }
    /** @param Conditional[][] $conditionals */
    private function set_conditionals_from_ext(array $conditionals): void
    {
        foreach ($conditionals as $conditional_range => $cf_rules) {
            ksort($cf_rules);
            // Priority is used as the key for sorting; but may not start at 0,
            // so we use array_values to reset the index after sorting.
            $existing = $this->worksheet->get_conditional_styles_collection();
            if (array_key_exists($conditional_range, $existing)) {
                $conditional_style = $existing[$conditional_range];
                $cf_rules = array_merge($conditional_style, $cf_rules);
            }
            $this->worksheet->get_style($conditional_range)->set_conditional_styles(array_values($cf_rules));
        }
    }
    /** @return array<string, array<int, Conditional>> */
    private function read_conditionals_from_ext(Simple_Xml_Element $ext_lst): array
    {
        $conditionals = [];
        if (!isset($ext_lst->ext)) {
            return $conditionals;
        }
        foreach ($ext_lst->ext as $extlstcond) {
            $ext_attrs = $extlstcond->attributes() ?? [];
            $ext_uri = (string) ($ext_attrs['uri'] ?? '');
            if ($ext_uri !== '{78C0D931-6437-407d-A8EE-F0AAD7539E65}') {
                continue;
            }
            $conditional_formatting_rule_xml = $extlstcond->children($this->ns['x14']);
            if (!$conditional_formatting_rule_xml->conditional_formattings) {
                return [];
            }
            foreach ($conditional_formatting_rule_xml->children($this->ns['x14']) as $ext_formatting_xml) {
                $ext_formatting_range_xml = $ext_formatting_xml->children($this->ns['xm']);
                if (!$ext_formatting_range_xml->sqref) {
                    continue;
                }
                $sqref = (string) $ext_formatting_range_xml->sqref;
                $ext_cf_rule_xml = $ext_formatting_xml->cf_rule;
                $attributes = $ext_cf_rule_xml->attributes();
                if (!$attributes) {
                    continue;
                }
                $condition_type = (string) $attributes->type;
                if (!Conditional::is_valid_condition_type($condition_type)) {
                    continue;
                }
                if ($condition_type === Conditional::CONDITION_DATABAR) {
                    continue;
                }
                $priority = (int) $attributes->priority;
                $conditional = $this->read_conditional_rule_from_ext($ext_cf_rule_xml, $attributes);
                $cf_style = $this->read_style_from_ext($ext_cf_rule_xml);
                $conditional->set_style($cf_style);
                $conditionals[$sqref][$priority] = $conditional;
            }
        }
        return $conditionals;
    }
    private function read_conditional_rule_from_ext(Simple_Xml_Element $cf_rule_xml, Simple_Xml_Element $attributes): Conditional
    {
        $condition_type = (string) $attributes->type;
        $operator_type = (string) $attributes->operator;
        $priority = (int) (string) $attributes->priority;
        $stop_if_true = (int) (string) $attributes->stop_if_true;
        $operands = [];
        foreach ($cf_rule_xml->children($this->ns['xm']) as $cf_rule_operands_xml) {
            $operands[] = (string) $cf_rule_operands_xml;
        }
        $conditional = new Conditional();
        $conditional->set_condition_type($condition_type);
        $conditional->set_operator_type($operator_type);
        $conditional->set_priority($priority);
        $conditional->set_stop_if_true($stop_if_true === 1);
        if ($condition_type === Conditional::CONDITION_CONTAINSTEXT || $condition_type === Conditional::CONDITION_NOTCONTAINSTEXT || $condition_type === Conditional::CONDITION_BEGINSWITH || $condition_type === Conditional::CONDITION_ENDSWITH || $condition_type === Conditional::CONDITION_TIMEPERIOD) {
            $conditional->set_text(array_pop($operands) ?? '');
        }
        $conditional->set_conditions($operands);
        return $conditional;
    }
    private function read_style_from_ext(Simple_Xml_Element $ext_cf_rule_xml): Style
    {
        $cf_style = new Style(false, true);
        if ($ext_cf_rule_xml->dxf) {
            $style_xml = $ext_cf_rule_xml->dxf->children();
            if ($style_xml->borders) {
                $this->style_reader->read_border_style($cf_style->get_borders(), $style_xml->borders);
            }
            if ($style_xml->fill) {
                $this->style_reader->read_fill_style($cf_style->get_fill(), $style_xml->fill);
            }
            if ($style_xml->font) {
                $this->style_reader->read_font_style($cf_style->get_font(), $style_xml->font);
            }
        }
        return $cf_style;
    }
    /** @return mixed[] */
    private function read_conditional_styles(Simple_Xml_Element $xml_sheet): array
    {
        $conditionals = [];
        foreach ($xml_sheet->conditional_formatting as $conditional) {
            foreach ($conditional->cf_rule as $cf_rule) {
                if (Conditional::is_valid_condition_type((string) $cf_rule['type']) && (!isset($cf_rule['dxfId']) || isset($this->dxfs[(int) $cf_rule['dxfId']]))) {
                    $conditionals[(string) $conditional['sqref']][(int) $cf_rule['priority']] = $cf_rule;
                } elseif ((string) $cf_rule['type'] == Conditional::CONDITION_DATABAR) {
                    $conditionals[(string) $conditional['sqref']][(int) $cf_rule['priority']] = $cf_rule;
                }
            }
        }
        return $conditionals;
    }
    /** @param mixed[] $conditionals */
    private function set_conditional_styles(Worksheet $worksheet, array $conditionals, Simple_Xml_Element $xml_ext_lst): void
    {
        foreach ($conditionals as $cell_range_reference => $cf_rules) {
            /** @var mixed[] $cfRules */
            ksort($cf_rules);
            // no longer needed for Xlsx, but helps Xls
            $conditional_styles = $this->read_style_rules($cf_rules, $xml_ext_lst);
            // Extract all cell references in $cellRangeReference
            // N.B. In Excel UI, intersection is space and union is comma.
            // But in Xml, intersection is comma and union is space.
            $cell_range_reference = str_replace(['$', ' ', ',', '^'], ['', '^', ' ', ','], strtoupper((string) $cell_range_reference));
            foreach ($conditional_styles as $cs) {
                $scale = $cs->get_color_scale();
                if ($scale !== null) {
                    $scale->set_sq_ref($cell_range_reference, $worksheet);
                }
            }
            $worksheet->get_style($cell_range_reference)->set_conditional_styles($conditional_styles);
        }
    }
    /**
     * @param mixed[] $cfRules
     *
     * @return Conditional[]
     */
    private function read_style_rules(array $cf_rules, Simple_Xml_Element $ext_lst): array
    {
        /** @var ConditionalFormattingRuleExtension[] */
        $conditional_formatting_rule_extensions = Conditional_Formatting_Rule_Extension::parse_ext_lst_xml($ext_lst);
        $conditional_styles = [];
        /** @var SimpleXMLElement $cfRule */
        foreach ($cf_rules as $cf_rule) {
            $obj_conditional = new Conditional();
            $obj_conditional->set_condition_type((string) $cf_rule['type']);
            $obj_conditional->set_operator_type((string) $cf_rule['operator']);
            $obj_conditional->set_priority((int) (string) $cf_rule['priority']);
            $obj_conditional->set_no_format_set(!isset($cf_rule['dxfId']));
            if ((string) $cf_rule['text'] != '') {
                $obj_conditional->set_text((string) $cf_rule['text']);
            } elseif ((string) $cf_rule['timePeriod'] != '') {
                $obj_conditional->set_text((string) $cf_rule['timePeriod']);
            }
            if (isset($cf_rule['stopIfTrue']) && (int) $cf_rule['stopIfTrue'] === 1) {
                $obj_conditional->set_stop_if_true(true);
            }
            if (count($cf_rule->formula) >= 1) {
                foreach ($cf_rule->formula as $formulax) {
                    $formula = (string) $formulax;
                    $formula = str_replace(['_xlfn.', '_xlws.'], '', $formula);
                    if ($formula === 'TRUE') {
                        $obj_conditional->add_condition(true);
                    } elseif ($formula === 'FALSE') {
                        $obj_conditional->add_condition(false);
                    } else {
                        $obj_conditional->add_condition($formula);
                    }
                }
            } else {
                $obj_conditional->add_condition('');
            }
            if (isset($cf_rule->data_bar)) {
                $obj_conditional->set_data_bar($this->read_data_bar_of_conditional_rule($cf_rule, $conditional_formatting_rule_extensions));
            } elseif (isset($cf_rule->color_scale)) {
                $obj_conditional->set_color_scale($this->read_color_scale($cf_rule));
            } elseif (isset($cf_rule->icon_set)) {
                $obj_conditional->set_icon_set($this->read_icon_set($cf_rule));
            } elseif (isset($cf_rule['dxfId'])) {
                $obj_conditional->set_style(clone $this->dxfs[(int) $cf_rule['dxfId']]);
            }
            $conditional_styles[] = $obj_conditional;
        }
        return $conditional_styles;
    }
    /** @param ConditionalFormattingRuleExtension[] $conditionalFormattingRuleExtensions */
    private function read_data_bar_of_conditional_rule(Simple_Xml_Element $cf_rule, array $conditional_formatting_rule_extensions): Conditional_Data_Bar
    {
        $data_bar = new Conditional_Data_Bar();
        //dataBar attribute
        if (isset($cf_rule->data_bar['showValue'])) {
            $data_bar->set_show_value((bool) $cf_rule->data_bar['showValue']);
        }
        //dataBar children
        //conditionalFormatValueObjects
        $cfvo_xml = $cf_rule->data_bar->cfvo;
        $cfvo_index = 0;
        foreach (count($cfvo_xml) > 1 ? $cfvo_xml : [$cfvo_xml] as $cfvo) {
            //* @phpstan-ignore-line
            /** @var SimpleXMLElement $cfvo */
            if ($cfvo_index === 0) {
                $data_bar->set_minimum_conditional_format_value_object(new Conditional_Format_Value_Object((string) $cfvo['type'], (string) $cfvo['val']));
            }
            if ($cfvo_index === 1) {
                $data_bar->set_maximum_conditional_format_value_object(new Conditional_Format_Value_Object((string) $cfvo['type'], (string) $cfvo['val']));
            }
            ++$cfvo_index;
        }
        //color
        if (isset($cf_rule->data_bar->color)) {
            $data_bar->set_color($this->style_reader->read_color($cf_rule->data_bar->color));
        }
        //extLst
        $this->read_data_bar_ext_lst_of_conditional_rule($data_bar, $cf_rule, $conditional_formatting_rule_extensions);
        return $data_bar;
    }
    private function read_color_scale(Simple_Xml_Element|stdClass $cf_rule): Conditional_Color_Scale
    {
        $color_scale = new Conditional_Color_Scale();
        /** @var SimpleXMLElement $cfRule */
        $count = count($cf_rule->color_scale->cfvo);
        $idx = 0;
        foreach ($cf_rule->color_scale->cfvo as $cfvo_xml) {
            $attr = $cfvo_xml->attributes() ?? [];
            $type = (string) ($attr['type'] ?? '');
            $val = $attr['val'] ?? null;
            if ($idx === 0) {
                $method = 'setMinimumConditionalFormatValueObject';
            } elseif ($idx === 1 && $count === 3) {
                $method = 'setMidpointConditionalFormatValueObject';
            } else {
                $method = 'setMaximumConditionalFormatValueObject';
            }
            if ($type !== 'formula') {
                $color_scale->{$method}(new Conditional_Format_Value_Object($type, $val));
            } else {
                $color_scale->{$method}(new Conditional_Format_Value_Object($type, null, $val));
            }
            ++$idx;
        }
        $idx = 0;
        foreach ($cf_rule->color_scale->color as $color) {
            $rgb = $this->style_reader->read_color($color);
            if ($idx === 0) {
                $color_scale->set_minimum_color(new Color($rgb));
            } elseif ($idx === 1 && $count === 3) {
                $color_scale->set_midpoint_color(new Color($rgb));
            } else {
                $color_scale->set_maximum_color(new Color($rgb));
            }
            ++$idx;
        }
        return $color_scale;
    }
    private function read_icon_set(Simple_Xml_Element $cf_rule): Conditional_Icon_Set
    {
        $icon_set = new Conditional_Icon_Set();
        if (isset($cf_rule->icon_set['iconSet'])) {
            $icon_set->set_icon_set_type(Icon_Set_Values::from($cf_rule->icon_set['iconSet']));
        }
        if (isset($cf_rule->icon_set['reverse'])) {
            $icon_set->set_reverse('1' === (string) $cf_rule->icon_set['reverse']);
        }
        if (isset($cf_rule->icon_set['showValue'])) {
            $icon_set->set_show_value('1' === (string) $cf_rule->icon_set['showValue']);
        }
        if (isset($cf_rule->icon_set['custom'])) {
            $icon_set->set_custom('1' === (string) $cf_rule->icon_set['custom']);
        }
        $cfvos = [];
        foreach ($cf_rule->icon_set->cfvo as $cfvo_xml) {
            $type = (string) $cfvo_xml['type'];
            $value = (string) ($cfvo_xml['val'] ?? '');
            $cfvo = new Conditional_Format_Value_Object($type, $value);
            if (isset($cfvo_xml['gte'])) {
                $cfvo->set_greater_than_or_equal('1' === (string) $cfvo_xml['gte']);
            }
            $cfvos[] = $cfvo;
        }
        $icon_set->set_cfvos($cfvos);
        // TODO: The cfIcon element is not implemented yet.
        return $icon_set;
    }
    /** @param ConditionalFormattingRuleExtension[] $conditionalFormattingRuleExtensions */
    private function read_data_bar_ext_lst_of_conditional_rule(Conditional_Data_Bar $data_bar, Simple_Xml_Element $cf_rule, array $conditional_formatting_rule_extensions): void
    {
        if (isset($cf_rule->ext_lst)) {
            $ns = $cf_rule->ext_lst->get_namespaces(true);
            foreach (count($cf_rule->ext_lst) > 0 ? $cf_rule->ext_lst->ext : [$cf_rule->ext_lst->ext] as $ext) {
                //* @phpstan-ignore-line
                /** @var SimpleXMLElement $ext */
                $ext_id = (string) $ext->children($ns['x14'])->id;
                if (isset($conditional_formatting_rule_extensions[$ext_id]) && (string) $ext['uri'] === '{B025F937-C7B1-47D3-B67F-A62EFF666E3E}') {
                    $data_bar->set_conditional_formatting_rule_ext($conditional_formatting_rule_extensions[$ext_id]);
                }
            }
        }
    }
}