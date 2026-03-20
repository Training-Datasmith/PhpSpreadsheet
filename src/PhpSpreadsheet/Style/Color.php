<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Theme;
class Color extends Supervisor
{
    public const NAMED_COLORS = ['Black', 'White', 'Red', 'Green', 'Blue', 'Yellow', 'Magenta', 'Cyan'];
    // Colors
    public const COLOR_BLACK = 'FF000000';
    public const COLOR_WHITE = 'FFFFFFFF';
    public const COLOR_RED = 'FFFF0000';
    public const COLOR_DARKRED = 'FF800000';
    public const COLOR_BLUE = 'FF0000FF';
    public const COLOR_DARKBLUE = 'FF000080';
    public const COLOR_GREEN = 'FF00FF00';
    public const COLOR_DARKGREEN = 'FF008000';
    public const COLOR_YELLOW = 'FFFFFF00';
    public const COLOR_DARKYELLOW = 'FF808000';
    public const COLOR_MAGENTA = 'FFFF00FF';
    public const COLOR_CYAN = 'FF00FFFF';
    public const NAMED_COLOR_TRANSLATIONS = ['Black' => self::COLOR_BLACK, 'White' => self::COLOR_WHITE, 'Red' => self::COLOR_RED, 'Green' => self::COLOR_GREEN, 'Blue' => self::COLOR_BLUE, 'Yellow' => self::COLOR_YELLOW, 'Magenta' => self::COLOR_MAGENTA, 'Cyan' => self::COLOR_CYAN];
    public const VALIDATE_ARGB_SIZE = 8;
    public const VALIDATE_RGB_SIZE = 6;
    public const VALIDATE_COLOR_6 = '/^[A-F0-9]{6}$/i';
    public const VALIDATE_COLOR_8 = '/^[A-F0-9]{8}$/i';
    private const INDEXED_COLORS = [
        1 => 'FF000000',
        //  System Colour #1 - Black
        2 => 'FFFFFFFF',
        //  System Colour #2 - White
        3 => 'FFFF0000',
        //  System Colour #3 - Red
        4 => 'FF00FF00',
        //  System Colour #4 - Green
        5 => 'FF0000FF',
        //  System Colour #5 - Blue
        6 => 'FFFFFF00',
        //  System Colour #6 - Yellow
        7 => 'FFFF00FF',
        //  System Colour #7- Magenta
        8 => 'FF00FFFF',
        //  System Colour #8- Cyan
        9 => 'FF800000',
        //  Standard Colour #9
        10 => 'FF008000',
        //  Standard Colour #10
        11 => 'FF000080',
        //  Standard Colour #11
        12 => 'FF808000',
        //  Standard Colour #12
        13 => 'FF800080',
        //  Standard Colour #13
        14 => 'FF008080',
        //  Standard Colour #14
        15 => 'FFC0C0C0',
        //  Standard Colour #15
        16 => 'FF808080',
        //  Standard Colour #16
        17 => 'FF9999FF',
        //  Chart Fill Colour #17
        18 => 'FF993366',
        //  Chart Fill Colour #18
        19 => 'FFFFFFCC',
        //  Chart Fill Colour #19
        20 => 'FFCCFFFF',
        //  Chart Fill Colour #20
        21 => 'FF660066',
        //  Chart Fill Colour #21
        22 => 'FFFF8080',
        //  Chart Fill Colour #22
        23 => 'FF0066CC',
        //  Chart Fill Colour #23
        24 => 'FFCCCCFF',
        //  Chart Fill Colour #24
        25 => 'FF000080',
        //  Chart Line Colour #25
        26 => 'FFFF00FF',
        //  Chart Line Colour #26
        27 => 'FFFFFF00',
        //  Chart Line Colour #27
        28 => 'FF00FFFF',
        //  Chart Line Colour #28
        29 => 'FF800080',
        //  Chart Line Colour #29
        30 => 'FF800000',
        //  Chart Line Colour #30
        31 => 'FF008080',
        //  Chart Line Colour #31
        32 => 'FF0000FF',
        //  Chart Line Colour #32
        33 => 'FF00CCFF',
        //  Standard Colour #33
        34 => 'FFCCFFFF',
        //  Standard Colour #34
        35 => 'FFCCFFCC',
        //  Standard Colour #35
        36 => 'FFFFFF99',
        //  Standard Colour #36
        37 => 'FF99CCFF',
        //  Standard Colour #37
        38 => 'FFFF99CC',
        //  Standard Colour #38
        39 => 'FFCC99FF',
        //  Standard Colour #39
        40 => 'FFFFCC99',
        //  Standard Colour #40
        41 => 'FF3366FF',
        //  Standard Colour #41
        42 => 'FF33CCCC',
        //  Standard Colour #42
        43 => 'FF99CC00',
        //  Standard Colour #43
        44 => 'FFFFCC00',
        //  Standard Colour #44
        45 => 'FFFF9900',
        //  Standard Colour #45
        46 => 'FFFF6600',
        //  Standard Colour #46
        47 => 'FF666699',
        //  Standard Colour #47
        48 => 'FF969696',
        //  Standard Colour #48
        49 => 'FF003366',
        //  Standard Colour #49
        50 => 'FF339966',
        //  Standard Colour #50
        51 => 'FF003300',
        //  Standard Colour #51
        52 => 'FF333300',
        //  Standard Colour #52
        53 => 'FF993300',
        //  Standard Colour #53
        54 => 'FF993366',
        //  Standard Colour #54
        55 => 'FF333399',
        //  Standard Colour #55
        56 => 'FF333333',
    ];
    /**
     * ARGB - Alpha RGB.
     */
    protected ?string $argb = null;
    private bool $has_changed = false;
    private int $theme = -1;
    /**
     * Create a new Color.
     *
     * @param string $colorValue ARGB value for the colour, or named colour
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     * @param bool $isConditional Flag indicating if this is a conditional style or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(string $color_value = self::COLOR_BLACK, bool $is_supervisor = false, bool $is_conditional = false)
    {
        //    Supervisor?
        parent::__construct($is_supervisor);
        //    Initialise values
        if (!$is_conditional) {
            $this->argb = $this->validate_color($color_value) ?: self::COLOR_BLACK;
        }
    }
    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    public function get_shared_component(): self
    {
        /** @var Style $parent */
        $parent = $this->parent;
        /** @var Border|Fill $sharedComponent */
        $shared_component = $parent->get_shared_component();
        if ($shared_component instanceof Fill) {
            if ($this->parent_property_name === 'endColor') {
                return $shared_component->get_end_color();
            }
            return $shared_component->get_start_color();
        }
        return $shared_component->get_color();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    public function get_style_array(array $array): array
    {
        /** @var Style $parent */
        $parent = $this->parent;
        return $parent->get_style_array([$this->parent_property_name => $array]);
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);
     * </code>
     *
     * @param array{rgb?: string, argb?: string, theme?: int} $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else {
            if (isset($style_array['rgb'])) {
                $this->set_rgb($style_array['rgb']);
            }
            if (isset($style_array['argb'])) {
                $this->set_argb($style_array['argb']);
            }
            if (isset($style_array['theme'])) {
                $this->set_theme($style_array['theme']);
            }
        }
        return $this;
    }
    private function validate_color(?string $color_value): string
    {
        if ($color_value === null || $color_value === '') {
            return self::COLOR_BLACK;
        }
        $named = ucfirst(strtolower($color_value));
        if (array_key_exists($named, self::NAMED_COLOR_TRANSLATIONS)) {
            return self::NAMED_COLOR_TRANSLATIONS[$named];
        }
        if (preg_match(self::VALIDATE_COLOR_8, $color_value) === 1) {
            return $color_value;
        }
        if (preg_match(self::VALIDATE_COLOR_6, $color_value) === 1) {
            return 'FF' . $color_value;
        }
        return '';
    }
    /**
     * Get ARGB.
     */
    public function get_argb(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_argb();
        }
        return $this->argb;
    }
    /**
     * Set ARGB.
     *
     * @param ?string $colorValue  ARGB value, or a named color
     *
     * @return $this
     */
    public function set_argb(?string $color_value = self::COLOR_BLACK, bool $null_string_okay = false): static
    {
        $this->has_changed = true;
        $this->set_theme(-1);
        if (!$null_string_okay || $color_value !== '') {
            $color_value = $this->validate_color($color_value);
            if ($color_value === '') {
                return $this;
            }
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['argb' => $color_value]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->argb = $color_value;
        }
        return $this;
    }
    /**
     * Get RGB.
     */
    public function get_rgb(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_rgb();
        }
        return substr($this->argb ?? '', 2);
    }
    /**
     * Set RGB.
     *
     * @param ?string $colorValue RGB value, or a named color
     *
     * @return $this
     */
    public function set_rgb(?string $color_value = self::COLOR_BLACK): static
    {
        return $this->set_argb($color_value);
    }
    /**
     * Get a specified colour component of an RGB value.
     *
     * @param string $rgbValue The colour as an RGB value (e.g. FF00CCCC or CCDDEE
     * @param int $offset Position within the RGB value to extract
     * @param bool $hex Flag indicating whether the component should be returned as a hex or a
     *                                    decimal value
     *
     * @return int|string The extracted colour component
     */
    private static function get_colour_component(string $rgb_value, int $offset, bool $hex = true): string|int
    {
        $colour = substr($rgb_value, $offset, 2) ?: '';
        if (preg_match('/^[0-9a-f]{2}$/i', $colour) !== 1) {
            $colour = '00';
        }
        return $hex ? $colour : (int) hexdec($colour);
    }
    /**
     * Get the red colour component of an RGB value.
     *
     * @param string $rgbValue The colour as an RGB value (e.g. FF00CCCC or CCDDEE
     * @param bool $hex Flag indicating whether the component should be returned as a hex or a
     *                                    decimal value
     *
     * @return int|string The red colour component
     */
    public static function get_red(string $rgb_value, bool $hex = true): string|int
    {
        return self::get_colour_component($rgb_value, strlen($rgb_value) - 6, $hex);
    }
    /**
     * Get the green colour component of an RGB value.
     *
     * @param string $rgbValue The colour as an RGB value (e.g. FF00CCCC or CCDDEE
     * @param bool $hex Flag indicating whether the component should be returned as a hex or a
     *                                    decimal value
     *
     * @return int|string The green colour component
     */
    public static function get_green(string $rgb_value, bool $hex = true): string|int
    {
        return self::get_colour_component($rgb_value, strlen($rgb_value) - 4, $hex);
    }
    /**
     * Get the blue colour component of an RGB value.
     *
     * @param string $rgbValue The colour as an RGB value (e.g. FF00CCCC or CCDDEE
     * @param bool $hex Flag indicating whether the component should be returned as a hex or a
     *                                    decimal value
     *
     * @return int|string The blue colour component
     */
    public static function get_blue(string $rgb_value, bool $hex = true): string|int
    {
        return self::get_colour_component($rgb_value, strlen($rgb_value) - 2, $hex);
    }
    /**
     * Adjust the brightness of a color.
     *
     * @param string $hexColourValue The colour as an RGBA or RGB value (e.g. FF00CCCC or CCDDEE)
     * @param float $adjustPercentage The percentage by which to adjust the colour as a float from -1 to 1
     *
     * @return string The adjusted colour as an RGBA or RGB value (e.g. FF00CCCC or CCDDEE)
     */
    public static function change_brightness(string $hex_colour_value, float $adjust_percentage): string
    {
        $rgba = strlen($hex_colour_value) === 8;
        $adjust_percentage = max(-1.0, min(1.0, $adjust_percentage));
        /** @var int $red */
        $red = self::get_red($hex_colour_value, false);
        /** @var int $green */
        $green = self::get_green($hex_colour_value, false);
        /** @var int $blue */
        $blue = self::get_blue($hex_colour_value, false);
        return ($rgba ? 'FF' : '') . Rgb_Tint::rgb_and_tint_to_rgb($red, $green, $blue, $adjust_percentage);
    }
    /**
     * Get indexed color.
     *
     * @param int $colorIndex Index entry point into the colour array
     * @param bool $background Flag to indicate whether default background or foreground colour
     *                                            should be returned if the indexed colour doesn't exist
     * @param null|string[] $palette
     */
    public static function indexed_color(int $color_index, bool $background = false, ?array $palette = null): self
    {
        if (empty($palette)) {
            if (isset(self::INDEXED_COLORS[$color_index])) {
                return new self(self::INDEXED_COLORS[$color_index]);
            }
        } else if (isset($palette[$color_index])) {
            return new self($palette[$color_index]);
        }
        return $background ? new self(self::COLOR_WHITE) : new self(self::COLOR_BLACK);
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_hash_code();
        }
        return md5($this->argb . $this->theme . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'argb', $this->get_argb());
        $this->export_array2($exported_array, 'theme', $this->get_theme());
        return $exported_array;
    }
    public function get_has_changed(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->has_changed;
        }
        return $this->has_changed;
    }
    public function get_theme(): int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_theme();
        }
        return $this->theme;
    }
    public function set_theme(int $theme): self
    {
        $this->has_changed = true;
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['theme' => $theme]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->theme = $theme;
        }
        return $this;
    }
    public function set_hyperlink_theme(): self
    {
        $rgb = $this->get_active_sheet()->get_parent()?->get_theme()->get_theme_colors();
        if (is_array($rgb) && array_key_exists('hlink', $rgb)) {
            $this->set_rgb($rgb['hlink']);
        }
        return $this->set_theme(Theme::HYPERLINK_THEME);
    }
}