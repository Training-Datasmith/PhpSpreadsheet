<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Drawing as SharedDrawing;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Header_Footer_Drawing;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
class Drawing extends Writer_Part
{
    /**
     * Write drawings to XML format.
     *
     * @param bool $includeCharts Flag indicating if we should include drawing details for charts
     *
     * @return string XML Output
     */
    public function write_drawings(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet, bool $include_charts = false): string
    {
        // Try to use pass-through drawing XML if available
        if ($pass_through_xml = $this->get_pass_through_drawing_xml($worksheet)) {
            return $pass_through_xml;
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
        // xdr:wsDr
        $obj_writer->start_element('xdr:wsDr');
        $obj_writer->write_attribute('xmlns:xdr', Namespaces::SPREADSHEET_DRAWING);
        $obj_writer->write_attribute('xmlns:a', Namespaces::DRAWINGML);
        // Loop through images and write drawings
        $i = 1;
        $iterator = $worksheet->get_drawing_collection()->getIterator();
        while ($iterator->valid()) {
            /** @var BaseDrawing $pDrawing */
            $p_drawing = $iterator->current();
            $p_relation_id = $i;
            $hlink_click_id = $p_drawing->get_hyperlink() === null ? null : ++$i;
            $this->write_drawing($obj_writer, $p_drawing, $p_relation_id, $hlink_click_id);
            $iterator->next();
            ++$i;
        }
        if ($include_charts) {
            $chart_count = $worksheet->get_chart_count();
            // Loop through charts and write the chart position
            if ($chart_count > 0) {
                for ($c = 0; $c < $chart_count; ++$c) {
                    $chart = $worksheet->get_chart_by_index((string) $c);
                    if ($chart !== false) {
                        $this->write_chart($obj_writer, $chart, $c + $i);
                    }
                }
            }
        }
        // unparsed AlternateContent
        /** @var string[][][][] */
        $unparsed_loaded_data = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data();
        if (isset($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingAlternateContents'])) {
            foreach ($unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['drawingAlternateContents'] as $drawing_alternate_content) {
                $obj_writer->write_raw($drawing_alternate_content);
            }
        }
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write drawings to XML format.
     */
    public function write_chart(Xml_Writer $obj_writer, \Php_Office\Php_Spreadsheet\Chart\Chart $chart, int $relation_id = -1): void
    {
        $tl = $chart->get_top_left_position();
        $tl_col_row = Coordinate::indexes_from_string($tl['cell']);
        $br = $chart->get_bottom_right_position();
        $is_two_cell_anchor = $br['cell'] !== '';
        if ($is_two_cell_anchor) {
            $br_col_row = Coordinate::indexes_from_string($br['cell']);
            $obj_writer->start_element('xdr:twoCellAnchor');
            $obj_writer->start_element('xdr:from');
            $obj_writer->write_element('xdr:col', (string) ($tl_col_row[0] - 1));
            $obj_writer->write_element('xdr:colOff', self::string_emu($tl['xOffset']));
            $obj_writer->write_element('xdr:row', (string) ($tl_col_row[1] - 1));
            $obj_writer->write_element('xdr:rowOff', self::string_emu($tl['yOffset']));
            $obj_writer->end_element();
            $obj_writer->start_element('xdr:to');
            $obj_writer->write_element('xdr:col', (string) ($br_col_row[0] - 1));
            $obj_writer->write_element('xdr:colOff', self::string_emu($br['xOffset']));
            $obj_writer->write_element('xdr:row', (string) ($br_col_row[1] - 1));
            $obj_writer->write_element('xdr:rowOff', self::string_emu($br['yOffset']));
            $obj_writer->end_element();
        } elseif ($chart->get_one_cell_anchor()) {
            $obj_writer->start_element('xdr:oneCellAnchor');
            $obj_writer->start_element('xdr:from');
            $obj_writer->write_element('xdr:col', (string) ($tl_col_row[0] - 1));
            $obj_writer->write_element('xdr:colOff', self::string_emu($tl['xOffset']));
            $obj_writer->write_element('xdr:row', (string) ($tl_col_row[1] - 1));
            $obj_writer->write_element('xdr:rowOff', self::string_emu($tl['yOffset']));
            $obj_writer->end_element();
            $obj_writer->start_element('xdr:ext');
            $obj_writer->write_attribute('cx', self::string_emu($br['xOffset']));
            $obj_writer->write_attribute('cy', self::string_emu($br['yOffset']));
            $obj_writer->end_element();
        } else {
            $obj_writer->start_element('xdr:absoluteAnchor');
            $obj_writer->start_element('xdr:pos');
            $obj_writer->write_attribute('x', '0');
            $obj_writer->write_attribute('y', '0');
            $obj_writer->end_element();
            $obj_writer->start_element('xdr:ext');
            $obj_writer->write_attribute('cx', self::string_emu($br['xOffset']));
            $obj_writer->write_attribute('cy', self::string_emu($br['yOffset']));
            $obj_writer->end_element();
        }
        $obj_writer->start_element('xdr:graphicFrame');
        $obj_writer->write_attribute('macro', '');
        $obj_writer->start_element('xdr:nvGraphicFramePr');
        $obj_writer->start_element('xdr:cNvPr');
        $obj_writer->write_attribute('name', 'Chart ' . $relation_id);
        $obj_writer->write_attribute('id', (string) (1025 * $relation_id));
        $obj_writer->end_element();
        $obj_writer->start_element('xdr:cNvGraphicFramePr');
        $obj_writer->start_element('a:graphicFrameLocks');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->start_element('xdr:xfrm');
        $obj_writer->start_element('a:off');
        $obj_writer->write_attribute('x', '0');
        $obj_writer->write_attribute('y', '0');
        $obj_writer->end_element();
        $obj_writer->start_element('a:ext');
        $obj_writer->write_attribute('cx', '0');
        $obj_writer->write_attribute('cy', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->start_element('a:graphic');
        $obj_writer->start_element('a:graphicData');
        $obj_writer->write_attribute('uri', Namespaces::CHART);
        $obj_writer->start_element('c:chart');
        $obj_writer->write_attribute('xmlns:c', Namespaces::CHART);
        $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
        $obj_writer->write_attribute('r:id', 'rId' . $relation_id);
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->start_element('xdr:clientData');
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Write drawings to XML format.
     */
    public function write_drawing(Xml_Writer $obj_writer, Base_Drawing $drawing, int $relation_id = -1, ?int $hlink_click_id = null): void
    {
        if ($relation_id >= 0) {
            $is_two_cell_anchor = $drawing->get_coordinates2() !== '';
            if ($is_two_cell_anchor) {
                // xdr:twoCellAnchor
                $obj_writer->start_element('xdr:twoCellAnchor');
                if ($drawing->valid_edit_as()) {
                    $obj_writer->write_attribute('editAs', $drawing->get_edit_as());
                }
                // Image location
                $a_coordinates = Coordinate::indexes_from_string($drawing->get_coordinates());
                $a_coordinates2 = Coordinate::indexes_from_string($drawing->get_coordinates2());
                // xdr:from
                $obj_writer->start_element('xdr:from');
                $obj_writer->write_element('xdr:col', (string) ($a_coordinates[0] - 1));
                $obj_writer->write_element('xdr:colOff', self::string_emu($drawing->get_offset_x()));
                $obj_writer->write_element('xdr:row', (string) ($a_coordinates[1] - 1));
                $obj_writer->write_element('xdr:rowOff', self::string_emu($drawing->get_offset_y()));
                $obj_writer->end_element();
                // xdr:to
                $obj_writer->start_element('xdr:to');
                $obj_writer->write_element('xdr:col', (string) ($a_coordinates2[0] - 1));
                $obj_writer->write_element('xdr:colOff', self::string_emu($drawing->get_offset_x2()));
                $obj_writer->write_element('xdr:row', (string) ($a_coordinates2[1] - 1));
                $obj_writer->write_element('xdr:rowOff', self::string_emu($drawing->get_offset_y2()));
                $obj_writer->end_element();
            } else {
                // xdr:oneCellAnchor
                $obj_writer->start_element('xdr:oneCellAnchor');
                // Image location
                $a_coordinates = Coordinate::indexes_from_string($drawing->get_coordinates());
                // xdr:from
                $obj_writer->start_element('xdr:from');
                $obj_writer->write_element('xdr:col', (string) ($a_coordinates[0] - 1));
                $obj_writer->write_element('xdr:colOff', self::string_emu($drawing->get_offset_x()));
                $obj_writer->write_element('xdr:row', (string) ($a_coordinates[1] - 1));
                $obj_writer->write_element('xdr:rowOff', self::string_emu($drawing->get_offset_y()));
                $obj_writer->end_element();
                // xdr:ext
                $obj_writer->start_element('xdr:ext');
                $obj_writer->write_attribute('cx', self::string_emu($drawing->get_width()));
                $obj_writer->write_attribute('cy', self::string_emu($drawing->get_height()));
                $obj_writer->end_element();
            }
            // xdr:pic
            $obj_writer->start_element('xdr:pic');
            // xdr:nvPicPr
            $obj_writer->start_element('xdr:nvPicPr');
            // xdr:cNvPr
            $obj_writer->start_element('xdr:cNvPr');
            $obj_writer->write_attribute('id', (string) $relation_id);
            $obj_writer->write_attribute('name', $drawing->get_name());
            $obj_writer->write_attribute('descr', $drawing->get_description());
            //a:hlinkClick
            $this->write_hyper_link_drawing($obj_writer, $hlink_click_id);
            $obj_writer->end_element();
            // xdr:cNvPicPr
            $obj_writer->start_element('xdr:cNvPicPr');
            // a:picLocks
            $obj_writer->start_element('a:picLocks');
            $obj_writer->write_attribute('noChangeAspect', '1');
            $obj_writer->end_element();
            $obj_writer->end_element();
            $obj_writer->end_element();
            // xdr:blipFill
            $obj_writer->start_element('xdr:blipFill');
            // a:blip
            $obj_writer->start_element('a:blip');
            $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
            $obj_writer->write_attribute('r:embed', 'rId' . $relation_id);
            $temp = $drawing->get_opacity();
            if (is_int($temp) && $temp >= 0 && $temp <= 100000) {
                $obj_writer->start_element('a:alphaModFix');
                $obj_writer->write_attribute('amt', "{$temp}");
                $obj_writer->end_element();
                // a:alphaModFix
            }
            $obj_writer->end_element();
            // a:blip
            $src_rect = $drawing->get_src_rect();
            if (!empty($src_rect)) {
                $obj_writer->start_element('a:srcRect');
                foreach ($src_rect as $key => $value) {
                    $obj_writer->write_attribute($key, (string) $value);
                }
                $obj_writer->end_element();
                // a:srcRect
                $obj_writer->start_element('a:stretch');
                $obj_writer->end_element();
                // a:stretch
            } else {
                // a:stretch
                $obj_writer->start_element('a:stretch');
                $obj_writer->write_element('a:fillRect');
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            // xdr:spPr
            $obj_writer->start_element('xdr:spPr');
            // a:xfrm
            $obj_writer->start_element('a:xfrm');
            $obj_writer->write_attribute('rot', (string) Shared_Drawing::degrees_to_angle($drawing->get_rotation()));
            self::write_attribute_if($obj_writer, $drawing->get_flip_vertical(), 'flipV', '1');
            self::write_attribute_if($obj_writer, $drawing->get_flip_horizontal(), 'flipH', '1');
            if ($is_two_cell_anchor) {
                $obj_writer->start_element('a:ext');
                $obj_writer->write_attribute('cx', self::string_emu($drawing->get_width()));
                $obj_writer->write_attribute('cy', self::string_emu($drawing->get_height()));
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            // a:prstGeom
            $obj_writer->start_element('a:prstGeom');
            $obj_writer->write_attribute('prst', 'rect');
            // a:avLst
            $obj_writer->write_element('a:avLst');
            $obj_writer->end_element();
            if ($drawing->get_shadow()->get_visible()) {
                // a:effectLst
                $obj_writer->start_element('a:effectLst');
                // a:outerShdw
                $obj_writer->start_element('a:outerShdw');
                $obj_writer->write_attribute('blurRad', self::string_emu($drawing->get_shadow()->get_blur_radius()));
                $obj_writer->write_attribute('dist', self::string_emu($drawing->get_shadow()->get_distance()));
                $obj_writer->write_attribute('dir', (string) Shared_Drawing::degrees_to_angle($drawing->get_shadow()->get_direction()));
                $obj_writer->write_attribute('algn', $drawing->get_shadow()->get_alignment());
                $obj_writer->write_attribute('rotWithShape', '0');
                // a:srgbClr
                $obj_writer->start_element('a:srgbClr');
                $obj_writer->write_attribute('val', $drawing->get_shadow()->get_color()->get_rgb());
                // a:alpha
                $obj_writer->start_element('a:alpha');
                $obj_writer->write_attribute('val', (string) ($drawing->get_shadow()->get_alpha() * 1000));
                $obj_writer->end_element();
                $obj_writer->end_element();
                $obj_writer->end_element();
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            $obj_writer->end_element();
            // xdr:clientData
            $obj_writer->write_element('xdr:clientData');
            $obj_writer->end_element();
        } else {
            throw new Writer_Exception('Invalid parameters passed.');
        }
    }
    /**
     * Write VML header/footer images to XML format.
     *
     * @return string XML Output
     */
    public function write_vml_header_footer_images(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): string
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
        // Header/footer images
        $images = $worksheet->get_header_footer()->get_images();
        // xml
        $obj_writer->start_element('xml');
        $obj_writer->write_attribute('xmlns:v', Namespaces::URN_VML);
        $obj_writer->write_attribute('xmlns:o', Namespaces::URN_MSOFFICE);
        $obj_writer->write_attribute('xmlns:x', Namespaces::URN_EXCEL);
        // o:shapelayout
        $obj_writer->start_element('o:shapelayout');
        $obj_writer->write_attribute('v:ext', 'edit');
        // o:idmap
        $obj_writer->start_element('o:idmap');
        $obj_writer->write_attribute('v:ext', 'edit');
        $obj_writer->write_attribute('data', '1');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // v:shapetype
        $obj_writer->start_element('v:shapetype');
        $obj_writer->write_attribute('id', '_x0000_t75');
        $obj_writer->write_attribute('coordsize', '21600,21600');
        $obj_writer->write_attribute('o:spt', '75');
        $obj_writer->write_attribute('o:preferrelative', 't');
        $obj_writer->write_attribute('path', 'm@4@5l@4@11@9@11@9@5xe');
        $obj_writer->write_attribute('filled', 'f');
        $obj_writer->write_attribute('stroked', 'f');
        // v:stroke
        $obj_writer->start_element('v:stroke');
        $obj_writer->write_attribute('joinstyle', 'miter');
        $obj_writer->end_element();
        // v:formulas
        $obj_writer->start_element('v:formulas');
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'if lineDrawn pixelLineWidth 0');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'sum @0 1 0');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'sum 0 0 @1');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @2 1 2');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @3 21600 pixelWidth');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @3 21600 pixelHeight');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'sum @0 0 1');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @6 1 2');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @7 21600 pixelWidth');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'sum @8 21600 0');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'prod @7 21600 pixelHeight');
        $obj_writer->end_element();
        // v:f
        $obj_writer->start_element('v:f');
        $obj_writer->write_attribute('eqn', 'sum @10 21600 0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // v:path
        $obj_writer->start_element('v:path');
        $obj_writer->write_attribute('o:extrusionok', 'f');
        $obj_writer->write_attribute('gradientshapeok', 't');
        $obj_writer->write_attribute('o:connecttype', 'rect');
        $obj_writer->end_element();
        // o:lock
        $obj_writer->start_element('o:lock');
        $obj_writer->write_attribute('v:ext', 'edit');
        $obj_writer->write_attribute('aspectratio', 't');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // Loop through images
        foreach ($images as $key => $value) {
            $this->write_vml_header_footer_image($obj_writer, $key, $value);
        }
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write VML comment to XML format.
     *
     * @param string $reference Reference
     */
    private function write_vml_header_footer_image(Xml_Writer $obj_writer, string $reference, Header_Footer_Drawing $image): void
    {
        // Calculate object id
        if (!Preg::is_match('{(\d+)}', md5($reference), $m)) {
            // @codeCoverageIgnoreStart
            throw new Writer_Exception('Regexp failure in writeVMLHeaderFooterImage');
            // @codeCoverageIgnoreEnd
        }
        $id = 1500 + (int) substr((string) $m[1], 0, 2);
        // Calculate offset
        $width = $image->get_width();
        $height = $image->get_height();
        $margin_left = $image->get_offset_x();
        $margin_top = $image->get_offset_y();
        // v:shape
        $obj_writer->start_element('v:shape');
        $obj_writer->write_attribute('id', $reference);
        $obj_writer->write_attribute('o:spid', '_x0000_s' . $id);
        $obj_writer->write_attribute('type', '#_x0000_t75');
        $obj_writer->write_attribute('style', "position:absolute;margin-left:{$margin_left}px;margin-top:{$margin_top}px;width:{$width}px;height:{$height}px;z-index:1");
        // v:imagedata
        $obj_writer->start_element('v:imagedata');
        $obj_writer->write_attribute('o:relid', 'rId' . $reference);
        $obj_writer->write_attribute('o:title', $image->get_name());
        $obj_writer->end_element();
        // o:lock
        $obj_writer->start_element('o:lock');
        $obj_writer->write_attribute('v:ext', 'edit');
        $obj_writer->write_attribute('textRotation', 't');
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Get an array of all drawings.
     *
     * @return BaseDrawing[] All drawings in PhpSpreadsheet
     */
    public function all_drawings(Spreadsheet $spreadsheet): array
    {
        // Get an array of all drawings
        $a_drawings = [];
        // Loop through PhpSpreadsheet
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            // Loop through images and add to array
            $iterator = $spreadsheet->get_sheet($i)->get_drawing_collection()->getIterator();
            while ($iterator->valid()) {
                $a_drawings[] = $iterator->current();
                $iterator->next();
            }
            $iterator = $spreadsheet->get_sheet($i)->get_in_cell_drawing_collection()->getIterator();
            while ($iterator->valid()) {
                $a_drawings[] = $iterator->current();
                $iterator->next();
            }
        }
        return $a_drawings;
    }
    private function write_hyper_link_drawing(Xml_Writer $obj_writer, ?int $hlink_click_id): void
    {
        if ($hlink_click_id === null) {
            return;
        }
        $obj_writer->start_element('a:hlinkClick');
        $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
        $obj_writer->write_attribute('r:id', 'rId' . $hlink_click_id);
        $obj_writer->end_element();
    }
    private static function string_emu(int $pixel_value): string
    {
        return (string) Shared_Drawing::pixels_to_emu($pixel_value);
    }
    private static function write_attribute_if(Xml_Writer $obj_writer, ?bool $condition, string $attr, string $val): void
    {
        if ($condition) {
            $obj_writer->write_attribute($attr, $val);
        }
    }
    /**
     * Get pass-through drawing XML if available.
     *
     * Returns the original drawing XML stored during load (when Reader pass-through was enabled).
     * This preserves unsupported drawing elements (shapes, textboxes) that PhpSpreadsheet cannot parse.
     *
     * @return ?string The pass-through XML, or null if not available or should not be used
     */
    private function get_pass_through_drawing_xml(\Php_Office\Php_Spreadsheet\Worksheet\Worksheet $worksheet): ?string
    {
        /** @var array<string, array<string, mixed>> $sheets */
        $sheets = $worksheet->get_parent_or_throw()->get_unparsed_loaded_data()['sheets'] ?? [];
        $sheet_data = $sheets[$worksheet->get_code_name()] ?? [];
        // Only use pass-through XML if the Reader flag was explicitly enabled
        /** @var string[] $drawings */
        $drawings = $sheet_data['Drawings'] ?? [];
        if (($sheet_data['drawingPassThroughEnabled'] ?? false) !== true || $drawings === []) {
            return null;
        }
        return reset($drawings) ?: null;
    }
}