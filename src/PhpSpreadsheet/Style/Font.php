<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Chart\Chart_Color;
use Php_Office\Php_Spreadsheet\Theme;
class Font extends Supervisor
{
    // Underline types
    public const UNDERLINE_NONE = 'none';
    public const UNDERLINE_DOUBLE = 'double';
    public const UNDERLINE_DOUBLEACCOUNTING = 'doubleAccounting';
    public const UNDERLINE_SINGLE = 'single';
    public const UNDERLINE_SINGLEACCOUNTING = 'singleAccounting';
    public const CAP_ALL = 'all';
    public const CAP_SMALL = 'small';
    public const CAP_NONE = 'none';
    private const VALID_CAPS = [self::CAP_ALL, self::CAP_SMALL, self::CAP_NONE];
    protected ?string $cap = null;
    public const DEFAULT_FONT_NAME = 'Calibri';
    /**
     * Font Name.
     */
    protected ?string $name = self::DEFAULT_FONT_NAME;
    /**
     * The following 7 are used only for chart titles, I think.
     */
    private string $latin = '';
    private string $east_asian = '';
    private string $complex_script = '';
    private int $base_line = 0;
    private string $strike_type = '';
    private ?Chart_Color $underline_color = null;
    private ?Chart_Color $chart_color = null;
    // end of chart title items
    /**
     * Font Size.
     */
    protected ?float $size = 11;
    /**
     * Bold.
     */
    protected ?bool $bold = false;
    /**
     * Italic.
     */
    protected ?bool $italic = false;
    /**
     * Superscript.
     */
    protected ?bool $superscript = false;
    /**
     * Subscript.
     */
    protected ?bool $subscript = false;
    /**
     * Underline.
     */
    protected ?string $underline = self::UNDERLINE_NONE;
    /**
     * Strikethrough.
     */
    protected ?bool $strikethrough = false;
    /**
     * Foreground color.
     */
    protected Color $color;
    protected bool $auto_color = false;
    public ?int $color_index = null;
    protected string $scheme = '';
    /**
     * Create a new Font.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     * @param bool $isConditional Flag indicating if this is a conditional style or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(bool $is_supervisor = false, bool $is_conditional = false)
    {
        // Supervisor?
        parent::__construct($is_supervisor);
        // Initialise values
        if ($is_conditional) {
            $this->name = null;
            $this->size = null;
            $this->bold = null;
            $this->italic = null;
            $this->superscript = null;
            $this->subscript = null;
            $this->underline = null;
            $this->strikethrough = null;
            $this->color = new Color(Color::COLOR_BLACK, $is_supervisor, $is_conditional);
        } else {
            $this->color = new Color(Color::COLOR_BLACK, $is_supervisor);
        }
        // bind parent if we are a supervisor
        if ($is_supervisor) {
            $this->color->bind_parent($this, 'color');
        }
    }
    public function apply_theme_fonts(Theme $theme): void
    {
        $this->set_name($theme->get_minor_font_latin());
        $this->set_latin($theme->get_minor_font_latin());
        $this->set_east_asian($theme->get_minor_font_east_asian());
        $this->set_complex_script($theme->get_minor_font_complex_script());
    }
    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    public function get_shared_component(): self
    {
        /** @var Style $parent */
        $parent = $this->parent;
        return $parent->get_shared_component()->get_font();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{font: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['font' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getFont()->applyFromArray(
     *     [
     *         'name' => 'Arial',
     *         'bold' => TRUE,
     *         'italic' => FALSE,
     *         'underline' => \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_DOUBLE,
     *         'strikethrough' => FALSE,
     *         'color' => [
     *             'rgb' => '808080'
     *         ]
     *     ]
     * );
     * </code>
     *
     * @param array{
     *   autoColor?: bool,
     *   bold?: bool,
     *   cap?: string,
     *   chartColor?: ChartColor,
     *   color?: string[],
     *   complexScript?: string,
     *   eastAsian?: string,
     *   italic?: bool,
     *   latin?: string,
     *   name?: string,
     *   scheme?: string,
     *   size?: null|float|int,
     *   strikethrough?: bool,
     *   superscript?: bool,
     *   subscript?: bool,
     *   underline?: bool|string,
     * } $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else {
            if (isset($style_array['name'])) {
                $this->set_name($style_array['name']);
            }
            if (isset($style_array['latin'])) {
                $this->set_latin($style_array['latin']);
            }
            if (isset($style_array['eastAsian'])) {
                $this->set_east_asian($style_array['eastAsian']);
            }
            if (isset($style_array['complexScript'])) {
                $this->set_complex_script($style_array['complexScript']);
            }
            if (isset($style_array['bold'])) {
                $this->set_bold($style_array['bold']);
            }
            if (isset($style_array['italic'])) {
                $this->set_italic($style_array['italic']);
            }
            if (isset($style_array['superscript'])) {
                $this->set_superscript($style_array['superscript']);
            }
            if (isset($style_array['subscript'])) {
                $this->set_subscript($style_array['subscript']);
            }
            if (isset($style_array['underline'])) {
                $this->set_underline($style_array['underline']);
            }
            if (isset($style_array['strikethrough'])) {
                $this->set_strikethrough($style_array['strikethrough']);
            }
            if (isset($style_array['color'])) {
                /** @var array{rgb?: string, argb?: string, theme?: int} */
                $temp = $style_array['color'];
                $this->get_color()->apply_from_array($temp);
            }
            if (isset($style_array['size'])) {
                $this->set_size($style_array['size']);
            }
            if (isset($style_array['chartColor'])) {
                $this->chart_color = $style_array['chartColor'];
            }
            if (isset($style_array['scheme'])) {
                $this->set_scheme($style_array['scheme']);
            }
            if (isset($style_array['cap'])) {
                $this->set_cap($style_array['cap']);
            }
            if (isset($style_array['autoColor'])) {
                $this->set_auto_color($style_array['autoColor']);
            }
        }
        return $this;
    }
    /**
     * Get Name.
     */
    public function get_name(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_name();
        }
        return $this->name;
    }
    public function get_latin(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_latin();
        }
        return $this->latin;
    }
    public function get_east_asian(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_east_asian();
        }
        return $this->east_asian;
    }
    public function get_complex_script(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_complex_script();
        }
        return $this->complex_script;
    }
    /**
     * Set Name and turn off Scheme.
     */
    public function set_name(string $fontname): self
    {
        if ($fontname == '') {
            $fontname = 'Calibri';
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['name' => $fontname]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->name = $fontname;
        }
        return $this->set_scheme('');
    }
    public function set_latin(string $fontname): self
    {
        if ($fontname == '') {
            $fontname = 'Calibri';
        }
        if (!$this->is_supervisor) {
            $this->latin = $fontname;
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['latin' => $fontname]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function set_east_asian(string $fontname): self
    {
        if ($fontname == '') {
            $fontname = 'Calibri';
        }
        if (!$this->is_supervisor) {
            $this->east_asian = $fontname;
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['eastAsian' => $fontname]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function set_complex_script(string $fontname): self
    {
        if ($fontname == '') {
            $fontname = 'Calibri';
        }
        if (!$this->is_supervisor) {
            $this->complex_script = $fontname;
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['complexScript' => $fontname]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    /**
     * Get Size.
     */
    public function get_size(): ?float
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_size();
        }
        return $this->size;
    }
    /**
     * Set Size.
     *
     * @param mixed $sizeInPoints A float representing the value of a positive measurement in points (1/72 of an inch)
     *
     * @return $this
     */
    public function set_size(mixed $size_in_points, bool $null_ok = false): static
    {
        if (is_string($size_in_points) || is_int($size_in_points)) {
            $size_in_points = (float) $size_in_points;
            // $pValue = 0 if given string is not numeric
        }
        // Size must be a positive floating point number
        // ECMA-376-1:2016, part 1, chapter 18.4.11 sz (Font Size), p. 1536
        if (!is_float($size_in_points) || !($size_in_points > 0)) {
            if (!$null_ok || $size_in_points !== null) {
                $size_in_points = 10.0;
            }
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['size' => $size_in_points]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->size = $size_in_points;
        }
        return $this;
    }
    /**
     * Get Bold.
     */
    public function get_bold(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_bold();
        }
        return $this->bold;
    }
    /**
     * Set Bold.
     *
     * @return $this
     */
    public function set_bold(bool $bold): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['bold' => $bold]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->bold = $bold;
        }
        return $this;
    }
    /**
     * Get Italic.
     */
    public function get_italic(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_italic();
        }
        return $this->italic;
    }
    /**
     * Set Italic.
     *
     * @return $this
     */
    public function set_italic(bool $italic): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['italic' => $italic]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->italic = $italic;
        }
        return $this;
    }
    /**
     * Get Superscript.
     */
    public function get_superscript(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_superscript();
        }
        return $this->superscript;
    }
    /**
     * Set Superscript.
     *
     * @return $this
     */
    public function set_superscript(bool $superscript): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['superscript' => $superscript]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->superscript = $superscript;
            if ($this->superscript) {
                $this->subscript = false;
            }
        }
        return $this;
    }
    /**
     * Get Subscript.
     */
    public function get_subscript(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_subscript();
        }
        return $this->subscript;
    }
    /**
     * Set Subscript.
     *
     * @return $this
     */
    public function set_subscript(bool $subscript): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['subscript' => $subscript]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->subscript = $subscript;
            if ($this->subscript) {
                $this->superscript = false;
            }
        }
        return $this;
    }
    public function get_base_line(): int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_base_line();
        }
        return $this->base_line;
    }
    public function set_base_line(int $base_line): self
    {
        if (!$this->is_supervisor) {
            $this->base_line = $base_line;
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['baseLine' => $base_line]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function get_strike_type(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_strike_type();
        }
        return $this->strike_type;
    }
    public function set_strike_type(string $strike_type): self
    {
        if (!$this->is_supervisor) {
            $this->strike_type = $strike_type;
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['strikeType' => $strike_type]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function get_underline_color(): ?Chart_Color
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_underline_color();
        }
        return $this->underline_color;
    }
    /** @param array{value: null|string, alpha: null|int|string, brightness?: null|int|string, type: null|string} $colorArray */
    public function set_underline_color(array $color_array): self
    {
        if (!$this->is_supervisor) {
            $this->underline_color = new Chart_Color($color_array);
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['underlineColor' => $color_array]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function get_chart_color(): ?Chart_Color
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_chart_color();
        }
        return $this->chart_color;
    }
    /** @param array{value: null|string, alpha: null|int|string, brightness?: null|int|string, type: null|string} $colorArray */
    public function set_chart_color(array $color_array): self
    {
        if (!$this->is_supervisor) {
            $this->chart_color = new Chart_Color($color_array);
        } else {
            // should never be true
            // @codeCoverageIgnoreStart
            $style_array = $this->get_style_array(['chartColor' => $color_array]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            // @codeCoverageIgnoreEnd
        }
        return $this;
    }
    public function set_chart_color_from_object(?Chart_Color $chart_color): self
    {
        $this->chart_color = $chart_color;
        return $this;
    }
    /**
     * Get Underline.
     */
    public function get_underline(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_underline();
        }
        return $this->underline;
    }
    /**
     * Set Underline.
     *
     * @param bool|string $underlineStyle \PhpOffice\PhpSpreadsheet\Style\Font underline type
     *                                    If a boolean is passed, then TRUE equates to UNDERLINE_SINGLE,
     *                                        false equates to UNDERLINE_NONE
     *
     * @return $this
     */
    public function set_underline($underline_style): static
    {
        if (is_bool($underline_style)) {
            $underline_style = $underline_style ? self::UNDERLINE_SINGLE : self::UNDERLINE_NONE;
        } elseif ($underline_style == '') {
            $underline_style = self::UNDERLINE_NONE;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['underline' => $underline_style]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->underline = $underline_style;
        }
        return $this;
    }
    /**
     * Get Strikethrough.
     */
    public function get_strikethrough(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_strikethrough();
        }
        return $this->strikethrough;
    }
    /**
     * Set Strikethrough.
     *
     * @return $this
     */
    public function set_strikethrough(bool $strikethru): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['strikethrough' => $strikethru]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->strikethrough = $strikethru;
        }
        return $this;
    }
    /**
     * Get Color.
     */
    public function get_color(): Color
    {
        return $this->color;
    }
    /**
     * Set Color.
     *
     * @return $this
     */
    public function set_color(Color $color): static
    {
        // make sure parameter is a real color and not a supervisor
        $color = $color->get_is_supervisor() ? $color->get_shared_component() : $color;
        if ($this->is_supervisor) {
            $style_array = $this->get_color()->get_style_array(['argb' => $color->get_argb()]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->color = $color;
        }
        return $this;
    }
    private function hash_chart_color(?Chart_Color $underline_color): string
    {
        if ($underline_color === null) {
            return '';
        }
        return $underline_color->get_value() . $underline_color->get_type() . $underline_color->get_alpha();
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
        return md5($this->name . $this->size . ($this->bold ? 't' : 'f') . ($this->italic ? 't' : 'f') . ($this->superscript ? 't' : 'f') . ($this->subscript ? 't' : 'f') . $this->underline . ($this->strikethrough ? 't' : 'f') . ($this->auto_color ? 't' : 'f') . $this->color->get_hash_code() . $this->scheme . implode('*', [$this->latin, $this->east_asian, $this->complex_script, $this->strike_type, $this->hash_chart_color($this->chart_color), $this->hash_chart_color($this->underline_color), (string) $this->base_line, (string) $this->cap]) . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'baseLine', $this->get_base_line());
        $this->export_array2($exported_array, 'bold', $this->get_bold());
        $this->export_array2($exported_array, 'cap', $this->get_cap());
        $this->export_array2($exported_array, 'chartColor', $this->get_chart_color());
        $this->export_array2($exported_array, 'color', $this->get_color());
        $this->export_array2($exported_array, 'complexScript', $this->get_complex_script());
        $this->export_array2($exported_array, 'eastAsian', $this->get_east_asian());
        $this->export_array2($exported_array, 'italic', $this->get_italic());
        $this->export_array2($exported_array, 'latin', $this->get_latin());
        $this->export_array2($exported_array, 'name', $this->get_name());
        $this->export_array2($exported_array, 'scheme', $this->get_scheme());
        $this->export_array2($exported_array, 'size', $this->get_size());
        $this->export_array2($exported_array, 'strikethrough', $this->get_strikethrough());
        $this->export_array2($exported_array, 'strikeType', $this->get_strike_type());
        $this->export_array2($exported_array, 'subscript', $this->get_subscript());
        $this->export_array2($exported_array, 'superscript', $this->get_superscript());
        $this->export_array2($exported_array, 'underline', $this->get_underline());
        $this->export_array2($exported_array, 'underlineColor', $this->get_underline_color());
        $this->export_array2($exported_array, 'autoColor', $this->get_auto_color());
        return $exported_array;
    }
    public function get_scheme(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_scheme();
        }
        return $this->scheme;
    }
    public function set_scheme(string $scheme): self
    {
        if ($scheme === '' || $scheme === 'major' || $scheme === 'minor') {
            if ($this->is_supervisor) {
                $style_array = $this->get_style_array(['scheme' => $scheme]);
                $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            } else {
                $this->scheme = $scheme;
            }
        }
        return $this;
    }
    /**
     * Set capitalization attribute. If not one of the permitted
     * values (all, small, or none), set it to null.
     * This will be honored only for the font for chart titles.
     * None is distinguished from null because null will inherit
     * the current value, whereas 'none' will override it.
     */
    public function set_cap(string $cap): self
    {
        $this->cap = in_array($cap, self::VALID_CAPS, true) ? $cap : null;
        return $this;
    }
    public function get_cap(): ?string
    {
        return $this->cap;
    }
    public function set_hyperlink_theme(): self
    {
        $this->color->set_hyperlink_theme();
        $this->set_underline(self::UNDERLINE_SINGLE);
        return $this;
    }
    public function set_auto_color(bool $auto_color): self
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['autoColor' => $auto_color]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->auto_color = $auto_color;
        }
        return $this;
    }
    public function get_auto_color(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_auto_color();
        }
        return $this->auto_color;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->color = clone $this->color;
        $this->chart_color = $this->chart_color === null ? null : clone $this->chart_color;
        $this->underline_color = $this->underline_color === null ? null : clone $this->underline_color;
    }
}