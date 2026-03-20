<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Data_Series
{
    public const TYPE_BARCHART = 'barChart';
    public const TYPE_BARCHART_3D = 'bar3DChart';
    public const TYPE_LINECHART = 'lineChart';
    public const TYPE_LINECHART_3D = 'line3DChart';
    public const TYPE_AREACHART = 'areaChart';
    public const TYPE_AREACHART_3D = 'area3DChart';
    public const TYPE_PIECHART = 'pieChart';
    public const TYPE_PIECHART_3D = 'pie3DChart';
    public const TYPE_DOUGHNUTCHART = 'doughnutChart';
    public const TYPE_DONUTCHART = self::TYPE_DOUGHNUTCHART;
    // Synonym
    public const TYPE_SCATTERCHART = 'scatterChart';
    public const TYPE_SURFACECHART = 'surfaceChart';
    public const TYPE_SURFACECHART_3D = 'surface3DChart';
    public const TYPE_RADARCHART = 'radarChart';
    public const TYPE_BUBBLECHART = 'bubbleChart';
    public const TYPE_STOCKCHART = 'stockChart';
    public const TYPE_CANDLECHART = self::TYPE_STOCKCHART;
    // Synonym
    public const GROUPING_CLUSTERED = 'clustered';
    public const GROUPING_STACKED = 'stacked';
    public const GROUPING_PERCENT_STACKED = 'percentStacked';
    public const GROUPING_STANDARD = 'standard';
    public const DIRECTION_BAR = 'bar';
    public const DIRECTION_HORIZONTAL = self::DIRECTION_BAR;
    public const DIRECTION_COL = 'col';
    public const DIRECTION_COLUMN = self::DIRECTION_COL;
    public const DIRECTION_VERTICAL = self::DIRECTION_COL;
    public const STYLE_LINEMARKER = 'lineMarker';
    public const STYLE_SMOOTHMARKER = 'smoothMarker';
    public const STYLE_MARKER = 'marker';
    public const STYLE_FILLED = 'filled';
    public const EMPTY_AS_GAP = 'gap';
    public const EMPTY_AS_ZERO = 'zero';
    public const EMPTY_AS_SPAN = 'span';
    public const DEFAULT_EMPTY_AS = self::EMPTY_AS_GAP;
    public const VALID_EMPTY_AS = [self::EMPTY_AS_GAP, self::EMPTY_AS_ZERO, self::EMPTY_AS_SPAN];
    /**
     * Plot Direction.
     */
    private string $plot_direction;
    /**
     * Plot Label.
     *
     * @var DataSeriesValues[]
     */
    private array $plot_label;
    /**
     * Plot Category.
     *
     * @var DataSeriesValues[]
     */
    private array $plot_category;
    /**
     * Plot Values.
     *
     * @var DataSeriesValues[]
     */
    private array $plot_values;
    /**
     * Plot Bubble Sizes.
     *
     * @var DataSeriesValues[]
     */
    private array $plot_bubble_sizes = [];
    /**
     * Create a new DataSeries.
     *
     * @param int[] $plotOrder
     * @param DataSeriesValues[] $plotLabel
     * @param DataSeriesValues[] $plotCategory
     * @param DataSeriesValues[] $plotValues
     */
    public function __construct(
        /**
         * Series Plot Type.
         */
        private ?string $plot_type = null,
        /**
         * Plot Grouping Type.
         */
        private ?string $plot_grouping = null,
        /**
         * Order of plots in Series.
         */
        private readonly array $plot_order = [],
        array $plot_label = [],
        array $plot_category = [],
        array $plot_values = [],
        ?string $plot_direction = null,
        /**
         * Smooth Line. Must be specified for both DataSeries and DataSeriesValues.
         */
        private bool $smooth_line = false,
        /**
         * Plot Style.
         */
        private ?string $plot_style = null
    )
    {
        $keys = array_keys($plot_values);
        $this->plot_values = $plot_values;
        if (!isset($plot_label[$keys[0]])) {
            $plot_label[$keys[0]] = new Data_Series_Values();
        }
        $this->plot_label = $plot_label;
        if (!isset($plot_category[$keys[0]])) {
            $plot_category[$keys[0]] = new Data_Series_Values();
        }
        $this->plot_category = $plot_category;
        if ($plot_direction === null) {
            $plot_direction = self::DIRECTION_COL;
        }
        $this->plot_direction = $plot_direction;
    }
    /**
     * Get Plot Type.
     */
    public function get_plot_type(): ?string
    {
        return $this->plot_type;
    }
    /**
     * Set Plot Type.
     *
     * @return $this
     */
    public function set_plot_type(string $plot_type): static
    {
        $this->plot_type = $plot_type;
        return $this;
    }
    /**
     * Get Plot Grouping Type.
     */
    public function get_plot_grouping(): ?string
    {
        return $this->plot_grouping;
    }
    /**
     * Set Plot Grouping Type.
     *
     * @return $this
     */
    public function set_plot_grouping(string $grouping_type): static
    {
        $this->plot_grouping = $grouping_type;
        return $this;
    }
    /**
     * Get Plot Direction.
     */
    public function get_plot_direction(): string
    {
        return $this->plot_direction;
    }
    /**
     * Set Plot Direction.
     *
     * @return $this
     */
    public function set_plot_direction(string $plot_direction): static
    {
        $this->plot_direction = $plot_direction;
        return $this;
    }
    /**
     * Get Plot Order.
     *
     * @return int[]
     */
    public function get_plot_order(): array
    {
        return $this->plot_order;
    }
    /**
     * Get Plot Labels.
     *
     * @return DataSeriesValues[]
     */
    public function get_plot_labels(): array
    {
        return $this->plot_label;
    }
    /**
     * Get Plot Label by Index.
     *
     * @return DataSeriesValues|false
     */
    public function get_plot_label_by_index(int $index): bool|Data_Series_Values
    {
        $keys = array_keys($this->plot_label);
        if (in_array($index, $keys)) {
            return $this->plot_label[$index];
        }
        return false;
    }
    /**
     * Get Plot Categories.
     *
     * @return DataSeriesValues[]
     */
    public function get_plot_categories(): array
    {
        return $this->plot_category;
    }
    /**
     * Get Plot Category by Index.
     *
     * @return DataSeriesValues|false
     */
    public function get_plot_category_by_index(int $index): bool|Data_Series_Values
    {
        $keys = array_keys($this->plot_category);
        if (in_array($index, $keys)) {
            return $this->plot_category[$index];
        }
        if (isset($keys[$index])) {
            return $this->plot_category[$keys[$index]];
        }
        return false;
    }
    /**
     * Get Plot Style.
     */
    public function get_plot_style(): ?string
    {
        return $this->plot_style;
    }
    /**
     * Set Plot Style.
     *
     * @return $this
     */
    public function set_plot_style(?string $plot_style): static
    {
        $this->plot_style = $plot_style;
        return $this;
    }
    /**
     * Get Plot Values.
     *
     * @return DataSeriesValues[]
     */
    public function get_plot_values(): array
    {
        return $this->plot_values;
    }
    /**
     * Get Plot Values by Index.
     *
     * @return DataSeriesValues|false
     */
    public function get_plot_values_by_index(int $index): bool|Data_Series_Values
    {
        $keys = array_keys($this->plot_values);
        if (in_array($index, $keys)) {
            return $this->plot_values[$index];
        }
        return false;
    }
    /**
     * Get Plot Bubble Sizes.
     *
     * @return DataSeriesValues[]
     */
    public function get_plot_bubble_sizes(): array
    {
        return $this->plot_bubble_sizes;
    }
    /**
     * Set Plot Bubble Sizes.
     *
     * @param DataSeriesValues[] $plotBubbleSizes
     */
    public function set_plot_bubble_sizes(array $plot_bubble_sizes): self
    {
        $this->plot_bubble_sizes = $plot_bubble_sizes;
        return $this;
    }
    /**
     * Get Number of Plot Series.
     */
    public function get_plot_series_count(): int
    {
        return count($this->plot_values);
    }
    /**
     * Get Smooth Line.
     */
    public function get_smooth_line(): bool
    {
        return $this->smooth_line;
    }
    /**
     * Set Smooth Line.
     *
     * @return $this
     */
    public function set_smooth_line(bool $smooth_line): static
    {
        $this->smooth_line = $smooth_line;
        return $this;
    }
    public function refresh(Worksheet $worksheet): void
    {
        foreach ($this->plot_values as $plot_values) {
            $plot_values->refresh($worksheet, true);
        }
        foreach ($this->plot_label as $plot_values) {
            $plot_values->refresh($worksheet, true);
        }
        foreach ($this->plot_category as $plot_values) {
            $plot_values->refresh($worksheet, false);
        }
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $plot_labels = $this->plot_label;
        $this->plot_label = [];
        foreach ($plot_labels as $plot_label) {
            $this->plot_label[] = $plot_label;
        }
        $plot_categories = $this->plot_category;
        $this->plot_category = [];
        foreach ($plot_categories as $plot_category) {
            $this->plot_category[] = clone $plot_category;
        }
        $plot_values = $this->plot_values;
        $this->plot_values = [];
        foreach ($plot_values as $plot_value) {
            $this->plot_values[] = clone $plot_value;
        }
        $plot_bubble_sizes = $this->plot_bubble_sizes;
        $this->plot_bubble_sizes = [];
        foreach ($plot_bubble_sizes as $plot_bubble_size) {
            $this->plot_bubble_sizes[] = clone $plot_bubble_size;
        }
    }
}