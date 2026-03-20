<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing as WorksheetDrawing;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
class Content_Types extends Writer_Part
{
    /**
     * Write content types to XML format.
     *
     * @param bool $includeCharts Flag indicating if we should include drawing details for charts
     *
     * @return string XML Output
     */
    public function write_content_types(Spreadsheet $spreadsheet, bool $include_charts = false): string
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
        // Types
        $obj_writer->start_element('Types');
        $obj_writer->write_attribute('xmlns', Namespaces::CONTENT_TYPES);
        // Theme
        $this->write_override_content_type($obj_writer, '/xl/theme/theme1.xml', 'application/vnd.openxmlformats-officedocument.theme+xml');
        // Styles
        $this->write_override_content_type($obj_writer, '/xl/styles.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml');
        // Rels
        $this->write_default_content_type($obj_writer, 'rels', 'application/vnd.openxmlformats-package.relationships+xml');
        // XML
        $this->write_default_content_type($obj_writer, 'xml', 'application/xml');
        // VML
        $this->write_default_content_type($obj_writer, 'vml', 'application/vnd.openxmlformats-officedocument.vmlDrawing');
        // Workbook
        if ($spreadsheet->has_macros()) {
            //Macros in workbook ?
            // Yes : not standard content but "macroEnabled"
            $this->write_override_content_type($obj_writer, '/xl/workbook.xml', 'application/vnd.ms-excel.sheet.macroEnabled.main+xml');
            //... and define a new type for the VBA project
            // Better use Override, because we can use 'bin' also for xl\printerSettings\printerSettings1.bin
            $this->write_override_content_type($obj_writer, '/xl/vbaProject.bin', 'application/vnd.ms-office.vbaProject');
            if ($spreadsheet->has_macros_certificate()) {
                // signed macros ?
                // Yes : add needed information
                $this->write_override_content_type($obj_writer, '/xl/vbaProjectSignature.bin', 'application/vnd.ms-office.vbaProjectSignature');
            }
        } else {
            // no macros in workbook, so standard type
            $this->write_override_content_type($obj_writer, '/xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml');
        }
        // DocProps
        $this->write_override_content_type($obj_writer, '/docProps/app.xml', 'application/vnd.openxmlformats-officedocument.extended-properties+xml');
        $this->write_override_content_type($obj_writer, '/docProps/core.xml', 'application/vnd.openxmlformats-package.core-properties+xml');
        $custom_property_list = $spreadsheet->get_properties()->get_custom_properties();
        if (!empty($custom_property_list)) {
            $this->write_override_content_type($obj_writer, '/docProps/custom.xml', 'application/vnd.openxmlformats-officedocument.custom-properties+xml');
        }
        // Worksheets
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            $this->write_override_content_type($obj_writer, '/xl/worksheets/sheet' . ($i + 1) . '.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
        }
        // Shared strings
        $this->write_override_content_type($obj_writer, '/xl/sharedStrings.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml');
        // Table
        $table = 1;
        for ($i = 0; $i < $sheet_count; ++$i) {
            $table_count = $spreadsheet->get_sheet($i)->get_table_collection()->count();
            for ($t = 1; $t <= $table_count; ++$t) {
                $this->write_override_content_type($obj_writer, '/xl/tables/table' . $table++ . '.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml');
            }
        }
        // Add worksheet relationship content types
        /** @var mixed[][][][] */
        $unparsed_loaded_data = $spreadsheet->get_unparsed_loaded_data();
        $chart = 1;
        for ($i = 0; $i < $sheet_count; ++$i) {
            $drawings = $spreadsheet->get_sheet($i)->get_drawing_collection();
            $drawing_count = count($drawings);
            $chart_count = $include_charts ? $spreadsheet->get_sheet($i)->get_chart_count() : 0;
            $has_unparsed_drawing = isset($unparsed_loaded_data['sheets'][$spreadsheet->get_sheet($i)->get_code_name()]['drawingOriginalIds']);
            //    We need a drawing relationship for the worksheet if we have either drawings or charts
            if ($drawing_count > 0 || $chart_count > 0 || $has_unparsed_drawing) {
                $this->write_override_content_type($obj_writer, '/xl/drawings/drawing' . ($i + 1) . '.xml', 'application/vnd.openxmlformats-officedocument.drawing+xml');
            }
            //    If we have charts, then we need a chart relationship for every individual chart
            if ($chart_count > 0) {
                for ($c = 0; $c < $chart_count; ++$c) {
                    $this->write_override_content_type($obj_writer, '/xl/charts/chart' . $chart++ . '.xml', 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml');
                }
            }
        }
        // Comments
        for ($i = 0; $i < $sheet_count; ++$i) {
            if (count($spreadsheet->get_sheet($i)->get_comments()) > 0) {
                $this->write_override_content_type($obj_writer, '/xl/comments' . ($i + 1) . '.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml');
            }
        }
        // Add media content-types
        $a_media_content_types = [];
        $media_count = $this->get_parent_writer()->get_drawing_hash_table()->count();
        for ($i = 0; $i < $media_count; ++$i) {
            $extension = '';
            $mime_type = '';
            $drawing = $this->get_parent_writer()->get_drawing_hash_table()->get_by_index($i);
            if ($drawing instanceof Worksheet_Drawing && $drawing->get_path() !== '') {
                $extension = strtolower($drawing->get_extension());
                if ($drawing->get_is_url()) {
                    $mime_type = image_type_to_mime_type($drawing->get_type());
                } else {
                    $mime_type = $this->get_image_mime_type($drawing->get_path());
                }
            } elseif ($drawing instanceof Memory_Drawing) {
                $extension = strtolower($drawing->get_mime_type());
                $extension = explode('/', $extension);
                $extension = $extension[1];
                $mime_type = $drawing->get_mime_type();
            }
            if ($mime_type !== '' && !isset($a_media_content_types[$extension])) {
                $a_media_content_types[$extension] = $mime_type;
                $this->write_default_content_type($obj_writer, $extension, $mime_type);
            }
        }
        if ($spreadsheet->has_in_cell_drawings()) {
            $this->write_override_content_type($obj_writer, '/xl/richData/richValueRel.xml', 'application/vnd.ms-excel.richvaluerel+xml');
            $this->write_override_content_type($obj_writer, '/xl/richData/rdrichvalue.xml', 'application/vnd.ms-excel.rdrichvalue+xml');
            $this->write_override_content_type($obj_writer, '/xl/richData/rdrichvaluestructure.xml', 'application/vnd.ms-excel.rdrichvaluestructure+xml');
            $this->write_override_content_type($obj_writer, '/xl/richData/rdRichValueTypes.xml', 'application/vnd.ms-excel.rdrichvaluetypes+xml');
        }
        // Add pass-through media content types
        /** @var array<string, array<string, mixed>> $sheets */
        $sheets = $unparsed_loaded_data['sheets'] ?? [];
        foreach ($sheets as $sheet_data) {
            if (($sheet_data['drawingPassThroughEnabled'] ?? false) !== true) {
                continue;
            }
            /** @var string[] $mediaFiles */
            $media_files = $sheet_data['drawingMediaFiles'] ?? [];
            foreach ($media_files as $media_path) {
                $extension = strtolower(pathinfo($media_path, PATHINFO_EXTENSION));
                if ($extension !== '' && !isset($a_media_content_types[$extension])) {
                    $mime_type = match ($extension) {
                        // @phpstan-ignore match.unhandled
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'bmp' => 'image/bmp',
                        'tif', 'tiff' => 'image/tiff',
                        'svg' => 'image/svg+xml',
                    };
                    $a_media_content_types[$extension] = $mime_type;
                    $this->write_default_content_type($obj_writer, $extension, $mime_type);
                }
            }
        }
        if ($spreadsheet->has_ribbon_bin_objects()) {
            // Some additional objects in the ribbon ?
            // we need to write "Extension" but not already write for media content
            /** @var string[] */
            $tab_ribbon_types = array_diff($spreadsheet->get_ribbon_bin_objects('types') ?? [], array_keys($a_media_content_types));
            foreach ($tab_ribbon_types as $a_ribbon_type) {
                $mime_type = 'image/.' . $a_ribbon_type;
                //we wrote $mimeType like customUI Editor
                $this->write_default_content_type($obj_writer, $a_ribbon_type, $mime_type);
            }
        }
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            foreach ($spreadsheet->get_sheet($i)->get_header_footer()->get_images() as $image) {
                if ($image->get_path() !== '' && !isset($a_media_content_types[strtolower($image->get_extension())])) {
                    $a_media_content_types[strtolower($image->get_extension())] = $this->get_image_mime_type($image->get_path());
                    $this->write_default_content_type($obj_writer, strtolower($image->get_extension()), $a_media_content_types[strtolower($image->get_extension())]);
                }
            }
            foreach ($spreadsheet->get_sheet($i)->get_comments() as $comment) {
                if (!$comment->has_background_image()) {
                    continue;
                }
                $bg_image = $comment->get_background_image();
                $bg_image_extention_key = strtolower($bg_image->get_image_file_extension_for_save(false));
                if (!isset($a_media_content_types[$bg_image_extention_key])) {
                    $a_media_content_types[$bg_image_extention_key] = $bg_image->get_image_mime_type();
                    $this->write_default_content_type($obj_writer, $bg_image_extention_key, $a_media_content_types[$bg_image_extention_key]);
                }
            }
            $bg_image = $spreadsheet->get_sheet($i)->get_background_image();
            $mime_type = $spreadsheet->get_sheet($i)->get_background_mime();
            $extension = $spreadsheet->get_sheet($i)->get_background_extension();
            if ($bg_image !== '' && !isset($a_media_content_types[$extension])) {
                $this->write_default_content_type($obj_writer, $extension, $mime_type);
            }
        }
        // unparsed defaults
        if (isset($unparsed_loaded_data['default_content_types'])) {
            /** @var array<string, string> */
            $unparsed_default = $unparsed_loaded_data['default_content_types'];
            foreach ($unparsed_default as $ext_name => $content_type) {
                $this->write_default_content_type($obj_writer, $ext_name, $content_type);
            }
        }
        // unparsed overrides
        if (isset($unparsed_loaded_data['override_content_types'])) {
            /** @var array<string, string> */
            $unparsed_override = $unparsed_loaded_data['override_content_types'];
            foreach ($unparsed_override as $part_name => $override_type) {
                $this->write_override_content_type($obj_writer, $part_name, $override_type);
            }
        }
        // Metadata needed for Dynamic Arrays
        if ($this->get_parent_writer()->use_dynamic_arrays() || $spreadsheet->has_in_cell_drawings()) {
            $this->write_override_content_type($obj_writer, '/xl/metadata.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheetMetadata+xml');
        }
        if ($spreadsheet->get_uses_checkbox_style()) {
            $this->write_override_content_type($obj_writer, '/xl/featurePropertyBag/featurePropertyBag.xml', 'application/vnd.ms-excel.featurepropertybag+xml');
        }
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    private static int $three = 3;
    // phpstan silliness
    /**
     * Get image mime type.
     *
     * @param string $filename Filename
     *
     * @return string Mime Type
     */
    private function get_image_mime_type(string $filename): string
    {
        if (File::file_exists($filename)) {
            $image = getimagesize($filename);
            return image_type_to_mime_type(is_array($image) && count($image) >= self::$three ? $image[2] : 0);
        }
        throw new Writer_Exception("File {$filename} does not exist");
    }
    /**
     * Write Default content type.
     *
     * @param string $partName Part name
     * @param string $contentType Content type
     */
    private function write_default_content_type(Xml_Writer $obj_writer, string $part_name, string $content_type): void
    {
        if ($part_name != '' && $content_type != '') {
            // Write content type
            $obj_writer->start_element('Default');
            $obj_writer->write_attribute('Extension', $part_name);
            $obj_writer->write_attribute('ContentType', $content_type);
            $obj_writer->end_element();
        } else {
            throw new Writer_Exception('Invalid parameters passed.');
        }
    }
    /**
     * Write Override content type.
     *
     * @param string $partName Part name
     * @param string $contentType Content type
     */
    private function write_override_content_type(Xml_Writer $obj_writer, string $part_name, string $content_type): void
    {
        if ($part_name != '' && $content_type != '') {
            // Write content type
            $obj_writer->start_element('Override');
            $obj_writer->write_attribute('PartName', $part_name);
            $obj_writer->write_attribute('ContentType', $content_type);
            $obj_writer->end_element();
        } else {
            throw new Writer_Exception('Invalid parameters passed.');
        }
    }
}