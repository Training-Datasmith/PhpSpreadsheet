<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart\Renderer;

use Acc_Bar_Plot;
use Acc_Line_Plot;
use Bar_Plot;
use Contour_Plot;
use Graph;
use Group_Bar_Plot;
use Line_Plot;
use Php_Office\Php_Spreadsheet\Chart\Chart;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Pie_Graph;
use Pie_Plot;
use Pie_Plot3d;
use Pie_Plot_C;
use Radar_Graph;
use Radar_Plot;
use Scatter_Plot;
use Spline;
use Stock_Plot;
/**
 * Base class for different Jpgraph implementations as charts renderer.
 */
abstract class Jp_Graph_Renderer_Base implements I_Renderer
{
    private const DEFAULT_WIDTH = 640.0;
    private const DEFAULT_HEIGHT = 480.0;
    private static array $colour_set = ['mediumpurple1', 'palegreen3', 'gold1', 'cadetblue1', 'darkmagenta', 'coral', 'dodgerblue3', 'eggplant', 'mediumblue', 'magenta', 'sandybrown', 'cyan', 'firebrick1', 'forestgreen', 'deeppink4', 'darkolivegreen', 'goldenrod2'];
    private static array $mark_set;
    private $graph;
    private static int $plot_colour = 0;
    private static int $plot_mark = 0;
    /**
     * Create a new jpgraph.
     */
    public function __construct(private readonly Chart $chart)
    {
        static::init();
        $this->graph = null;
        self::$mark_set = ['diamond' => MARK_DIAMOND, 'square' => MARK_SQUARE, 'triangle' => MARK_UTRIANGLE, 'x' => MARK_X, 'star' => MARK_STAR, 'dot' => MARK_FILLEDCIRCLE, 'dash' => MARK_DTRIANGLE, 'circle' => MARK_CIRCLE, 'plus' => MARK_CROSS];
    }
    private function get_graph_width(): float
    {
        return $this->chart->get_rendered_width() ?? self::DEFAULT_WIDTH;
    }
    private function get_graph_height(): float
    {
        return $this->chart->get_rendered_height() ?? self::DEFAULT_HEIGHT;
    }
    /**
     * This method should be overridden in descendants to do real JpGraph library initialization.
     */
    abstract protected static function init(): void;
    private function format_point_marker($series_plot, ?string $marker_id)
    {
        $plot_mark_keys = array_keys(self::$mark_set);
        if ($marker_id === null) {
            //    Use default plot marker (next marker in the series)
            self::$plot_mark %= count(self::$mark_set);
            $series_plot->mark->set_type(self::$mark_set[$plot_mark_keys[self::$plot_mark++]]);
        } elseif ($marker_id !== 'none') {
            //    Use specified plot marker (if it exists)
            if (isset(self::$mark_set[$marker_id])) {
                $series_plot->mark->set_type(self::$mark_set[$marker_id]);
            } else {
                //    If the specified plot marker doesn't exist, use default plot marker (next marker in the series)
                self::$plot_mark %= count(self::$mark_set);
                $series_plot->mark->set_type(self::$mark_set[$plot_mark_keys[self::$plot_mark++]]);
            }
        } else {
            //    Hide plot marker
            $series_plot->mark->Hide();
        }
        $series_plot->mark->set_color(self::$colour_set[self::$plot_colour]);
        $series_plot->mark->set_fill_color(self::$colour_set[self::$plot_colour]);
        $series_plot->set_color(self::$colour_set[self::$plot_colour++]);
        return $series_plot;
    }
    private function format_data_set_labels(int $group_id, array $dataset_labels, string $rotation = ''): array
    {
        $dataset_label_format_code = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0)->get_format_code() ?? '';
        //    Retrieve any label formatting code
        $dataset_label_format_code = stripslashes($dataset_label_format_code);
        $test_current_index = 0;
        foreach ($dataset_labels as $i => $dataset_label) {
            if (is_array($dataset_label)) {
                if ($rotation == 'bar') {
                    $dataset_labels[$i] = implode(' ', $dataset_label);
                } else {
                    $dataset_label = array_reverse($dataset_label);
                    $dataset_labels[$i] = implode("\n", $dataset_label);
                }
            } else {
                //    Format labels according to any formatting code
                $dataset_labels[$i] = Number_Format::to_formatted_string($dataset_label, $dataset_label_format_code);
            }
            ++$test_current_index;
        }
        return $dataset_labels;
    }
    private function percentage_sum_calculation(int $group_id, int $series_count): ?array
    {
        $sum_values = [];
        //    Adjust our values to a percentage value across all series in the group
        for ($i = 0; $i < $series_count; ++$i) {
            if ($i == 0) {
                $sum_values = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_data_values();
            } else {
                $next_values = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_data_values();
                foreach ($next_values as $k => $value) {
                    if (isset($sum_values[$k])) {
                        $sum_values[$k] += $value;
                    } else {
                        $sum_values[$k] = $value;
                    }
                }
            }
        }
        return $sum_values;
    }
    private function percentage_adjust_values(array $data_values, array $sum_values): array
    {
        foreach ($data_values as $k => $data_value) {
            $data_values[$k] = $data_value / $sum_values[$k] * 100;
        }
        return $data_values;
    }
    private function get_caption(?\Php_Office\Php_Spreadsheet\Chart\Title $caption_element): \Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text|string|null
    {
        //    Read any caption
        $caption = $caption_element !== null ? $caption_element->get_caption() : null;
        //    Test if we have a title caption to display
        if ($caption !== null) {
            //    If we do, it could be a plain string or an array
            if (is_array($caption)) {
                //    Implode an array to a plain string
                $caption = implode('', $caption);
            }
        }
        return $caption;
    }
    private function render_title(): void
    {
        $title = $this->get_caption($this->chart->get_title());
        if ($title !== null) {
            $this->graph->title->Set($title);
        }
    }
    private function render_legend(): void
    {
        $legend = $this->chart->get_legend();
        if ($legend !== null) {
            $legend_position = $legend->get_position();
            switch ($legend_position) {
                case 'r':
                    $this->graph->legend->set_pos(0.01, 0.5, 'right', 'center');
                    //    right
                    $this->graph->legend->set_columns(1);
                    break;
                case 'l':
                    $this->graph->legend->set_pos(0.01, 0.5, 'left', 'center');
                    //    left
                    $this->graph->legend->set_columns(1);
                    break;
                case 't':
                    $this->graph->legend->set_pos(0.5, 0.01, 'center', 'top');
                    //    top
                    break;
                case 'b':
                    $this->graph->legend->set_pos(0.5, 0.99, 'center', 'bottom');
                    //    bottom
                    break;
                default:
                    $this->graph->legend->set_pos(0.01, 0.01, 'right', 'top');
                    //    top-right
                    $this->graph->legend->set_columns(1);
                    break;
            }
        } else {
            $this->graph->legend->Hide();
        }
    }
    private function render_cartesian_plot_area(string $type = 'textlin'): void
    {
        $this->graph = new Graph($this->get_graph_width(), $this->get_graph_height());
        $this->graph->set_scale($type);
        $this->render_title();
        //    Rotate for bar rather than column chart
        $rotation = $this->chart->get_plot_area()->get_plot_group_by_index(0)->get_plot_direction();
        $reverse = $rotation == 'bar';
        $x_axis_label = $this->chart->get_x_axis_label();
        if ($x_axis_label !== null) {
            $title = $this->get_caption($x_axis_label);
            if ($title !== null) {
                $this->graph->xaxis->set_title($title, 'center');
                $this->graph->xaxis->title->set_margin(35);
                if ($reverse) {
                    $this->graph->xaxis->title->set_angle(90);
                    $this->graph->xaxis->title->set_margin(90);
                }
            }
        }
        $y_axis_label = $this->chart->get_y_axis_label();
        if ($y_axis_label !== null) {
            $title = $this->get_caption($y_axis_label);
            if ($title !== null) {
                $this->graph->yaxis->set_title($title, 'center');
                if ($reverse) {
                    $this->graph->yaxis->title->set_angle(0);
                    $this->graph->yaxis->title->set_margin(-55);
                }
            }
        }
    }
    private function render_pie_plot_area(): void
    {
        $this->graph = new Pie_Graph($this->get_graph_width(), $this->get_graph_height());
        $this->render_title();
    }
    private function render_radar_plot_area(): void
    {
        $this->graph = new Radar_Graph($this->get_graph_width(), $this->get_graph_height());
        $this->graph->set_scale('lin');
        $this->render_title();
    }
    private function get_data_label(int $group_id, int $index): mixed
    {
        $plot_label = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_label_by_index($index);
        if (!$plot_label) {
            return '';
        }
        return $plot_label->get_data_value();
    }
    private function render_plot_line(int $group_id, bool $filled = false, bool $combination = false): void
    {
        $grouping = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_grouping();
        $index = array_keys($this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_order())[0];
        $label_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($index)->get_point_count();
        if ($label_count > 0) {
            $dataset_labels = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0)->get_data_values();
            $dataset_labels = $this->format_data_set_labels($group_id, $dataset_labels);
            $this->graph->xaxis->set_tick_labels($dataset_labels);
        }
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        $series_plots = [];
        if ($grouping == 'percentStacked') {
            $sum_values = $this->percentage_sum_calculation($group_id, $series_count);
        } else {
            $sum_values = [];
        }
        //    Loop through each data series in turn
        for ($i = 0; $i < $series_count; ++$i) {
            $index = array_keys($this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_order())[$i];
            $data_values = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($index)->get_data_values();
            $marker = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($index)->get_point_marker();
            if ($grouping == 'percentStacked') {
                $data_values = $this->percentage_adjust_values($data_values, $sum_values);
            }
            //    Fill in any missing values in the $dataValues array
            $test_current_index = 0;
            foreach ($data_values as $k => $data_value) {
                while ($k != $test_current_index) {
                    $data_values[$test_current_index] = null;
                    ++$test_current_index;
                }
                ++$test_current_index;
            }
            $series_plot = new Line_Plot($data_values);
            if ($combination) {
                $series_plot->set_bar_center();
            }
            if ($filled) {
                $series_plot->set_filled(true);
                $series_plot->set_color('black');
                $series_plot->set_fill_color(self::$colour_set[self::$plot_colour++]);
            } else {
                //    Set the appropriate plot marker
                $this->format_point_marker($series_plot, $marker);
            }
            $series_plot->set_legend($this->get_data_label($group_id, $index));
            $series_plots[] = $series_plot;
        }
        if ($grouping == 'standard') {
            $group_plot = $series_plots;
        } else {
            $group_plot = new Acc_Line_Plot($series_plots);
        }
        $this->graph->Add($group_plot);
    }
    private function render_plot_bar(int $group_id, ?string $dimensions = '2d'): void
    {
        $rotation = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_direction();
        //    Rotate for bar rather than column chart
        if ($group_id == 0 && $rotation == 'bar') {
            $this->graph->set90and_margin();
        }
        $grouping = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_grouping();
        $index = array_keys($this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_order())[0];
        $label_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($index)->get_point_count();
        if ($label_count > 0) {
            $dataset_labels = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0)->get_data_values();
            $dataset_labels = $this->format_data_set_labels($group_id, $dataset_labels, $rotation);
            //    Rotate for bar rather than column chart
            if ($rotation == 'bar') {
                $dataset_labels = array_reverse($dataset_labels);
                $this->graph->yaxis->set_pos('max');
                $this->graph->yaxis->set_label_align('center', 'top');
                $this->graph->yaxis->set_label_side(SIDE_RIGHT);
            }
            $this->graph->xaxis->set_tick_labels($dataset_labels);
        }
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        $series_plots = [];
        if ($grouping == 'percentStacked') {
            $sum_values = $this->percentage_sum_calculation($group_id, $series_count);
        } else {
            $sum_values = [];
        }
        //    Loop through each data series in turn
        for ($j = 0; $j < $series_count; ++$j) {
            $index = array_keys($this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_order())[$j];
            $data_values = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($index)->get_data_values();
            if ($grouping == 'percentStacked') {
                $data_values = $this->percentage_adjust_values($data_values, $sum_values);
            }
            //    Fill in any missing values in the $dataValues array
            $test_current_index = 0;
            foreach ($data_values as $k => $data_value) {
                while ($k != $test_current_index) {
                    $data_values[$test_current_index] = null;
                    ++$test_current_index;
                }
                ++$test_current_index;
            }
            //    Reverse the $dataValues order for bar rather than column chart
            if ($rotation == 'bar') {
                $data_values = array_reverse($data_values);
            }
            $series_plot = new Bar_Plot($data_values);
            $series_plot->set_color('black');
            $series_plot->set_fill_color(self::$colour_set[self::$plot_colour++]);
            if ($dimensions == '3d') {
                $series_plot->set_shadow();
            }
            $series_plot->set_legend($this->get_data_label($group_id, $j));
            $series_plots[] = $series_plot;
        }
        //    Reverse the plot order for bar rather than column chart
        if ($rotation == 'bar' && $grouping != 'percentStacked') {
            $series_plots = array_reverse($series_plots);
        }
        if ($grouping == 'clustered') {
            $group_plot = new Group_Bar_Plot($series_plots);
        } elseif ($grouping == 'standard') {
            $group_plot = new Group_Bar_Plot($series_plots);
        } else {
            $group_plot = new Acc_Bar_Plot($series_plots);
            if ($dimensions == '3d') {
                $group_plot->set_shadow();
            }
        }
        $this->graph->Add($group_plot);
    }
    private function render_plot_scatter(int $group_id, bool $bubble): void
    {
        $scatter_style = $bubble_size = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_style();
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        //    Loop through each data series in turn
        for ($i = 0; $i < $series_count; ++$i) {
            $plot_category_by_index = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index($i);
            if ($plot_category_by_index === false) {
                $plot_category_by_index = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0);
            }
            $data_values_y = $plot_category_by_index->get_data_values();
            $data_values_x = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_data_values();
            $redo_data_values_y = true;
            if ($bubble) {
                if (!$bubble_size) {
                    $bubble_size = '10';
                }
                $redo_data_values_y = false;
                foreach ($data_values_y as $data_value_y) {
                    if (!is_int($data_value_y) && !is_float($data_value_y)) {
                        $redo_data_values_y = true;
                        break;
                    }
                }
            }
            if ($redo_data_values_y) {
                foreach ($data_values_y as $k => $data_value_y) {
                    $data_values_y[$k] = $k;
                }
            }
            $series_plot = new Scatter_Plot($data_values_x, $data_values_y);
            if ($scatter_style == 'lineMarker') {
                $series_plot->set_link_points();
                $series_plot->link->set_color(self::$colour_set[self::$plot_colour]);
            } elseif ($scatter_style == 'smoothMarker') {
                $spline = new Spline($data_values_y, $data_values_x);
                [$spline_data_y, $spline_data_x] = $spline->Get(count($data_values_x) * $this->get_graph_width() / 20);
                $lplot = new Line_Plot($spline_data_x, $spline_data_y);
                $lplot->set_color(self::$colour_set[self::$plot_colour]);
                $this->graph->Add($lplot);
            }
            if ($bubble) {
                $this->format_point_marker($series_plot, 'dot');
                $series_plot->mark->set_color('black');
                $series_plot->mark->set_size($bubble_size);
            } else {
                $marker = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_point_marker();
                $this->format_point_marker($series_plot, $marker);
            }
            $series_plot->set_legend($this->get_data_label($group_id, $i));
            $this->graph->Add($series_plot);
        }
    }
    private function render_plot_radar(int $group_id): void
    {
        $radar_style = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_style();
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        //    Loop through each data series in turn
        for ($i = 0; $i < $series_count; ++$i) {
            $data_values_y = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index($i)->get_data_values();
            $data_values_x = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_data_values();
            $marker = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_point_marker();
            $data_values = [];
            foreach ($data_values_y as $k => $data_value_y) {
                $data_values[$k] = is_array($data_value_y) ? implode(' ', array_reverse($data_value_y)) : $data_value_y;
            }
            $tmp = array_shift($data_values);
            $data_values[] = $tmp;
            $tmp = array_shift($data_values_x);
            $data_values_x[] = $tmp;
            $this->graph->set_titles(array_reverse($data_values));
            $series_plot = new Radar_Plot(array_reverse($data_values_x));
            $series_plot->set_color(self::$colour_set[self::$plot_colour++]);
            if ($radar_style == 'filled') {
                $series_plot->set_fill_color(self::$colour_set[self::$plot_colour]);
            }
            $this->format_point_marker($series_plot, $marker);
            $series_plot->set_legend($this->get_data_label($group_id, $i));
            $this->graph->Add($series_plot);
        }
    }
    private function render_plot_contour(int $group_id): void
    {
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        $data_values = [];
        //    Loop through each data series in turn
        for ($i = 0; $i < $series_count; ++$i) {
            $data_values_x = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($i)->get_data_values();
            $data_values[$i] = $data_values_x;
        }
        $series_plot = new Contour_Plot($data_values);
        $this->graph->Add($series_plot);
    }
    private function render_plot_stock(int $group_id): void
    {
        $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
        $plot_order = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_order();
        $data_values = [];
        //    Loop through each data series in turn and build the plot arrays
        foreach ($plot_order as $i => $v) {
            $data_values_x = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($v);
            if ($data_values_x === false) {
                continue;
            }
            $data_values_x = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($v)->get_data_values();
            foreach ($data_values_x as $j => $data_value_x) {
                $data_values[$plot_order[$i]][$j] = $data_value_x;
            }
        }
        if (empty($data_values)) {
            return;
        }
        $data_values_plot = [];
        // Flatten the plot arrays to a single dimensional array to work with jpgraph
        $j_max = count($data_values[0]);
        for ($j = 0; $j < $j_max; ++$j) {
            for ($i = 0; $i < $series_count; ++$i) {
                $data_values_plot[] = $data_values[$i][$j] ?? null;
            }
        }
        // Set the x-axis labels
        $label_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index(0)->get_point_count();
        if ($label_count > 0) {
            $dataset_labels = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0)->get_data_values();
            $dataset_labels = $this->format_data_set_labels($group_id, $dataset_labels);
            $this->graph->xaxis->set_tick_labels($dataset_labels);
        }
        $series_plot = new Stock_Plot($data_values_plot);
        $series_plot->set_width(20);
        $this->graph->Add($series_plot);
    }
    private function render_area_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area();
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_line($i, true, false);
        }
    }
    private function render_line_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area();
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_line($i, false, false);
        }
    }
    private function render_bar_chart(int $group_count, ?string $dimensions = '2d'): void
    {
        $this->render_cartesian_plot_area();
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_bar($i, $dimensions);
        }
    }
    private function render_scatter_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area('linlin');
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_scatter($i, false);
        }
    }
    private function render_bubble_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area('linlin');
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_scatter($i, true);
        }
    }
    private function render_pie_chart(int $group_count, ?string $dimensions = '2d', bool $doughnut = false, bool $multiple_plots = false): void
    {
        $this->render_pie_plot_area();
        $i_limit = $multiple_plots ? $group_count : 1;
        for ($group_id = 0; $group_id < $i_limit; ++$group_id) {
            $exploded = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_style();
            $dataset_labels = [];
            if ($group_id == 0) {
                $label_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index(0)->get_point_count();
                if ($label_count > 0) {
                    $dataset_labels = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_category_by_index(0)->get_data_values();
                    $dataset_labels = $this->format_data_set_labels($group_id, $dataset_labels);
                }
            }
            $series_count = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_series_count();
            //    For pie charts, we only display the first series: doughnut charts generally display all series
            $j_limit = $multiple_plots ? $series_count : 1;
            //    Loop through each data series in turn
            for ($j = 0; $j < $j_limit; ++$j) {
                $data_values = $this->chart->get_plot_area()->get_plot_group_by_index($group_id)->get_plot_values_by_index($j)->get_data_values();
                //    Fill in any missing values in the $dataValues array
                $test_current_index = 0;
                foreach ($data_values as $k => $data_value) {
                    while ($k != $test_current_index) {
                        $data_values[$test_current_index] = null;
                        ++$test_current_index;
                    }
                    ++$test_current_index;
                }
                if ($dimensions == '3d') {
                    $series_plot = new Pie_Plot3d($data_values);
                } else if ($doughnut) {
                    $series_plot = new Pie_Plot_C($data_values);
                } else {
                    $series_plot = new Pie_Plot($data_values);
                }
                if ($multiple_plots) {
                    $series_plot->set_size(($j_limit - $j) / ($j_limit * 4));
                }
                if ($doughnut && method_exists($series_plot, 'SetMidColor')) {
                    $series_plot->set_mid_color('white');
                }
                $series_plot->set_color(self::$colour_set[self::$plot_colour++]);
                if (count($dataset_labels) > 0) {
                    $series_plot->set_labels(array_fill(0, count($dataset_labels), ''));
                }
                if ($dimensions != '3d') {
                    $series_plot->set_guide_lines(false);
                }
                if ($j == 0) {
                    if ($exploded) {
                        $series_plot->explode_all();
                    }
                    $series_plot->set_legends($dataset_labels);
                }
                $this->graph->Add($series_plot);
            }
        }
    }
    private function render_radar_chart(int $group_count): void
    {
        $this->render_radar_plot_area();
        for ($group_id = 0; $group_id < $group_count; ++$group_id) {
            $this->render_plot_radar($group_id);
        }
    }
    private function render_stock_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area('intint');
        for ($group_id = 0; $group_id < $group_count; ++$group_id) {
            $this->render_plot_stock($group_id);
        }
    }
    private function render_contour_chart(int $group_count): void
    {
        $this->render_cartesian_plot_area('intint');
        for ($i = 0; $i < $group_count; ++$i) {
            $this->render_plot_contour($i);
        }
    }
    private function render_combination_chart(int $group_count, ?string $output_destination): bool
    {
        $this->render_cartesian_plot_area();
        for ($i = 0; $i < $group_count; ++$i) {
            $dimensions = null;
            $chart_type = $this->chart->get_plot_area()->get_plot_group_by_index($i)->get_plot_type();
            switch ($chart_type) {
                case 'area3DChart':
                case 'areaChart':
                    $this->render_plot_line($i, true, true);
                    break;
                case 'bar3DChart':
                    $dimensions = '3d';
                // no break
                case 'barChart':
                    $this->render_plot_bar($i, $dimensions);
                    break;
                case 'line3DChart':
                case 'lineChart':
                    $this->render_plot_line($i, false, true);
                    break;
                case 'scatterChart':
                    $this->render_plot_scatter($i, false);
                    break;
                case 'bubbleChart':
                    $this->render_plot_scatter($i, true);
                    break;
                default:
                    $this->graph = null;
                    return false;
            }
        }
        $this->render_legend();
        $this->graph->Stroke($output_destination);
        return true;
    }
    public function render(?string $output_destination): bool
    {
        self::$plot_colour = 0;
        $group_count = $this->chart->get_plot_area()->get_plot_group_count();
        $dimensions = null;
        if ($group_count == 1) {
            $chart_type = $this->chart->get_plot_area()->get_plot_group_by_index(0)->get_plot_type();
        } else {
            $chart_types = [];
            for ($i = 0; $i < $group_count; ++$i) {
                $chart_types[] = $this->chart->get_plot_area()->get_plot_group_by_index($i)->get_plot_type();
            }
            $chart_types = array_unique($chart_types);
            if (count($chart_types) == 1) {
                $chart_type = array_pop($chart_types);
            } elseif (count($chart_types) == 0) {
                echo 'Chart is not yet implemented<br />';
                return false;
            } else {
                return $this->render_combination_chart($group_count, $output_destination);
            }
        }
        switch ($chart_type) {
            case 'area3DChart':
                $dimensions = '3d';
            // no break
            case 'areaChart':
                $this->render_area_chart($group_count);
                break;
            case 'bar3DChart':
                $dimensions = '3d';
            // no break
            case 'barChart':
                $this->render_bar_chart($group_count, $dimensions);
                break;
            case 'line3DChart':
                $dimensions = '3d';
            // no break
            case 'lineChart':
                $this->render_line_chart($group_count);
                break;
            case 'pie3DChart':
                $dimensions = '3d';
            // no break
            case 'pieChart':
                $this->render_pie_chart($group_count, $dimensions, false, false);
                break;
            case 'doughnut3DChart':
                $dimensions = '3d';
            // no break
            case 'doughnutChart':
                $this->render_pie_chart($group_count, $dimensions, true, true);
                break;
            case 'scatterChart':
                $this->render_scatter_chart($group_count);
                break;
            case 'bubbleChart':
                $this->render_bubble_chart($group_count);
                break;
            case 'radarChart':
                $this->render_radar_chart($group_count);
                break;
            case 'surface3DChart':
            case 'surfaceChart':
                $this->render_contour_chart($group_count);
                break;
            case 'stockChart':
                $this->render_stock_chart($group_count);
                break;
            default:
                echo $chart_type . ' is not yet implemented<br />';
                return false;
        }
        $this->render_legend();
        $this->graph->Stroke($output_destination);
        return true;
    }
}