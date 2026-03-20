<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Settings extends Writer_Part
{
    /**
     * Write settings.xml to XML format.
     *
     * @return string XML Output
     */
    public function write(): string
    {
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8');
        // Settings
        $obj_writer->start_element('office:document-settings');
        $obj_writer->write_attribute('xmlns:office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $obj_writer->write_attribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $obj_writer->write_attribute('xmlns:config', 'urn:oasis:names:tc:opendocument:xmlns:config:1.0');
        $obj_writer->write_attribute('xmlns:ooo', 'http://openoffice.org/2004/office');
        $obj_writer->write_attribute('office:version', '1.2');
        $obj_writer->start_element('office:settings');
        $obj_writer->start_element('config:config-item-set');
        $obj_writer->write_attribute('config:name', 'ooo:view-settings');
        $obj_writer->start_element('config:config-item-map-indexed');
        $obj_writer->write_attribute('config:name', 'Views');
        $obj_writer->start_element('config:config-item-map-entry');
        $spreadsheet = $this->get_parent_writer()->get_spreadsheet();
        $obj_writer->start_element('config:config-item');
        $obj_writer->write_attribute('config:name', 'ViewId');
        $obj_writer->write_attribute('config:type', 'string');
        $obj_writer->text('view1');
        $obj_writer->end_element();
        // ViewId
        $obj_writer->start_element('config:config-item-map-named');
        $this->write_all_worksheet_settings($obj_writer, $spreadsheet);
        $wstitle = $spreadsheet->get_active_sheet()->get_title();
        $obj_writer->start_element('config:config-item');
        $obj_writer->write_attribute('config:name', 'ActiveTable');
        $obj_writer->write_attribute('config:type', 'string');
        $obj_writer->text($wstitle);
        $obj_writer->end_element();
        // config:config-item ActiveTable
        $obj_writer->end_element();
        // config:config-item-map-entry
        $obj_writer->end_element();
        // config:config-item-map-indexed Views
        $obj_writer->end_element();
        // config:config-item-set ooo:view-settings
        $obj_writer->start_element('config:config-item-set');
        $obj_writer->write_attribute('config:name', 'ooo:configuration-settings');
        $obj_writer->end_element();
        // config:config-item-set ooo:configuration-settings
        $obj_writer->end_element();
        // office:settings
        $obj_writer->end_element();
        // office:document-settings
        return $obj_writer->get_data();
    }
    private function write_all_worksheet_settings(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        $obj_writer->write_attribute('config:name', 'Tables');
        foreach ($spreadsheet->get_worksheet_iterator() as $worksheet) {
            $this->write_worksheet_settings($obj_writer, $worksheet);
        }
        $obj_writer->end_element();
        // config:config-item-map-entry Tables
    }
    private function write_worksheet_settings(Xml_Writer $obj_writer, Worksheet $worksheet): void
    {
        $obj_writer->start_element('config:config-item-map-entry');
        $obj_writer->write_attribute('config:name', $worksheet->get_title());
        $this->write_selected_cells($obj_writer, $worksheet);
        $this->write_freeze_pane($obj_writer, $worksheet);
        $obj_writer->end_element();
        // config:config-item-map-entry Worksheet
    }
    private function write_selected_cells(Xml_Writer $obj_writer, Worksheet $worksheet): void
    {
        $selected = $worksheet->get_selected_cells();
        if (Preg::is_match('/^([a-z]+)([0-9]+)/i', $selected, $matches)) {
            $col_sel = Coordinate::column_index_from_string($matches[1]) - 1;
            $row_sel = (int) $matches[2] - 1;
            $obj_writer->start_element('config:config-item');
            $obj_writer->write_attribute('config:name', 'CursorPositionX');
            $obj_writer->write_attribute('config:type', 'int');
            $obj_writer->text((string) $col_sel);
            $obj_writer->end_element();
            $obj_writer->start_element('config:config-item');
            $obj_writer->write_attribute('config:name', 'CursorPositionY');
            $obj_writer->write_attribute('config:type', 'int');
            $obj_writer->text((string) $row_sel);
            $obj_writer->end_element();
        }
    }
    private function write_split_value(Xml_Writer $obj_writer, string $split_mode, string $type, string $value): void
    {
        $obj_writer->start_element('config:config-item');
        $obj_writer->write_attribute('config:name', $split_mode);
        $obj_writer->write_attribute('config:type', $type);
        $obj_writer->text($value);
        $obj_writer->end_element();
    }
    private function write_freeze_pane(Xml_Writer $obj_writer, Worksheet $worksheet): void
    {
        $freeze_pane = Cell_Address::from_cell_address($worksheet->get_freeze_pane() ?? 'A1');
        if ($freeze_pane->cell_address() === 'A1') {
            return;
        }
        $column_id = $freeze_pane->column_id();
        $column_name = $freeze_pane->column_name();
        $row = $freeze_pane->row_id();
        $this->write_split_value($obj_writer, 'HorizontalSplitMode', 'short', '2');
        $this->write_split_value($obj_writer, 'HorizontalSplitPosition', 'int', (string) ($column_id - 1));
        $this->write_split_value($obj_writer, 'PositionLeft', 'short', '0');
        $this->write_split_value($obj_writer, 'PositionRight', 'short', (string) ($column_id - 1));
        for ($column = 'A'; $column !== $column_name; String_Helper::string_increment($column)) {
            $worksheet->get_column_dimension($column)->set_auto_size(true);
        }
        $this->write_split_value($obj_writer, 'VerticalSplitMode', 'short', '2');
        $this->write_split_value($obj_writer, 'VerticalSplitPosition', 'int', (string) ($row - 1));
        $this->write_split_value($obj_writer, 'PositionTop', 'short', '0');
        $this->write_split_value($obj_writer, 'PositionBottom', 'short', (string) ($row - 1));
        $this->write_split_value($obj_writer, 'ActiveSplitRange', 'short', '3');
    }
}