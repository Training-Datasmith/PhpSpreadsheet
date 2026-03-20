<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

/**
 * Created by PhpStorm.
 * User: nhw2h8s
 * Date: 7/2/14
 * Time: 5:45 PM.
 */
abstract class Properties
{
    public const AXIS_LABELS_LOW = 'low';
    public const AXIS_LABELS_HIGH = 'high';
    public const AXIS_LABELS_NEXT_TO = 'nextTo';
    public const AXIS_LABELS_NONE = 'none';
    public const TICK_MARK_NONE = 'none';
    public const TICK_MARK_INSIDE = 'in';
    public const TICK_MARK_OUTSIDE = 'out';
    public const TICK_MARK_CROSS = 'cross';
    public const HORIZONTAL_CROSSES_AUTOZERO = 'autoZero';
    public const HORIZONTAL_CROSSES_MAXIMUM = 'max';
    public const FORMAT_CODE_GENERAL = 'General';
    public const FORMAT_CODE_NUMBER = '#,##0.00';
    public const FORMAT_CODE_CURRENCY = '$#,##0.00';
    public const FORMAT_CODE_ACCOUNTING = '_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)';
    public const FORMAT_CODE_DATE = 'm/d/yyyy';
    public const FORMAT_CODE_DATE_ISO8601 = 'yyyy-mm-dd';
    public const FORMAT_CODE_TIME = '[$-F400]h:mm:ss AM/PM';
    public const FORMAT_CODE_PERCENTAGE = '0.00%';
    public const FORMAT_CODE_FRACTION = '# ?/?';
    public const FORMAT_CODE_SCIENTIFIC = '0.00E+00';
    public const FORMAT_CODE_TEXT = '@';
    public const FORMAT_CODE_SPECIAL = '00000';
    public const ORIENTATION_NORMAL = 'minMax';
    public const ORIENTATION_REVERSED = 'maxMin';
    public const LINE_STYLE_COMPOUND_SIMPLE = 'sng';
    public const LINE_STYLE_COMPOUND_DOUBLE = 'dbl';
    public const LINE_STYLE_COMPOUND_THICKTHIN = 'thickThin';
    public const LINE_STYLE_COMPOUND_THINTHICK = 'thinThick';
    public const LINE_STYLE_COMPOUND_TRIPLE = 'tri';
    public const LINE_STYLE_DASH_SOLID = 'solid';
    public const LINE_STYLE_DASH_ROUND_DOT = 'sysDot';
    public const LINE_STYLE_DASH_SQUARE_DOT = 'sysDash';
    public const LINE_STYPE_DASH_DASH = 'dash';
    public const LINE_STYLE_DASH_DASH_DOT = 'dashDot';
    public const LINE_STYLE_DASH_LONG_DASH = 'lgDash';
    public const LINE_STYLE_DASH_LONG_DASH_DOT = 'lgDashDot';
    public const LINE_STYLE_DASH_LONG_DASH_DOT_DOT = 'lgDashDotDot';
    public const LINE_STYLE_CAP_SQUARE = 'sq';
    public const LINE_STYLE_CAP_ROUND = 'rnd';
    public const LINE_STYLE_CAP_FLAT = 'flat';
    public const LINE_STYLE_JOIN_ROUND = 'round';
    public const LINE_STYLE_JOIN_MITER = 'miter';
    public const LINE_STYLE_JOIN_BEVEL = 'bevel';
    public const LINE_STYLE_ARROW_TYPE_NOARROW = null;
    public const LINE_STYLE_ARROW_TYPE_ARROW = 'triangle';
    public const LINE_STYLE_ARROW_TYPE_OPEN = 'arrow';
    public const LINE_STYLE_ARROW_TYPE_STEALTH = 'stealth';
    public const LINE_STYLE_ARROW_TYPE_DIAMOND = 'diamond';
    public const LINE_STYLE_ARROW_TYPE_OVAL = 'oval';
    public const LINE_STYLE_ARROW_SIZE_1 = 1;
    public const LINE_STYLE_ARROW_SIZE_2 = 2;
    public const LINE_STYLE_ARROW_SIZE_3 = 3;
    public const LINE_STYLE_ARROW_SIZE_4 = 4;
    public const LINE_STYLE_ARROW_SIZE_5 = 5;
    public const LINE_STYLE_ARROW_SIZE_6 = 6;
    public const LINE_STYLE_ARROW_SIZE_7 = 7;
    public const LINE_STYLE_ARROW_SIZE_8 = 8;
    public const LINE_STYLE_ARROW_SIZE_9 = 9;
    public const SHADOW_PRESETS_NOSHADOW = null;
    public const SHADOW_PRESETS_OUTER_BOTTTOM_RIGHT = 1;
    public const SHADOW_PRESETS_OUTER_BOTTOM = 2;
    public const SHADOW_PRESETS_OUTER_BOTTOM_LEFT = 3;
    public const SHADOW_PRESETS_OUTER_RIGHT = 4;
    public const SHADOW_PRESETS_OUTER_CENTER = 5;
    public const SHADOW_PRESETS_OUTER_LEFT = 6;
    public const SHADOW_PRESETS_OUTER_TOP_RIGHT = 7;
    public const SHADOW_PRESETS_OUTER_TOP = 8;
    public const SHADOW_PRESETS_OUTER_TOP_LEFT = 9;
    public const SHADOW_PRESETS_INNER_BOTTTOM_RIGHT = 10;
    public const SHADOW_PRESETS_INNER_BOTTOM = 11;
    public const SHADOW_PRESETS_INNER_BOTTOM_LEFT = 12;
    public const SHADOW_PRESETS_INNER_RIGHT = 13;
    public const SHADOW_PRESETS_INNER_CENTER = 14;
    public const SHADOW_PRESETS_INNER_LEFT = 15;
    public const SHADOW_PRESETS_INNER_TOP_RIGHT = 16;
    public const SHADOW_PRESETS_INNER_TOP = 17;
    public const SHADOW_PRESETS_INNER_TOP_LEFT = 18;
    public const SHADOW_PRESETS_PERSPECTIVE_BELOW = 19;
    public const SHADOW_PRESETS_PERSPECTIVE_UPPER_RIGHT = 20;
    public const SHADOW_PRESETS_PERSPECTIVE_UPPER_LEFT = 21;
    public const SHADOW_PRESETS_PERSPECTIVE_LOWER_RIGHT = 22;
    public const SHADOW_PRESETS_PERSPECTIVE_LOWER_LEFT = 23;
    public const POINTS_WIDTH_MULTIPLIER = 12700;
    public const ANGLE_MULTIPLIER = 60000;
    // direction and size-kx size-ky
    public const PERCENTAGE_MULTIPLIER = 100000;
    // size sx and sy, and gradient pos
    public const MAX_SKEW_ANGLE_XML = 90 * self::ANGLE_MULTIPLIER - 1;
    public const MAX_SKEW_ANGLE_DEGREES = self::MAX_SKEW_ANGLE_XML / self::ANGLE_MULTIPLIER;
    // max for size-kx size-ky
    protected bool $object_state = false;
    // used only for minor gridlines
    protected ?float $glow_size = null;
    protected Chart_Color $glow_color;
    /** @var array{size: ?float} */
    protected array $soft_edges = ['size' => null];
    /** @var mixed[] */
    protected array $shadow_properties = self::PRESETS_OPTIONS[0];
    protected Chart_Color $shadow_color;
    public function __construct()
    {
        $this->line_color = new Chart_Color();
        $this->glow_color = new Chart_Color();
        $this->shadow_color = new Chart_Color();
        $this->shadow_color->set_type(Chart_Color::EXCEL_COLOR_TYPE_STANDARD);
        $this->shadow_color->set_value('black');
        $this->shadow_color->set_alpha(40);
    }
    /**
     * Get Object State.
     */
    public function get_object_state(): bool
    {
        return $this->object_state;
    }
    /**
     * Change Object State to True.
     *
     * @return $this
     */
    public function activate_object()
    {
        $this->object_state = true;
        return $this;
    }
    public static function points_to_xml(float $width): string
    {
        return (string) (int) ($width * self::POINTS_WIDTH_MULTIPLIER);
    }
    public static function xml_to_points(string $width): float
    {
        return (float) $width / self::POINTS_WIDTH_MULTIPLIER;
    }
    public static function angle_to_xml(float $angle): string
    {
        return (string) (int) ($angle * self::ANGLE_MULTIPLIER);
    }
    public static function xml_to_angle(string $angle): float
    {
        return (float) $angle / self::ANGLE_MULTIPLIER;
    }
    public static function tenth_of_percent_to_xml(float $value): string
    {
        return (string) (int) ($value * self::PERCENTAGE_MULTIPLIER);
    }
    public static function xml_to_tenth_of_percent(string $value): float
    {
        return (float) $value / self::PERCENTAGE_MULTIPLIER;
    }
    /** @return array{type: ?string, value: ?string, alpha: ?int} */
    protected function set_color_properties(?string $color, null|float|int|string $alpha, ?string $color_type): array
    {
        return ['type' => $color_type, 'value' => $color, 'alpha' => $alpha === null ? null : (int) $alpha];
    }
    protected const PRESETS_OPTIONS = [
        //NONE
        0 => [
            'presets' => self::SHADOW_PRESETS_NOSHADOW,
            'effect' => null,
            //'color' => [
            //    'type' => ChartColor::EXCEL_COLOR_TYPE_STANDARD,
            //    'value' => 'black',
            //    'alpha' => 40,
            //],
            'size' => ['sx' => null, 'sy' => null, 'kx' => null, 'ky' => null],
            'blur' => null,
            'direction' => null,
            'distance' => null,
            'algn' => null,
            'rotWithShape' => null,
        ],
        //OUTER
        1 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 2700000 / self::ANGLE_MULTIPLIER, 'algn' => 'tl', 'rotWithShape' => '0'],
        2 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 5400000 / self::ANGLE_MULTIPLIER, 'algn' => 't', 'rotWithShape' => '0'],
        3 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 8100000 / self::ANGLE_MULTIPLIER, 'algn' => 'tr', 'rotWithShape' => '0'],
        4 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'algn' => 'l', 'rotWithShape' => '0'],
        5 => ['effect' => 'outerShdw', 'size' => ['sx' => 102000 / self::PERCENTAGE_MULTIPLIER, 'sy' => 102000 / self::PERCENTAGE_MULTIPLIER], 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'algn' => 'ctr', 'rotWithShape' => '0'],
        6 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 10800000 / self::ANGLE_MULTIPLIER, 'algn' => 'r', 'rotWithShape' => '0'],
        7 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 18900000 / self::ANGLE_MULTIPLIER, 'algn' => 'bl', 'rotWithShape' => '0'],
        8 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 16200000 / self::ANGLE_MULTIPLIER, 'rotWithShape' => '0'],
        9 => ['effect' => 'outerShdw', 'blur' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 38100 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 13500000 / self::ANGLE_MULTIPLIER, 'algn' => 'br', 'rotWithShape' => '0'],
        //INNER
        10 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 2700000 / self::ANGLE_MULTIPLIER],
        11 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 5400000 / self::ANGLE_MULTIPLIER],
        12 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 8100000 / self::ANGLE_MULTIPLIER],
        13 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER],
        14 => ['effect' => 'innerShdw', 'blur' => 114300 / self::POINTS_WIDTH_MULTIPLIER],
        15 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 10800000 / self::ANGLE_MULTIPLIER],
        16 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 18900000 / self::ANGLE_MULTIPLIER],
        17 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 16200000 / self::ANGLE_MULTIPLIER],
        18 => ['effect' => 'innerShdw', 'blur' => 63500 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 50800 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 13500000 / self::ANGLE_MULTIPLIER],
        //perspective
        19 => ['effect' => 'outerShdw', 'blur' => 152400 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 317500 / self::POINTS_WIDTH_MULTIPLIER, 'size' => ['sx' => 90000 / self::PERCENTAGE_MULTIPLIER, 'sy' => -19000 / self::PERCENTAGE_MULTIPLIER], 'direction' => 5400000 / self::ANGLE_MULTIPLIER, 'rotWithShape' => '0'],
        20 => ['effect' => 'outerShdw', 'blur' => 76200 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 18900000 / self::ANGLE_MULTIPLIER, 'size' => ['sy' => 23000 / self::PERCENTAGE_MULTIPLIER, 'kx' => -1200000 / self::ANGLE_MULTIPLIER], 'algn' => 'bl', 'rotWithShape' => '0'],
        21 => ['effect' => 'outerShdw', 'blur' => 76200 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 13500000 / self::ANGLE_MULTIPLIER, 'size' => ['sy' => 23000 / self::PERCENTAGE_MULTIPLIER, 'kx' => 1200000 / self::ANGLE_MULTIPLIER], 'algn' => 'br', 'rotWithShape' => '0'],
        22 => ['effect' => 'outerShdw', 'blur' => 76200 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 12700 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 2700000 / self::ANGLE_MULTIPLIER, 'size' => ['sy' => -23000 / self::PERCENTAGE_MULTIPLIER, 'kx' => -800400 / self::ANGLE_MULTIPLIER], 'algn' => 'bl', 'rotWithShape' => '0'],
        23 => ['effect' => 'outerShdw', 'blur' => 76200 / self::POINTS_WIDTH_MULTIPLIER, 'distance' => 12700 / self::POINTS_WIDTH_MULTIPLIER, 'direction' => 8100000 / self::ANGLE_MULTIPLIER, 'size' => ['sy' => -23000 / self::PERCENTAGE_MULTIPLIER, 'kx' => 800400 / self::ANGLE_MULTIPLIER], 'algn' => 'br', 'rotWithShape' => '0'],
    ];
    /** @return mixed[] */
    protected function get_shadow_presets_map(int $presets_option): array
    {
        return self::PRESETS_OPTIONS[$presets_option] ?? self::PRESETS_OPTIONS[0];
    }
    /**
     * Get value of array element.
     *
     * @param mixed[] $properties
     * @param array<mixed>|int|string $elements
     */
    protected function get_array_elements_value(array $properties, array|int|string $elements): mixed
    {
        $reference =& $properties;
        if (!is_array($elements)) {
            return $reference[$elements];
        }
        foreach ($elements as $keys) {
            $reference =& $reference[$keys];
            //* @phpstan-ignore-line
        }
        return $reference;
    }
    /**
     * Set Glow Properties.
     */
    public function set_glow_properties(float $size, ?string $color_value = null, ?int $color_alpha = null, ?string $color_type = null): void
    {
        $this->activate_object()->set_glow_size($size);
        $this->glow_color->set_color_properties_array(['value' => $color_value, 'type' => $color_type, 'alpha' => $color_alpha]);
    }
    /**
     * Get Glow Property.
     *
     * @param mixed[]|string $property
     *
     * @return null|array<mixed>|float|int|string
     */
    public function get_glow_property(array|string $property): null|array|float|int|string
    {
        $ret_val = null;
        if ($property === 'size') {
            $ret_val = $this->glow_size;
        } elseif ($property === 'color') {
            $ret_val = ['value' => $this->glow_color->get_color_property('value'), 'type' => $this->glow_color->get_color_property('type'), 'alpha' => $this->glow_color->get_color_property('alpha')];
        } elseif (is_array($property) && count($property) >= 2 && $property[0] === 'color') {
            /** @var string */
            $temp = $property[1];
            $ret_val = $this->glow_color->get_color_property($temp);
        }
        return $ret_val;
    }
    /**
     * Get Glow Color Property.
     */
    public function get_glow_color(string $property_name): null|int|string
    {
        return $this->glow_color->get_color_property($property_name);
    }
    public function get_glow_color_object(): Chart_Color
    {
        return $this->glow_color;
    }
    /**
     * Get Glow Size.
     */
    public function get_glow_size(): ?float
    {
        return $this->glow_size;
    }
    /**
     * Set Glow Size.
     *
     * @return $this
     */
    protected function set_glow_size(?float $size)
    {
        $this->glow_size = $size;
        return $this;
    }
    /**
     * Set Soft Edges Size.
     */
    public function set_soft_edges(?float $size): void
    {
        if ($size !== null) {
            $this->activate_object();
            $this->soft_edges['size'] = $size;
        }
    }
    /**
     * Get Soft Edges Size.
     */
    public function get_soft_edges_size(): ?float
    {
        return $this->soft_edges['size'];
    }
    /** @param null|array{value?: ?string, alpha?: null|int|string, brightness?: null|int|string, type?: ?string}|float|string  $value */
    public function set_shadow_property(string $property_name, mixed $value): self
    {
        $this->activate_object();
        if ($property_name === 'color' && is_array($value)) {
            /** @var array{value: ?string, alpha: null|int|string, brightness?: null|int|string, type: ?string} */
            $valuex = $value;
            $this->shadow_color->set_color_properties_array($valuex);
        } else {
            $this->shadow_properties[$property_name] = $value;
        }
        return $this;
    }
    /**
     * Set Shadow Properties.
     */
    public function set_shadow_properties(int $presets, ?string $color_value = null, ?string $color_type = null, null|float|int|string $color_alpha = null, ?float $blur = null, ?int $angle = null, ?float $distance = null): void
    {
        $this->activate_object()->set_shadow_presets_properties($presets);
        if ($presets === 0) {
            $this->shadow_color->set_type(Chart_Color::EXCEL_COLOR_TYPE_STANDARD);
            $this->shadow_color->set_value('black');
            $this->shadow_color->set_alpha(40);
        }
        if ($color_value !== null) {
            $this->shadow_color->set_value($color_value);
        }
        if ($color_type !== null) {
            $this->shadow_color->set_type($color_type);
        }
        if (is_numeric($color_alpha)) {
            $this->shadow_color->set_alpha((int) $color_alpha);
        }
        $this->set_shadow_blur($blur)->set_shadow_angle($angle)->set_shadow_distance($distance);
    }
    /**
     * Set Shadow Presets Properties.
     *
     * @return $this
     */
    protected function set_shadow_presets_properties(int $presets)
    {
        $this->shadow_properties['presets'] = $presets;
        $this->set_shadow_properties_map_values($this->get_shadow_presets_map($presets));
        return $this;
    }
    protected const SHADOW_ARRAY_KEYS = ['size', 'color'];
    /**
     * Set Shadow Properties Values.
     *
     * @param mixed[] $propertiesMap
     * @param null|mixed[] $reference
     *
     * @return $this
     */
    protected function set_shadow_properties_map_values(array $properties_map, ?array &$reference = null)
    {
        $base_reference = $reference;
        foreach ($properties_map as $property_key => $property_val) {
            if (is_array($property_val)) {
                if (in_array($property_key, self::SHADOW_ARRAY_KEYS, true)) {
                    $temp =& $this->shadow_properties[$property_key];
                    $reference =& $temp;
                    $this->set_shadow_properties_map_values($property_val, $reference);
                }
            } else if ($base_reference === null) {
                $this->shadow_properties[$property_key] = $property_val;
            } else {
                $reference[$property_key] = $property_val;
            }
        }
        return $this;
    }
    /**
     * Set Shadow Blur.
     *
     * @return $this
     */
    protected function set_shadow_blur(?float $blur)
    {
        if ($blur !== null) {
            $this->shadow_properties['blur'] = $blur;
        }
        return $this;
    }
    /**
     * Set Shadow Angle.
     *
     * @return $this
     */
    protected function set_shadow_angle(null|float|int|string $angle)
    {
        if (is_numeric($angle)) {
            $this->shadow_properties['direction'] = $angle;
        }
        return $this;
    }
    /**
     * Set Shadow Distance.
     *
     * @return $this
     */
    protected function set_shadow_distance(?float $distance)
    {
        if ($distance !== null) {
            $this->shadow_properties['distance'] = $distance;
        }
        return $this;
    }
    public function get_shadow_color_object(): Chart_Color
    {
        return $this->shadow_color;
    }
    /**
     * Get Shadow Property.
     *
     * @param string|string[] $elements
     *
     * @return null|mixed[]|string
     */
    public function get_shadow_property($elements): array|string|null
    {
        if ($elements === 'color') {
            return ['value' => $this->shadow_color->get_value(), 'type' => $this->shadow_color->get_type(), 'alpha' => $this->shadow_color->get_alpha()];
        }
        $ret_val = $this->get_array_elements_value($this->shadow_properties, $elements);
        if (is_scalar($ret_val)) {
            $ret_val = (string) $ret_val;
        } elseif ($ret_val !== null && !is_array($ret_val)) {
            // @codeCoverageIgnoreStart
            throw new Exception('Unexpected value for shadowProperty');
            // @codeCoverageIgnoreEnd
        }
        return $ret_val;
    }
    /** @return mixed[] */
    public function get_shadow_array(): array
    {
        $array = $this->shadow_properties;
        if ($this->get_shadow_color_object()->is_usable()) {
            $array['color'] = $this->get_shadow_property('color');
        }
        return $array;
    }
    protected Chart_Color $line_color;
    /** @var array{width: null|float|int|string, compound: ?string, dash: ?string, cap: ?string, join: ?string, arrow: array{head: array{type: ?string, size: null|int|string, w: ?string, len: ?string}, end: array{type: ?string, size: null|int|string, w: ?string, len: ?string}}} */
    protected array $line_style_properties = [
        'width' => null,
        //'9525',
        'compound' => '',
        //self::LINE_STYLE_COMPOUND_SIMPLE,
        'dash' => '',
        //self::LINE_STYLE_DASH_SOLID,
        'cap' => '',
        //self::LINE_STYLE_CAP_FLAT,
        'join' => '',
        //self::LINE_STYLE_JOIN_BEVEL,
        'arrow' => ['head' => [
            'type' => '',
            //self::LINE_STYLE_ARROW_TYPE_NOARROW,
            'size' => '',
            //self::LINE_STYLE_ARROW_SIZE_5,
            'w' => '',
            'len' => '',
        ], 'end' => [
            'type' => '',
            //self::LINE_STYLE_ARROW_TYPE_NOARROW,
            'size' => '',
            //self::LINE_STYLE_ARROW_SIZE_8,
            'w' => '',
            'len' => '',
        ]],
    ];
    public function copy_line_styles(self $other_properties): void
    {
        $this->line_style_properties = $other_properties->line_style_properties;
        $this->line_color = $other_properties->line_color;
        $this->glow_size = $other_properties->glow_size;
        $this->glow_color = $other_properties->glow_color;
        $this->soft_edges = $other_properties->soft_edges;
        $this->shadow_properties = $other_properties->shadow_properties;
    }
    public function get_line_color(): Chart_Color
    {
        return $this->line_color;
    }
    /**
     * Set Line Color Properties.
     */
    public function set_line_color_properties(?string $value, ?int $alpha = null, ?string $color_type = null): void
    {
        $this->activate_object();
        $this->line_color->set_color_properties_array($this->set_color_properties($value, $alpha, $color_type));
    }
    /**
     * Get Line Color Property.
     */
    public function get_line_color_property(string $property_name): null|int|string
    {
        return $this->line_color->get_color_property($property_name);
    }
    /**
     * Set Line Style Properties.
     */
    public function set_line_style_properties(null|float|int|string $line_width = null, ?string $compound_type = '', ?string $dash_type = '', ?string $cap_type = '', ?string $join_type = '', ?string $head_arrow_type = '', int $head_arrow_size = 0, ?string $end_arrow_type = '', int $end_arrow_size = 0, ?string $head_arrow_width = '', ?string $head_arrow_length = '', ?string $end_arrow_width = '', ?string $end_arrow_length = ''): void
    {
        $this->activate_object();
        if (is_numeric($line_width)) {
            $this->line_style_properties['width'] = $line_width;
        }
        if ($compound_type !== '') {
            $this->line_style_properties['compound'] = $compound_type;
        }
        if ($dash_type !== '') {
            $this->line_style_properties['dash'] = $dash_type;
        }
        if ($cap_type !== '') {
            $this->line_style_properties['cap'] = $cap_type;
        }
        if ($join_type !== '') {
            $this->line_style_properties['join'] = $join_type;
        }
        if ($head_arrow_type !== '') {
            $this->line_style_properties['arrow']['head']['type'] = $head_arrow_type;
        }
        if (isset(self::ARROW_SIZES[$head_arrow_size])) {
            $this->line_style_properties['arrow']['head']['size'] = $head_arrow_size;
            $this->line_style_properties['arrow']['head']['w'] = self::ARROW_SIZES[$head_arrow_size]['w'];
            $this->line_style_properties['arrow']['head']['len'] = self::ARROW_SIZES[$head_arrow_size]['len'];
        }
        if ($end_arrow_type !== '') {
            $this->line_style_properties['arrow']['end']['type'] = $end_arrow_type;
        }
        if (isset(self::ARROW_SIZES[$end_arrow_size])) {
            $this->line_style_properties['arrow']['end']['size'] = $end_arrow_size;
            $this->line_style_properties['arrow']['end']['w'] = self::ARROW_SIZES[$end_arrow_size]['w'];
            $this->line_style_properties['arrow']['end']['len'] = self::ARROW_SIZES[$end_arrow_size]['len'];
        }
        if ($head_arrow_width !== '') {
            $this->line_style_properties['arrow']['head']['w'] = $head_arrow_width;
        }
        if ($head_arrow_length !== '') {
            $this->line_style_properties['arrow']['head']['len'] = $head_arrow_length;
        }
        if ($end_arrow_width !== '') {
            $this->line_style_properties['arrow']['end']['w'] = $end_arrow_width;
        }
        if ($end_arrow_length !== '') {
            $this->line_style_properties['arrow']['end']['len'] = $end_arrow_length;
        }
    }
    /** @return mixed[] */
    public function get_line_style_array(): array
    {
        return $this->line_style_properties;
    }
    /** @param mixed[] $lineStyleProperties */
    public function set_line_style_array(array $line_style_properties = []): self
    {
        /** @var array{width?: ?string, compound?: string, dash?: string, cap?: string, join?: string, arrow?: array{head?: array{type?: string, size?: int, w?: string, len?: string}, end?: array{type?: string, size?: int, w?: string, len?: string}}} $lineStyleProperties */
        $this->activate_object();
        $this->line_style_properties['width'] = $line_style_properties['width'] ?? null;
        $this->line_style_properties['compound'] = $line_style_properties['compound'] ?? '';
        $this->line_style_properties['dash'] = $line_style_properties['dash'] ?? '';
        $this->line_style_properties['cap'] = $line_style_properties['cap'] ?? '';
        $this->line_style_properties['join'] = $line_style_properties['join'] ?? '';
        $this->line_style_properties['arrow']['head']['type'] = $line_style_properties['arrow']['head']['type'] ?? '';
        $this->line_style_properties['arrow']['head']['size'] = $line_style_properties['arrow']['head']['size'] ?? '';
        $this->line_style_properties['arrow']['head']['w'] = $line_style_properties['arrow']['head']['w'] ?? '';
        $this->line_style_properties['arrow']['head']['len'] = $line_style_properties['arrow']['head']['len'] ?? '';
        $this->line_style_properties['arrow']['end']['type'] = $line_style_properties['arrow']['end']['type'] ?? '';
        $this->line_style_properties['arrow']['end']['size'] = $line_style_properties['arrow']['end']['size'] ?? '';
        $this->line_style_properties['arrow']['end']['w'] = $line_style_properties['arrow']['end']['w'] ?? '';
        $this->line_style_properties['arrow']['end']['len'] = $line_style_properties['arrow']['end']['len'] ?? '';
        return $this;
    }
    public function set_line_style_property(string $property_name, mixed $value): self
    {
        $this->activate_object();
        $this->line_style_properties[$property_name] = $value;
        //* @phpstan-ignore-line
        return $this;
    }
    /**
     * Get Line Style Property.
     *
     * @param array<mixed>|string $elements
     */
    public function get_line_style_property(array|string $elements): ?string
    {
        $ret_val = $this->get_array_elements_value($this->line_style_properties, $elements);
        if (is_scalar($ret_val)) {
            $ret_val = (string) $ret_val;
        } elseif ($ret_val !== null) {
            // @codeCoverageIgnoreStart
            throw new Exception('Unexpected value for lineStyleProperty');
            // @codeCoverageIgnoreEnd
        }
        return $ret_val;
    }
    protected const ARROW_SIZES = [1 => ['w' => 'sm', 'len' => 'sm'], 2 => ['w' => 'sm', 'len' => 'med'], 3 => ['w' => 'sm', 'len' => 'lg'], 4 => ['w' => 'med', 'len' => 'sm'], 5 => ['w' => 'med', 'len' => 'med'], 6 => ['w' => 'med', 'len' => 'lg'], 7 => ['w' => 'lg', 'len' => 'sm'], 8 => ['w' => 'lg', 'len' => 'med'], 9 => ['w' => 'lg', 'len' => 'lg']];
    /**
     * Get Line Style Arrow Size.
     */
    protected function get_line_style_arrow_size(int $array_selector, string $array_kay_selector): string
    {
        return self::ARROW_SIZES[$array_selector][$array_kay_selector] ?? '';
    }
    /**
     * Get Line Style Arrow Parameters.
     */
    public function get_line_style_arrow_parameters(string $arrow_selector, string $property_selector): string
    {
        return $this->get_line_style_arrow_size((int) $this->line_style_properties['arrow'][$arrow_selector]['size'], $property_selector);
    }
    /**
     * Get Line Style Arrow Width.
     */
    public function get_line_style_arrow_width(string $arrow): ?string
    {
        return $this->get_line_style_property(['arrow', $arrow, 'w']);
    }
    /**
     * Get Line Style Arrow Excel Length.
     */
    public function get_line_style_arrow_length(string $arrow): ?string
    {
        return $this->get_line_style_property(['arrow', $arrow, 'len']);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->line_color = clone $this->line_color;
        $this->glow_color = clone $this->glow_color;
        $this->shadow_color = clone $this->shadow_color;
    }
}