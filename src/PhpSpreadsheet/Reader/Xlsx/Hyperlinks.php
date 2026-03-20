<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Hyperlinks
{
    /** @var string[] */
    private array $hyperlinks = [];
    public function __construct(private readonly Worksheet $worksheet)
    {
    }
    public function read_hyperlinks(Simple_Xml_Element $rels_worksheet): void
    {
        foreach ($rels_worksheet->children(Namespaces::RELATIONSHIPS)->Relationship as $elementx) {
            $element = Xlsx::get_attributes($elementx);
            if ($element->Type == Namespaces::HYPERLINK) {
                $this->hyperlinks[(string) $element->Id] = (string) $element->Target;
            }
        }
    }
    public function set_hyperlinks(Simple_Xml_Element $worksheet_xml): void
    {
        foreach ($worksheet_xml->children(Namespaces::MAIN)->hyperlink as $hyperlink) {
            $this->set_hyperlink($hyperlink, $this->worksheet);
        }
    }
    private function set_hyperlink(Simple_Xml_Element $hyperlink, Worksheet $worksheet): void
    {
        // Link url
        $link_rel = Xlsx::get_attributes($hyperlink, Namespaces::SCHEMA_OFFICE_DOCUMENT);
        $attributes = Xlsx::get_attributes($hyperlink);
        foreach (Coordinate::extract_all_cell_references_in_range($attributes->ref) as $cell_reference) {
            $cell = $worksheet->get_cell($cell_reference);
            if (isset($attributes['location'])) {
                $cell->get_hyperlink()->set_url('sheet://' . $attributes['location']);
            } elseif (isset($link_rel['id'])) {
                $hyperlink_url = $this->hyperlinks[(string) $link_rel['id']] ?? '';
                $cell->get_hyperlink()->set_url($hyperlink_url);
            }
            // Tooltip
            if (isset($attributes['tooltip'])) {
                $cell->get_hyperlink()->set_tooltip((string) $attributes['tooltip']);
            }
            if (isset($attributes['display'])) {
                $cell->get_hyperlink()->set_display((string) $attributes['display']);
            }
        }
    }
}