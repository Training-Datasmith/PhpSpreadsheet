<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Gnumeric;

use Php_Office\Php_Spreadsheet\Reader\Gnumeric;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Simple_Xml_Element;
class Properties
{
    public function __construct(protected Spreadsheet $spreadsheet)
    {
    }
    private function doc_properties_old(Simple_Xml_Element $gnm_xml): void
    {
        $doc_props = $this->spreadsheet->get_properties();
        foreach ($gnm_xml->Summary->Item as $summary_item) {
            $property_name = $summary_item->name;
            $property_value = $summary_item->{'val-string'};
            switch ($property_name) {
                case 'title':
                    $doc_props->set_title(trim($property_value));
                    break;
                case 'comments':
                    $doc_props->set_description(trim($property_value));
                    break;
                case 'keywords':
                    $doc_props->set_keywords(trim($property_value));
                    break;
                case 'category':
                    $doc_props->set_category(trim($property_value));
                    break;
                case 'manager':
                    $doc_props->set_manager(trim($property_value));
                    break;
                case 'author':
                    $doc_props->set_creator(trim($property_value));
                    $doc_props->set_last_modified_by(trim($property_value));
                    break;
                case 'company':
                    $doc_props->set_company(trim($property_value));
                    break;
            }
        }
    }
    private function doc_properties_dc(Simple_Xml_Element $office_property_dc): void
    {
        $doc_props = $this->spreadsheet->get_properties();
        foreach ($office_property_dc as $property_name => $property_value) {
            $property_value = trim((string) $property_value);
            switch ($property_name) {
                case 'title':
                    $doc_props->set_title($property_value);
                    break;
                case 'subject':
                    $doc_props->set_subject($property_value);
                    break;
                case 'creator':
                    $doc_props->set_creator($property_value);
                    $doc_props->set_last_modified_by($property_value);
                    break;
                case 'date':
                    $creation_date = $property_value;
                    $doc_props->set_modified($creation_date);
                    break;
                case 'description':
                    $doc_props->set_description($property_value);
                    break;
            }
        }
    }
    private function doc_properties_meta(Simple_Xml_Element $office_property_meta): void
    {
        $doc_props = $this->spreadsheet->get_properties();
        foreach ($office_property_meta as $property_name => $property_value) {
            $attributes = $property_value->attributes(Gnumeric::NAMESPACE_META);
            $property_value = trim((string) $property_value);
            switch ($property_name) {
                case 'keyword':
                    $doc_props->set_keywords($property_value);
                    break;
                case 'initial-creator':
                    $doc_props->set_creator($property_value);
                    $doc_props->set_last_modified_by($property_value);
                    break;
                case 'creation-date':
                    $creation_date = $property_value;
                    $doc_props->set_created($creation_date);
                    break;
                case 'user-defined':
                    if ($attributes) {
                        [, $attr_name] = explode(':', (string) $attributes['name']);
                        $this->user_defined_properties($attr_name, $property_value);
                    }
                    break;
            }
        }
    }
    private function user_defined_properties(string $attr_name, string $property_value): void
    {
        $doc_props = $this->spreadsheet->get_properties();
        switch ($attr_name) {
            case 'publisher':
                $doc_props->set_company($property_value);
                break;
            case 'category':
                $doc_props->set_category($property_value);
                break;
            case 'manager':
                $doc_props->set_manager($property_value);
                break;
        }
    }
    public function read_properties(Simple_Xml_Element $xml, Simple_Xml_Element $gnm_xml): void
    {
        $office_xml = $xml->children(Gnumeric::NAMESPACE_OFFICE);
        if (!empty($office_xml)) {
            $office_doc_xml = $office_xml->{'document-meta'};
            $office_doc_meta_xml = $office_doc_xml->meta;
            foreach ($office_doc_meta_xml as $office_property_data) {
                $office_property_dc = $office_property_data->children(Gnumeric::NAMESPACE_DC);
                $this->doc_properties_dc($office_property_dc);
                $office_property_meta = $office_property_data->children(Gnumeric::NAMESPACE_META);
                $this->doc_properties_meta($office_property_meta);
            }
        } elseif (isset($gnm_xml->Summary)) {
            $this->doc_properties_old($gnm_xml);
        }
    }
}