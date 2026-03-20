<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
class Rels extends Writer_Part
{
    /**
     * Write relationships to XML format.
     *
     * @return string XML Output
     */
    public function write_relationships(Spreadsheet $spreadsheet): string
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
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        $custom_property_list = $spreadsheet->get_properties()->get_custom_properties();
        if (!empty($custom_property_list)) {
            // Relationship docProps/app.xml
            $this->write_relationship($obj_writer, 4, Namespaces::RELATIONSHIPS_CUSTOM_PROPERTIES, 'docProps/custom.xml');
        }
        // Relationship docProps/app.xml
        $this->write_relationship($obj_writer, 3, Namespaces::RELATIONSHIPS_EXTENDED_PROPERTIES, 'docProps/app.xml');
        // Relationship docProps/core.xml
        $this->write_relationship($obj_writer, 2, Namespaces::CORE_PROPERTIES, 'docProps/core.xml');
        // Relationship xl/workbook.xml
        $this->write_relationship($obj_writer, 1, Namespaces::OFFICE_DOCUMENT, 'xl/workbook.xml');
        // a custom UI in workbook ?
        $target = $spreadsheet->get_ribbon_xml_data('target');
        if ($spreadsheet->has_ribbon()) {
            $this->write_relation_ship($obj_writer, 5, Namespaces::EXTENSIBILITY, is_string($target) ? $target : '');
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write workbook relationships to XML format.
     *
     * @return string XML Output
     */
    public function write_workbook_relationships(Spreadsheet $spreadsheet): string
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
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        // Relationship styles.xml
        $this->write_relationship($obj_writer, 1, Namespaces::STYLES, 'styles.xml');
        // Relationship theme/theme1.xml
        $this->write_relationship($obj_writer, 2, Namespaces::THEME2, 'theme/theme1.xml');
        // Relationship sharedStrings.xml
        $this->write_relationship($obj_writer, 3, Namespaces::SHARED_STRINGS, 'sharedStrings.xml');
        // Relationships with sheets
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            $this->write_relationship($obj_writer, $i + 1 + 3, Namespaces::WORKSHEET, 'worksheets/sheet' . ($i + 1) . '.xml');
        }
        // Relationships for vbaProject if needed
        // id : just after the last sheet
        if ($spreadsheet->has_macros()) {
            $this->write_relation_ship($obj_writer, $i + 1 + 3, Namespaces::VBA, 'vbaProject.bin');
            ++$i;
            //increment i if needed for another relation
        }
        // Metadata needed for Dynamic Arrays
        if ($this->get_parent_writer()->use_dynamic_arrays() || $spreadsheet->has_in_cell_drawings()) {
            $this->write_relation_ship($obj_writer, $i + 1 + 3, Namespaces::RELATIONSHIPS_METADATA, 'metadata.xml');
            ++$i;
            //increment i if needed for another relation
        }
        if ($spreadsheet->get_active_sheet()->get_in_cell_drawing_collection()->count() > 0) {
            $i = $i + 1 + 3;
            $this->write_relationship($obj_writer, $i, Namespaces::RELATIONSHIPS_RICH_VALUE, 'richData/rdrichvalue.xml');
            $this->write_relationship($obj_writer, ++$i, Namespaces::RELATIONSHIPS_RICH_VALUE_STRUCTURE, 'richData/rdrichvaluestructure.xml');
            $this->write_relationship($obj_writer, ++$i, Namespaces::RELATIONSHIPS_RICH_VALUE_TYPES, 'richData/rdRichValueTypes.xml');
            $this->write_relationship($obj_writer, ++$i, Namespaces::RELATIONSHIPS_RICH_VALUE_REL, 'richData/richValueRel.xml');
        }
        if ($spreadsheet->get_uses_check_box_style()) {
            $this->write_relationship($obj_writer, 'Fpb', Namespaces::RELATIONSHIPS_FEATURE_PROPERTY_BAG, 'featurePropertyBag/featurePropertyBag.xml');
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write worksheet relationships to XML format.
     *
     * Numbering is as follows:
     *     rId1                 - Drawings
     *  rId_hyperlink_x     - Hyperlinks
     *
     * @param bool $includeCharts Flag indicating if we should write charts
     * @param int $tableRef Table ID
     * @param string[] $zipContent
     *
     * @return string XML Output
     */
    public function write_worksheet_relationships(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet, int $worksheet_id = 1, bool $include_charts = false, int $table_ref = 1, array &$zip_content = []): string
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
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        // Write drawing relationships?
        $drawing_original_ids = [];
        /** @var string[][][][] */
        $unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        if (isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingOriginalIds'])) {
            $drawing_original_ids = $unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingOriginalIds'];
        }
        if ($include_charts) {
            $charts = $worksheet->get_chart_collection();
        } else {
            $charts = [];
        }
        if ($worksheet->get_drawing_collection()->count() > 0 || count($charts) > 0 || $drawing_original_ids) {
            $r_id = 1;
            $rel_path = array_key_first($drawing_original_ids);
            if (isset($rel_path, $drawing_original_ids[$rel_path])) {
                $r_id = (int) substr($drawing_original_ids[$rel_path], 3);
            }
            // Generate new $relPath to write drawing relationship
            $rel_path = '../drawings/drawing' . $worksheet_id . '.xml';
            $this->write_relationship($obj_writer, $r_id, Namespaces::RELATIONSHIPS_DRAWING, $rel_path);
        }
        $background_image = $worksheet->get_background_image();
        if ($background_image !== '') {
            $r_id = 'Bg';
            $unique_name = md5(mt_rand(0, 9999) . time() . mt_rand(0, 9999));
            $rel_path = "../media/{$unique_name}." . $worksheet->get_background_extension();
            $this->write_relationship($obj_writer, $r_id, Namespaces::IMAGE, $rel_path);
            $zip_content["xl/media/{$unique_name}." . $worksheet->get_background_extension()] = $background_image;
        }
        // Write hyperlink relationships?
        $i = 1;
        foreach ($worksheet->get_hyperlink_collection() as $hyperlink) {
            if (!$hyperlink->is_internal()) {
                $this->write_relationship($obj_writer, '_hyperlink_' . $i, Namespaces::HYPERLINK, $hyperlink->get_url(), 'External');
                ++$i;
            }
        }
        // Write comments relationship?
        $i = 1;
        if (count($worksheet->get_comments()) > 0 || isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['legacyDrawing'])) {
            $this->write_relationship($obj_writer, '_comments_vml' . $i, Namespaces::VML, '../drawings/vmlDrawing' . $worksheet_id . '.vml');
        }
        if (count($worksheet->get_comments()) > 0) {
            $this->write_relationship($obj_writer, '_comments' . $i, Namespaces::COMMENTS, '../comments' . $worksheet_id . '.xml');
        }
        // Write Table
        $table_count = $worksheet->get_table_collection()->count();
        for ($i = 1; $i <= $table_count; ++$i) {
            $this->write_relationship($obj_writer, '_table_' . $i, Namespaces::RELATIONSHIPS_TABLE, '../tables/table' . $table_ref++ . '.xml');
        }
        // Write header/footer relationship?
        $i = 1;
        if (count($worksheet->get_header_footer()->get_images()) > 0) {
            $this->write_relationship($obj_writer, '_headerfooter_vml' . $i, Namespaces::VML, '../drawings/vmlDrawingHF' . $worksheet_id . '.vml');
        }
        $this->write_unparsed_relationship($worksheet, $obj_writer, 'ctrlProps', Namespaces::RELATIONSHIPS_CTRLPROP);
        $this->write_unparsed_relationship($worksheet, $obj_writer, 'vmlDrawings', Namespaces::VML);
        $this->write_unparsed_relationship($worksheet, $obj_writer, 'printerSettings', Namespaces::RELATIONSHIPS_PRINTER_SETTINGS);
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    private function write_unparsed_relationship(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet, Xml_Writer $obj_writer, string $relationship, string $type): void
    {
        /** @var mixed[][][][] */
        $unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        if (!isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()][$relationship])) {
            return;
        }
        foreach ($unparsed_loaded_data['sheets'][$worksheet->get_code_name()][$relationship] as $r_id => $value) {
            if (!str_starts_with((string) $r_id, '_headerfooter_vml')) {
                /** @var string[] $value */
                $this->write_relationship($obj_writer, $r_id, $type, $value['relFilePath']);
            }
        }
    }
    /**
     * Write drawing relationships to XML format.
     *
     * @param int $chartRef Chart ID
     * @param bool $includeCharts Flag indicating if we should write charts
     *
     * @return string XML Output
     */
    public function write_drawing_relationships(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet, int &$chart_ref, bool $include_charts = false): string
    {
        // Check if we should use pass-through relationships
        $pass_through_rels = $this->get_pass_through_drawing_relationships($worksheet);
        if ($pass_through_rels !== null) {
            return $pass_through_rels;
        }
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        // Loop through images and write relationships
        $i = 1;
        $iterator = $worksheet->get_drawing_collection()->getIterator();
        while ($iterator->valid()) {
            $drawing = $iterator->current();
            if ($drawing instanceof \Php_Office\Php_Spreadsheet\Worksheet\Drawing || $drawing instanceof Memory_Drawing) {
                // Write relationship for image drawing
                $this->write_relationship($obj_writer, $i, Namespaces::IMAGE, '../media/' . $drawing->get_indexed_filename());
                $i = $this->write_drawing_hyper_link($obj_writer, $drawing, $i);
            }
            $iterator->next();
            ++$i;
        }
        if ($include_charts) {
            // Loop through charts and write relationships
            $chart_count = $worksheet->get_chart_count();
            if ($chart_count > 0) {
                for ($c = 0; $c < $chart_count; ++$c) {
                    $this->write_relationship($obj_writer, $i++, Namespaces::RELATIONSHIPS_CHART, '../charts/chart' . ++$chart_ref . '.xml');
                }
            }
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write header/footer drawing relationships to XML format.
     *
     * @return string XML Output
     */
    public function write_header_footer_drawing_relationships(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): string
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
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        // Loop through images and write relationships
        foreach ($worksheet->get_header_footer()->get_images() as $key => $value) {
            // Write relationship for image drawing
            $this->write_relationship($obj_writer, $key, Namespaces::IMAGE, '../media/' . $value->get_indexed_filename());
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    public function write_vml_drawing_relationships(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): string
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
        // Relationships
        $obj_writer->start_element('Relationships');
        $obj_writer->write_attribute('xmlns', Namespaces::RELATIONSHIPS);
        // Loop through images and write relationships
        foreach ($worksheet->get_comments() as $comment) {
            if (!$comment->has_background_image()) {
                continue;
            }
            $bg_image = $comment->get_background_image();
            $this->write_relationship($obj_writer, $bg_image->get_image_index(), Namespaces::IMAGE, '../media/' . $bg_image->get_media_filename());
        }
        $obj_writer->end_element();
        return $obj_writer->get_data();
    }
    /**
     * Write Override content type.
     *
     * @param int|string $id Relationship ID. rId will be prepended!
     * @param string $type Relationship type
     * @param string $target Relationship target
     * @param string $targetMode Relationship target mode
     */
    private function write_relationship(Xml_Writer $obj_writer, $id, string $type, string $target, string $target_mode = ''): void
    {
        if ($type != '' && $target != '') {
            // Write relationship
            $obj_writer->start_element('Relationship');
            $obj_writer->write_attribute('Id', 'rId' . $id);
            $obj_writer->write_attribute('Type', $type);
            $obj_writer->write_attribute('Target', $target);
            if ($target_mode != '') {
                $obj_writer->write_attribute('TargetMode', $target_mode);
            }
            $obj_writer->end_element();
        } else {
            throw new Writer_Exception('Invalid parameters passed.');
        }
    }
    private function write_drawing_hyper_link(Xml_Writer $obj_writer, Base_Drawing $drawing, int $i): int
    {
        if ($drawing->get_hyperlink() === null) {
            return $i;
        }
        ++$i;
        $this->write_relationship($obj_writer, $i, Namespaces::HYPERLINK, Preg::replace('~^sheet://~', '#', $drawing->get_hyperlink()->get_url()), $drawing->get_hyperlink()->get_type_hyperlink());
        return $i;
    }
    /**
     * Get pass-through drawing relationships XML if available.
     *
     * Note: When pass-through is used, the original relationships are returned as-is.
     * This means any drawings (images, charts, shapes) added programmatically after
     * loading will not be included in the relationships. This is a known limitation
     * when combining pass-through with drawing modifications.
     */
    private function get_pass_through_drawing_relationships(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): ?string
    {
        /** @var array<string, array<string, mixed>> $sheets */
        $sheets = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data()['sheets'] ?? [];
        $sheet_data = $sheets[$worksheet->get_code_name()] ?? [];
        if (($sheet_data['drawingPassThroughEnabled'] ?? false) !== true || !is_string($sheet_data['drawingRelationships'] ?? null)) {
            return null;
        }
        return $sheet_data['drawingRelationships'];
    }
}