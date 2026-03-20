<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Plot_Area
{
    /**
     * No fill in plot area (show Excel gridlines through chart).
     */
    private bool $no_fill = false;
    /**
     * PlotArea Gradient Stop list.
     * Each entry is a 2-element array.
     *     First is position in %.
     *     Second is ChartColor.
     *
     * @var array<array{float, ChartColor}>
     */
    private array $gradient_fill_stops = [];
    /**
     * PlotArea Gradient Angle.
     */
    private ?float $gradient_fill_angle = null;
    /**
     * Create a new PlotArea.
     *
     * @param DataSeries[] $plotSeries
     */
    public function __construct(
        /**
         * PlotArea Layout.
         */
        private ?Layout $layout = null,
        /**
         * Plot Series.
         */
        private array $plot_series = []
    )
    {
    }
    public function get_layout(): ?Layout
    {
        return $this->layout;
    }
    /**
     * Get Number of Plot Groups.
     */
    public function get_plot_group_count(): int
    {
        return count($this->plot_series);
    }
    /**
     * Get Number of Plot Series.
     */
    public function get_plot_series_count(): int|float
    {
        $series_count = 0;
        foreach ($this->plot_series as $plot) {
            $series_count += $plot->get_plot_series_count();
        }
        return $series_count;
    }
    /**
     * Get Plot Series.
     *
     * @return DataSeries[]
     */
    public function get_plot_group(): array
    {
        return $this->plot_series;
    }
    /**
     * Get Plot Series by Index.
     */
    public function get_plot_group_by_index(int $index): Data_Series
    {
        return $this->plot_series[$index];
    }
    /**
     * Set Plot Series.
     *
     * @param DataSeries[] $plotSeries
     *
     * @return $this
     */
    public function set_plot_series(array $plot_series): static
    {
        $this->plot_series = $plot_series;
        return $this;
    }
    public function refresh(Worksheet $worksheet): void
    {
        foreach ($this->plot_series as $plot_series) {
            $plot_series->refresh($worksheet);
        }
    }
    public function set_no_fill(bool $no_fill): self
    {
        $this->no_fill = $no_fill;
        return $this;
    }
    public function get_no_fill(): bool
    {
        return $this->no_fill;
    }
    /** @param array<array{float, ChartColor}> $gradientFillStops */
    public function set_gradient_fill_properties(array $gradient_fill_stops, ?float $gradient_fill_angle): self
    {
        $this->gradient_fill_stops = $gradient_fill_stops;
        $this->gradient_fill_angle = $gradient_fill_angle;
        return $this;
    }
    /**
     * Get gradientFillAngle.
     */
    public function get_gradient_fill_angle(): ?float
    {
        return $this->gradient_fill_angle;
    }
    /**
     * Get gradientFillStops.
     *
     * @return array<array{float, ChartColor}>
     */
    public function get_gradient_fill_stops(): array
    {
        return $this->gradient_fill_stops;
    }
    private ?int $gap_width = null;
    private bool $use_up_bars = false;
    private bool $use_down_bars = false;
    public function get_gap_width(): ?int
    {
        return $this->gap_width;
    }
    public function set_gap_width(?int $gap_width): self
    {
        $this->gap_width = $gap_width;
        return $this;
    }
    public function get_use_up_bars(): bool
    {
        return $this->use_up_bars;
    }
    public function set_use_up_bars(bool $use_up_bars): self
    {
        $this->use_up_bars = $use_up_bars;
        return $this;
    }
    public function get_use_down_bars(): bool
    {
        return $this->use_down_bars;
    }
    public function set_use_down_bars(bool $use_down_bars): self
    {
        $this->use_down_bars = $use_down_bars;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->layout = $this->layout === null ? null : clone $this->layout;
        $plot_series = $this->plot_series;
        $this->plot_series = [];
        foreach ($plot_series as $series) {
            $this->plot_series[] = clone $series;
        }
    }
}