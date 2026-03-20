<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Chart\Axis;
use Php_Office\Php_Spreadsheet\Chart\Chart as SpreadsheetChart;
use Php_Office\Php_Spreadsheet\Chart\Chart_Color;
use Php_Office\Php_Spreadsheet\Chart\Data_Series;
use Php_Office\Php_Spreadsheet\Chart\Data_Series_Values;
use Php_Office\Php_Spreadsheet\Chart\Layout;
use Php_Office\Php_Spreadsheet\Chart\Legend;
use Php_Office\Php_Spreadsheet\Chart\Plot_Area;
use Php_Office\Php_Spreadsheet\Chart\Properties;
use Php_Office\Php_Spreadsheet\Chart\Title;
use Php_Office\Php_Spreadsheet\Chart\Trend_Line;
use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
class Chart extends Writer_Part
{
    private int $series_index;
    /**
     * Write charts to XML format.
     *
     * @return string XML Output
     */
    public function write_chart(Spreadsheet_Chart $chart, bool $calculate_cell_values = true): string
    {
        // Create XML writer
        $obj_writer = null;
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        //    Ensure that data series values are up-to-date before we save
        if ($calculate_cell_values) {
            $chart->refresh();
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // c:chartSpace
        $obj_writer->start_element('c:chartSpace');
        $obj_writer->write_attribute('xmlns:c', Namespaces::CHART);
        $obj_writer->write_attribute('xmlns:a', Namespaces::DRAWINGML);
        $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
        $obj_writer->start_element('c:date1904');
        $obj_writer->write_attribute('val', '0');
        $obj_writer->end_element();
        $obj_writer->start_element('c:lang');
        $obj_writer->write_attribute('val', 'en-GB');
        $obj_writer->end_element();
        $obj_writer->start_element('c:roundedCorners');
        $obj_writer->write_attribute('val', $chart->get_rounded_corners() ? '1' : '0');
        $obj_writer->end_element();
        $this->write_alternate_content($obj_writer);
        $obj_writer->start_element('c:chart');
        $this->write_title($obj_writer, $chart->get_title());
        $obj_writer->start_element('c:autoTitleDeleted');
        $obj_writer->write_attribute('val', (string) (int) $chart->get_auto_title_deleted());
        $obj_writer->end_element();
        $obj_writer->start_element('c:view3D');
        $surface2D = false;
        $plot_area = $chart->get_plot_area();
        if ($plot_area !== null) {
            $series_array = $plot_area->get_plot_group();
            foreach ($series_array as $series) {
                if ($series->get_plot_type() === Data_Series::TYPE_SURFACECHART) {
                    $surface2D = true;
                    break;
                }
            }
        }
        $this->write_view3d($obj_writer, $chart->get_rot_x(), 'c:rotX', $surface2D, 90);
        $this->write_view3d($obj_writer, $chart->get_rot_y(), 'c:rotY', $surface2D);
        $this->write_view3d($obj_writer, $chart->get_r_ang_ax(), 'c:rAngAx', $surface2D);
        $this->write_view3d($obj_writer, $chart->get_perspective(), 'c:perspective', $surface2D);
        $obj_writer->end_element();
        // view3D
        $this->write_plot_area($obj_writer, $chart->get_plot_area(), $chart->get_x_axis_label(), $chart->get_y_axis_label(), $chart->get_chart_axis_x(), $chart->get_chart_axis_y());
        $this->write_legend($obj_writer, $chart->get_legend());
        $obj_writer->start_element('c:plotVisOnly');
        $obj_writer->write_attribute('val', (string) (int) $chart->get_plot_visible_only());
        $obj_writer->end_element();
        $obj_writer->start_element('c:dispBlanksAs');
        $obj_writer->write_attribute('val', $chart->get_display_blanks_as());
        $obj_writer->end_element();
        $obj_writer->start_element('c:showDLblsOverMax');
        $obj_writer->write_attribute('val', '0');
        $obj_writer->end_element();
        $obj_writer->end_element();
        // c:chart
        $obj_writer->start_element('c:spPr');
        if ($chart->get_no_fill()) {
            $obj_writer->start_element('a:noFill');
            $obj_writer->end_element();
            // a:noFill
        }
        $fill_color = $chart->get_fill_color();
        if ($fill_color->is_usable()) {
            $this->write_color($obj_writer, $fill_color);
        }
        $border_lines = $chart->get_border_lines();
        $this->write_line_styles($obj_writer, $border_lines, $chart->get_no_border());
        $this->write_effects($obj_writer, $border_lines);
        $obj_writer->end_element();
        // c:spPr
        $this->write_print_settings($obj_writer);
        $obj_writer->end_element();
        // c:chartSpace
        // Return
        return $obj_writer->get_data();
    }
    private function write_view3d(Xml_Writer $obj_writer, ?int $value, string $tag, bool $surface2D, int $default = 0): void
    {
        if ($value === null && $surface2D) {
            $value = $default;
        }
        if ($value !== null) {
            $obj_writer->start_element($tag);
            $obj_writer->write_attribute('val', "{$value}");
            $obj_writer->end_element();
        }
    }
    /**
     * Write Chart Title.
     */
    private function write_title(Xml_Writer $obj_writer, ?Title $title = null): void
    {
        if ($title === null) {
            return;
        }
        if ($this->write_calculated_title($obj_writer, $title)) {
            return;
        }
        $obj_writer->start_element('c:title');
        $caption = $title->get_caption();
        $obj_writer->start_element('c:tx');
        $obj_writer->start_element('c:rich');
        $obj_writer->start_element('a:bodyPr');
        $obj_writer->end_element();
        // a:bodyPr
        $obj_writer->start_element('a:lstStyle');
        $obj_writer->end_element();
        // a:lstStyle
        $obj_writer->start_element('a:p');
        $obj_writer->start_element('a:pPr');
        $obj_writer->start_element('a:defRPr');
        $obj_writer->end_element();
        // a:defRPr
        $obj_writer->end_element();
        // a:pPr
        if (is_array($caption)) {
            $caption = $caption[0] ?? '';
        }
        $this->get_parent_writer()->get_writer_partstringtable()->write_rich_text_for_charts($obj_writer, $caption, 'a');
        $obj_writer->end_element();
        // a:p
        $obj_writer->end_element();
        // c:rich
        $obj_writer->end_element();
        // c:tx
        $this->write_layout($obj_writer, $title->get_layout());
        $obj_writer->start_element('c:overlay');
        $obj_writer->write_attribute('val', $title->get_overlay() ? '1' : '0');
        $obj_writer->end_element();
        // c:overlay
        $obj_writer->end_element();
        // c:title
    }
    /**
     * Write Calculated Chart Title.
     */
    private function write_calculated_title(Xml_Writer $obj_writer, Title $title): bool
    {
        $calc = $title->get_calculated_title($this->get_parent_writer()->get_spreadsheet());
        if (empty($calc)) {
            return false;
        }
        $obj_writer->start_element('c:title');
        $obj_writer->start_element('c:tx');
        $obj_writer->start_element('c:strRef');
        $obj_writer->write_element('c:f', $title->get_cell_reference());
        $obj_writer->start_element('c:strCache');
        $obj_writer->start_element('c:ptCount');
        $obj_writer->write_attribute('val', '1');
        $obj_writer->end_element();
        // c:ptCount
        $obj_writer->start_element('c:pt');
        $obj_writer->write_attribute('idx', '0');
        $obj_writer->write_element('c:v', $calc);
        $obj_writer->end_element();
        // c:pt
        $obj_writer->end_element();
        // c:strCache
        $obj_writer->end_element();
        // c:strRef
        $obj_writer->end_element();
        // c:tx
        $this->write_layout($obj_writer, $title->get_layout());
        $obj_writer->start_element('c:overlay');
        $obj_writer->write_attribute('val', $title->get_overlay() ? '1' : '0');
        $obj_writer->end_element();
        // c:overlay
        // c:spPr
        // c:txPr
        $label_font = $title->get_font();
        if ($label_font !== null) {
            $obj_writer->start_element('c:txPr');
            $obj_writer->start_element('a:bodyPr');
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a:lstStyle
            $this->write_label_font($obj_writer, $label_font, null);
            $obj_writer->end_element();
            // c:txPr
        }
        $obj_writer->end_element();
        // c:title
        return true;
    }
    /**
     * Write Chart Legend.
     */
    private function write_legend(Xml_Writer $obj_writer, ?Legend $legend = null): void
    {
        if ($legend === null) {
            return;
        }
        $obj_writer->start_element('c:legend');
        $obj_writer->start_element('c:legendPos');
        $obj_writer->write_attribute('val', $legend->get_position());
        $obj_writer->end_element();
        $this->write_layout($obj_writer, $legend->get_layout());
        $obj_writer->start_element('c:overlay');
        $obj_writer->write_attribute('val', $legend->get_overlay() ? '1' : '0');
        $obj_writer->end_element();
        $obj_writer->start_element('c:spPr');
        $fill_color = $legend->get_fill_color();
        if ($fill_color->is_usable()) {
            $this->write_color($obj_writer, $fill_color);
        }
        $border_lines = $legend->get_border_lines();
        $this->write_line_styles($obj_writer, $border_lines);
        $this->write_effects($obj_writer, $border_lines);
        $obj_writer->end_element();
        // c:spPr
        $legend_text = $legend->get_legend_text();
        $obj_writer->start_element('c:txPr');
        $obj_writer->start_element('a:bodyPr');
        $obj_writer->end_element();
        $obj_writer->start_element('a:lstStyle');
        $obj_writer->end_element();
        $obj_writer->start_element('a:p');
        $obj_writer->start_element('a:pPr');
        $obj_writer->write_attribute('rtl', '0');
        $obj_writer->start_element('a:defRPr');
        if ($legend_text !== null) {
            $this->write_color($obj_writer, $legend_text->get_fill_color_object());
            $this->write_effects($obj_writer, $legend_text);
        }
        $obj_writer->end_element();
        // a:defRpr
        $obj_writer->end_element();
        // a:pPr
        $obj_writer->start_element('a:endParaRPr');
        $obj_writer->write_attribute('lang', 'en-US');
        $obj_writer->end_element();
        // a:endParaRPr
        $obj_writer->end_element();
        // a:p
        $obj_writer->end_element();
        // c:txPr
        $obj_writer->end_element();
        // c:legend
    }
    /**
     * Write Chart Plot Area.
     */
    private function write_plot_area(Xml_Writer $obj_writer, ?Plot_Area $plot_area, ?Title $x_axis_label = null, ?Title $y_axis_label = null, ?Axis $x_axis = null, ?Axis $y_axis = null): void
    {
        if ($plot_area === null) {
            return;
        }
        $id1 = $id2 = $id3 = '0';
        $this->series_index = 0;
        $obj_writer->start_element('c:plotArea');
        $layout = $plot_area->get_layout();
        $this->write_layout($obj_writer, $layout);
        $chart_types = self::get_chart_type($plot_area);
        $cat_is_multi_level_series = $val_is_multi_level_series = false;
        $plot_grouping_type = '';
        $chart_type = null;
        foreach ($chart_types as $chart_type) {
            $obj_writer->start_element('c:' . $chart_type);
            $group_count = $plot_area->get_plot_group_count();
            $plot_group = null;
            for ($i = 0; $i < $group_count; ++$i) {
                $plot_group = $plot_area->get_plot_group_by_index($i);
                $group_type = $plot_group->get_plot_type();
                if ($group_type == $chart_type) {
                    $plot_style = $plot_group->get_plot_style();
                    if (!empty($plot_style) && $group_type === Data_Series::TYPE_RADARCHART) {
                        $obj_writer->start_element('c:radarStyle');
                        $obj_writer->write_attribute('val', $plot_style);
                        $obj_writer->end_element();
                    } elseif (!empty($plot_style) && $group_type === Data_Series::TYPE_SCATTERCHART) {
                        $obj_writer->start_element('c:scatterStyle');
                        $obj_writer->write_attribute('val', $plot_style);
                        $obj_writer->end_element();
                    } elseif ($group_type === Data_Series::TYPE_SURFACECHART_3D || $group_type === Data_Series::TYPE_SURFACECHART) {
                        $obj_writer->start_element('c:wireframe');
                        $obj_writer->write_attribute('val', $plot_style ? '1' : '0');
                        $obj_writer->end_element();
                    }
                    $this->write_plot_group($plot_group, $chart_type, $obj_writer, $cat_is_multi_level_series, $val_is_multi_level_series, $plot_grouping_type);
                }
            }
            $this->write_data_labels($obj_writer, $layout);
            if ($chart_type === Data_Series::TYPE_LINECHART && $plot_group) {
                //    Line only, Line3D can't be smoothed
                $obj_writer->start_element('c:smooth');
                $obj_writer->write_attribute('val', (string) (int) $plot_group->get_smooth_line());
                $obj_writer->end_element();
            } elseif ($chart_type === Data_Series::TYPE_BARCHART || $chart_type === Data_Series::TYPE_BARCHART_3D) {
                $obj_writer->start_element('c:gapWidth');
                $obj_writer->write_attribute('val', '150');
                $obj_writer->end_element();
                if ($plot_grouping_type == 'percentStacked' || $plot_grouping_type == 'stacked') {
                    $obj_writer->start_element('c:overlap');
                    $obj_writer->write_attribute('val', '100');
                    $obj_writer->end_element();
                }
            } elseif ($chart_type === Data_Series::TYPE_BUBBLECHART) {
                $scale = $plot_group === null ? '' : (string) $plot_group->get_plot_style();
                if ($scale !== '') {
                    $obj_writer->start_element('c:bubbleScale');
                    $obj_writer->write_attribute('val', $scale);
                    $obj_writer->end_element();
                }
                $obj_writer->start_element('c:showNegBubbles');
                $obj_writer->write_attribute('val', '0');
                $obj_writer->end_element();
            } elseif ($chart_type === Data_Series::TYPE_STOCKCHART) {
                $obj_writer->start_element('c:hiLowLines');
                $obj_writer->end_element();
                $gap_width = $plot_area->get_gap_width();
                $up_bars = $plot_area->get_use_up_bars();
                $down_bars = $plot_area->get_use_down_bars();
                if ($gap_width !== null || $up_bars || $down_bars) {
                    $obj_writer->start_element('c:upDownBars');
                    if ($gap_width !== null) {
                        $obj_writer->start_element('c:gapWidth');
                        $obj_writer->write_attribute('val', "{$gap_width}");
                        $obj_writer->end_element();
                    }
                    if ($up_bars) {
                        $obj_writer->start_element('c:upBars');
                        $obj_writer->end_element();
                    }
                    if ($down_bars) {
                        $obj_writer->start_element('c:downBars');
                        $obj_writer->end_element();
                    }
                    $obj_writer->end_element();
                    // c:upDownBars
                }
            }
            //    Generate 3 unique numbers to use for axId values
            $id1 = '110438656';
            $id2 = '110444544';
            $id3 = '110365312';
            // used in Surface Chart
            if ($chart_type !== Data_Series::TYPE_PIECHART && $chart_type !== Data_Series::TYPE_PIECHART_3D && $chart_type !== Data_Series::TYPE_DONUTCHART) {
                $obj_writer->start_element('c:axId');
                $obj_writer->write_attribute('val', $id1);
                $obj_writer->end_element();
                $obj_writer->start_element('c:axId');
                $obj_writer->write_attribute('val', $id2);
                $obj_writer->end_element();
                if ($chart_type === Data_Series::TYPE_SURFACECHART_3D || $chart_type === Data_Series::TYPE_SURFACECHART) {
                    $obj_writer->start_element('c:axId');
                    $obj_writer->write_attribute('val', $id3);
                    $obj_writer->end_element();
                }
            } else {
                $obj_writer->start_element('c:firstSliceAng');
                $obj_writer->write_attribute('val', '0');
                $obj_writer->end_element();
                if ($chart_type === Data_Series::TYPE_DONUTCHART) {
                    $obj_writer->start_element('c:holeSize');
                    $obj_writer->write_attribute('val', '50');
                    $obj_writer->end_element();
                }
            }
            $obj_writer->end_element();
        }
        if ($chart_type !== Data_Series::TYPE_PIECHART && $chart_type !== Data_Series::TYPE_PIECHART_3D && $chart_type !== Data_Series::TYPE_DONUTCHART) {
            if ($chart_type === Data_Series::TYPE_BUBBLECHART) {
                $this->write_value_axis($obj_writer, $x_axis_label, $chart_type, $id2, $id1, $cat_is_multi_level_series, $x_axis ?? new Axis());
            } else {
                $this->write_category_axis($obj_writer, $x_axis_label, $id1, $id2, $cat_is_multi_level_series, $x_axis ?? new Axis());
            }
            $this->write_value_axis($obj_writer, $y_axis_label, $chart_type, $id1, $id2, $val_is_multi_level_series, $y_axis ?? new Axis());
            if ($chart_type === Data_Series::TYPE_SURFACECHART_3D || $chart_type === Data_Series::TYPE_SURFACECHART) {
                $this->write_ser_axis($obj_writer, $id2, $id3);
            }
        }
        $stops = $plot_area->get_gradient_fill_stops();
        if ($plot_area->get_no_fill() || !empty($stops)) {
            $obj_writer->start_element('c:spPr');
            if ($plot_area->get_no_fill()) {
                $obj_writer->start_element('a:noFill');
                $obj_writer->end_element();
                // a:noFill
            }
            if (!empty($stops)) {
                $obj_writer->start_element('a:gradFill');
                $obj_writer->start_element('a:gsLst');
                foreach ($stops as $stop) {
                    $obj_writer->start_element('a:gs');
                    $obj_writer->write_attribute('pos', (string) (Properties::PERCENTAGE_MULTIPLIER * (float) $stop[0]));
                    $this->write_color($obj_writer, $stop[1], false);
                    $obj_writer->end_element();
                    // a:gs
                }
                $obj_writer->end_element();
                // a:gsLst
                $angle = $plot_area->get_gradient_fill_angle();
                if ($angle !== null) {
                    $obj_writer->start_element('a:lin');
                    $obj_writer->write_attribute('ang', Properties::angle_to_xml($angle));
                    $obj_writer->end_element();
                    // a:lin
                }
                $obj_writer->end_element();
                // a:gradFill
            }
            $obj_writer->end_element();
            // c:spPr
        }
        $obj_writer->end_element();
        // c:plotArea
    }
    private function write_data_labels_bool(Xml_Writer $obj_writer, string $name, ?bool $value): void
    {
        if ($value !== null) {
            $obj_writer->start_element("c:{$name}");
            $obj_writer->write_attribute('val', $value ? '1' : '0');
            $obj_writer->end_element();
        }
    }
    /**
     * Write Data Labels.
     */
    private function write_data_labels(Xml_Writer $obj_writer, ?Layout $chart_layout = null): void
    {
        if (!isset($chart_layout)) {
            return;
        }
        $obj_writer->start_element('c:dLbls');
        $fill_color = $chart_layout->get_label_fill_color();
        $border_color = $chart_layout->get_label_border_color();
        if ($fill_color && $fill_color->is_usable()) {
            $obj_writer->start_element('c:spPr');
            $this->write_color($obj_writer, $fill_color);
            if ($border_color && $border_color->is_usable()) {
                $obj_writer->start_element('a:ln');
                $this->write_color($obj_writer, $border_color);
                $obj_writer->end_element();
                // a:ln
            }
            $obj_writer->end_element();
            // c:spPr
        }
        $label_font = $chart_layout->get_label_font();
        if ($label_font !== null) {
            $obj_writer->start_element('c:txPr');
            $obj_writer->start_element('a:bodyPr');
            $obj_writer->write_attribute('wrap', 'square');
            $obj_writer->write_attribute('lIns', '38100');
            $obj_writer->write_attribute('tIns', '19050');
            $obj_writer->write_attribute('rIns', '38100');
            $obj_writer->write_attribute('bIns', '19050');
            $obj_writer->write_attribute('anchor', 'ctr');
            $obj_writer->start_element('a:spAutoFit');
            $obj_writer->end_element();
            // a:spAutoFit
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a:lstStyle
            $this->write_label_font($obj_writer, $label_font, $chart_layout->get_label_effects());
            $obj_writer->end_element();
            // c:txPr
        }
        if ($chart_layout->get_num_fmt_code() !== '') {
            $obj_writer->start_element('c:numFmt');
            $obj_writer->write_attribute('formatCode', $chart_layout->getnum_fmt_code());
            $obj_writer->write_attribute('sourceLinked', (string) (int) $chart_layout->getnum_fmt_linked());
            $obj_writer->end_element();
            // c:numFmt
        }
        if ($chart_layout->get_d_lbl_pos() !== '') {
            $obj_writer->start_element('c:dLblPos');
            $obj_writer->write_attribute('val', $chart_layout->get_d_lbl_pos());
            $obj_writer->end_element();
            // c:dLblPos
        }
        $this->write_data_labels_bool($obj_writer, 'showLegendKey', $chart_layout->get_show_legend_key());
        $this->write_data_labels_bool($obj_writer, 'showVal', $chart_layout->get_show_val());
        $this->write_data_labels_bool($obj_writer, 'showCatName', $chart_layout->get_show_cat_name());
        $this->write_data_labels_bool($obj_writer, 'showSerName', $chart_layout->get_show_ser_name());
        $this->write_data_labels_bool($obj_writer, 'showPercent', $chart_layout->get_show_percent());
        $this->write_data_labels_bool($obj_writer, 'showBubbleSize', $chart_layout->get_show_bubble_size());
        $this->write_data_labels_bool($obj_writer, 'showLeaderLines', $chart_layout->get_show_leader_lines());
        $obj_writer->end_element();
        // c:dLbls
    }
    /**
     * Write Category Axis.
     */
    private function write_category_axis(Xml_Writer $obj_writer, ?Title $x_axis_label, string $id1, string $id2, bool $is_multi_level_series, Axis $y_axis): void
    {
        // N.B. writeCategoryAxis may be invoked with the last parameter($yAxis) using $xAxis for ScatterChart, etc
        // In that case, xAxis may contain values like the yAxis, or it may be a date axis (LINECHART).
        $axis_type = $y_axis->get_axis_type();
        if ($axis_type !== '') {
            $obj_writer->start_element("c:{$axis_type}");
        } elseif ($y_axis->get_axis_is_numeric_format()) {
            $obj_writer->start_element('c:' . Axis::AXIS_TYPE_VALUE);
        } else {
            $obj_writer->start_element('c:' . Axis::AXIS_TYPE_CATEGORY);
        }
        $major_gridlines = $y_axis->get_major_gridlines();
        $minor_gridlines = $y_axis->get_minor_gridlines();
        if ($id1 !== '0') {
            $obj_writer->start_element('c:axId');
            $obj_writer->write_attribute('val', $id1);
            $obj_writer->end_element();
        }
        $obj_writer->start_element('c:scaling');
        if (is_numeric($y_axis->get_axis_options_property('logBase'))) {
            $log_base = $y_axis->get_axis_options_property('logBase') + 0;
            if ($log_base >= 2 && $log_base <= 1000) {
                $obj_writer->start_element('c:logBase');
                $obj_writer->write_attribute('val', (string) $log_base);
                $obj_writer->end_element();
            }
        }
        if ($y_axis->get_axis_options_property('maximum') !== null) {
            $obj_writer->start_element('c:max');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('maximum'));
            $obj_writer->end_element();
        }
        if ($y_axis->get_axis_options_property('minimum') !== null) {
            $obj_writer->start_element('c:min');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('minimum'));
            $obj_writer->end_element();
        }
        if (!empty($y_axis->get_axis_options_property('orientation'))) {
            $obj_writer->start_element('c:orientation');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('orientation'));
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        // c:scaling
        $obj_writer->start_element('c:delete');
        $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('hidden') ?? '0');
        $obj_writer->end_element();
        $obj_writer->start_element('c:axPos');
        $obj_writer->write_attribute('val', 'b');
        $obj_writer->end_element();
        if ($major_gridlines !== null) {
            $obj_writer->start_element('c:majorGridlines');
            $obj_writer->start_element('c:spPr');
            $this->write_line_styles($obj_writer, $major_gridlines);
            $this->write_effects($obj_writer, $major_gridlines);
            $obj_writer->end_element();
            //end spPr
            $obj_writer->end_element();
            //end majorGridLines
        }
        if ($minor_gridlines !== null && $minor_gridlines->get_object_state()) {
            $obj_writer->start_element('c:minorGridlines');
            $obj_writer->start_element('c:spPr');
            $this->write_line_styles($obj_writer, $minor_gridlines);
            $this->write_effects($obj_writer, $minor_gridlines);
            $obj_writer->end_element();
            //end spPr
            $obj_writer->end_element();
            //end minorGridLines
        }
        if ($x_axis_label !== null) {
            $obj_writer->start_element('c:title');
            $caption = $x_axis_label->get_caption();
            $obj_writer->start_element('c:tx');
            $obj_writer->start_element('c:rich');
            $obj_writer->start_element('a:bodyPr');
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a::lstStyle
            $obj_writer->start_element('a:p');
            if (is_array($caption)) {
                $caption = $caption[0];
            }
            $this->get_parent_writer()->get_writer_partstringtable()->write_rich_text_for_charts($obj_writer, $caption, 'a');
            $obj_writer->end_element();
            // a:p
            $obj_writer->end_element();
            // c:rich
            $obj_writer->end_element();
            // c:tx
            $layout = $x_axis_label->get_layout();
            $this->write_layout($obj_writer, $layout);
            $obj_writer->start_element('c:overlay');
            $obj_writer->write_attribute('val', '0');
            $obj_writer->end_element();
            // c:overlay
            $obj_writer->end_element();
            // c:title
        }
        $obj_writer->start_element('c:numFmt');
        $obj_writer->write_attribute('formatCode', $y_axis->get_axis_number_format());
        $obj_writer->write_attribute('sourceLinked', $y_axis->get_axis_number_source_linked());
        $obj_writer->end_element();
        if (!empty($y_axis->get_axis_options_property('major_tick_mark'))) {
            $obj_writer->start_element('c:majorTickMark');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('major_tick_mark'));
            $obj_writer->end_element();
        }
        if (!empty($y_axis->get_axis_options_property('minor_tick_mark'))) {
            $obj_writer->start_element('c:minorTickMark');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('minor_tick_mark'));
            $obj_writer->end_element();
        }
        if (!empty($y_axis->get_axis_options_property('axis_labels'))) {
            $obj_writer->start_element('c:tickLblPos');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('axis_labels'));
            $obj_writer->end_element();
        }
        $text_rotation = $y_axis->get_axis_options_property('textRotation');
        $axis_text = $y_axis->get_axis_text();
        if ($axis_text !== null || is_numeric($text_rotation)) {
            $obj_writer->start_element('c:txPr');
            $obj_writer->start_element('a:bodyPr');
            if (is_numeric($text_rotation)) {
                $obj_writer->write_attribute('rot', Properties::angle_to_xml((float) $text_rotation));
            }
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a:lstStyle
            $this->write_label_font($obj_writer, $axis_text === null ? null : $axis_text->get_font(), $axis_text);
            $obj_writer->end_element();
            // c:txPr
        }
        $obj_writer->start_element('c:spPr');
        $this->write_color($obj_writer, $y_axis->get_fill_color_object());
        $this->write_line_styles($obj_writer, $y_axis, $y_axis->get_no_fill());
        $this->write_effects($obj_writer, $y_axis);
        $obj_writer->end_element();
        // spPr
        if ($y_axis->get_axis_options_property('major_unit') !== null) {
            $obj_writer->start_element('c:majorUnit');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('major_unit'));
            $obj_writer->end_element();
        }
        if ($y_axis->get_axis_options_property('minor_unit') !== null) {
            $obj_writer->start_element('c:minorUnit');
            $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('minor_unit'));
            $obj_writer->end_element();
        }
        if ($id2 !== '0') {
            $obj_writer->start_element('c:crossAx');
            $obj_writer->write_attribute('val', $id2);
            $obj_writer->end_element();
            if (!empty($y_axis->get_axis_options_property('horizontal_crosses'))) {
                $obj_writer->start_element('c:crosses');
                $obj_writer->write_attribute('val', $y_axis->get_axis_options_property('horizontal_crosses'));
                $obj_writer->end_element();
            }
        }
        $obj_writer->start_element('c:auto');
        // LineChart with dateAx wants '0'
        $obj_writer->write_attribute('val', $axis_type === Axis::AXIS_TYPE_DATE ? '0' : '1');
        $obj_writer->end_element();
        $obj_writer->start_element('c:lblAlgn');
        $obj_writer->write_attribute('val', 'ctr');
        $obj_writer->end_element();
        $obj_writer->start_element('c:lblOffset');
        $obj_writer->write_attribute('val', '100');
        $obj_writer->end_element();
        if ($axis_type === Axis::AXIS_TYPE_DATE) {
            $property = 'baseTimeUnit';
            $property_val = $y_axis->get_axis_options_property($property);
            if (!empty($property_val)) {
                $obj_writer->start_element("c:{$property}");
                $obj_writer->write_attribute('val', $property_val);
                $obj_writer->end_element();
            }
            $property = 'majorTimeUnit';
            $property_val = $y_axis->get_axis_options_property($property);
            if (!empty($property_val)) {
                $obj_writer->start_element("c:{$property}");
                $obj_writer->write_attribute('val', $property_val);
                $obj_writer->end_element();
            }
            $property = 'minorTimeUnit';
            $property_val = $y_axis->get_axis_options_property($property);
            if (!empty($property_val)) {
                $obj_writer->start_element("c:{$property}");
                $obj_writer->write_attribute('val', $property_val);
                $obj_writer->end_element();
            }
        }
        if ($is_multi_level_series) {
            $obj_writer->start_element('c:noMultiLvlLbl');
            $obj_writer->write_attribute('val', '0');
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
    }
    /**
     * Write Value Axis.
     *
     * @param null|string $groupType Chart type
     */
    private function write_value_axis(Xml_Writer $obj_writer, ?Title $y_axis_label, ?string $group_type, string $id1, string $id2, bool $is_multi_level_series, Axis $x_axis): void
    {
        $obj_writer->start_element('c:' . Axis::AXIS_TYPE_VALUE);
        $major_gridlines = $x_axis->get_major_gridlines();
        $minor_gridlines = $x_axis->get_minor_gridlines();
        if ($id2 !== '0') {
            $obj_writer->start_element('c:axId');
            $obj_writer->write_attribute('val', $id2);
            $obj_writer->end_element();
        }
        $obj_writer->start_element('c:scaling');
        if (is_numeric($x_axis->get_axis_options_property('logBase'))) {
            $log_base = $x_axis->get_axis_options_property('logBase') + 0;
            if ($log_base >= 2 && $log_base <= 1000) {
                $obj_writer->start_element('c:logBase');
                $obj_writer->write_attribute('val', (string) $log_base);
                $obj_writer->end_element();
            }
        }
        if ($x_axis->get_axis_options_property('maximum') !== null) {
            $obj_writer->start_element('c:max');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('maximum'));
            $obj_writer->end_element();
        }
        if ($x_axis->get_axis_options_property('minimum') !== null) {
            $obj_writer->start_element('c:min');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('minimum'));
            $obj_writer->end_element();
        }
        if (!empty($x_axis->get_axis_options_property('orientation'))) {
            $obj_writer->start_element('c:orientation');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('orientation'));
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        // c:scaling
        $obj_writer->start_element('c:delete');
        $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('hidden') ?? '0');
        $obj_writer->end_element();
        $obj_writer->start_element('c:axPos');
        $obj_writer->write_attribute('val', 'l');
        $obj_writer->end_element();
        if ($major_gridlines !== null) {
            $obj_writer->start_element('c:majorGridlines');
            $obj_writer->start_element('c:spPr');
            $this->write_line_styles($obj_writer, $major_gridlines);
            $this->write_effects($obj_writer, $major_gridlines);
            $obj_writer->end_element();
            //end spPr
            $obj_writer->end_element();
            //end majorGridLines
        }
        if ($minor_gridlines !== null && $minor_gridlines->get_object_state()) {
            $obj_writer->start_element('c:minorGridlines');
            $obj_writer->start_element('c:spPr');
            $this->write_line_styles($obj_writer, $minor_gridlines);
            $this->write_effects($obj_writer, $minor_gridlines);
            $obj_writer->end_element();
            //end spPr
            $obj_writer->end_element();
            //end minorGridLines
        }
        if ($y_axis_label !== null) {
            $obj_writer->start_element('c:title');
            $caption = $y_axis_label->get_caption();
            $obj_writer->start_element('c:tx');
            $obj_writer->start_element('c:rich');
            $obj_writer->start_element('a:bodyPr');
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a:lstStyle
            $obj_writer->start_element('a:p');
            if (is_array($caption)) {
                $caption = $caption[0];
            }
            $this->get_parent_writer()->get_writer_partstringtable()->write_rich_text_for_charts($obj_writer, $caption, 'a');
            $obj_writer->end_element();
            // a:p
            $obj_writer->end_element();
            // c:rich
            $obj_writer->end_element();
            // c:tx
            if ($group_type !== Data_Series::TYPE_BUBBLECHART) {
                $layout = $y_axis_label->get_layout();
                $this->write_layout($obj_writer, $layout);
            }
            $obj_writer->start_element('c:overlay');
            $obj_writer->write_attribute('val', '0');
            $obj_writer->end_element();
            // c:overlay
            $obj_writer->end_element();
            // c:title
        }
        $obj_writer->start_element('c:numFmt');
        $obj_writer->write_attribute('formatCode', $x_axis->get_axis_number_format());
        $obj_writer->write_attribute('sourceLinked', $x_axis->get_axis_number_source_linked());
        $obj_writer->end_element();
        if (!empty($x_axis->get_axis_options_property('major_tick_mark'))) {
            $obj_writer->start_element('c:majorTickMark');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('major_tick_mark'));
            $obj_writer->end_element();
        }
        if (!empty($x_axis->get_axis_options_property('minor_tick_mark'))) {
            $obj_writer->start_element('c:minorTickMark');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('minor_tick_mark'));
            $obj_writer->end_element();
        }
        if (!empty($x_axis->get_axis_options_property('axis_labels'))) {
            $obj_writer->start_element('c:tickLblPos');
            $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('axis_labels'));
            $obj_writer->end_element();
        }
        $text_rotation = $x_axis->get_axis_options_property('textRotation');
        $axis_text = $x_axis->get_axis_text();
        if ($axis_text !== null || is_numeric($text_rotation)) {
            $obj_writer->start_element('c:txPr');
            $obj_writer->start_element('a:bodyPr');
            if (is_numeric($text_rotation)) {
                $obj_writer->write_attribute('rot', Properties::angle_to_xml((float) $text_rotation));
            }
            $obj_writer->end_element();
            // a:bodyPr
            $obj_writer->start_element('a:lstStyle');
            $obj_writer->end_element();
            // a:lstStyle
            $this->write_label_font($obj_writer, $axis_text === null ? null : $axis_text->get_font(), $axis_text);
            $obj_writer->end_element();
            // c:txPr
        }
        $obj_writer->start_element('c:spPr');
        $this->write_color($obj_writer, $x_axis->get_fill_color_object());
        $this->write_line_styles($obj_writer, $x_axis, $x_axis->get_no_fill());
        $this->write_effects($obj_writer, $x_axis);
        $obj_writer->end_element();
        //end spPr
        if ($id1 !== '0') {
            $obj_writer->start_element('c:crossAx');
            $obj_writer->write_attribute('val', $id1);
            $obj_writer->end_element();
            if ($x_axis->get_axis_options_property('horizontal_crosses_value') !== null) {
                $obj_writer->start_element('c:crossesAt');
                $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('horizontal_crosses_value'));
                $obj_writer->end_element();
            } else {
                $crosses = $x_axis->get_axis_options_property('horizontal_crosses');
                if ($crosses) {
                    $obj_writer->start_element('c:crosses');
                    $obj_writer->write_attribute('val', $crosses);
                    $obj_writer->end_element();
                }
            }
            $cross_between = $x_axis->get_cross_between();
            if ($cross_between !== '') {
                $obj_writer->start_element('c:crossBetween');
                $obj_writer->write_attribute('val', $cross_between);
                $obj_writer->end_element();
            }
            if ($x_axis->get_axis_type() === Axis::AXIS_TYPE_VALUE) {
                $disp_units = $x_axis->get_axis_options_property('dispUnitsBuiltIn');
                $disp_units = $disp_units == Axis::TRILLION_INDEX ? Axis::DISP_UNITS_TRILLIONS : (is_numeric($disp_units) ? Axis::DISP_UNITS_BUILTIN_INT[(int) $disp_units] ?? '' : $disp_units);
                if (in_array($disp_units, Axis::DISP_UNITS_BUILTIN_INT, true)) {
                    $obj_writer->start_element('c:dispUnits');
                    $obj_writer->start_element('c:builtInUnit');
                    $obj_writer->write_attribute('val', $disp_units);
                    $obj_writer->end_element();
                    // c:builtInUnit
                    if ($x_axis->get_disp_units_title() !== null) {
                        // TODO output title elements
                        $obj_writer->write_element('c:dispUnitsLbl');
                    }
                    $obj_writer->end_element();
                    // c:dispUnits
                }
            }
            if ($x_axis->get_axis_options_property('major_unit') !== null) {
                $obj_writer->start_element('c:majorUnit');
                $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('major_unit'));
                $obj_writer->end_element();
            }
            if ($x_axis->get_axis_options_property('minor_unit') !== null) {
                $obj_writer->start_element('c:minorUnit');
                $obj_writer->write_attribute('val', $x_axis->get_axis_options_property('minor_unit'));
                $obj_writer->end_element();
            }
        }
        if ($is_multi_level_series) {
            if ($group_type !== Data_Series::TYPE_BUBBLECHART) {
                $obj_writer->start_element('c:noMultiLvlLbl');
                $obj_writer->write_attribute('val', '0');
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
    }
    /**
     * Write Ser Axis, for Surface chart.
     */
    private function write_ser_axis(Xml_Writer $obj_writer, string $id2, string $id3): void
    {
        $obj_writer->start_element('c:serAx');
        $obj_writer->start_element('c:axId');
        $obj_writer->write_attribute('val', $id3);
        $obj_writer->end_element();
        // axId
        $obj_writer->start_element('c:scaling');
        $obj_writer->start_element('c:orientation');
        $obj_writer->write_attribute('val', 'minMax');
        $obj_writer->end_element();
        // orientation
        $obj_writer->end_element();
        // scaling
        $obj_writer->start_element('c:delete');
        $obj_writer->write_attribute('val', '0');
        $obj_writer->end_element();
        // delete
        $obj_writer->start_element('c:axPos');
        $obj_writer->write_attribute('val', 'b');
        $obj_writer->end_element();
        // axPos
        $obj_writer->start_element('c:majorTickMark');
        $obj_writer->write_attribute('val', 'out');
        $obj_writer->end_element();
        // majorTickMark
        $obj_writer->start_element('c:minorTickMark');
        $obj_writer->write_attribute('val', 'none');
        $obj_writer->end_element();
        // minorTickMark
        $obj_writer->start_element('c:tickLblPos');
        $obj_writer->write_attribute('val', 'nextTo');
        $obj_writer->end_element();
        // tickLblPos
        $obj_writer->start_element('c:crossAx');
        $obj_writer->write_attribute('val', $id2);
        $obj_writer->end_element();
        // crossAx
        $obj_writer->start_element('c:crosses');
        $obj_writer->write_attribute('val', 'autoZero');
        $obj_writer->end_element();
        // crosses
        $obj_writer->end_element();
        //serAx
    }
    /**
     * Get the data series type(s) for a chart plot series.
     *
     * @return string[]
     */
    private static function get_chart_type(Plot_Area $plot_area): array
    {
        $group_count = $plot_area->get_plot_group_count();
        if ($group_count == 1) {
            $plot_type = $plot_area->get_plot_group_by_index(0)->get_plot_type();
            $chart_type = $plot_type === null ? [] : [$plot_type];
        } else {
            $chart_types = [];
            for ($i = 0; $i < $group_count; ++$i) {
                $plot_type = $plot_area->get_plot_group_by_index($i)->get_plot_type();
                if ($plot_type !== null) {
                    $chart_types[] = $plot_type;
                }
            }
            $chart_type = array_unique($chart_types);
        }
        if (count($chart_type) == 0) {
            throw new Writer_Exception('Chart is not yet implemented');
        }
        return $chart_type;
    }
    /**
     * Method writing plot series values.
     */
    private function write_plot_series_values_element(Xml_Writer $obj_writer, int $val, ?Chart_Color $fill_color): void
    {
        if ($fill_color === null || !$fill_color->is_usable()) {
            return;
        }
        $obj_writer->start_element('c:dPt');
        $obj_writer->start_element('c:idx');
        $obj_writer->write_attribute('val', "{$val}");
        $obj_writer->end_element();
        // c:idx
        $obj_writer->start_element('c:spPr');
        $this->write_color($obj_writer, $fill_color);
        $obj_writer->end_element();
        // c:spPr
        $obj_writer->end_element();
        // c:dPt
    }
    /**
     * Write Plot Group (series of related plots).
     *
     * @param string $groupType Type of plot for dataseries
     * @param bool $catIsMultiLevelSeries Is category a multi-series category
     * @param bool $valIsMultiLevelSeries Is value set a multi-series set
     * @param string $plotGroupingType Type of grouping for multi-series values
     */
    private function write_plot_group(?Data_Series $plot_group, string $group_type, Xml_Writer $obj_writer, bool &$cat_is_multi_level_series, bool &$val_is_multi_level_series, string &$plot_grouping_type): void
    {
        if ($plot_group === null) {
            return;
        }
        if ($group_type == Data_Series::TYPE_BARCHART || $group_type == Data_Series::TYPE_BARCHART_3D) {
            $obj_writer->start_element('c:barDir');
            $obj_writer->write_attribute('val', $plot_group->get_plot_direction());
            $obj_writer->end_element();
        }
        $plot_grouping_type = (string) $plot_group->get_plot_grouping();
        if ($plot_grouping_type !== '' && $group_type !== Data_Series::TYPE_SURFACECHART && $group_type !== Data_Series::TYPE_SURFACECHART_3D) {
            $obj_writer->start_element('c:grouping');
            $obj_writer->write_attribute('val', $plot_grouping_type);
            $obj_writer->end_element();
        }
        //    Get these details before the loop, because we can use the count to check for varyColors
        $plot_series_order = $plot_group->get_plot_order();
        $plot_series_count = count($plot_series_order);
        if ($group_type !== Data_Series::TYPE_RADARCHART && $group_type !== Data_Series::TYPE_STOCKCHART) {
            if ($group_type !== Data_Series::TYPE_LINECHART) {
                if ($group_type == Data_Series::TYPE_PIECHART || $group_type == Data_Series::TYPE_PIECHART_3D || $group_type == Data_Series::TYPE_DONUTCHART || $plot_series_count > 1) {
                    $obj_writer->start_element('c:varyColors');
                    $obj_writer->write_attribute('val', '1');
                    $obj_writer->end_element();
                } else {
                    $obj_writer->start_element('c:varyColors');
                    $obj_writer->write_attribute('val', '0');
                    $obj_writer->end_element();
                }
            }
        }
        $plot_series_idx = 0;
        foreach ($plot_series_order as $plot_series_idx => $plot_series_ref) {
            $obj_writer->start_element('c:ser');
            $obj_writer->start_element('c:idx');
            $adder = array_key_exists(0, $plot_series_order) ? $this->series_index : 0;
            $obj_writer->write_attribute('val', (string) ($adder + $plot_series_idx));
            $obj_writer->end_element();
            $obj_writer->start_element('c:order');
            $obj_writer->write_attribute('val', (string) ($adder + $plot_series_ref));
            $obj_writer->end_element();
            $plot_label = $plot_group->get_plot_label_by_index($plot_series_idx);
            $label_fill = null;
            if ($plot_label && $group_type === Data_Series::TYPE_LINECHART) {
                $label_fill = $plot_label->get_fill_color_object();
                $label_fill = $label_fill instanceof Chart_Color ? $label_fill : null;
            }
            //    Values
            $plot_series_values = $plot_group->get_plot_values_by_index($plot_series_idx);
            if ($plot_series_values !== false && in_array($group_type, self::CUSTOM_COLOR_TYPES, true)) {
                $fill_color_values = $plot_series_values->get_fill_color_object();
                if ($fill_color_values !== null && is_array($fill_color_values)) {
                    foreach ($plot_series_values->get_data_values() ?? [] as $data_key => $data_value) {
                        $this->write_plot_series_values_element($obj_writer, $data_key, $fill_color_values[$data_key] ?? null);
                    }
                }
            }
            if ($plot_series_values !== false && $plot_series_values->get_label_layout()) {
                $this->write_data_labels($obj_writer, $plot_series_values->get_label_layout());
            }
            //    Labels
            $plot_series_label = $plot_group->get_plot_label_by_index($plot_series_idx);
            if ($plot_series_label && $plot_series_label->get_point_count() > 0) {
                $obj_writer->start_element('c:tx');
                $obj_writer->start_element('c:strRef');
                $this->write_plot_series_label($plot_series_label, $obj_writer);
                $obj_writer->end_element();
                $obj_writer->end_element();
            }
            //    Formatting for the points
            if ($plot_series_values !== false) {
                $obj_writer->start_element('c:spPr');
                if ($plot_label && $group_type !== Data_Series::TYPE_LINECHART) {
                    $fill_color = $plot_label->get_fill_color_object();
                    if ($fill_color !== null && !is_array($fill_color) && $fill_color->is_usable()) {
                        $this->write_color($obj_writer, $fill_color);
                    }
                }
                $fill_object = $label_fill ?? $plot_series_values->get_fill_color_object();
                $call_line_styles = true;
                if ($fill_object instanceof Chart_Color && $fill_object->is_usable()) {
                    if ($group_type === Data_Series::TYPE_LINECHART) {
                        $obj_writer->start_element('a:ln');
                        $call_line_styles = false;
                    }
                    $this->write_color($obj_writer, $fill_object);
                    if (!$call_line_styles) {
                        $obj_writer->end_element();
                        // a:ln
                    }
                }
                $nofill = $group_type === Data_Series::TYPE_STOCKCHART || ($group_type === Data_Series::TYPE_SCATTERCHART || $group_type === Data_Series::TYPE_LINECHART) && !$plot_series_values->get_scatter_lines();
                if ($call_line_styles) {
                    $this->write_line_styles($obj_writer, $plot_series_values, $nofill);
                    $this->write_effects($obj_writer, $plot_series_values);
                }
                $obj_writer->end_element();
                // c:spPr
            }
            if ($plot_series_values) {
                $plot_series_marker = $plot_series_values->get_point_marker();
                $marker_fill_color = $plot_series_values->get_marker_fill_color();
                $fill_used = $marker_fill_color->is_usable();
                $marker_border_color = $plot_series_values->get_marker_border_color();
                $border_used = $marker_border_color->is_usable();
                if ($plot_series_marker || $fill_used || $border_used) {
                    $obj_writer->start_element('c:marker');
                    $obj_writer->start_element('c:symbol');
                    if ($plot_series_marker) {
                        $obj_writer->write_attribute('val', $plot_series_marker);
                    }
                    $obj_writer->end_element();
                    if ($plot_series_marker !== 'none') {
                        $obj_writer->start_element('c:size');
                        $obj_writer->write_attribute('val', (string) $plot_series_values->get_point_size());
                        $obj_writer->end_element();
                        // c:size
                        $obj_writer->start_element('c:spPr');
                        $this->write_color($obj_writer, $marker_fill_color);
                        if ($border_used) {
                            $obj_writer->start_element('a:ln');
                            $this->write_color($obj_writer, $marker_border_color);
                            $obj_writer->end_element();
                            // a:ln
                        }
                        $obj_writer->end_element();
                        // spPr
                    }
                    $obj_writer->end_element();
                }
            }
            if ($group_type === Data_Series::TYPE_BARCHART || $group_type === Data_Series::TYPE_BARCHART_3D || $group_type === Data_Series::TYPE_BUBBLECHART) {
                $obj_writer->start_element('c:invertIfNegative');
                $obj_writer->write_attribute('val', '0');
                $obj_writer->end_element();
            }
            // Trendlines
            if ($plot_series_values !== false) {
                foreach ($plot_series_values->get_trend_lines() as $trend_line) {
                    $trend_line_type = $trend_line->get_trend_line_type();
                    $order = $trend_line->get_order();
                    $period = $trend_line->get_period();
                    $disp_r_sqr = $trend_line->get_disp_r_sqr();
                    $disp_eq = $trend_line->get_disp_eq();
                    $forward = $trend_line->get_forward();
                    $backward = $trend_line->get_backward();
                    $intercept = $trend_line->get_intercept();
                    $name = $trend_line->get_name();
                    $trend_line_color = $trend_line->get_line_color();
                    // ChartColor
                    $obj_writer->start_element('c:trendline');
                    // N.B. lowercase 'ell'
                    if ($name !== '') {
                        $obj_writer->start_element('c:name');
                        $obj_writer->write_raw_data($name);
                        $obj_writer->end_element();
                        // c:name
                    }
                    $obj_writer->start_element('c:spPr');
                    if (!$trend_line_color->is_usable()) {
                        // use dataSeriesValues line color as a backup if $trendLineColor is null
                        $dsv_line_color = $plot_series_values->get_line_color();
                        if ($dsv_line_color->is_usable()) {
                            $trend_line->get_line_color()->set_color_properties($dsv_line_color->get_value(), $dsv_line_color->get_alpha(), $dsv_line_color->get_type());
                        }
                    }
                    // otherwise, hope Excel does the right thing
                    $this->write_line_styles($obj_writer, $trend_line, false);
                    // suppress noFill
                    $obj_writer->end_element();
                    // spPr
                    $obj_writer->start_element('c:trendlineType');
                    // N.B lowercase 'ell'
                    $obj_writer->write_attribute('val', $trend_line_type);
                    $obj_writer->end_element();
                    // trendlineType
                    if ($backward !== 0.0) {
                        $obj_writer->start_element('c:backward');
                        $obj_writer->write_attribute('val', "{$backward}");
                        $obj_writer->end_element();
                        // c:backward
                    }
                    if ($forward !== 0.0) {
                        $obj_writer->start_element('c:forward');
                        $obj_writer->write_attribute('val', "{$forward}");
                        $obj_writer->end_element();
                        // c:forward
                    }
                    if ($intercept !== 0.0) {
                        $obj_writer->start_element('c:intercept');
                        $obj_writer->write_attribute('val', "{$intercept}");
                        $obj_writer->end_element();
                        // c:intercept
                    }
                    if ($trend_line_type == Trend_Line::TRENDLINE_POLYNOMIAL) {
                        $obj_writer->start_element('c:order');
                        $obj_writer->write_attribute('val', "{$order}");
                        $obj_writer->end_element();
                        // order
                    }
                    if ($trend_line_type == Trend_Line::TRENDLINE_MOVING_AVG) {
                        $obj_writer->start_element('c:period');
                        $obj_writer->write_attribute('val', "{$period}");
                        $obj_writer->end_element();
                        // period
                    }
                    $obj_writer->start_element('c:dispRSqr');
                    $obj_writer->write_attribute('val', $disp_r_sqr ? '1' : '0');
                    $obj_writer->end_element();
                    $obj_writer->start_element('c:dispEq');
                    $obj_writer->write_attribute('val', $disp_eq ? '1' : '0');
                    $obj_writer->end_element();
                    if ($group_type === Data_Series::TYPE_SCATTERCHART || $group_type === Data_Series::TYPE_LINECHART) {
                        $obj_writer->start_element('c:trendlineLbl');
                        $obj_writer->start_element('c:numFmt');
                        $obj_writer->write_attribute('formatCode', 'General');
                        $obj_writer->write_attribute('sourceLinked', '0');
                        $obj_writer->end_element();
                        // numFmt
                        $obj_writer->end_element();
                        // trendlineLbl
                    }
                    $obj_writer->end_element();
                    // trendline
                }
            }
            //    Category Labels
            $plot_series_category = $plot_group->get_plot_category_by_index($plot_series_idx);
            if ($plot_series_category && $plot_series_category->get_point_count() > 0) {
                $cat_is_multi_level_series = $cat_is_multi_level_series || $plot_series_category->is_multi_level_series();
                if ($group_type == Data_Series::TYPE_PIECHART || $group_type == Data_Series::TYPE_PIECHART_3D || $group_type == Data_Series::TYPE_DONUTCHART) {
                    $plot_style = $plot_group->get_plot_style();
                    if (is_numeric($plot_style)) {
                        $obj_writer->start_element('c:explosion');
                        $obj_writer->write_attribute('val', $plot_style);
                        $obj_writer->end_element();
                    }
                }
                if ($group_type === Data_Series::TYPE_BUBBLECHART || $group_type === Data_Series::TYPE_SCATTERCHART) {
                    $obj_writer->start_element('c:xVal');
                } else {
                    $obj_writer->start_element('c:cat');
                }
                // xVals (Categories) are not always 'str'
                // Test X-axis Label's Datatype to decide 'str' vs 'num'
                $category_datatype = $plot_series_category->get_data_type();
                if ($category_datatype == Data_Series_Values::DATASERIES_TYPE_NUMBER) {
                    $this->write_plot_series_values($plot_series_category, $obj_writer, $group_type, 'num');
                } else {
                    $this->write_plot_series_values($plot_series_category, $obj_writer, $group_type, 'str');
                }
                $obj_writer->end_element();
            }
            //    Values
            if ($plot_series_values) {
                $val_is_multi_level_series = $val_is_multi_level_series || $plot_series_values->is_multi_level_series();
                if ($group_type === Data_Series::TYPE_BUBBLECHART || $group_type === Data_Series::TYPE_SCATTERCHART) {
                    $obj_writer->start_element('c:yVal');
                } else {
                    $obj_writer->start_element('c:val');
                }
                $this->write_plot_series_values($plot_series_values, $obj_writer, $group_type, 'num');
                $obj_writer->end_element();
                if ($group_type === Data_Series::TYPE_SCATTERCHART && $plot_group->get_plot_style() === 'smoothMarker') {
                    $obj_writer->start_element('c:smooth');
                    $obj_writer->write_attribute('val', $plot_series_values->get_smooth_line() ? '1' : '0');
                    $obj_writer->end_element();
                }
            }
            if ($group_type === Data_Series::TYPE_BUBBLECHART) {
                if (!empty($plot_group->get_plot_bubble_sizes()[$plot_series_idx])) {
                    $obj_writer->start_element('c:bubbleSize');
                    $this->write_plot_series_values($plot_group->get_plot_bubble_sizes()[$plot_series_idx], $obj_writer, $group_type, 'num');
                    $obj_writer->end_element();
                    if ($plot_series_values !== false) {
                        $obj_writer->start_element('c:bubble3D');
                        $obj_writer->write_attribute('val', $plot_series_values->get_bubble3d() ? '1' : '0');
                        $obj_writer->end_element();
                    }
                } elseif ($plot_series_values !== false) {
                    $this->write_bubbles($plot_series_values, $obj_writer);
                }
            }
            $obj_writer->end_element();
        }
        $this->series_index += $plot_series_idx + 1;
    }
    /**
     * Write Plot Series Label.
     */
    private function write_plot_series_label(?Data_Series_Values $plot_series_label, Xml_Writer $obj_writer): void
    {
        if ($plot_series_label === null) {
            return;
        }
        $obj_writer->start_element('c:f');
        $obj_writer->write_raw_data($plot_series_label->get_data_source());
        $obj_writer->end_element();
        $obj_writer->start_element('c:strCache');
        $obj_writer->start_element('c:ptCount');
        $obj_writer->write_attribute('val', (string) $plot_series_label->get_point_count());
        $obj_writer->end_element();
        foreach ($plot_series_label->get_data_values() ?? [] as $plot_label_key => $plot_label_value) {
            /** @var string $plotLabelValue */
            $obj_writer->start_element('c:pt');
            $obj_writer->write_attribute('idx', $plot_label_key);
            $obj_writer->start_element('c:v');
            $obj_writer->write_raw_data($plot_label_value);
            $obj_writer->end_element();
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
    }
    /**
     * Write Plot Series Values.
     *
     * @param string $groupType Type of plot for dataseries
     * @param string $dataType Datatype of series values
     */
    private function write_plot_series_values(?Data_Series_Values $plot_series_values, Xml_Writer $obj_writer, string $group_type, string $data_type = 'str'): void
    {
        if ($plot_series_values === null) {
            return;
        }
        if ($plot_series_values->is_multi_level_series()) {
            $level_count = $plot_series_values->multi_level_count();
            $obj_writer->start_element('c:multiLvlStrRef');
            $obj_writer->start_element('c:f');
            $obj_writer->write_raw_data($plot_series_values->get_data_source());
            $obj_writer->end_element();
            $obj_writer->start_element('c:multiLvlStrCache');
            $obj_writer->start_element('c:ptCount');
            $obj_writer->write_attribute('val', (string) $plot_series_values->get_point_count());
            $obj_writer->end_element();
            for ($level = 0; $level < $level_count; ++$level) {
                $obj_writer->start_element('c:lvl');
                foreach ($plot_series_values->get_data_values() ?? [] as $plot_series_key => $plot_series_value) {
                    /** @var string[] $plotSeriesValue */
                    if (isset($plot_series_value[$level])) {
                        $obj_writer->start_element('c:pt');
                        $obj_writer->write_attribute('idx', $plot_series_key);
                        $obj_writer->start_element('c:v');
                        $obj_writer->write_raw_data($plot_series_value[$level]);
                        $obj_writer->end_element();
                        $obj_writer->end_element();
                    }
                }
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
            $obj_writer->end_element();
        } else {
            $obj_writer->start_element('c:' . $data_type . 'Ref');
            $obj_writer->start_element('c:f');
            $obj_writer->write_raw_data($plot_series_values->get_data_source());
            $obj_writer->end_element();
            $count = $plot_series_values->get_point_count();
            $source = $plot_series_values->get_data_source();
            $values = $plot_series_values->get_data_values();
            if ($count > 1 || $count === 1 && is_array($values) && array_key_exists(0, $values) && "={$source}" !== String_Helper::convert_to_string($values[0], false)) {
                $obj_writer->start_element('c:' . $data_type . 'Cache');
                if ($group_type != Data_Series::TYPE_PIECHART && $group_type != Data_Series::TYPE_PIECHART_3D && $group_type != Data_Series::TYPE_DONUTCHART) {
                    if ($plot_series_values->get_format_code() !== null && $plot_series_values->get_format_code() !== '') {
                        $obj_writer->start_element('c:formatCode');
                        $obj_writer->write_raw_data($plot_series_values->get_format_code());
                        $obj_writer->end_element();
                    }
                }
                $obj_writer->start_element('c:ptCount');
                $obj_writer->write_attribute('val', (string) $plot_series_values->get_point_count());
                $obj_writer->end_element();
                /** @var array<string, string> */
                $data_values = $plot_series_values->get_data_values();
                if (!empty($data_values)) {
                    foreach ($data_values as $plot_series_key => $plot_series_value) {
                        $obj_writer->start_element('c:pt');
                        $obj_writer->write_attribute('idx', $plot_series_key);
                        $obj_writer->start_element('c:v');
                        $obj_writer->write_raw_data($plot_series_value);
                        $obj_writer->end_element();
                        $obj_writer->end_element();
                    }
                }
                $obj_writer->end_element();
                // *Cache
            }
            $obj_writer->end_element();
            // *Ref
        }
    }
    private const CUSTOM_COLOR_TYPES = [Data_Series::TYPE_BARCHART, Data_Series::TYPE_BARCHART_3D, Data_Series::TYPE_PIECHART, Data_Series::TYPE_PIECHART_3D, Data_Series::TYPE_DONUTCHART];
    /**
     * Write Bubble Chart Details.
     */
    private function write_bubbles(?Data_Series_Values $plot_series_values, Xml_Writer $obj_writer): void
    {
        if ($plot_series_values === null) {
            return;
        }
        $obj_writer->start_element('c:bubbleSize');
        $obj_writer->start_element('c:numLit');
        $obj_writer->start_element('c:formatCode');
        $obj_writer->write_raw_data('General');
        $obj_writer->end_element();
        $obj_writer->start_element('c:ptCount');
        $obj_writer->write_attribute('val', (string) $plot_series_values->get_point_count());
        $obj_writer->end_element();
        $data_values = $plot_series_values->get_data_values();
        if (!empty($data_values)) {
            foreach ($data_values as $plot_series_key => $plot_series_value) {
                $obj_writer->start_element('c:pt');
                $obj_writer->write_attribute('idx', $plot_series_key);
                $obj_writer->start_element('c:v');
                $obj_writer->write_raw_data('1');
                $obj_writer->end_element();
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->start_element('c:bubble3D');
        $obj_writer->write_attribute('val', $plot_series_values->get_bubble3d() ? '1' : '0');
        $obj_writer->end_element();
    }
    /**
     * Write Layout.
     */
    private function write_layout(Xml_Writer $obj_writer, ?Layout $layout = null): void
    {
        $obj_writer->start_element('c:layout');
        if ($layout !== null) {
            $obj_writer->start_element('c:manualLayout');
            $layout_target = $layout->get_layout_target();
            if ($layout_target !== null) {
                $obj_writer->start_element('c:layoutTarget');
                $obj_writer->write_attribute('val', $layout_target);
                $obj_writer->end_element();
            }
            $x_mode = $layout->get_x_mode();
            if ($x_mode !== null) {
                $obj_writer->start_element('c:xMode');
                $obj_writer->write_attribute('val', $x_mode);
                $obj_writer->end_element();
            }
            $y_mode = $layout->get_y_mode();
            if ($y_mode !== null) {
                $obj_writer->start_element('c:yMode');
                $obj_writer->write_attribute('val', $y_mode);
                $obj_writer->end_element();
            }
            $x = $layout->get_x_position();
            if ($x !== null) {
                $obj_writer->start_element('c:x');
                $obj_writer->write_attribute('val', "{$x}");
                $obj_writer->end_element();
            }
            $y = $layout->get_y_position();
            if ($y !== null) {
                $obj_writer->start_element('c:y');
                $obj_writer->write_attribute('val', "{$y}");
                $obj_writer->end_element();
            }
            $w = $layout->get_width();
            if ($w !== null) {
                $obj_writer->start_element('c:w');
                $obj_writer->write_attribute('val', "{$w}");
                $obj_writer->end_element();
            }
            $h = $layout->get_height();
            if ($h !== null) {
                $obj_writer->start_element('c:h');
                $obj_writer->write_attribute('val', "{$h}");
                $obj_writer->end_element();
            }
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
    }
    /**
     * Write Alternate Content block.
     */
    private function write_alternate_content(Xml_Writer $obj_writer): void
    {
        $obj_writer->start_element('mc:AlternateContent');
        $obj_writer->write_attribute('xmlns:mc', Namespaces::COMPATIBILITY);
        $obj_writer->start_element('mc:Choice');
        $obj_writer->write_attribute('Requires', 'c14');
        $obj_writer->write_attribute('xmlns:c14', Namespaces::CHART_ALTERNATE);
        $obj_writer->start_element('c14:style');
        $obj_writer->write_attribute('val', '102');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->start_element('mc:Fallback');
        $obj_writer->start_element('c:style');
        $obj_writer->write_attribute('val', '2');
        $obj_writer->end_element();
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Write Printer Settings.
     */
    private function write_print_settings(Xml_Writer $obj_writer): void
    {
        $obj_writer->start_element('c:printSettings');
        $obj_writer->start_element('c:headerFooter');
        $obj_writer->end_element();
        $obj_writer->start_element('c:pageMargins');
        $obj_writer->write_attribute('footer', '0.3');
        $obj_writer->write_attribute('header', '0.3');
        $obj_writer->write_attribute('r', '0.7');
        $obj_writer->write_attribute('l', '0.7');
        $obj_writer->write_attribute('t', '0.75');
        $obj_writer->write_attribute('b', '0.75');
        $obj_writer->end_element();
        $obj_writer->start_element('c:pageSetup');
        $obj_writer->write_attribute('orientation', 'portrait');
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    private function write_effects(Xml_Writer $obj_writer, Properties $y_axis): void
    {
        if (!empty($y_axis->get_soft_edges_size()) || !empty($y_axis->get_shadow_property('effect')) || !empty($y_axis->get_glow_property('size'))) {
            $obj_writer->start_element('a:effectLst');
            $this->write_glow($obj_writer, $y_axis);
            $this->write_shadow($obj_writer, $y_axis);
            $this->write_soft_edge($obj_writer, $y_axis);
            $obj_writer->end_element();
            // effectLst
        }
    }
    private function write_shadow(Xml_Writer $obj_writer, Properties $x_axis): void
    {
        if (empty($x_axis->get_shadow_property('effect'))) {
            return;
        }
        /** @var non-falsy-string $effect */
        $effect = $x_axis->get_shadow_property('effect');
        $obj_writer->start_element("a:{$effect}");
        if (is_numeric($x_axis->get_shadow_property('blur'))) {
            $obj_writer->write_attribute('blurRad', Properties::points_to_xml((float) $x_axis->get_shadow_property('blur')));
        }
        if (is_numeric($x_axis->get_shadow_property('distance'))) {
            $obj_writer->write_attribute('dist', Properties::points_to_xml((float) $x_axis->get_shadow_property('distance')));
        }
        if (is_numeric($x_axis->get_shadow_property('direction'))) {
            $obj_writer->write_attribute('dir', Properties::angle_to_xml((float) $x_axis->get_shadow_property('direction')));
        }
        $algn = $x_axis->get_shadow_property('algn');
        if (is_string($algn) && $algn !== '') {
            $obj_writer->write_attribute('algn', $algn);
        }
        foreach (['sx', 'sy'] as $size_type) {
            $size_value = $x_axis->get_shadow_property(['size', $size_type]);
            if (is_numeric($size_value)) {
                $obj_writer->write_attribute($size_type, Properties::tenth_of_percent_to_xml((float) $size_value));
            }
        }
        foreach (['kx', 'ky'] as $size_type) {
            $size_value = $x_axis->get_shadow_property(['size', $size_type]);
            if (is_numeric($size_value)) {
                $temp = (float) Properties::angle_to_xml((float) $size_value);
                if (abs($temp) <= Properties::MAX_SKEW_ANGLE_XML) {
                    // This corresponds to values between -90 amd +90 EXCLUSIVE.
                    // 90 and anything higher is invalid.
                    $obj_writer->write_attribute($size_type, Properties::angle_to_xml((float) $size_value));
                }
            }
        }
        $rot_with_shape = $x_axis->get_shadow_property('rotWithShape');
        if (is_numeric($rot_with_shape)) {
            $obj_writer->write_attribute('rotWithShape', (string) (int) $rot_with_shape);
        }
        $this->write_color($obj_writer, $x_axis->get_shadow_color_object(), false);
        $obj_writer->end_element();
    }
    private function write_glow(Xml_Writer $obj_writer, Properties $y_axis): void
    {
        $size = $y_axis->get_glow_property('size');
        if (empty($size)) {
            return;
        }
        $obj_writer->start_element('a:glow');
        $obj_writer->write_attribute('rad', Properties::points_to_xml((float) $size));
        $this->write_color($obj_writer, $y_axis->get_glow_color_object(), false);
        $obj_writer->end_element();
        // glow
    }
    private function write_soft_edge(Xml_Writer $obj_writer, Properties $y_axis): void
    {
        $soft_edge_size = $y_axis->get_soft_edges_size();
        if (empty($soft_edge_size)) {
            return;
        }
        $obj_writer->start_element('a:softEdge');
        $obj_writer->write_attribute('rad', Properties::points_to_xml($soft_edge_size));
        $obj_writer->end_element();
        //end softEdge
    }
    private function write_line_styles(Xml_Writer $obj_writer, Properties $gridlines, bool $no_fill = false): void
    {
        $obj_writer->start_element('a:ln');
        $width_temp = $gridlines->get_line_style_property('width');
        if (is_numeric($width_temp)) {
            $obj_writer->write_attribute('w', Properties::points_to_xml((float) $width_temp));
        }
        $this->write_not_empty($obj_writer, 'cap', $gridlines->get_line_style_property('cap'));
        $this->write_not_empty($obj_writer, 'cmpd', $gridlines->get_line_style_property('compound'));
        if ($no_fill) {
            $obj_writer->start_element('a:noFill');
            $obj_writer->end_element();
        } else {
            $this->write_color($obj_writer, $gridlines->get_line_color());
        }
        $dash = $gridlines->get_line_style_property('dash');
        if (!empty($dash)) {
            $obj_writer->start_element('a:prstDash');
            $this->write_not_empty($obj_writer, 'val', $dash);
            $obj_writer->end_element();
        }
        if ($gridlines->get_line_style_property('join') === 'miter') {
            $obj_writer->start_element('a:miter');
            $obj_writer->write_attribute('lim', '800000');
            $obj_writer->end_element();
        } elseif ($gridlines->get_line_style_property('join') === 'bevel') {
            $obj_writer->start_element('a:bevel');
            $obj_writer->end_element();
        }
        if ($gridlines->get_line_style_property(['arrow', 'head', 'type'])) {
            $obj_writer->start_element('a:headEnd');
            $obj_writer->write_attribute('type', $gridlines->get_line_style_property(['arrow', 'head', 'type']));
            $this->write_not_empty($obj_writer, 'w', $gridlines->get_line_style_arrow_width('head'));
            $this->write_not_empty($obj_writer, 'len', $gridlines->get_line_style_arrow_length('head'));
            $obj_writer->end_element();
        }
        if ($gridlines->get_line_style_property(['arrow', 'end', 'type'])) {
            $obj_writer->start_element('a:tailEnd');
            $obj_writer->write_attribute('type', $gridlines->get_line_style_property(['arrow', 'end', 'type']));
            $this->write_not_empty($obj_writer, 'w', $gridlines->get_line_style_arrow_width('end'));
            $this->write_not_empty($obj_writer, 'len', $gridlines->get_line_style_arrow_length('end'));
            $obj_writer->end_element();
        }
        $obj_writer->end_element();
        //end ln
    }
    private function write_not_empty(Xml_Writer $obj_writer, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $obj_writer->write_attribute($name, $value);
        }
    }
    private function write_color(Xml_Writer $obj_writer, Chart_Color $chart_color, bool $solid_fill = true): void
    {
        $type = $chart_color->get_type();
        $value = $chart_color->get_value();
        if (!empty($type) && !empty($value)) {
            if ($solid_fill) {
                $obj_writer->start_element('a:solidFill');
            }
            $obj_writer->start_element("a:{$type}");
            $obj_writer->write_attribute('val', $value);
            $alpha = $chart_color->get_alpha();
            if (is_numeric($alpha)) {
                $obj_writer->start_element('a:alpha');
                $obj_writer->write_attribute('val', Chart_Color::alpha_to_xml($alpha));
                $obj_writer->end_element();
                // a:alpha
            }
            $brightness = $chart_color->get_brightness();
            if (is_numeric($brightness)) {
                $lum_off = 100 - $brightness;
                $obj_writer->start_element('a:lumMod');
                $obj_writer->write_attribute('val', Chart_Color::alpha_to_xml($brightness));
                $obj_writer->end_element();
                // a:lumMod
                $obj_writer->start_element('a:lumOff');
                $obj_writer->write_attribute('val', Chart_Color::alpha_to_xml($lum_off));
                $obj_writer->end_element();
                // a:lumOff
            }
            $obj_writer->end_element();
            //a:srgbClr/schemeClr/prstClr
            if ($solid_fill) {
                $obj_writer->end_element();
                //a:solidFill
            }
        }
    }
    private function write_label_font(Xml_Writer $obj_writer, ?Font $label_font, ?Properties $axis_text): void
    {
        $obj_writer->start_element('a:p');
        $obj_writer->start_element('a:pPr');
        $obj_writer->start_element('a:defRPr');
        if ($label_font !== null) {
            $font_size = $label_font->get_size();
            if (is_numeric($font_size)) {
                $font_size *= $font_size < 100 ? 100 : 1;
                $obj_writer->write_attribute('sz', (string) $font_size);
            }
            if ($label_font->get_bold() === true) {
                $obj_writer->write_attribute('b', '1');
            }
            if ($label_font->get_italic() === true) {
                $obj_writer->write_attribute('i', '1');
            }
            $cap = $label_font->get_cap();
            if ($cap !== null) {
                $obj_writer->write_attribute('cap', $cap);
            }
            $font_color = $label_font->get_chart_color();
            if ($font_color !== null) {
                $this->write_color($obj_writer, $font_color);
            }
        }
        if ($axis_text !== null) {
            $this->write_effects($obj_writer, $axis_text);
        }
        if ($label_font !== null) {
            $default_font = $label_font->get_name() !== Font::DEFAULT_FONT_NAME ? $label_font->get_name() : '';
            $font_name = $label_font->get_latin() ?: $default_font;
            if (!empty($font_name)) {
                $obj_writer->start_element('a:latin');
                $obj_writer->write_attribute('typeface', $font_name);
                $obj_writer->end_element();
            }
            $font_name = $label_font->get_east_asian() ?: $default_font;
            if (!empty($font_name)) {
                $obj_writer->start_element('a:eastAsian');
                $obj_writer->write_attribute('typeface', $font_name);
                $obj_writer->end_element();
            }
            $font_name = $label_font->get_complex_script() ?: $default_font;
            if (!empty($font_name)) {
                $obj_writer->start_element('a:complexScript');
                $obj_writer->write_attribute('typeface', $font_name);
                $obj_writer->end_element();
            }
        }
        $obj_writer->end_element();
        // a:defRPr
        $obj_writer->end_element();
        // a:pPr
        $obj_writer->end_element();
        // a:p
    }
}