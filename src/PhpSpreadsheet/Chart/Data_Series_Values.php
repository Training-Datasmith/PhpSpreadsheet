<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Data_Series_Values extends Properties
{
    public const DATASERIES_TYPE_STRING = 'String';
    public const DATASERIES_TYPE_NUMBER = 'Number';
    private const DATA_TYPE_VALUES = [self::DATASERIES_TYPE_STRING, self::DATASERIES_TYPE_NUMBER];
    /**
     * Series Data Type.
     */
    private string $data_type;
    private Chart_Color $marker_fill_color;
    private Chart_Color $marker_border_color;
    /**
     * Series Point Size.
     */
    private int $point_size = 3;
    /**
     * Fill color (can be array with colors if dataseries have custom colors).
     *
     * @var null|ChartColor|ChartColor[]
     */
    private array|\Php_Office\Php_Spreadsheet\Chart\Chart_Color|null $fill_color = null;
    private bool $scatter_lines = true;
    private bool $bubble3D = false;
    private ?Layout $label_layout = null;
    /** @var TrendLine[] */
    private array $trend_lines = [];
    /**
     * Create a new DataSeriesValues object.
     *
     * @param null|mixed[] $dataValues
     * @param null|ChartColor|ChartColor[]|string|string[] $fillColor
     */
    public function __construct(
        string $data_type = self::DATASERIES_TYPE_NUMBER,
        /**
         * Series Data Source.
         */
        private ?string $data_source = null,
        /**
         * Format Code.
         */
        private ?string $format_code = null,
        /**
         * Point Count (The number of datapoints in the dataseries).
         */
        private int $point_count = 0,
        /**
         * Data Values.
         */
        private ?array $data_values = [],
        /**
         * Series Point Marker.
         */
        private ?string $point_marker = null,
        null|Chart_Color|array|string $fill_color = null,
        int|string $point_size = 3
    )
    {
        parent::__construct();
        $this->marker_fill_color = new Chart_Color();
        $this->marker_border_color = new Chart_Color();
        $this->set_data_type($data_type);
        if ($fill_color !== null) {
            $this->set_fill_color($fill_color);
        }
        if (is_numeric($point_size)) {
            $this->point_size = (int) $point_size;
        }
    }
    /**
     * Get Series Data Type.
     */
    public function get_data_type(): string
    {
        return $this->data_type;
    }
    /**
     * Set Series Data Type.
     *
     * @param string $dataType Datatype of this data series
     *                                Typical values are:
     *                                    DataSeriesValues::DATASERIES_TYPE_STRING
     *                                        Normally used for axis point values
     *                                    DataSeriesValues::DATASERIES_TYPE_NUMBER
     *                                        Normally used for chart data values
     *
     * @return $this
     */
    public function set_data_type(string $data_type): static
    {
        if (!in_array($data_type, self::DATA_TYPE_VALUES)) {
            throw new Exception('Invalid datatype for chart data series values');
        }
        $this->data_type = $data_type;
        return $this;
    }
    /**
     * Get Series Data Source (formula).
     */
    public function get_data_source(): ?string
    {
        return $this->data_source;
    }
    /**
     * Set Series Data Source (formula).
     *
     * @return $this
     */
    public function set_data_source(?string $data_source): static
    {
        $this->data_source = $data_source;
        return $this;
    }
    /**
     * Get Point Marker.
     */
    public function get_point_marker(): ?string
    {
        return $this->point_marker;
    }
    /**
     * Set Point Marker.
     *
     * @return $this
     */
    public function set_point_marker(string $marker): static
    {
        $this->point_marker = $marker;
        return $this;
    }
    public function get_marker_fill_color(): Chart_Color
    {
        return $this->marker_fill_color;
    }
    public function get_marker_border_color(): Chart_Color
    {
        return $this->marker_border_color;
    }
    /**
     * Get Point Size.
     */
    public function get_point_size(): int
    {
        return $this->point_size;
    }
    /**
     * Set Point Size.
     *
     * @return $this
     */
    public function set_point_size(int $size = 3): static
    {
        $this->point_size = $size;
        return $this;
    }
    /**
     * Get Series Format Code.
     */
    public function get_format_code(): ?string
    {
        return $this->format_code;
    }
    /**
     * Set Series Format Code.
     *
     * @return $this
     */
    public function set_format_code(string $format_code): static
    {
        $this->format_code = $format_code;
        return $this;
    }
    /**
     * Get Series Point Count.
     */
    public function get_point_count(): int
    {
        return $this->point_count;
    }
    /**
     * Get fill color object.
     *
     * @return null|ChartColor|ChartColor[]
     */
    public function get_fill_color_object()
    {
        return $this->fill_color;
    }
    private function string_to_chart_color(string $fill_string): Chart_Color
    {
        $value = $type = '';
        if (str_starts_with($fill_string, '*')) {
            $type = 'schemeClr';
            $value = substr($fill_string, 1);
        } elseif (str_starts_with($fill_string, '/')) {
            $type = 'prstClr';
            $value = substr($fill_string, 1);
        } elseif ($fill_string !== '') {
            $type = 'srgbClr';
            $value = $fill_string;
            $this->validate_color($value);
        }
        return new Chart_Color($value, null, $type);
    }
    private function chart_color_to_string(Chart_Color $chart_color): string
    {
        $type = (string) $chart_color->get_color_property('type');
        $value = (string) $chart_color->get_color_property('value');
        if ($type === '' || $value === '') {
            return '';
        }
        if ($type === 'schemeClr') {
            return "*{$value}";
        }
        if ($type === 'prstClr') {
            return "/{$value}";
        }
        return $value;
    }
    /**
     * Get fill color.
     *
     * @return string|string[] HEX color or array with HEX colors
     */
    public function get_fill_color(): string|array
    {
        if ($this->fill_color === null) {
            return '';
        }
        if (is_array($this->fill_color)) {
            $array = [];
            foreach ($this->fill_color as $chart_color) {
                $array[] = $this->chart_color_to_string($chart_color);
            }
            return $array;
        }
        return $this->chart_color_to_string($this->fill_color);
    }
    /**
     * Set fill color for series.
     *
     * @param ChartColor|ChartColor[]|string|string[] $color HEX color or array with HEX colors
     *
     * @return   $this
     */
    public function set_fill_color($color): static
    {
        if (is_array($color)) {
            $this->fill_color = [];
            foreach ($color as $fill_string) {
                if ($fill_string instanceof Chart_Color) {
                    $this->fill_color[] = $fill_string;
                } else {
                    $this->fill_color[] = $this->string_to_chart_color($fill_string);
                }
            }
        } elseif ($color instanceof Chart_Color) {
            $this->fill_color = $color;
        } else {
            $this->fill_color = $this->string_to_chart_color($color);
        }
        return $this;
    }
    /**
     * Method for validating hex color.
     *
     * @param string $color value for color
     */
    private function validate_color(string $color): void
    {
        if (!preg_match('/^[a-f0-9]{6}$/i', $color)) {
            throw new Exception(sprintf('Invalid hex color for chart series (color: "%s")', $color));
        }
    }
    /**
     * Get line width for series.
     */
    public function get_line_width(): null|float|int
    {
        /** @var null|float|int */
        $temp = $this->line_style_properties['width'];
        return $temp;
    }
    /**
     * Set line width for the series.
     *
     * @return $this
     */
    public function set_line_width(null|float|int $width): static
    {
        $this->line_style_properties['width'] = $width;
        return $this;
    }
    /**
     * Identify if the Data Series is a multi-level or a simple series.
     */
    public function is_multi_level_series(): ?bool
    {
        if (!empty($this->data_values)) {
            return is_array(array_values($this->data_values)[0]);
        }
        return null;
    }
    /**
     * Return the level count of a multi-level Data Series.
     */
    public function multi_level_count(): int
    {
        $level_count = 0;
        foreach ($this->data_values ?? [] as $data_value_set) {
            /** @var mixed[] $dataValueSet */
            $level_count = max($level_count, count($data_value_set));
        }
        return $level_count;
    }
    /**
     * Get Series Data Values.
     *
     * @return null|mixed[]
     */
    public function get_data_values(): ?array
    {
        return $this->data_values;
    }
    /**
     * Get the first Series Data value.
     */
    public function get_data_value(): mixed
    {
        if ($this->data_values === null) {
            return null;
        }
        $count = count($this->data_values);
        if ($count == 0) {
            return null;
        }
        if ($count == 1) {
            return $this->data_values[0];
        }
        return $this->data_values;
    }
    /**
     * Set Series Data Values.
     *
     * @param mixed[] $dataValues
     *
     * @return $this
     */
    public function set_data_values(array $data_values): static
    {
        $this->data_values = Functions::flatten_array($data_values);
        $this->point_count = count($data_values);
        return $this;
    }
    public function refresh(Worksheet $worksheet, bool $flatten = true): void
    {
        if ($this->data_source !== null) {
            $calc_engine = Calculation::get_instance($worksheet->get_parent());
            $new_data_values = Calculation::unwrap_result($calc_engine->_calculate_formula_value('=' . $this->data_source, null, $worksheet->get_cell('A1')));
            if ($flatten) {
                $this->data_values = Functions::flatten_array($new_data_values);
                foreach ($this->data_values as &$data_value) {
                    if (is_string($data_value) && !empty($data_value) && $data_value[0] == '#') {
                        $data_value = 0.0;
                    }
                }
                unset($data_value);
            } else {
                [, $cell_range] = Worksheet::extract_sheet_title($this->data_source, true);
                $dimensions = Coordinate::range_dimension(str_replace('$', '', $cell_range ?? ''));
                if ($dimensions[0] == 1 || $dimensions[1] == 1) {
                    $this->data_values = Functions::flatten_array($new_data_values);
                } else {
                    /** @var array<int, mixed[]> */
                    $new_data_valuesx = $new_data_values;
                    /** @var mixed[][] $newArray */
                    $new_array = array_values(array_shift($new_data_valuesx) ?? []);
                    foreach ($new_array as $i => $new_data_set) {
                        $new_array[$i] = [$new_data_set];
                    }
                    foreach ($new_data_valuesx as $new_data_set) {
                        $i = 0;
                        foreach ($new_data_set as $new_data_val) {
                            array_unshift($new_array[$i++], $new_data_val);
                        }
                    }
                    $this->data_values = $new_array;
                }
            }
            $this->point_count = count($this->data_values ?? []);
        }
    }
    public function get_scatter_lines(): bool
    {
        return $this->scatter_lines;
    }
    public function set_scatter_lines(bool $scatter_lines): self
    {
        $this->scatter_lines = $scatter_lines;
        return $this;
    }
    public function get_bubble3d(): bool
    {
        return $this->bubble3D;
    }
    public function set_bubble3d(bool $bubble3D): self
    {
        $this->bubble3D = $bubble3D;
        return $this;
    }
    /**
     * Smooth Line. Must be specified for both DataSeries and DataSeriesValues.
     */
    private bool $smooth_line = false;
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
    public function get_label_layout(): ?Layout
    {
        return $this->label_layout;
    }
    public function set_label_layout(?Layout $label_layout): self
    {
        $this->label_layout = $label_layout;
        return $this;
    }
    /** @param TrendLine[] $trendLines */
    public function set_trend_lines(array $trend_lines): self
    {
        $this->trend_lines = $trend_lines;
        return $this;
    }
    /** @return TrendLine[] */
    public function get_trend_lines(): array
    {
        return $this->trend_lines;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        parent::__clone();
        $this->marker_fill_color = clone $this->marker_fill_color;
        $this->marker_border_color = clone $this->marker_border_color;
        if (is_array($this->fill_color)) {
            $fill_color = $this->fill_color;
            $this->fill_color = [];
            foreach ($fill_color as $color) {
                $this->fill_color[] = clone $color;
            }
        } elseif ($this->fill_color instanceof Chart_Color) {
            $this->fill_color = clone $this->fill_color;
        }
        $this->label_layout = $this->label_layout === null ? null : clone $this->label_layout;
        $trend_lines = $this->trend_lines;
        $this->trend_lines = [];
        foreach ($trend_lines as $trend_line) {
            $this->trend_lines[] = clone $trend_line;
        }
    }
}