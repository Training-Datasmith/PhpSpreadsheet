<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

/**
 * Created by PhpStorm.
 * User: Wiktor Trzonkowski
 * Date: 6/17/14
 * Time: 12:11 PM.
 */
class Axis extends Properties
{
    public const AXIS_TYPE_CATEGORY = 'catAx';
    public const AXIS_TYPE_DATE = 'dateAx';
    public const AXIS_TYPE_VALUE = 'valAx';
    public const TIME_UNIT_DAYS = 'days';
    public const TIME_UNIT_MONTHS = 'months';
    public const TIME_UNIT_YEARS = 'years';
    public function __construct()
    {
        parent::__construct();
        $this->fill_color = new Chart_Color();
    }
    /**
     * Chart Major Gridlines as.
     */
    private ?Grid_Lines $major_gridlines = null;
    /**
     * Chart Minor Gridlines as.
     */
    private ?Grid_Lines $minor_gridlines = null;
    /**
     * Axis Number.
     *
     * @var array{format: string, source_linked: int, numeric: ?bool}
     */
    private array $axis_number = ['format' => self::FORMAT_CODE_GENERAL, 'source_linked' => 1, 'numeric' => null];
    private string $axis_type = '';
    private ?Axis_Text $axis_text = null;
    private ?Title $disp_units_title = null;
    /**
     * Axis Options.
     *
     * @var array<string, null|string>
     */
    private array $axis_options = ['minimum' => null, 'maximum' => null, 'major_unit' => null, 'minor_unit' => null, 'orientation' => self::ORIENTATION_NORMAL, 'minor_tick_mark' => self::TICK_MARK_NONE, 'major_tick_mark' => self::TICK_MARK_NONE, 'axis_labels' => self::AXIS_LABELS_NEXT_TO, 'horizontal_crosses' => self::HORIZONTAL_CROSSES_AUTOZERO, 'horizontal_crosses_value' => null, 'textRotation' => null, 'hidden' => null, 'majorTimeUnit' => self::TIME_UNIT_YEARS, 'minorTimeUnit' => self::TIME_UNIT_MONTHS, 'baseTimeUnit' => self::TIME_UNIT_DAYS, 'logBase' => null, 'dispUnitsBuiltIn' => null];
    public const DISP_UNITS_HUNDREDS = 'hundreds';
    public const DISP_UNITS_THOUSANDS = 'thousands';
    public const DISP_UNITS_TEN_THOUSANDS = 'tenThousands';
    public const DISP_UNITS_HUNDRED_THOUSANDS = 'hundredThousands';
    public const DISP_UNITS_MILLIONS = 'millions';
    public const DISP_UNITS_TEN_MILLIONS = 'tenMillions';
    public const DISP_UNITS_HUNDRED_MILLIONS = 'hundredMillions';
    public const DISP_UNITS_BILLIONS = 'billions';
    public const DISP_UNITS_TRILLIONS = 'trillions';
    public const TRILLION_INDEX = PHP_INT_SIZE > 4 ? 1000000000000 : '1000000000000';
    public const DISP_UNITS_BUILTIN_INT = [100 => self::DISP_UNITS_HUNDREDS, 1000 => self::DISP_UNITS_THOUSANDS, 10000 => self::DISP_UNITS_TEN_THOUSANDS, 100000 => self::DISP_UNITS_HUNDRED_THOUSANDS, 1000000 => self::DISP_UNITS_MILLIONS, 10000000 => self::DISP_UNITS_TEN_MILLIONS, 100000000 => self::DISP_UNITS_HUNDRED_MILLIONS, 1000000000 => self::DISP_UNITS_BILLIONS, self::TRILLION_INDEX => self::DISP_UNITS_TRILLIONS];
    /**
     * Fill Properties.
     */
    private Chart_Color $fill_color;
    private const NUMERIC_FORMAT = [Properties::FORMAT_CODE_NUMBER, Properties::FORMAT_CODE_DATE, Properties::FORMAT_CODE_DATE_ISO8601];
    private bool $no_fill = false;
    /**
     * Get Series Data Type.
     */
    public function set_axis_number_properties(string $format_code, ?bool $numeric = null, int $source_linked = 0): void
    {
        $format = $format_code;
        $this->axis_number['format'] = $format;
        $this->axis_number['source_linked'] = $source_linked;
        if (is_bool($numeric)) {
            $this->axis_number['numeric'] = $numeric;
        } elseif (in_array($format, self::NUMERIC_FORMAT, true)) {
            $this->axis_number['numeric'] = true;
        }
    }
    /**
     * Get Axis Number Format Data Type.
     */
    public function get_axis_number_format(): string
    {
        return $this->axis_number['format'];
    }
    /**
     * Get Axis Number Source Linked.
     */
    public function get_axis_number_source_linked(): string
    {
        return (string) $this->axis_number['source_linked'];
    }
    public function get_axis_is_numeric_format(): bool
    {
        return $this->axis_type === self::AXIS_TYPE_DATE || (bool) $this->axis_number['numeric'];
    }
    public function set_axis_option(string $key, null|float|int|string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->axis_options[$key] = (string) $value;
        }
    }
    /**
     * Set Axis Options Properties.
     */
    public function set_axis_options_properties(string $axis_labels, ?string $horizontal_crosses_value = null, ?string $horizontal_crosses = null, ?string $axis_orientation = null, ?string $major_tmt = null, ?string $minor_tmt = null, null|float|int|string $minimum = null, null|float|int|string $maximum = null, null|float|int|string $major_unit = null, null|float|int|string $minor_unit = null, null|float|int|string $text_rotation = null, ?string $hidden = null, ?string $base_time_unit = null, ?string $major_time_unit = null, ?string $minor_time_unit = null, null|float|int|string $log_base = null, ?string $disp_units_built_in = null): void
    {
        $this->axis_options['axis_labels'] = $axis_labels;
        $this->set_axis_option('horizontal_crosses_value', $horizontal_crosses_value);
        $this->set_axis_option('horizontal_crosses', $horizontal_crosses);
        $this->set_axis_option('orientation', $axis_orientation);
        $this->set_axis_option('major_tick_mark', $major_tmt);
        $this->set_axis_option('minor_tick_mark', $minor_tmt);
        $this->set_axis_option('minimum', $minimum);
        $this->set_axis_option('maximum', $maximum);
        $this->set_axis_option('major_unit', $major_unit);
        $this->set_axis_option('minor_unit', $minor_unit);
        $this->set_axis_option('textRotation', $text_rotation);
        $this->set_axis_option('hidden', $hidden);
        $this->set_axis_option('baseTimeUnit', $base_time_unit);
        $this->set_axis_option('majorTimeUnit', $major_time_unit);
        $this->set_axis_option('minorTimeUnit', $minor_time_unit);
        $this->set_axis_option('logBase', $log_base);
        $this->set_axis_option('dispUnitsBuiltIn', $disp_units_built_in);
    }
    /**
     * Get Axis Options Property.
     */
    public function get_axis_options_property(string $property): ?string
    {
        if ($property !== 'textRotation') {
            return $this->axis_options[$property];
        }
        if ($this->axis_text === null) {
            return $this->axis_options[$property];
        }
        if ($this->axis_text->get_rotation() !== null) {
            return (string) $this->axis_text->get_rotation();
        }
        return $this->axis_options[$property];
    }
    /**
     * Set Axis Orientation Property.
     */
    public function set_axis_orientation(string $orientation): void
    {
        $this->axis_options['orientation'] = $orientation;
    }
    public function get_axis_type(): string
    {
        return $this->axis_type;
    }
    public function set_axis_type(string $type): self
    {
        if ($type === self::AXIS_TYPE_CATEGORY || $type === self::AXIS_TYPE_VALUE || $type === self::AXIS_TYPE_DATE) {
            $this->axis_type = $type;
        } else {
            $this->axis_type = '';
        }
        return $this;
    }
    /**
     * Set Fill Property.
     */
    public function set_fill_parameters(?string $color, ?int $alpha = null, ?string $alpha_type = Chart_Color::EXCEL_COLOR_TYPE_RGB): void
    {
        $this->fill_color->set_color_properties($color, $alpha, $alpha_type);
    }
    /**
     * Get Fill Property.
     */
    public function get_fill_property(string $property): string
    {
        return (string) $this->fill_color->get_color_property($property);
    }
    public function get_fill_color_object(): Chart_Color
    {
        return $this->fill_color;
    }
    private string $cross_between = '';
    // 'between' or 'midCat' might be better
    public function set_cross_between(string $cross_between): self
    {
        $this->cross_between = $cross_between;
        return $this;
    }
    public function get_cross_between(): string
    {
        return $this->cross_between;
    }
    public function get_major_gridlines(): ?Grid_Lines
    {
        return $this->major_gridlines;
    }
    public function get_minor_gridlines(): ?Grid_Lines
    {
        return $this->minor_gridlines;
    }
    public function set_major_gridlines(?Grid_Lines $gridlines): self
    {
        $this->major_gridlines = $gridlines;
        return $this;
    }
    public function set_minor_gridlines(?Grid_Lines $gridlines): self
    {
        $this->minor_gridlines = $gridlines;
        return $this;
    }
    public function get_axis_text(): ?Axis_Text
    {
        return $this->axis_text;
    }
    public function set_axis_text(?Axis_Text $axis_text): self
    {
        $this->axis_text = $axis_text;
        return $this;
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
    public function set_disp_units_title(?Title $disp_units_title): self
    {
        $this->disp_units_title = $disp_units_title;
        return $this;
    }
    public function get_disp_units_title(): ?Title
    {
        return $this->disp_units_title;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        parent::__clone();
        $this->major_gridlines = $this->minor_gridlines === null ? null : clone $this->minor_gridlines;
        $this->axis_text = $this->axis_text === null ? null : clone $this->axis_text;
        $this->disp_units_title = $this->disp_units_title === null ? null : clone $this->disp_units_title;
        $this->fill_color = clone $this->fill_color;
    }
}