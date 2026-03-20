<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

class Fill extends Supervisor
{
    // Fill types
    public const FILL_NONE = 'none';
    public const FILL_SOLID = 'solid';
    public const FILL_GRADIENT_LINEAR = 'linear';
    public const FILL_GRADIENT_PATH = 'path';
    public const FILL_PATTERN_DARKDOWN = 'darkDown';
    public const FILL_PATTERN_DARKGRAY = 'darkGray';
    public const FILL_PATTERN_DARKGRID = 'darkGrid';
    public const FILL_PATTERN_DARKHORIZONTAL = 'darkHorizontal';
    public const FILL_PATTERN_DARKTRELLIS = 'darkTrellis';
    public const FILL_PATTERN_DARKUP = 'darkUp';
    public const FILL_PATTERN_DARKVERTICAL = 'darkVertical';
    public const FILL_PATTERN_GRAY0625 = 'gray0625';
    public const FILL_PATTERN_GRAY125 = 'gray125';
    public const FILL_PATTERN_LIGHTDOWN = 'lightDown';
    public const FILL_PATTERN_LIGHTGRAY = 'lightGray';
    public const FILL_PATTERN_LIGHTGRID = 'lightGrid';
    public const FILL_PATTERN_LIGHTHORIZONTAL = 'lightHorizontal';
    public const FILL_PATTERN_LIGHTTRELLIS = 'lightTrellis';
    public const FILL_PATTERN_LIGHTUP = 'lightUp';
    public const FILL_PATTERN_LIGHTVERTICAL = 'lightVertical';
    public const FILL_PATTERN_MEDIUMGRAY = 'mediumGray';
    public ?int $startcolor_index = null;
    public ?int $endcolor_index = null;
    /**
     * Fill type.
     */
    protected ?string $fill_type = self::FILL_NONE;
    /**
     * Rotation.
     */
    protected float $rotation = 0.0;
    /**
     * Start color.
     */
    protected Color $start_color;
    /**
     * End color.
     */
    protected Color $end_color;
    private bool $color_changed = false;
    /**
     * Create a new Fill.
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
            $this->fill_type = null;
        }
        $this->start_color = new Color(Color::COLOR_WHITE, $is_supervisor, $is_conditional);
        $this->end_color = new Color(Color::COLOR_BLACK, $is_supervisor, $is_conditional);
        // bind parent if we are a supervisor
        if ($is_supervisor) {
            $this->start_color->bind_parent($this, 'startColor');
            $this->end_color->bind_parent($this, 'endColor');
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
        return $parent->get_shared_component()->get_fill();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{fill: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['fill' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getFill()->applyFromArray(
     *     [
     *         'fillType' => Fill::FILL_GRADIENT_LINEAR,
     *         'rotation' => 0.0,
     *         'startColor' => [
     *             'rgb' => '000000'
     *         ],
     *         'endColor' => [
     *             'argb' => 'FFFFFFFF'
     *         ]
     *     ]
     * );
     * </code>
     *
     * @param array{fillType?: string, rotation?: float, startColor?: array{rgb?: string, argb?: string}, endColor?: array{rgb?: string, argb?: string}, color?: array{rgb?: string, argb?: string}} $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else {
            if (isset($style_array['fillType'])) {
                $this->set_fill_type($style_array['fillType']);
            }
            if (isset($style_array['rotation'])) {
                $this->set_rotation($style_array['rotation']);
            }
            if (isset($style_array['startColor'])) {
                $this->get_start_color()->apply_from_array($style_array['startColor']);
            }
            if (isset($style_array['endColor'])) {
                $this->get_end_color()->apply_from_array($style_array['endColor']);
            }
            if (isset($style_array['color'])) {
                $this->get_start_color()->apply_from_array($style_array['color']);
                $this->get_end_color()->apply_from_array($style_array['color']);
            }
        }
        return $this;
    }
    /**
     * Get Fill Type.
     */
    public function get_fill_type(): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_fill_type();
        }
        return $this->fill_type;
    }
    /**
     * Set Fill Type.
     *
     * @param string $fillType Fill type, see self::FILL_*
     *
     * @return $this
     */
    public function set_fill_type(string $fill_type): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['fillType' => $fill_type]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->fill_type = $fill_type;
        }
        return $this;
    }
    /**
     * Get Rotation.
     */
    public function get_rotation(): float
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_rotation();
        }
        return $this->rotation;
    }
    /**
     * Set Rotation.
     *
     * @return $this
     */
    public function set_rotation(float $angle_in_degrees): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['rotation' => $angle_in_degrees]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->rotation = $angle_in_degrees;
        }
        return $this;
    }
    /**
     * Get Start Color.
     */
    public function get_start_color(): Color
    {
        return $this->start_color;
    }
    /**
     * Set Start Color.
     *
     * @return $this
     */
    public function set_start_color(Color $color): static
    {
        $this->color_changed = true;
        // make sure parameter is a real color and not a supervisor
        $color = $color->get_is_supervisor() ? $color->get_shared_component() : $color;
        if ($this->is_supervisor) {
            $style_array = $this->get_start_color()->get_style_array(['argb' => $color->get_argb()]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->start_color = $color;
        }
        return $this;
    }
    /**
     * Get End Color.
     */
    public function get_end_color(): Color
    {
        return $this->end_color;
    }
    /**
     * Set End Color.
     *
     * @return $this
     */
    public function set_end_color(Color $color): static
    {
        $this->color_changed = true;
        // make sure parameter is a real color and not a supervisor
        $color = $color->get_is_supervisor() ? $color->get_shared_component() : $color;
        if ($this->is_supervisor) {
            $style_array = $this->get_end_color()->get_style_array(['argb' => $color->get_argb()]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->end_color = $color;
        }
        return $this;
    }
    public function get_colors_changed(): bool
    {
        if ($this->is_supervisor) {
            $changed = $this->get_shared_component()->color_changed;
        } else {
            $changed = $this->color_changed;
        }
        if ($changed) {
            return true;
        }
        if ($this->start_color->get_has_changed()) {
            return true;
        }
        return $this->end_color->get_has_changed();
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
        // Note that we don't care about colours for fill type NONE, but could have duplicate NONEs with
        //  different hashes if we don't explicitly prevent this
        return md5($this->get_fill_type() . $this->get_rotation() . ($this->get_fill_type() !== self::FILL_NONE ? $this->get_start_color()->get_hash_code() : '') . ($this->get_fill_type() !== self::FILL_NONE ? $this->get_end_color()->get_hash_code() : '') . $this->get_colors_changed() . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'fillType', $this->get_fill_type());
        $this->export_array2($exported_array, 'rotation', $this->get_rotation());
        if ($this->get_colors_changed()) {
            $this->export_array2($exported_array, 'endColor', $this->get_end_color());
            $this->export_array2($exported_array, 'startColor', $this->get_start_color());
        }
        return $exported_array;
    }
}