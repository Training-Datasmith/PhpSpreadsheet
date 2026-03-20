<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Chart\Axis;
use Php_Office\Php_Spreadsheet\Chart\Axis_Text;
use Php_Office\Php_Spreadsheet\Chart\Chart_Color;
use Php_Office\Php_Spreadsheet\Chart\Data_Series;
use Php_Office\Php_Spreadsheet\Chart\Data_Series_Values;
use Php_Office\Php_Spreadsheet\Chart\Grid_Lines;
use Php_Office\Php_Spreadsheet\Chart\Layout;
use Php_Office\Php_Spreadsheet\Chart\Legend;
use Php_Office\Php_Spreadsheet\Chart\Plot_Area;
use Php_Office\Php_Spreadsheet\Chart\Properties as ChartProperties;
use Php_Office\Php_Spreadsheet\Chart\Title;
use Php_Office\Php_Spreadsheet\Chart\Trend_Line;
use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Style\Font;
use Simple_Xml_Element;
class Chart
{
    public function __construct(private readonly string $c_namespace = Namespaces::CHART, private readonly string $a_namespace = Namespaces::DRAWINGML)
    {
    }
    private static function get_attribute_string(Simple_Xml_Element $component, string $name): string|null
    {
        $attributes = $component->attributes();
        if (@isset($attributes[$name])) {
            return (string) $attributes[$name];
        }
        return null;
    }
    private static function get_attribute_integer(Simple_Xml_Element $component, string $name): int|null
    {
        $attributes = $component->attributes();
        if (@isset($attributes[$name])) {
            return (int) $attributes[$name];
        }
        return null;
    }
    private static function get_attribute_boolean(Simple_Xml_Element $component, string $name): bool|null
    {
        $attributes = $component->attributes();
        if (@isset($attributes[$name])) {
            $value = (string) $attributes[$name];
            return $value === 'true' || $value === '1';
        }
        return null;
    }
    private static function get_attribute_float(Simple_Xml_Element $component, string $name): float|null
    {
        $attributes = $component->attributes();
        if (@isset($attributes[$name])) {
            return (float) $attributes[$name];
        }
        return null;
    }
    public function read_chart(Simple_Xml_Element $chart_elements, string $chart_name): \Php_Office\Php_Spreadsheet\Chart\Chart
    {
        $chart_elements_c = $chart_elements->children($this->c_namespace);
        $xaxis_label = $yaxis_label = $legend = $title = null;
        $disp_blanks_as = null;
        $plot_vis_only = false;
        $plot_area = null;
        $rot_x = $rot_y = $r_ang_ax = $perspective = null;
        $x_axis = new Axis();
        $y_axis = new Axis();
        $auto_title_deleted = null;
        $chart_no_fill = false;
        $chart_border_lines = null;
        $chart_fill_color = null;
        $gradient_array = [];
        $gradient_lin = null;
        $rounded_corners = false;
        $gap_width = null;
        $use_up_bars = null;
        $use_down_bars = null;
        $no_border = false;
        foreach ($chart_elements_c as $chart_element_key => $chart_element) {
            switch ($chart_element_key) {
                case 'spPr':
                    $children = $chart_elements_c->sp_pr->children($this->a_namespace);
                    if (isset($children->no_fill)) {
                        $chart_no_fill = true;
                    }
                    if (isset($children->solid_fill)) {
                        $chart_fill_color = $this->read_color($children->solid_fill);
                    }
                    if (isset($children->ln)) {
                        $chart_border_lines = new Grid_Lines();
                        $this->read_line_style($chart_elements_c, $chart_border_lines);
                        if (isset($children->ln->no_fill)) {
                            $no_border = true;
                        }
                    }
                    break;
                case 'roundedCorners':
                    /** @var bool $roundedCorners */
                    $rounded_corners = self::get_attribute_boolean($chart_elements_c->rounded_corners, 'val');
                    break;
                case 'chart':
                    foreach ($chart_element as $chart_details_key => $chart_details) {
                        $chart_details = Xlsx::test_simple_xml($chart_details);
                        switch ($chart_details_key) {
                            case 'autoTitleDeleted':
                                /** @var bool $autoTitleDeleted */
                                $auto_title_deleted = self::get_attribute_boolean($chart_elements_c->chart->auto_title_deleted, 'val');
                                break;
                            case 'view3D':
                                $rot_x = self::get_attribute_integer($chart_details->rot_x, 'val');
                                $rot_y = self::get_attribute_integer($chart_details->rot_y, 'val');
                                $r_ang_ax = self::get_attribute_integer($chart_details->r_ang_ax, 'val');
                                $perspective = self::get_attribute_integer($chart_details->perspective, 'val');
                                break;
                            case 'plotArea':
                                $plot_area_layout = $xaxis_label = $yaxis_label = null;
                                $plot_series = $plot_attributes = [];
                                $cat_ax_read = false;
                                $plot_no_fill = false;
                                foreach ($chart_details as $chart_detail_key => $chart_detail) {
                                    $chart_detail = Xlsx::test_simple_xml($chart_detail);
                                    switch ($chart_detail_key) {
                                        case 'spPr':
                                            $possible_no_fill = $chart_details->sp_pr->children($this->a_namespace);
                                            if (isset($possible_no_fill->no_fill)) {
                                                $plot_no_fill = true;
                                            }
                                            if (isset($possible_no_fill->grad_fill->gs_lst)) {
                                                foreach ($possible_no_fill->grad_fill->gs_lst->gs as $gradient) {
                                                    $gradient = Xlsx::test_simple_xml($gradient);
                                                    /** @var float $pos */
                                                    $pos = self::get_attribute_float($gradient, 'pos');
                                                    $gradient_array[] = [$pos / Chart_Properties::PERCENTAGE_MULTIPLIER, new Chart_Color($this->read_color($gradient))];
                                                }
                                            }
                                            if (isset($possible_no_fill->grad_fill->lin)) {
                                                $gradient_lin = Chart_Properties::xml_to_angle((string) self::get_attribute_string($possible_no_fill->grad_fill->lin, 'ang'));
                                            }
                                            break;
                                        case 'layout':
                                            $plot_area_layout = $this->chart_layout_details($chart_detail);
                                            break;
                                        case Axis::AXIS_TYPE_CATEGORY:
                                        case Axis::AXIS_TYPE_DATE:
                                            $cat_ax_read = true;
                                            if (isset($chart_detail->title)) {
                                                $xaxis_label = $this->chart_title($chart_detail->title->children($this->c_namespace));
                                            }
                                            $x_axis->set_axis_type($chart_detail_key);
                                            $this->read_effects($chart_detail, $x_axis);
                                            $this->read_line_style($chart_detail, $x_axis);
                                            if (isset($chart_detail->sp_pr)) {
                                                $sppr = $chart_detail->sp_pr->children($this->a_namespace);
                                                if (isset($sppr->solid_fill)) {
                                                    $axis_color_array = $this->read_color($sppr->solid_fill);
                                                    $x_axis->set_fill_parameters($axis_color_array['value'], $axis_color_array['alpha'], $axis_color_array['type']);
                                                }
                                                if (isset($chart_detail->sp_pr->ln->no_fill)) {
                                                    $x_axis->set_no_fill(true);
                                                }
                                            }
                                            if (isset($chart_detail->major_gridlines)) {
                                                $major_gridlines = new Grid_Lines();
                                                if (isset($chart_detail->major_gridlines->sp_pr)) {
                                                    $this->read_effects($chart_detail->major_gridlines, $major_gridlines);
                                                    $this->read_line_style($chart_detail->major_gridlines, $major_gridlines);
                                                }
                                                $x_axis->set_major_gridlines($major_gridlines);
                                            }
                                            if (isset($chart_detail->minor_gridlines)) {
                                                $minor_gridlines = new Grid_Lines();
                                                $minor_gridlines->activate_object();
                                                if (isset($chart_detail->minor_gridlines->sp_pr)) {
                                                    $this->read_effects($chart_detail->minor_gridlines, $minor_gridlines);
                                                    $this->read_line_style($chart_detail->minor_gridlines, $minor_gridlines);
                                                }
                                                $x_axis->set_minor_gridlines($minor_gridlines);
                                            }
                                            $this->set_axis_properties($chart_detail, $x_axis);
                                            break;
                                        case Axis::AXIS_TYPE_VALUE:
                                            $which_axis = null;
                                            $ax_pos = null;
                                            if (isset($chart_detail->ax_pos)) {
                                                $ax_pos = self::get_attribute_string($chart_detail->ax_pos, 'val');
                                            }
                                            if ($cat_ax_read) {
                                                $which_axis = $y_axis;
                                                $y_axis->set_axis_type($chart_detail_key);
                                            } elseif (!empty($ax_pos)) {
                                                switch ($ax_pos) {
                                                    case 't':
                                                    case 'b':
                                                        $which_axis = $x_axis;
                                                        $x_axis->set_axis_type($chart_detail_key);
                                                        break;
                                                    case 'r':
                                                    case 'l':
                                                        $which_axis = $y_axis;
                                                        $y_axis->set_axis_type($chart_detail_key);
                                                        break;
                                                }
                                            }
                                            if (isset($chart_detail->title)) {
                                                $axis_label = $this->chart_title($chart_detail->title->children($this->c_namespace));
                                                switch ($ax_pos) {
                                                    case 't':
                                                    case 'b':
                                                        $xaxis_label = $axis_label;
                                                        break;
                                                    case 'r':
                                                    case 'l':
                                                        $yaxis_label = $axis_label;
                                                        break;
                                                }
                                            }
                                            $this->read_effects($chart_detail, $which_axis);
                                            $this->read_line_style($chart_detail, $which_axis);
                                            if ($which_axis !== null && isset($chart_detail->sp_pr)) {
                                                $sppr = $chart_detail->sp_pr->children($this->a_namespace);
                                                if (isset($sppr->solid_fill)) {
                                                    $axis_color_array = $this->read_color($sppr->solid_fill);
                                                    $which_axis->set_fill_parameters($axis_color_array['value'], $axis_color_array['alpha'], $axis_color_array['type']);
                                                }
                                                if (isset($sppr->ln->no_fill)) {
                                                    $which_axis->set_no_fill(true);
                                                }
                                            }
                                            if ($which_axis !== null && isset($chart_detail->major_gridlines)) {
                                                $major_gridlines = new Grid_Lines();
                                                if (isset($chart_detail->major_gridlines->sp_pr)) {
                                                    $this->read_effects($chart_detail->major_gridlines, $major_gridlines);
                                                    $this->read_line_style($chart_detail->major_gridlines, $major_gridlines);
                                                }
                                                $which_axis->set_major_gridlines($major_gridlines);
                                            }
                                            if ($which_axis !== null && isset($chart_detail->minor_gridlines)) {
                                                $minor_gridlines = new Grid_Lines();
                                                $minor_gridlines->activate_object();
                                                if (isset($chart_detail->minor_gridlines->sp_pr)) {
                                                    $this->read_effects($chart_detail->minor_gridlines, $minor_gridlines);
                                                    $this->read_line_style($chart_detail->minor_gridlines, $minor_gridlines);
                                                }
                                                $which_axis->set_minor_gridlines($minor_gridlines);
                                            }
                                            $this->set_axis_properties($chart_detail, $which_axis);
                                            break;
                                        case 'barChart':
                                        case 'bar3DChart':
                                            $bar_direction = self::get_attribute_string($chart_detail->bar_dir, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_direction("{$bar_direction}");
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'lineChart':
                                        case 'line3DChart':
                                        case 'areaChart':
                                        case 'area3DChart':
                                            $plot_series[] = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'doughnutChart':
                                        case 'pieChart':
                                        case 'pie3DChart':
                                            $explosion = self::get_attribute_string($chart_detail->ser->explosion, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_style("{$explosion}");
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'scatterChart':
                                            $scatter_style = self::get_attribute_string($chart_detail->scatter_style, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_style($scatter_style);
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'bubbleChart':
                                            $bubble_scale = self::get_attribute_integer($chart_detail->bubble_scale, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_style("{$bubble_scale}");
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'radarChart':
                                            $radar_style = self::get_attribute_string($chart_detail->radar_style, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_style($radar_style);
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'surfaceChart':
                                        case 'surface3DChart':
                                            $wire_frame = self::get_attribute_boolean($chart_detail->wireframe, 'val');
                                            $plot_ser = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            $plot_ser->set_plot_style("{$wire_frame}");
                                            $plot_series[] = $plot_ser;
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                        case 'stockChart':
                                            $plot_series[] = $this->chart_data_series($chart_detail, $chart_detail_key);
                                            if (isset($chart_detail->up_down_bars->gap_width)) {
                                                $gap_width = self::get_attribute_integer($chart_detail->up_down_bars->gap_width, 'val');
                                            }
                                            if (isset($chart_detail->up_down_bars->up_bars)) {
                                                $use_up_bars = true;
                                            }
                                            if (isset($chart_detail->up_down_bars->down_bars)) {
                                                $use_down_bars = true;
                                            }
                                            $plot_attributes = $this->read_chart_attributes($chart_detail);
                                            break;
                                    }
                                }
                                if ($plot_area_layout == null) {
                                    $plot_area_layout = new Layout();
                                }
                                $plot_area = new Plot_Area($plot_area_layout, $plot_series);
                                $this->set_chart_attributes($plot_area_layout, $plot_attributes);
                                if ($plot_no_fill) {
                                    $plot_area->set_no_fill(true);
                                }
                                if (!empty($gradient_array)) {
                                    $plot_area->set_gradient_fill_properties($gradient_array, $gradient_lin);
                                }
                                if (is_int($gap_width)) {
                                    $plot_area->set_gap_width($gap_width);
                                }
                                if ($use_up_bars === true) {
                                    $plot_area->set_use_up_bars(true);
                                }
                                if ($use_down_bars === true) {
                                    $plot_area->set_use_down_bars(true);
                                }
                                break;
                            case 'plotVisOnly':
                                $plot_vis_only = (bool) self::get_attribute_string($chart_details, 'val');
                                break;
                            case 'dispBlanksAs':
                                $disp_blanks_as = self::get_attribute_string($chart_details, 'val');
                                break;
                            case 'title':
                                $title = $this->chart_title($chart_details);
                                break;
                            case 'legend':
                                $legend_pos = 'r';
                                $legend_layout = null;
                                $legend_overlay = false;
                                $legend_border_lines = null;
                                $legend_fill_color = null;
                                $legend_text = null;
                                $add_legend_text = false;
                                foreach ($chart_details as $chart_detail_key => $chart_detail) {
                                    $chart_detail = Xlsx::test_simple_xml($chart_detail);
                                    switch ($chart_detail_key) {
                                        case 'legendPos':
                                            $legend_pos = self::get_attribute_string($chart_detail, 'val');
                                            break;
                                        case 'overlay':
                                            $legend_overlay = self::get_attribute_boolean($chart_detail, 'val');
                                            break;
                                        case 'layout':
                                            $legend_layout = $this->chart_layout_details($chart_detail);
                                            break;
                                        case 'spPr':
                                            $children = $chart_details->sp_pr->children($this->a_namespace);
                                            if (isset($children->solid_fill)) {
                                                $legend_fill_color = $this->read_color($children->solid_fill);
                                            }
                                            if (isset($children->ln)) {
                                                $legend_border_lines = new Grid_Lines();
                                                $this->read_line_style($chart_details, $legend_border_lines);
                                            }
                                            break;
                                        case 'txPr':
                                            $children = $chart_details->tx_pr->children($this->a_namespace);
                                            $add_legend_text = false;
                                            $legend_text = new Axis_Text();
                                            if (isset($children->p->p_pr->def_r_pr->solid_fill)) {
                                                $color_array = $this->read_color($children->p->p_pr->def_r_pr->solid_fill);
                                                $legend_text->get_fill_color_object()->set_color_properties_array($color_array);
                                                $add_legend_text = true;
                                            }
                                            if (isset($children->p->p_pr->def_r_pr->effect_lst)) {
                                                $this->read_effects($children->p->p_pr->def_r_pr, $legend_text, false);
                                                $add_legend_text = true;
                                            }
                                            break;
                                    }
                                }
                                $legend = new Legend("{$legend_pos}", $legend_layout, (bool) $legend_overlay);
                                if ($legend_fill_color !== null) {
                                    $legend->get_fill_color()->set_color_properties_array($legend_fill_color);
                                }
                                if ($legend_border_lines !== null) {
                                    $legend->set_border_lines($legend_border_lines);
                                }
                                if ($add_legend_text) {
                                    $legend->set_legend_text($legend_text);
                                }
                                break;
                        }
                    }
            }
        }
        $chart = new \Php_Office\Php_Spreadsheet\Chart\Chart($chart_name, $title, $legend, $plot_area, $plot_vis_only, (string) $disp_blanks_as, $xaxis_label, $yaxis_label, $x_axis, $y_axis);
        if ($chart_no_fill) {
            $chart->set_no_fill(true);
        }
        if ($chart_fill_color !== null) {
            $chart->get_fill_color()->set_color_properties_array($chart_fill_color);
        }
        if ($chart_border_lines !== null) {
            $chart->set_border_lines($chart_border_lines);
        }
        $chart->set_no_border($no_border);
        $chart->set_rounded_corners($rounded_corners);
        if (is_bool($auto_title_deleted)) {
            $chart->set_auto_title_deleted($auto_title_deleted);
        }
        if (is_int($rot_x)) {
            $chart->set_rot_x($rot_x);
        }
        if (is_int($rot_y)) {
            $chart->set_rot_y($rot_y);
        }
        if (is_int($r_ang_ax)) {
            $chart->set_r_ang_ax($r_ang_ax);
        }
        if (is_int($perspective)) {
            $chart->set_perspective($perspective);
        }
        return $chart;
    }
    private function chart_title(Simple_Xml_Element $title_details): Title
    {
        $caption = '';
        $title_layout = null;
        $title_overlay = false;
        $title_formula = null;
        $title_font = null;
        foreach ($title_details as $title_detail_key => $chart_detail) {
            $chart_detail = Xlsx::test_simple_xml($chart_detail);
            switch ($title_detail_key) {
                case 'tx':
                    $caption = [];
                    if (isset($chart_detail->rich)) {
                        $title_details = $chart_detail->rich->children($this->a_namespace);
                        foreach ($title_details as $title_key => $title_detail) {
                            $title_detail = Xlsx::test_simple_xml($title_detail);
                            switch ($title_key) {
                                case 'p':
                                    $title_detail_part = $title_detail->children($this->a_namespace);
                                    $caption[] = $this->parse_rich_text($title_detail_part);
                            }
                        }
                    } elseif (isset($chart_detail->str_ref->str_cache)) {
                        foreach ($chart_detail->str_ref->str_cache->pt as $pt) {
                            if (isset($pt->v)) {
                                $caption[] = (string) $pt->v;
                            }
                        }
                        if (isset($chart_detail->str_ref->f)) {
                            $title_formula = (string) $chart_detail->str_ref->f;
                        }
                    }
                    break;
                case 'overlay':
                    $title_overlay = self::get_attribute_boolean($chart_detail, 'val');
                    break;
                case 'layout':
                    $title_layout = $this->chart_layout_details($chart_detail);
                    break;
                case 'txPr':
                    if (isset($chart_detail->children($this->a_namespace)->p)) {
                        $title_font = $this->parse_font($chart_detail->children($this->a_namespace)->p);
                    }
                    break;
            }
        }
        $title = new Title($caption, $title_layout, (bool) $title_overlay);
        if (!empty($title_formula)) {
            $title->set_cell_reference($title_formula);
        }
        if ($title_font !== null) {
            $title->set_font($title_font);
        }
        return $title;
    }
    private function chart_layout_details(Simple_Xml_Element $chart_detail): ?Layout
    {
        if (!isset($chart_detail->manual_layout)) {
            return null;
        }
        $details = $chart_detail->manual_layout->children($this->c_namespace);
        if ($details === null) {
            return null;
        }
        $layout = [];
        foreach ($details as $detail_key => $detail) {
            $detail = Xlsx::test_simple_xml($detail);
            $layout[$detail_key] = self::get_attribute_string($detail, 'val');
        }
        return new Layout($layout);
    }
    private function chart_data_series(Simple_Xml_Element $chart_detail, string $plot_type): Data_Series
    {
        $multi_series_type = null;
        $smooth_line = false;
        $series_label = $series_category = $series_values = $plot_order = $series_bubbles = [];
        $plot_direction = null;
        $series_detail_set = $chart_detail->children($this->c_namespace);
        foreach ($series_detail_set as $series_detail_key => $series_details) {
            switch ($series_detail_key) {
                case 'grouping':
                    $multi_series_type = self::get_attribute_string($chart_detail->grouping, 'val');
                    break;
                case 'ser':
                    $marker = null;
                    $series_index = '';
                    $fill_color = null;
                    $point_size = null;
                    $no_fill = false;
                    $bubble3D = false;
                    $dpt_colors = [];
                    $marker_fill_color = null;
                    $marker_border_color = null;
                    $line_style = null;
                    $label_layout = null;
                    $trend_lines = [];
                    foreach ($series_details as $series_key => $series_detail) {
                        $series_detail = Xlsx::test_simple_xml($series_detail);
                        switch ($series_key) {
                            case 'idx':
                                $series_index = self::get_attribute_integer($series_detail, 'val');
                                break;
                            case 'order':
                                $series_order = self::get_attribute_integer($series_detail, 'val');
                                if ($series_order !== null) {
                                    $plot_order[$series_index] = $series_order;
                                }
                                break;
                            case 'tx':
                                $temp = $this->chart_data_series_value_set($series_detail);
                                if ($temp !== null) {
                                    $series_label[$series_index] = $temp;
                                }
                                break;
                            case 'spPr':
                                $children = $series_detail->children($this->a_namespace);
                                if (isset($children->ln)) {
                                    $ln = $children->ln;
                                    if (is_countable($ln->no_fill) && count($ln->no_fill) === 1) {
                                        $no_fill = true;
                                    }
                                    $line_style = new Grid_Lines();
                                    $this->read_line_style($series_details, $line_style);
                                }
                                if (isset($children->effect_lst)) {
                                    if ($line_style === null) {
                                        $line_style = new Grid_Lines();
                                    }
                                    $this->read_effects($series_details, $line_style);
                                }
                                if (isset($children->solid_fill)) {
                                    $fill_color = new Chart_Color($this->read_color($children->solid_fill));
                                }
                                break;
                            case 'dPt':
                                $dpt_idx = (int) self::get_attribute_string($series_detail->idx, 'val');
                                if (isset($series_detail->sp_pr)) {
                                    $children = $series_detail->sp_pr->children($this->a_namespace);
                                    if (isset($children->solid_fill)) {
                                        $array_colors = $this->read_color($children->solid_fill);
                                        $dpt_colors[$dpt_idx] = new Chart_Color($array_colors);
                                    }
                                }
                                break;
                            case 'trendline':
                                $trend_line = new Trend_Line();
                                $this->read_line_style($series_detail, $trend_line);
                                $trend_line_type = self::get_attribute_string($series_detail->trendline_type, 'val');
                                $disp_r_sqr = self::get_attribute_boolean($series_detail->disp_r_sqr, 'val');
                                $disp_eq = self::get_attribute_boolean($series_detail->disp_eq, 'val');
                                $order = self::get_attribute_integer($series_detail->order, 'val');
                                $period = self::get_attribute_integer($series_detail->period, 'val');
                                $forward = self::get_attribute_float($series_detail->forward, 'val');
                                $backward = self::get_attribute_float($series_detail->backward, 'val');
                                $intercept = self::get_attribute_float($series_detail->intercept, 'val');
                                $name = (string) $series_detail->name;
                                $trend_line->set_trend_line_properties($trend_line_type, $order, $period, $disp_r_sqr, $disp_eq, $backward, $forward, $intercept, $name);
                                $trend_lines[] = $trend_line;
                                break;
                            case 'marker':
                                $marker = self::get_attribute_string($series_detail->symbol, 'val');
                                $point_size = self::get_attribute_string($series_detail->size, 'val');
                                $point_size = is_numeric($point_size) ? (int) $point_size : null;
                                if (isset($series_detail->sp_pr)) {
                                    $children = $series_detail->sp_pr->children($this->a_namespace);
                                    if (isset($children->solid_fill)) {
                                        $marker_fill_color = $this->read_color($children->solid_fill);
                                    }
                                    if (isset($children->ln->solid_fill)) {
                                        $marker_border_color = $this->read_color($children->ln->solid_fill);
                                    }
                                }
                                break;
                            case 'smooth':
                                $smooth_line = self::get_attribute_boolean($series_detail, 'val') ?? false;
                                break;
                            case 'cat':
                                $temp = $this->chart_data_series_value_set($series_detail);
                                if ($temp !== null) {
                                    $series_category[$series_index] = $temp;
                                }
                                break;
                            case 'val':
                            case 'yVal':
                                $temp = $this->chart_data_series_value_set($series_detail, "{$marker}", $fill_color, "{$point_size}");
                                if ($temp !== null) {
                                    $series_values[$series_index] = $temp;
                                }
                                break;
                            case 'xVal':
                                $temp = $this->chart_data_series_value_set($series_detail, "{$marker}", $fill_color, "{$point_size}");
                                if ($temp !== null) {
                                    $series_category[$series_index] = $temp;
                                }
                                break;
                            case 'bubbleSize':
                                $series_bubble = $this->chart_data_series_value_set($series_detail, "{$marker}", $fill_color, "{$point_size}");
                                if ($series_bubble !== null) {
                                    $series_bubbles[$series_index] = $series_bubble;
                                }
                                break;
                            case 'bubble3D':
                                $bubble3D = self::get_attribute_boolean($series_detail, 'val');
                                break;
                            case 'dLbls':
                                $label_layout = new Layout($this->read_chart_attributes($series_details));
                                break;
                        }
                    }
                    if ($label_layout) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_label_layout($label_layout);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_label_layout($label_layout);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_label_layout($label_layout);
                        }
                    }
                    if ($no_fill) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_scatter_lines(false);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_scatter_lines(false);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_scatter_lines(false);
                        }
                    }
                    if ($line_style !== null) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->copy_line_styles($line_style);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->copy_line_styles($line_style);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->copy_line_styles($line_style);
                        }
                    }
                    if ($bubble3D) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_bubble3d($bubble3D);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_bubble3d($bubble3D);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_bubble3d($bubble3D);
                        }
                    }
                    if (!empty($dpt_colors)) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_fill_color($dpt_colors);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_fill_color($dpt_colors);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_fill_color($dpt_colors);
                        }
                    }
                    if ($marker_fill_color !== null) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->get_marker_fill_color()->set_color_properties_array($marker_fill_color);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->get_marker_fill_color()->set_color_properties_array($marker_fill_color);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->get_marker_fill_color()->set_color_properties_array($marker_fill_color);
                        }
                    }
                    if ($marker_border_color !== null) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->get_marker_border_color()->set_color_properties_array($marker_border_color);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->get_marker_border_color()->set_color_properties_array($marker_border_color);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->get_marker_border_color()->set_color_properties_array($marker_border_color);
                        }
                    }
                    if ($smooth_line) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_smooth_line(true);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_smooth_line(true);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_smooth_line(true);
                        }
                    }
                    if (!empty($trend_lines)) {
                        if (isset($series_label[$series_index])) {
                            $series_label[$series_index]->set_trend_lines($trend_lines);
                        }
                        if (isset($series_category[$series_index])) {
                            $series_category[$series_index]->set_trend_lines($trend_lines);
                        }
                        if (isset($series_values[$series_index])) {
                            $series_values[$series_index]->set_trend_lines($trend_lines);
                        }
                    }
            }
        }
        $series = new Data_Series($plot_type, $multi_series_type, $plot_order, $series_label, $series_category, $series_values, $plot_direction, $smooth_line);
        $series->set_plot_bubble_sizes($series_bubbles);
        return $series;
    }
    private function chart_data_series_value_set(Simple_Xml_Element $series_detail, ?string $marker = null, ?Chart_Color $fill_color = null, ?string $point_size = null): ?Data_Series_Values
    {
        if (isset($series_detail->str_ref)) {
            $series_source = (string) $series_detail->str_ref->f;
            $series_values = new Data_Series_Values(Data_Series_Values::DATASERIES_TYPE_STRING, $series_source, null, 0, null, $marker, $fill_color, "{$point_size}");
            if (isset($series_detail->str_ref->str_cache)) {
                /** @var array{formatCode: string, dataValues: mixed[]} */
                $series_data = $this->chart_data_series_values($series_detail->str_ref->str_cache->children($this->c_namespace), 's');
                $series_values->set_format_code($series_data['formatCode'])->set_data_values($series_data['dataValues']);
            }
            return $series_values;
        }
        if (isset($series_detail->num_ref)) {
            $series_source = (string) $series_detail->num_ref->f;
            $series_values = new Data_Series_Values(Data_Series_Values::DATASERIES_TYPE_NUMBER, $series_source, null, 0, null, $marker, $fill_color, "{$point_size}");
            if (isset($series_detail->num_ref->num_cache)) {
                /** @var array{formatCode: string, dataValues: mixed[]} */
                $series_data = $this->chart_data_series_values($series_detail->num_ref->num_cache->children($this->c_namespace));
                $series_values->set_format_code($series_data['formatCode'])->set_data_values($series_data['dataValues']);
            }
            return $series_values;
        }
        if (isset($series_detail->multi_lvl_str_ref)) {
            $series_source = (string) $series_detail->multi_lvl_str_ref->f;
            $series_values = new Data_Series_Values(Data_Series_Values::DATASERIES_TYPE_STRING, $series_source, null, 0, null, $marker, $fill_color, "{$point_size}");
            if (isset($series_detail->multi_lvl_str_ref->multi_lvl_str_cache)) {
                /** @var array{formatCode: string, dataValues: mixed[]} */
                $series_data = $this->chart_data_series_values_multi_level($series_detail->multi_lvl_str_ref->multi_lvl_str_cache->children($this->c_namespace), 's');
                $series_values->set_format_code($series_data['formatCode'])->set_data_values($series_data['dataValues']);
            }
            return $series_values;
        }
        if (isset($series_detail->multi_lvl_num_ref)) {
            $series_source = (string) $series_detail->multi_lvl_num_ref->f;
            $series_values = new Data_Series_Values(Data_Series_Values::DATASERIES_TYPE_STRING, $series_source, null, 0, null, $marker, $fill_color, "{$point_size}");
            if (isset($series_detail->multi_lvl_num_ref->multi_lvl_num_cache)) {
                /** @var array{formatCode: string, dataValues: mixed[]} */
                $series_data = $this->chart_data_series_values_multi_level($series_detail->multi_lvl_num_ref->multi_lvl_num_cache->children($this->c_namespace), 's');
                $series_values->set_format_code($series_data['formatCode'])->set_data_values($series_data['dataValues']);
            }
            return $series_values;
        }
        if (isset($series_detail->v)) {
            return new Data_Series_Values(Data_Series_Values::DATASERIES_TYPE_STRING, null, null, 1, [(string) $series_detail->v]);
        }
        return null;
    }
    /** @return mixed[] */
    private function chart_data_series_values(Simple_Xml_Element $series_value_set, string $data_type = 'n'): array
    {
        $series_val = [];
        $format_code = '';
        $point_count = 0;
        foreach ($series_value_set as $series_value_idx => $series_value) {
            $series_value = Xlsx::test_simple_xml($series_value);
            switch ($series_value_idx) {
                case 'ptCount':
                    $point_count = self::get_attribute_integer($series_value, 'val');
                    break;
                case 'formatCode':
                    $format_code = (string) $series_value;
                    break;
                case 'pt':
                    $point_val = self::get_attribute_integer($series_value, 'idx');
                    if ($data_type == 's') {
                        $series_val[$point_val] = (string) $series_value->v;
                    } elseif ((string) $series_value->v === Excel_Error::NA()) {
                        $series_val[$point_val] = null;
                    } else {
                        $series_val[$point_val] = (float) $series_value->v;
                    }
                    break;
            }
        }
        return ['formatCode' => $format_code, 'pointCount' => $point_count, 'dataValues' => $series_val];
    }
    /** @return mixed[] */
    private function chart_data_series_values_multi_level(Simple_Xml_Element $series_value_set, string $data_type = 'n'): array
    {
        $series_val = [];
        $format_code = '';
        $point_count = 0;
        foreach ($series_value_set->lvl as $series_level) {
            foreach ($series_level as $series_value_idx => $series_value) {
                $series_value = Xlsx::test_simple_xml($series_value);
                switch ($series_value_idx) {
                    case 'ptCount':
                        $point_count = self::get_attribute_integer($series_value, 'val');
                        break;
                    case 'formatCode':
                        $format_code = (string) $series_value;
                        break;
                    case 'pt':
                        $point_val = self::get_attribute_integer($series_value, 'idx');
                        if ($data_type == 's') {
                            $series_val[$point_val][] = (string) $series_value->v;
                        } elseif ((string) $series_value->v === Excel_Error::NA()) {
                            $series_val[$point_val] = null;
                        } else {
                            $series_val[$point_val][] = (float) $series_value->v;
                        }
                        break;
                }
            }
        }
        return ['formatCode' => $format_code, 'pointCount' => $point_count, 'dataValues' => $series_val];
    }
    private function parse_rich_text(Simple_Xml_Element $title_detail_part): Rich_Text
    {
        $value = new Rich_Text();
        $default_font_size = null;
        $default_bold = null;
        $default_italic = null;
        $default_underscore = null;
        $default_strikethrough = null;
        $default_baseline = null;
        $default_font_name = null;
        $default_latin = null;
        $default_east_asian = null;
        $default_complex_script = null;
        $default_font_color = null;
        if (isset($title_detail_part->p_pr->def_r_pr)) {
            $default_font_size = self::get_attribute_integer($title_detail_part->p_pr->def_r_pr, 'sz');
            $default_bold = self::get_attribute_boolean($title_detail_part->p_pr->def_r_pr, 'b');
            $default_italic = self::get_attribute_boolean($title_detail_part->p_pr->def_r_pr, 'i');
            $default_underscore = self::get_attribute_string($title_detail_part->p_pr->def_r_pr, 'u');
            $default_strikethrough = self::get_attribute_string($title_detail_part->p_pr->def_r_pr, 'strike');
            $default_baseline = self::get_attribute_integer($title_detail_part->p_pr->def_r_pr, 'baseline');
            if (isset($title_detail_part->def_r_pr->r_font['val'])) {
                $default_font_name = (string) $title_detail_part->def_r_pr->r_font['val'];
            }
            if (isset($title_detail_part->p_pr->def_r_pr->latin)) {
                $default_latin = self::get_attribute_string($title_detail_part->p_pr->def_r_pr->latin, 'typeface');
            }
            if (isset($title_detail_part->p_pr->def_r_pr->ea)) {
                $default_east_asian = self::get_attribute_string($title_detail_part->p_pr->def_r_pr->ea, 'typeface');
            }
            if (isset($title_detail_part->p_pr->def_r_pr->cs)) {
                $default_complex_script = self::get_attribute_string($title_detail_part->p_pr->def_r_pr->cs, 'typeface');
            }
            if (isset($title_detail_part->p_pr->def_r_pr->solid_fill)) {
                $default_font_color = $this->read_color($title_detail_part->p_pr->def_r_pr->solid_fill);
            }
        }
        foreach ($title_detail_part as $title_detail_element_key => $title_detail_element) {
            if ($title_detail_element_key !== 'r') {
                continue;
            }
            if (!isset($title_detail_element->t)) {
                continue;
            }
            $obj_text = $value->create_text_run((string) $title_detail_element->t);
            if ($obj_text->get_font() === null) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }
            $font_size = null;
            $bold = null;
            $italic = null;
            $underscore = null;
            $strikethrough = null;
            $baseline = null;
            $font_name = null;
            $latin_name = null;
            $east_asian = null;
            $complex_script = null;
            $font_color = null;
            $underline_color = null;
            if (isset($title_detail_element->r_pr)) {
                // not used now, not sure it ever was, grandfathering
                if (isset($title_detail_element->r_pr->r_font['val'])) {
                    // @codeCoverageIgnoreStart
                    $font_name = (string) $title_detail_element->r_pr->r_font['val'];
                    // @codeCoverageIgnoreEnd
                }
                if (isset($title_detail_element->r_pr->latin)) {
                    $latin_name = self::get_attribute_string($title_detail_element->r_pr->latin, 'typeface');
                }
                if (isset($title_detail_element->r_pr->ea)) {
                    $east_asian = self::get_attribute_string($title_detail_element->r_pr->ea, 'typeface');
                }
                if (isset($title_detail_element->r_pr->cs)) {
                    $complex_script = self::get_attribute_string($title_detail_element->r_pr->cs, 'typeface');
                }
                $font_size = self::get_attribute_integer($title_detail_element->r_pr, 'sz');
                // not used now, not sure it ever was, grandfathering
                if (isset($title_detail_element->r_pr->solid_fill)) {
                    $font_color = $this->read_color($title_detail_element->r_pr->solid_fill);
                }
                $bold = self::get_attribute_boolean($title_detail_element->r_pr, 'b');
                $italic = self::get_attribute_boolean($title_detail_element->r_pr, 'i');
                $baseline = self::get_attribute_integer($title_detail_element->r_pr, 'baseline');
                $underscore = self::get_attribute_string($title_detail_element->r_pr, 'u');
                if (isset($title_detail_element->r_pr->u_fill->solid_fill)) {
                    $underline_color = $this->read_color($title_detail_element->r_pr->u_fill->solid_fill);
                }
                $strikethrough = self::get_attribute_string($title_detail_element->r_pr, 'strike');
            }
            $font_found = false;
            $latin_name ??= $default_latin;
            if ($latin_name !== null) {
                $obj_text->get_font()->set_latin($latin_name);
                $font_found = true;
            }
            $east_asian ??= $default_east_asian;
            if ($east_asian !== null) {
                $obj_text->get_font()->set_east_asian($east_asian);
                $font_found = true;
            }
            $complex_script ??= $default_complex_script;
            if ($complex_script !== null) {
                $obj_text->get_font()->set_complex_script($complex_script);
                $font_found = true;
            }
            $font_name ??= $default_font_name;
            if ($font_name !== null) {
                // @codeCoverageIgnoreStart
                $obj_text->get_font()->set_name($font_name);
                $font_found = true;
                // @codeCoverageIgnoreEnd
            }
            $font_size ??= $default_font_size;
            if (is_int($font_size)) {
                $obj_text->get_font()->set_size(floor($font_size / 100));
                $font_found = true;
            } else {
                $obj_text->get_font()->set_size(null, true);
            }
            $font_color ??= $default_font_color;
            if (!empty($font_color)) {
                $obj_text->get_font()->set_chart_color($font_color);
                $font_found = true;
            }
            $bold ??= $default_bold;
            if ($bold !== null) {
                $obj_text->get_font()->set_bold($bold);
                $font_found = true;
            }
            $italic ??= $default_italic;
            if ($italic !== null) {
                $obj_text->get_font()->set_italic($italic);
                $font_found = true;
            }
            $baseline ??= $default_baseline;
            if ($baseline !== null) {
                $obj_text->get_font()->set_base_line($baseline);
                if ($baseline > 0) {
                    $obj_text->get_font()->set_superscript(true);
                } elseif ($baseline < 0) {
                    $obj_text->get_font()->set_subscript(true);
                }
                $font_found = true;
            }
            $underscore ??= $default_underscore;
            if ($underscore !== null) {
                if ($underscore == 'sng') {
                    $obj_text->get_font()->set_underline(Font::UNDERLINE_SINGLE);
                } elseif ($underscore == 'dbl') {
                    $obj_text->get_font()->set_underline(Font::UNDERLINE_DOUBLE);
                } elseif ($underscore !== '') {
                    $obj_text->get_font()->set_underline($underscore);
                } else {
                    $obj_text->get_font()->set_underline(Font::UNDERLINE_NONE);
                }
                $font_found = true;
                if ($underline_color) {
                    $obj_text->get_font()->set_underline_color($underline_color);
                }
            }
            $strikethrough ??= $default_strikethrough;
            if ($strikethrough !== null) {
                $obj_text->get_font()->set_strike_type($strikethrough);
                if ($strikethrough == 'noStrike') {
                    $obj_text->get_font()->set_strikethrough(false);
                } else {
                    $obj_text->get_font()->set_strikethrough(true);
                }
                $font_found = true;
            }
            if ($font_found === false) {
                $obj_text->set_font();
            }
        }
        return $value;
    }
    private function parse_font(Simple_Xml_Element $title_detail_part): ?Font
    {
        if (!isset($title_detail_part->p_pr->def_r_pr)) {
            return null;
        }
        $font_array = [];
        $font_array['size'] = self::get_attribute_integer($title_detail_part->p_pr->def_r_pr, 'sz');
        if ($font_array['size'] !== null && $font_array['size'] >= 100) {
            $font_array['size'] /= 100.0;
        }
        if ($font_array['size'] !== null) {
            $font_array['size'] = (int) $font_array['size'];
        }
        $font_array['bold'] = (bool) self::get_attribute_boolean($title_detail_part->p_pr->def_r_pr, 'b');
        $font_array['italic'] = (bool) self::get_attribute_boolean($title_detail_part->p_pr->def_r_pr, 'i');
        $font_array['underscore'] = self::get_attribute_string($title_detail_part->p_pr->def_r_pr, 'u');
        $strikethrough = self::get_attribute_string($title_detail_part->p_pr->def_r_pr, 'strike');
        if ($strikethrough !== null) {
            if ($strikethrough == 'noStrike') {
                $font_array['strikethrough'] = false;
            } else {
                $font_array['strikethrough'] = true;
            }
        }
        $font_array['cap'] = (string) self::get_attribute_string($title_detail_part->p_pr->def_r_pr, 'cap');
        if (isset($title_detail_part->p_pr->def_r_pr->latin)) {
            $font_array['latin'] = (string) self::get_attribute_string($title_detail_part->p_pr->def_r_pr->latin, 'typeface');
        }
        if (isset($title_detail_part->p_pr->def_r_pr->ea)) {
            $font_array['eastAsian'] = (string) self::get_attribute_string($title_detail_part->p_pr->def_r_pr->ea, 'typeface');
        }
        if (isset($title_detail_part->p_pr->def_r_pr->cs)) {
            $font_array['complexScript'] = (string) self::get_attribute_string($title_detail_part->p_pr->def_r_pr->cs, 'typeface');
        }
        if (isset($title_detail_part->p_pr->def_r_pr->solid_fill)) {
            $font_array['chartColor'] = new Chart_Color($this->read_color($title_detail_part->p_pr->def_r_pr->solid_fill));
        }
        $font = new Font();
        //$font->setSize(null, true);
        $font->apply_from_array($font_array);
        return $font;
    }
    /** @return mixed[] */
    private function read_chart_attributes(?Simple_Xml_Element $chart_detail): array
    {
        $plot_attributes = [];
        if (isset($chart_detail->d_lbls)) {
            if (isset($chart_detail->d_lbls->d_lbl_pos)) {
                $plot_attributes['dLblPos'] = self::get_attribute_string($chart_detail->d_lbls->d_lbl_pos, 'val');
            }
            if (isset($chart_detail->d_lbls->num_fmt)) {
                $plot_attributes['numFmtCode'] = self::get_attribute_string($chart_detail->d_lbls->num_fmt, 'formatCode');
                $plot_attributes['numFmtLinked'] = self::get_attribute_boolean($chart_detail->d_lbls->num_fmt, 'sourceLinked');
            }
            if (isset($chart_detail->d_lbls->show_legend_key)) {
                $plot_attributes['showLegendKey'] = self::get_attribute_string($chart_detail->d_lbls->show_legend_key, 'val');
            }
            if (isset($chart_detail->d_lbls->show_val)) {
                $plot_attributes['showVal'] = self::get_attribute_string($chart_detail->d_lbls->show_val, 'val');
            }
            if (isset($chart_detail->d_lbls->show_cat_name)) {
                $plot_attributes['showCatName'] = self::get_attribute_string($chart_detail->d_lbls->show_cat_name, 'val');
            }
            if (isset($chart_detail->d_lbls->show_ser_name)) {
                $plot_attributes['showSerName'] = self::get_attribute_string($chart_detail->d_lbls->show_ser_name, 'val');
            }
            if (isset($chart_detail->d_lbls->show_percent)) {
                $plot_attributes['showPercent'] = self::get_attribute_string($chart_detail->d_lbls->show_percent, 'val');
            }
            if (isset($chart_detail->d_lbls->show_bubble_size)) {
                $plot_attributes['showBubbleSize'] = self::get_attribute_string($chart_detail->d_lbls->show_bubble_size, 'val');
            }
            if (isset($chart_detail->d_lbls->show_leader_lines)) {
                $plot_attributes['showLeaderLines'] = self::get_attribute_string($chart_detail->d_lbls->show_leader_lines, 'val');
            }
            if (isset($chart_detail->d_lbls->sp_pr)) {
                $sppr = $chart_detail->d_lbls->sp_pr->children($this->a_namespace);
                if (isset($sppr->solid_fill)) {
                    $plot_attributes['labelFillColor'] = new Chart_Color($this->read_color($sppr->solid_fill));
                }
                if (isset($sppr->ln->solid_fill)) {
                    $plot_attributes['labelBorderColor'] = new Chart_Color($this->read_color($sppr->ln->solid_fill));
                }
            }
            if (isset($chart_detail->d_lbls->tx_pr)) {
                $txpr = $chart_detail->d_lbls->tx_pr->children($this->a_namespace);
                if (isset($txpr->p)) {
                    $plot_attributes['labelFont'] = $this->parse_font($txpr->p);
                    if (isset($txpr->p->p_pr->def_r_pr->effect_lst)) {
                        $label_effects = new Grid_Lines();
                        $this->read_effects($txpr->p->p_pr->def_r_pr, $label_effects, false);
                        $plot_attributes['labelEffects'] = $label_effects;
                    }
                }
            }
        }
        return $plot_attributes;
    }
    /** @param array<mixed> $plotAttributes */
    private function set_chart_attributes(Layout $plot_area, array $plot_attributes): void
    {
        foreach ($plot_attributes as $plot_attribute_key => $plot_attribute_value) {
            /** @var ?bool $plotAttributeValue */
            switch ($plot_attribute_key) {
                case 'showLegendKey':
                    $plot_area->set_show_legend_key($plot_attribute_value);
                    break;
                case 'showVal':
                    $plot_area->set_show_val($plot_attribute_value);
                    break;
                case 'showCatName':
                    $plot_area->set_show_cat_name($plot_attribute_value);
                    break;
                case 'showSerName':
                    $plot_area->set_show_ser_name($plot_attribute_value);
                    break;
                case 'showPercent':
                    $plot_area->set_show_percent($plot_attribute_value);
                    break;
                case 'showBubbleSize':
                    $plot_area->set_show_bubble_size($plot_attribute_value);
                    break;
                case 'showLeaderLines':
                    $plot_area->set_show_leader_lines($plot_attribute_value);
                    break;
                case 'labelFont':
                    /** @var ?Font $plotAttributeValue */
                    $plot_area->set_label_font($plot_attribute_value);
                    break;
            }
        }
    }
    private function read_effects(Simple_Xml_Element $chart_detail, ?Chart_Properties $chart_object, bool $get_sppr = true): void
    {
        if (!isset($chart_object)) {
            return;
        }
        if ($get_sppr) {
            if (!isset($chart_detail->sp_pr)) {
                return;
            }
            $sppr = $chart_detail->sp_pr->children($this->a_namespace);
        } else {
            $sppr = $chart_detail;
        }
        if (isset($sppr->effect_lst->glow)) {
            $axis_glow_size = (float) self::get_attribute_integer($sppr->effect_lst->glow, 'rad') / Chart_Properties::POINTS_WIDTH_MULTIPLIER;
            if ($axis_glow_size != 0.0) {
                $color_array = $this->read_color($sppr->effect_lst->glow);
                $chart_object->set_glow_properties($axis_glow_size, $color_array['value'], $color_array['alpha'], $color_array['type']);
            }
        }
        if (isset($sppr->effect_lst->soft_edge)) {
            $soft_edge_size = self::get_attribute_string($sppr->effect_lst->soft_edge, 'rad');
            if (is_numeric($soft_edge_size)) {
                $chart_object->set_soft_edges(Chart_Properties::xml_to_points($soft_edge_size));
            }
        }
        $type = '';
        foreach (self::SHADOW_TYPES as $shadow_type) {
            if (isset($sppr->effect_lst->{$shadow_type})) {
                $type = $shadow_type;
                break;
            }
        }
        if ($type !== '') {
            $blur = self::get_attribute_string($sppr->effect_lst->{$type}, 'blurRad');
            $blur = is_numeric($blur) ? Chart_Properties::xml_to_points($blur) : null;
            $dist = self::get_attribute_string($sppr->effect_lst->{$type}, 'dist');
            $dist = is_numeric($dist) ? Chart_Properties::xml_to_points($dist) : null;
            $direction = self::get_attribute_string($sppr->effect_lst->{$type}, 'dir');
            $direction = is_numeric($direction) ? Chart_Properties::xml_to_angle($direction) : null;
            $algn = self::get_attribute_string($sppr->effect_lst->{$type}, 'algn');
            $rot = self::get_attribute_string($sppr->effect_lst->{$type}, 'rotWithShape');
            $size = [];
            foreach (['sx', 'sy'] as $size_type) {
                $size_value = self::get_attribute_string($sppr->effect_lst->{$type}, $size_type);
                if (is_numeric($size_value)) {
                    $size[$size_type] = Chart_Properties::xml_to_tenth_of_percent($size_value);
                } else {
                    $size[$size_type] = null;
                }
            }
            foreach (['kx', 'ky'] as $size_type) {
                $size_value = self::get_attribute_string($sppr->effect_lst->{$type}, $size_type);
                if (is_numeric($size_value)) {
                    $size[$size_type] = Chart_Properties::xml_to_angle($size_value);
                } else {
                    $size[$size_type] = null;
                }
            }
            $color_array = $this->read_color($sppr->effect_lst->{$type});
            $chart_object->set_shadow_property('effect', $type)->set_shadow_property('blur', $blur)->set_shadow_property('direction', $direction)->set_shadow_property('distance', $dist)->set_shadow_property('algn', $algn)->set_shadow_property('rotWithShape', $rot)->set_shadow_property('size', $size)->set_shadow_property('color', $color_array);
        }
    }
    private const SHADOW_TYPES = ['outerShdw', 'innerShdw'];
    /** @return array{type: ?string, value: ?string, alpha: ?int, brightness: ?int} */
    private function read_color(Simple_Xml_Element $color_xml): array
    {
        $result = ['type' => null, 'value' => null, 'alpha' => null, 'brightness' => null];
        foreach (Chart_Color::EXCEL_COLOR_TYPES as $type) {
            if (isset($color_xml->{$type})) {
                $result['type'] = $type;
                $result['value'] = self::get_attribute_string($color_xml->{$type}, 'val');
                if (isset($color_xml->{$type}->alpha)) {
                    $alpha = self::get_attribute_string($color_xml->{$type}->alpha, 'val');
                    if (is_numeric($alpha)) {
                        $result['alpha'] = Chart_Color::alpha_from_xml($alpha);
                    }
                }
                if (isset($color_xml->{$type}->lum_mod)) {
                    $brightness = self::get_attribute_string($color_xml->{$type}->lum_mod, 'val');
                    if (is_numeric($brightness)) {
                        $result['brightness'] = Chart_Color::alpha_from_xml($brightness);
                    }
                }
                break;
            }
        }
        return $result;
    }
    private function read_line_style(Simple_Xml_Element $chart_detail, ?Chart_Properties $chart_object): void
    {
        if (!isset($chart_object, $chart_detail->sp_pr)) {
            return;
        }
        $sppr = $chart_detail->sp_pr->children($this->a_namespace);
        if (!isset($sppr->ln)) {
            return;
        }
        $line_width = null;
        $line_width_temp = self::get_attribute_string($sppr->ln, 'w');
        if (is_numeric($line_width_temp)) {
            $line_width = Chart_Properties::xml_to_points($line_width_temp);
        }
        $compound_type = self::get_attribute_string($sppr->ln, 'cmpd');
        $dash_type = self::get_attribute_string($sppr->ln->prst_dash, 'val');
        $cap_type = self::get_attribute_string($sppr->ln, 'cap');
        if (isset($sppr->ln->miter)) {
            $join_type = Chart_Properties::LINE_STYLE_JOIN_MITER;
        } elseif (isset($sppr->ln->bevel)) {
            $join_type = Chart_Properties::LINE_STYLE_JOIN_BEVEL;
        } else {
            $join_type = '';
        }
        $head_arrow_size = 0;
        $end_arrow_size = 0;
        $head_arrow_type = self::get_attribute_string($sppr->ln->head_end, 'type');
        $head_arrow_width = self::get_attribute_string($sppr->ln->head_end, 'w');
        $head_arrow_length = self::get_attribute_string($sppr->ln->head_end, 'len');
        $end_arrow_type = self::get_attribute_string($sppr->ln->tail_end, 'type');
        $end_arrow_width = self::get_attribute_string($sppr->ln->tail_end, 'w');
        $end_arrow_length = self::get_attribute_string($sppr->ln->tail_end, 'len');
        $chart_object->set_line_style_properties($line_width, $compound_type, $dash_type, $cap_type, $join_type, $head_arrow_type, $head_arrow_size, $end_arrow_type, $end_arrow_size, $head_arrow_width, $head_arrow_length, $end_arrow_width, $end_arrow_length);
        $color_array = $this->read_color($sppr->ln->solid_fill);
        $chart_object->get_line_color()->set_color_properties_array($color_array);
    }
    private function set_axis_properties(Simple_Xml_Element $chart_detail, ?Axis $which_axis): void
    {
        if (!isset($which_axis)) {
            return;
        }
        if (isset($chart_detail->delete)) {
            $which_axis->set_axis_option('hidden', (string) self::get_attribute_string($chart_detail->delete, 'val'));
        }
        if (isset($chart_detail->num_fmt)) {
            $which_axis->set_axis_number_properties((string) self::get_attribute_string($chart_detail->num_fmt, 'formatCode'), null, (int) self::get_attribute_integer($chart_detail->num_fmt, 'sourceLinked'));
        }
        if (isset($chart_detail->cross_between)) {
            $which_axis->set_cross_between((string) self::get_attribute_string($chart_detail->cross_between, 'val'));
        }
        if (isset($chart_detail->disp_units, $chart_detail->disp_units->built_in_unit)) {
            $which_axis->set_axis_option('dispUnitsBuiltIn', (string) self::get_attribute_string($chart_detail->disp_units->built_in_unit, 'val'));
            if (isset($chart_detail->disp_units->disp_units_lbl)) {
                $which_axis->set_disp_units_title(new Title());
                // TODO parse title elements
            }
        }
        if (isset($chart_detail->major_tick_mark)) {
            $which_axis->set_axis_option('major_tick_mark', (string) self::get_attribute_string($chart_detail->major_tick_mark, 'val'));
        }
        if (isset($chart_detail->minor_tick_mark)) {
            $which_axis->set_axis_option('minor_tick_mark', (string) self::get_attribute_string($chart_detail->minor_tick_mark, 'val'));
        }
        if (isset($chart_detail->tick_lbl_pos)) {
            $which_axis->set_axis_option('axis_labels', (string) self::get_attribute_string($chart_detail->tick_lbl_pos, 'val'));
        }
        if (isset($chart_detail->crosses)) {
            $which_axis->set_axis_option('horizontal_crosses', (string) self::get_attribute_string($chart_detail->crosses, 'val'));
        }
        if (isset($chart_detail->crosses_at)) {
            $which_axis->set_axis_option('horizontal_crosses_value', (string) self::get_attribute_string($chart_detail->crosses_at, 'val'));
        }
        if (isset($chart_detail->scaling->log_base)) {
            $which_axis->set_axis_option('logBase', (string) self::get_attribute_string($chart_detail->scaling->log_base, 'val'));
        }
        if (isset($chart_detail->scaling->orientation)) {
            $which_axis->set_axis_option('orientation', (string) self::get_attribute_string($chart_detail->scaling->orientation, 'val'));
        }
        if (isset($chart_detail->scaling->max)) {
            $which_axis->set_axis_option('maximum', (string) self::get_attribute_string($chart_detail->scaling->max, 'val'));
        }
        if (isset($chart_detail->scaling->min)) {
            $which_axis->set_axis_option('minimum', (string) self::get_attribute_string($chart_detail->scaling->min, 'val'));
        }
        if (isset($chart_detail->scaling->min)) {
            $which_axis->set_axis_option('minimum', (string) self::get_attribute_string($chart_detail->scaling->min, 'val'));
        }
        if (isset($chart_detail->major_unit)) {
            $which_axis->set_axis_option('major_unit', (string) self::get_attribute_string($chart_detail->major_unit, 'val'));
        }
        if (isset($chart_detail->minor_unit)) {
            $which_axis->set_axis_option('minor_unit', (string) self::get_attribute_string($chart_detail->minor_unit, 'val'));
        }
        if (isset($chart_detail->base_time_unit)) {
            $which_axis->set_axis_option('baseTimeUnit', (string) self::get_attribute_string($chart_detail->base_time_unit, 'val'));
        }
        if (isset($chart_detail->major_time_unit)) {
            $which_axis->set_axis_option('majorTimeUnit', (string) self::get_attribute_string($chart_detail->major_time_unit, 'val'));
        }
        if (isset($chart_detail->minor_time_unit)) {
            $which_axis->set_axis_option('minorTimeUnit', (string) self::get_attribute_string($chart_detail->minor_time_unit, 'val'));
        }
        if (isset($chart_detail->tx_pr)) {
            $children = $chart_detail->tx_pr->children($this->a_namespace);
            $add_axis_text = false;
            $axis_text = new Axis_Text();
            if (isset($children->body_pr)) {
                $text_rotation = self::get_attribute_string($children->body_pr, 'rot');
                if (is_numeric($text_rotation)) {
                    $axis_text->set_rotation((int) Chart_Properties::xml_to_angle($text_rotation));
                    $add_axis_text = true;
                }
            }
            if (isset($children->p->p_pr->def_r_pr)) {
                $font = $this->parse_font($children->p);
                if ($font !== null) {
                    $axis_text->set_font($font);
                    $add_axis_text = true;
                }
            }
            if (isset($children->p->p_pr->def_r_pr->effect_lst)) {
                $this->read_effects($children->p->p_pr->def_r_pr, $axis_text, false);
                $add_axis_text = true;
            }
            if ($add_axis_text) {
                $which_axis->set_axis_text($axis_text);
            }
        }
    }
}