<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Settings;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Chart
{
    /**
     * Worksheet.
     */
    private ?Worksheet $worksheet = null;
    /**
     * Display Blanks as.
     */
    private string $display_blanks_as;
    /**
     * Top-Left Cell Position.
     */
    private string $top_left_cell_ref = 'A1';
    /**
     * Top-Left X-Offset.
     */
    private int $top_left_x_offset = 0;
    /**
     * Top-Left Y-Offset.
     */
    private int $top_left_y_offset = 0;
    /**
     * Bottom-Right Cell Position.
     */
    private string $bottom_right_cell_ref = '';
    /**
     * Bottom-Right X-Offset.
     */
    private int $bottom_right_x_offset = 10;
    /**
     * Bottom-Right Y-Offset.
     */
    private int $bottom_right_y_offset = 10;
    private ?int $rot_x = null;
    private ?int $rot_y = null;
    private ?int $r_ang_ax = null;
    private ?int $perspective = null;
    private bool $one_cell_anchor = false;
    private bool $auto_title_deleted = false;
    private bool $no_fill = false;
    private bool $no_border = false;
    private bool $rounded_corners = false;
    private Grid_Lines $border_lines;
    private Chart_Color $fill_color;
    /**
     * Rendered width in pixels.
     */
    private ?float $rendered_width = null;
    /**
     * Rendered height in pixels.
     */
    private ?float $rendered_height = null;
    /**
     * Create a new Chart.
     * majorGridlines and minorGridlines are deprecated, moved to Axis.
     */
    public function __construct(
        /**
         * Chart Name.
         */
        private string $name,
        /**
         * Chart Title.
         */
        private ?Title $title = null,
        /**
         * Chart Legend.
         */
        private ?Legend $legend = null,
        /**
         * Chart Plot Area.
         */
        private ?Plot_Area $plot_area = null,
        /**
         * Plot Visible Only.
         */
        private bool $plot_visible_only = true,
        string $display_blanks_as = Data_Series::DEFAULT_EMPTY_AS,
        /**
         * X-Axis Label.
         */
        private ?Title $x_axis_label = null,
        /**
         * Y-Axis Label.
         */
        private ?Title $y_axis_label = null,
        private ?Axis $x_axis = new Axis(),
        private ?Axis $y_axis = new Axis(),
        ?Grid_Lines $major_gridlines = null,
        ?Grid_Lines $minor_gridlines = null
    )
    {
        $this->set_display_blanks_as($display_blanks_as);
        if ($major_gridlines !== null) {
            $this->y_axis->set_major_gridlines($major_gridlines);
        }
        if ($minor_gridlines !== null) {
            $this->y_axis->set_minor_gridlines($minor_gridlines);
        }
        $this->fill_color = new Chart_Color();
        $this->border_lines = new Grid_Lines();
    }
    public function __destruct()
    {
        $this->worksheet = null;
    }
    /**
     * Get Name.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    public function set_name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    /**
     * Get Worksheet.
     */
    public function get_worksheet(): ?Worksheet
    {
        return $this->worksheet;
    }
    /**
     * Set Worksheet.
     *
     * @return $this
     */
    public function set_worksheet(?Worksheet $worksheet = null): static
    {
        $this->worksheet = $worksheet;
        return $this;
    }
    public function get_title(): ?Title
    {
        return $this->title;
    }
    /**
     * Set Title.
     *
     * @return $this
     */
    public function set_title(Title $title): static
    {
        $this->title = $title;
        return $this;
    }
    public function get_legend(): ?Legend
    {
        return $this->legend;
    }
    /**
     * Set Legend.
     *
     * @return $this
     */
    public function set_legend(Legend $legend): static
    {
        $this->legend = $legend;
        return $this;
    }
    public function get_x_axis_label(): ?Title
    {
        return $this->x_axis_label;
    }
    /**
     * Set X-Axis Label.
     *
     * @return $this
     */
    public function set_x_axis_label(Title $label): static
    {
        $this->x_axis_label = $label;
        return $this;
    }
    public function get_y_axis_label(): ?Title
    {
        return $this->y_axis_label;
    }
    /**
     * Set Y-Axis Label.
     *
     * @return $this
     */
    public function set_y_axis_label(Title $label): static
    {
        $this->y_axis_label = $label;
        return $this;
    }
    public function get_plot_area(): ?Plot_Area
    {
        return $this->plot_area;
    }
    public function get_plot_area_or_throw(): Plot_Area
    {
        $plot_area = $this->get_plot_area();
        if ($plot_area !== null) {
            return $plot_area;
        }
        throw new Exception('Chart has no PlotArea');
    }
    /**
     * Set Plot Area.
     */
    public function set_plot_area(Plot_Area $plot_area): self
    {
        $this->plot_area = $plot_area;
        return $this;
    }
    /**
     * Get Plot Visible Only.
     */
    public function get_plot_visible_only(): bool
    {
        return $this->plot_visible_only;
    }
    /**
     * Set Plot Visible Only.
     *
     * @return $this
     */
    public function set_plot_visible_only(bool $plot_visible_only): static
    {
        $this->plot_visible_only = $plot_visible_only;
        return $this;
    }
    /**
     * Get Display Blanks as.
     */
    public function get_display_blanks_as(): string
    {
        return $this->display_blanks_as;
    }
    /**
     * Set Display Blanks as.
     *
     * @return $this
     */
    public function set_display_blanks_as(string $display_blanks_as): static
    {
        $display_blanks_as = strtolower($display_blanks_as);
        $this->display_blanks_as = in_array($display_blanks_as, Data_Series::VALID_EMPTY_AS, true) ? $display_blanks_as : Data_Series::DEFAULT_EMPTY_AS;
        return $this;
    }
    public function get_chart_axis_y(): Axis
    {
        return $this->y_axis;
    }
    /**
     * Set yAxis.
     */
    public function set_chart_axis_y(?Axis $axis): self
    {
        $this->y_axis = $axis ?? new Axis();
        return $this;
    }
    public function get_chart_axis_x(): Axis
    {
        return $this->x_axis;
    }
    /**
     * Set xAxis.
     */
    public function set_chart_axis_x(?Axis $axis): self
    {
        $this->x_axis = $axis ?? new Axis();
        return $this;
    }
    /**
     * Set the Top Left position for the chart.
     *
     * @return $this
     */
    public function set_top_left_position(string $cell_address, ?int $x_offset = null, ?int $y_offset = null): static
    {
        $this->top_left_cell_ref = $cell_address;
        if ($x_offset !== null) {
            $this->set_top_left_x_offset($x_offset);
        }
        if ($y_offset !== null) {
            $this->set_top_left_y_offset($y_offset);
        }
        return $this;
    }
    /**
     * Get the top left position of the chart.
     *
     * Returns ['cell' => string cell address, 'xOffset' => int, 'yOffset' => int].
     *
     * @return array{cell: string, xOffset: int, yOffset: int} an associative array containing the cell address, X-Offset and Y-Offset from the top left of that cell
     */
    public function get_top_left_position(): array
    {
        return ['cell' => $this->top_left_cell_ref, 'xOffset' => $this->top_left_x_offset, 'yOffset' => $this->top_left_y_offset];
    }
    /**
     * Get the cell address where the top left of the chart is fixed.
     */
    public function get_top_left_cell(): string
    {
        return $this->top_left_cell_ref;
    }
    /**
     * Set the Top Left cell position for the chart.
     *
     * @return $this
     */
    public function set_top_left_cell(string $cell_address): static
    {
        $this->top_left_cell_ref = $cell_address;
        return $this;
    }
    /**
     * Set the offset position within the Top Left cell for the chart.
     *
     * @return $this
     */
    public function set_top_left_offset(?int $x_offset, ?int $y_offset): static
    {
        if ($x_offset !== null) {
            $this->set_top_left_x_offset($x_offset);
        }
        if ($y_offset !== null) {
            $this->set_top_left_y_offset($y_offset);
        }
        return $this;
    }
    /**
     * Get the offset position within the Top Left cell for the chart.
     *
     * @return int[]
     */
    public function get_top_left_offset(): array
    {
        return ['X' => $this->top_left_x_offset, 'Y' => $this->top_left_y_offset];
    }
    /**
     * @return $this
     */
    public function set_top_left_x_offset(int $x_offset): static
    {
        $this->top_left_x_offset = $x_offset;
        return $this;
    }
    public function get_top_left_x_offset(): int
    {
        return $this->top_left_x_offset;
    }
    /**
     * @return $this
     */
    public function set_top_left_y_offset(int $y_offset): static
    {
        $this->top_left_y_offset = $y_offset;
        return $this;
    }
    public function get_top_left_y_offset(): int
    {
        return $this->top_left_y_offset;
    }
    /**
     * Set the Bottom Right position of the chart.
     *
     * @return $this
     */
    public function set_bottom_right_position(string $cell_address = '', ?int $x_offset = null, ?int $y_offset = null): static
    {
        $this->bottom_right_cell_ref = $cell_address;
        if ($x_offset !== null) {
            $this->set_bottom_right_x_offset($x_offset);
        }
        if ($y_offset !== null) {
            $this->set_bottom_right_y_offset($y_offset);
        }
        return $this;
    }
    /**
     * Get the bottom right position of the chart.
     *
     * @return array{cell: string, xOffset: int, yOffset:int} an associative array containing the cell address, X-Offset and Y-Offset from the top left of that cell
     */
    public function get_bottom_right_position(): array
    {
        return ['cell' => $this->bottom_right_cell_ref, 'xOffset' => $this->bottom_right_x_offset, 'yOffset' => $this->bottom_right_y_offset];
    }
    /**
     * Set the Bottom Right cell for the chart.
     *
     * @return $this
     */
    public function set_bottom_right_cell(string $cell_address = ''): static
    {
        $this->bottom_right_cell_ref = $cell_address;
        return $this;
    }
    /**
     * Get the cell address where the bottom right of the chart is fixed.
     */
    public function get_bottom_right_cell(): string
    {
        return $this->bottom_right_cell_ref;
    }
    /**
     * Set the offset position within the Bottom Right cell for the chart.
     *
     * @return $this
     */
    public function set_bottom_right_offset(?int $x_offset, ?int $y_offset): static
    {
        if ($x_offset !== null) {
            $this->set_bottom_right_x_offset($x_offset);
        }
        if ($y_offset !== null) {
            $this->set_bottom_right_y_offset($y_offset);
        }
        return $this;
    }
    /**
     * Get the offset position within the Bottom Right cell for the chart.
     *
     * @return int[]
     */
    public function get_bottom_right_offset(): array
    {
        return ['X' => $this->bottom_right_x_offset, 'Y' => $this->bottom_right_y_offset];
    }
    /**
     * @return $this
     */
    public function set_bottom_right_x_offset(int $x_offset): static
    {
        $this->bottom_right_x_offset = $x_offset;
        return $this;
    }
    public function get_bottom_right_x_offset(): int
    {
        return $this->bottom_right_x_offset;
    }
    /**
     * @return $this
     */
    public function set_bottom_right_y_offset(int $y_offset): static
    {
        $this->bottom_right_y_offset = $y_offset;
        return $this;
    }
    public function get_bottom_right_y_offset(): int
    {
        return $this->bottom_right_y_offset;
    }
    public function refresh(): void
    {
        if ($this->worksheet !== null && $this->plot_area !== null) {
            $this->plot_area->refresh($this->worksheet);
        }
    }
    /**
     * Render the chart to given file (or stream).
     *
     * @param ?string $outputDestination Name of the file render to
     *
     * @return bool true on success
     */
    public function render(?string $output_destination = null): bool
    {
        if ($output_destination == 'php://output') {
            $output_destination = null;
        }
        $library_name = Settings::get_chart_renderer();
        if ($library_name === null) {
            return false;
        }
        // Ensure that data series values are up-to-date before we render
        $this->refresh();
        $renderer = new $library_name($this);
        return $renderer->render($output_destination);
    }
    public function get_rot_x(): ?int
    {
        return $this->rot_x;
    }
    public function set_rot_x(?int $rot_x): self
    {
        $this->rot_x = $rot_x;
        return $this;
    }
    public function get_rot_y(): ?int
    {
        return $this->rot_y;
    }
    public function set_rot_y(?int $rot_y): self
    {
        $this->rot_y = $rot_y;
        return $this;
    }
    public function get_r_ang_ax(): ?int
    {
        return $this->r_ang_ax;
    }
    public function set_r_ang_ax(?int $r_ang_ax): self
    {
        $this->r_ang_ax = $r_ang_ax;
        return $this;
    }
    public function get_perspective(): ?int
    {
        return $this->perspective;
    }
    public function set_perspective(?int $perspective): self
    {
        $this->perspective = $perspective;
        return $this;
    }
    public function get_one_cell_anchor(): bool
    {
        return $this->one_cell_anchor;
    }
    public function set_one_cell_anchor(bool $one_cell_anchor): self
    {
        $this->one_cell_anchor = $one_cell_anchor;
        return $this;
    }
    public function get_auto_title_deleted(): bool
    {
        return $this->auto_title_deleted;
    }
    public function set_auto_title_deleted(bool $auto_title_deleted): self
    {
        $this->auto_title_deleted = $auto_title_deleted;
        return $this;
    }
    public function get_no_fill(): bool
    {
        return $this->no_fill;
    }
    public function set_no_fill(bool $no_fill): self
    {
        $this->no_fill = $no_fill;
        return $this;
    }
    public function get_no_border(): bool
    {
        return $this->no_border;
    }
    public function set_no_border(bool $no_border): self
    {
        $this->no_border = $no_border;
        return $this;
    }
    public function get_rounded_corners(): bool
    {
        return $this->rounded_corners;
    }
    public function set_rounded_corners(?bool $rounded_corners): self
    {
        if ($rounded_corners !== null) {
            $this->rounded_corners = $rounded_corners;
        }
        return $this;
    }
    public function get_border_lines(): Grid_Lines
    {
        return $this->border_lines;
    }
    public function set_border_lines(Grid_Lines $border_lines): self
    {
        $this->border_lines = $border_lines;
        return $this;
    }
    public function get_fill_color(): Chart_Color
    {
        return $this->fill_color;
    }
    public function set_rendered_width(?float $width): self
    {
        $this->rendered_width = $width;
        return $this;
    }
    public function get_rendered_width(): ?float
    {
        return $this->rendered_width;
    }
    public function set_rendered_height(?float $height): self
    {
        $this->rendered_height = $height;
        return $this;
    }
    public function get_rendered_height(): ?float
    {
        return $this->rendered_height;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->worksheet = null;
        $this->title = $this->title === null ? null : clone $this->title;
        $this->legend = $this->legend === null ? null : clone $this->legend;
        $this->x_axis_label = $this->x_axis_label === null ? null : clone $this->x_axis_label;
        $this->y_axis_label = $this->y_axis_label === null ? null : clone $this->y_axis_label;
        $this->plot_area = $this->plot_area === null ? null : clone $this->plot_area;
        $this->x_axis = clone $this->x_axis;
        $this->y_axis = clone $this->y_axis;
        $this->border_lines = clone $this->border_lines;
        $this->fill_color = clone $this->fill_color;
    }
}