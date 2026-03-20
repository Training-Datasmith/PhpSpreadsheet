<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Chart\Chart_Color;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Rich_Text\Run;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet as ActualWorksheet;
class String_Table extends Writer_Part
{
    /**
     * Create worksheet stringtable.
     *
     * @param string[] $existingTable Existing table to eventually merge with
     *
     * @return string[] String table for worksheet
     */
    public function create_string_table(Actual_Worksheet $worksheet, ?array $existing_table = null): array
    {
        // Create string lookup table
        /** @var string[] */
        $a_string_table = $existing_table ?? [];
        // Fill index array
        $a_flipped_string_table = $this->flip_string_table($a_string_table);
        // Loop through cells
        foreach ($worksheet->get_cell_collection()->get_coordinates() as $coordinate) {
            /** @var Cell $cell */
            $cell = $worksheet->get_cell_collection()->get($coordinate);
            /** @var null|int|RichText|string */
            $cell_value = $cell->get_value();
            if (!is_object($cell_value) && $cell_value !== null && $cell_value !== '' && ($cell->get_data_type() == Data_Type::TYPE_STRING || $cell->get_data_type() == Data_Type::TYPE_STRING2 || $cell->get_data_type() == Data_Type::TYPE_NULL) && !isset($a_flipped_string_table[$cell_value])) {
                $a_string_table[] = $cell_value;
                $a_flipped_string_table[$cell_value] = true;
            } elseif ($cell_value instanceof Rich_Text && !isset($a_flipped_string_table[$cell_value->get_hash_code()])) {
                $a_string_table[] = $cell_value;
                $a_flipped_string_table[$cell_value->get_hash_code()] = true;
            }
        }
        /** @var string[] $aStringTable */
        return $a_string_table;
    }
    /**
     * Write string table to XML format.
     *
     * @param (RichText|string)[] $stringTable
     *
     * @return string XML Output
     */
    public function write_string_table(array $string_table): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // String table
        $obj_writer->start_element('sst');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        $obj_writer->write_attribute('uniqueCount', (string) count($string_table));
        // Loop through string table
        foreach ($string_table as $text_element) {
            $obj_writer->start_element('si');
            if (!$text_element instanceof Rich_Text) {
                $text_to_write = String_Helper::control_character_php2ooxml($text_element);
                $obj_writer->start_element('t');
                if ($text_to_write !== trim($text_to_write)) {
                    $obj_writer->write_attribute('xml:space', 'preserve');
                }
                $obj_writer->write_raw_data($text_to_write);
                $obj_writer->end_element();
            } else {
                $this->write_rich_text($obj_writer, $text_element);
            }
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write Rich Text.
     *
     * @param ?string $prefix Optional Namespace prefix
     */
    public function write_rich_text(Xml_Writer $obj_writer, Rich_Text $rich_text, ?string $prefix = null, ?Font $default_font = null): void
    {
        if ($prefix !== null) {
            $prefix .= ':';
        }
        // Loop through rich text elements
        $elements = $rich_text->get_rich_text_elements();
        foreach ($elements as $element) {
            // r
            $obj_writer->start_element($prefix . 'r');
            $font = $element instanceof Run ? $element->get_font() : $default_font;
            // rPr
            if ($font !== null) {
                // rPr
                $obj_writer->start_element($prefix . 'rPr');
                // rFont
                if ($font->get_name() !== null) {
                    $obj_writer->start_element($prefix . 'rFont');
                    $obj_writer->write_attribute('val', $font->get_name());
                    $obj_writer->end_element();
                }
                // Bold
                $obj_writer->start_element($prefix . 'b');
                $obj_writer->write_attribute('val', $font->get_bold() ? 'true' : 'false');
                $obj_writer->end_element();
                // Italic
                $obj_writer->start_element($prefix . 'i');
                $obj_writer->write_attribute('val', $font->get_italic() ? 'true' : 'false');
                $obj_writer->end_element();
                // Superscript / subscript
                if ($font->get_superscript() || $font->get_subscript()) {
                    $obj_writer->start_element($prefix . 'vertAlign');
                    if ($font->get_superscript()) {
                        $obj_writer->write_attribute('val', 'superscript');
                    } elseif ($font->get_subscript()) {
                        $obj_writer->write_attribute('val', 'subscript');
                    }
                    $obj_writer->end_element();
                }
                // Strikethrough
                $obj_writer->start_element($prefix . 'strike');
                $obj_writer->write_attribute('val', $font->get_strikethrough() ? 'true' : 'false');
                $obj_writer->end_element();
                // Color
                if ($font->get_color()->get_argb() !== null) {
                    $obj_writer->start_element($prefix . 'color');
                    $obj_writer->write_attribute('rgb', $font->get_color()->get_argb());
                    $obj_writer->end_element();
                }
                // Size
                if ($font->get_size() !== null) {
                    $obj_writer->start_element($prefix . 'sz');
                    $obj_writer->write_attribute('val', (string) $font->get_size());
                    $obj_writer->end_element();
                }
                // Underline
                if ($font->get_underline() !== null) {
                    $obj_writer->start_element($prefix . 'u');
                    $obj_writer->write_attribute('val', $font->get_underline());
                    $obj_writer->end_element();
                }
                $obj_writer->end_element();
            }
            // t
            $obj_writer->start_element($prefix . 't');
            $obj_writer->write_attribute('xml:space', 'preserve');
            $obj_writer->write_raw_data(String_Helper::control_character_php2ooxml($element->get_text()));
            $obj_writer->end_element();
            $obj_writer->end_element();
        }
    }
    /**
     * Write Rich Text.
     *
     * @param RichText|string $richText text string or Rich text
     * @param string $prefix Optional Namespace prefix
     */
    public function write_rich_text_for_charts(Xml_Writer $obj_writer, $rich_text = null, string $prefix = ''): void
    {
        if (!$rich_text instanceof Rich_Text) {
            $text_run = $rich_text;
            $rich_text = new Rich_Text();
            $run = $rich_text->create_text_run($text_run ?? '');
            $run->set_font();
        }
        if ($prefix !== '') {
            $prefix .= ':';
        }
        // Loop through rich text elements
        $elements = $rich_text->get_rich_text_elements();
        foreach ($elements as $element) {
            // r
            $obj_writer->start_element($prefix . 'r');
            if ($element->get_font() !== null) {
                // rPr
                $obj_writer->start_element($prefix . 'rPr');
                $font_size = $element->get_font()->get_size();
                if (is_numeric($font_size)) {
                    $font_size *= $font_size < 100 ? 100 : 1;
                    $obj_writer->write_attribute('sz', (string) $font_size);
                }
                // Bold
                $obj_writer->write_attribute('b', $element->get_font()->get_bold() ? '1' : '0');
                // Italic
                $obj_writer->write_attribute('i', $element->get_font()->get_italic() ? '1' : '0');
                // Underline
                $underline_type = $element->get_font()->get_underline();
                switch ($underline_type) {
                    case 'single':
                        $underline_type = 'sng';
                        break;
                    case 'double':
                        $underline_type = 'dbl';
                        break;
                }
                if ($underline_type !== null) {
                    $obj_writer->write_attribute('u', $underline_type);
                }
                // Strikethrough
                $obj_writer->write_attribute('strike', $element->get_font()->get_striketype() ?: 'noStrike');
                // Superscript/subscript
                if ($element->get_font()->get_base_line()) {
                    $obj_writer->write_attribute('baseline', (string) $element->get_font()->get_base_line());
                }
                // Color
                $this->write_chart_text_color($obj_writer, $element->get_font()->get_chart_color(), $prefix);
                // Underscore Color
                $this->write_chart_text_color($obj_writer, $element->get_font()->get_underline_color(), $prefix, 'uFill');
                // fontName
                if ($element->get_font()->get_latin()) {
                    $obj_writer->start_element($prefix . 'latin');
                    $obj_writer->write_attribute('typeface', $element->get_font()->get_latin());
                    $obj_writer->end_element();
                }
                if ($element->get_font()->get_east_asian()) {
                    $obj_writer->start_element($prefix . 'ea');
                    $obj_writer->write_attribute('typeface', $element->get_font()->get_east_asian());
                    $obj_writer->end_element();
                }
                if ($element->get_font()->get_complex_script()) {
                    $obj_writer->start_element($prefix . 'cs');
                    $obj_writer->write_attribute('typeface', $element->get_font()->get_complex_script());
                    $obj_writer->end_element();
                }
                $obj_writer->end_element();
            }
            // t
            $obj_writer->start_element($prefix . 't');
            $obj_writer->write_raw_data(String_Helper::control_character_php2ooxml($element->get_text()));
            $obj_writer->end_element();
            $obj_writer->end_element();
        }
    }
    private function write_chart_text_color(Xml_Writer $obj_writer, ?Chart_Color $underline_color, string $prefix, ?string $open_tag = ''): void
    {
        if ($underline_color !== null) {
            $type = $underline_color->get_type();
            $value = $underline_color->get_value();
            if (!empty($type) && !empty($value)) {
                if ($open_tag !== '') {
                    $obj_writer->start_element($prefix . $open_tag);
                }
                $obj_writer->start_element($prefix . 'solidFill');
                $obj_writer->start_element($prefix . $type);
                $obj_writer->write_attribute('val', $value);
                $alpha = $underline_color->get_alpha();
                if (is_numeric($alpha)) {
                    $obj_writer->start_element($prefix . 'alpha');
                    $obj_writer->write_attribute('val', Chart_Color::alpha_to_xml($alpha));
                    $obj_writer->end_element();
                }
                $obj_writer->end_element();
                // srgbClr/schemeClr/prstClr
                $obj_writer->end_element();
                // solidFill
                if ($open_tag !== '') {
                    $obj_writer->end_element();
                    // uFill
                }
            }
        }
    }
    /**
     * Flip string table (for index searching).
     *
     * @param array<RichText|string> $stringTable Stringtable
     *
     * @return array<RichText|string>
     */
    public function flip_string_table(array $string_table): array
    {
        // Return value
        $return_value = [];
        // Loop through stringtable and add flipped items to $returnValue
        foreach ($string_table as $key => $value) {
            if (!$value instanceof Rich_Text) {
                $return_value[$value] = $key;
            } elseif ($value instanceof Rich_Text) {
                $return_value[$value->get_hash_code()] = $key;
            }
        }
        return $return_value;
    }
}