<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml;

use Php_Office\Php_Spreadsheet\Document\Properties as DocumentProperties;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Simple_Xml_Element;
class Properties
{
    public function __construct(protected Spreadsheet $spreadsheet)
    {
    }
    /** @param string[] $namespaces */
    public function read_properties(Simple_Xml_Element $xml, array $namespaces): void
    {
        $this->read_standard_properties($xml);
        $this->read_custom_properties($xml, $namespaces);
    }
    protected function read_standard_properties(Simple_Xml_Element $xml): void
    {
        if (isset($xml->document_properties[0])) {
            $doc_props = $this->spreadsheet->get_properties();
            foreach ($xml->document_properties[0] as $property_name => $property_value) {
                $property_value = (string) $property_value;
                $this->process_standard_property($doc_props, $property_name, $property_value);
            }
        }
    }
    /** @param string[] $namespaces */
    protected function read_custom_properties(Simple_Xml_Element $xml, array $namespaces): void
    {
        if (isset($xml->custom_document_properties) && is_iterable($xml->custom_document_properties[0])) {
            $doc_props = $this->spreadsheet->get_properties();
            foreach ($xml->custom_document_properties[0] as $property_name => $property_value) {
                $property_attributes = self::get_attributes($property_value, $namespaces['dt']);
                $property_name = (string) preg_replace_callback('/_x([0-9a-f]{4})_/i', $this->hex2str(...), $property_name);
                $this->process_custom_property($doc_props, $property_name, $property_value, $property_attributes);
            }
        }
    }
    protected function process_standard_property(Document_Properties $doc_props, string $property_name, string $string_value): void
    {
        switch ($property_name) {
            case 'Title':
                $doc_props->set_title($string_value);
                break;
            case 'Subject':
                $doc_props->set_subject($string_value);
                break;
            case 'Author':
                $doc_props->set_creator($string_value);
                break;
            case 'Created':
                $doc_props->set_created($string_value);
                break;
            case 'LastAuthor':
                $doc_props->set_last_modified_by($string_value);
                break;
            case 'LastSaved':
                $doc_props->set_modified($string_value);
                break;
            case 'Company':
                $doc_props->set_company($string_value);
                break;
            case 'Category':
                $doc_props->set_category($string_value);
                break;
            case 'Manager':
                $doc_props->set_manager($string_value);
                break;
            case 'HyperlinkBase':
                $doc_props->set_hyperlink_base($string_value);
                break;
            case 'Keywords':
                $doc_props->set_keywords($string_value);
                break;
            case 'Description':
                $doc_props->set_description($string_value);
                break;
        }
    }
    protected function process_custom_property(Document_Properties $doc_props, string $property_name, ?Simple_Xml_Element $property_value, Simple_Xml_Element $property_attributes): void
    {
        switch ((string) $property_attributes) {
            case 'boolean':
                $property_type = Document_Properties::PROPERTY_TYPE_BOOLEAN;
                $property_value = (bool) (string) $property_value;
                break;
            case 'integer':
                $property_type = Document_Properties::PROPERTY_TYPE_INTEGER;
                $property_value = (int) $property_value;
                break;
            case 'float':
                $property_type = Document_Properties::PROPERTY_TYPE_FLOAT;
                $property_value = (float) $property_value;
                break;
            case 'dateTime.tz':
            case 'dateTime.iso8601tz':
                $property_type = Document_Properties::PROPERTY_TYPE_DATE;
                $property_value = trim((string) $property_value);
                break;
            default:
                $property_type = Document_Properties::PROPERTY_TYPE_STRING;
                $property_value = trim((string) $property_value);
                break;
        }
        $doc_props->set_custom_property($property_name, $property_value, $property_type);
    }
    /** @param string[] $hex */
    protected function hex2str(array $hex): string
    {
        return mb_chr((int) hexdec($hex[1]), 'UTF-8');
    }
    private static function get_attributes(?Simple_Xml_Element $simple, string $node): Simple_Xml_Element
    {
        return $simple === null ? new Simple_Xml_Element('<xml></xml>') : $simple->attributes($node) ?? new Simple_Xml_Element('<xml></xml>');
    }
}