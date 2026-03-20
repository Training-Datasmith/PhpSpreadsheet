<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Border extends Supervisor
{
    // Border style
    public const BORDER_NONE = 'none';
    public const BORDER_DASHDOT = 'dashDot';
    public const BORDER_DASHDOTDOT = 'dashDotDot';
    public const BORDER_DASHED = 'dashed';
    public const BORDER_DOTTED = 'dotted';
    public const BORDER_DOUBLE = 'double';
    public const BORDER_HAIR = 'hair';
    public const BORDER_MEDIUM = 'medium';
    public const BORDER_MEDIUMDASHDOT = 'mediumDashDot';
    public const BORDER_MEDIUMDASHDOTDOT = 'mediumDashDotDot';
    public const BORDER_MEDIUMDASHED = 'mediumDashed';
    public const BORDER_SLANTDASHDOT = 'slantDashDot';
    public const BORDER_THICK = 'thick';
    public const BORDER_THIN = 'thin';
    public const BORDER_OMIT = 'omit';
    // should be used only for Conditional
    /**
     * Border style.
     */
    protected string $border_style = self::BORDER_NONE;
    /**
     * Border color.
     */
    protected Color $color;
    public ?int $color_index = null;
    /**
     * Create a new Border.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(bool $is_supervisor = false, bool $is_conditional = false)
    {
        // Supervisor?
        parent::__construct($is_supervisor);
        // Initialise values
        $this->color = new Color(Color::COLOR_BLACK, $is_supervisor);
        // bind parent if we are a supervisor
        if ($is_supervisor) {
            $this->color->bind_parent($this, 'color');
        }
        if ($is_conditional) {
            $this->border_style = self::BORDER_OMIT;
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
        /** @var Borders $sharedComponent */
        $shared_component = $parent->get_shared_component();
        return match ($this->parent_property_name) {
            'bottom' => $shared_component->get_bottom(),
            'diagonal' => $shared_component->get_diagonal(),
            'left' => $shared_component->get_left(),
            'right' => $shared_component->get_right(),
            'top' => $shared_component->get_top(),
            default => throw new Php_Spreadsheet_Exception('Cannot get shared component for a pseudo-border.'),
        };
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
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getBorders()->getTop()->applyFromArray(
     *        [
     *            'borderStyle' => Border::BORDER_DASHDOT,
     *            'color' => [
     *                'rgb' => '808080'
     *            ]
     *        ]
     * );
     * </code>
     *
     * @param mixed[] $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else {
            /** @var array{borderStyle?: string, color?: array{rgb?: string, argb?: string}} $styleArray */
            if (isset($style_array['borderStyle'])) {
                $this->set_border_style($style_array['borderStyle']);
            }
            if (isset($style_array['color'])) {
                $this->get_color()->apply_from_array($style_array['color']);
            }
        }
        return $this;
    }
    /**
     * Get Border style.
     */
    public function get_border_style(): string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_border_style();
        }
        return $this->border_style;
    }
    /**
     * Set Border style.
     *
     * @param bool|string $style When passing a boolean, FALSE equates Border::BORDER_NONE
     *                                and TRUE to Border::BORDER_MEDIUM
     *
     * @return $this
     */
    public function set_border_style(bool|string $style): static
    {
        if (empty($style)) {
            $style = self::BORDER_NONE;
        } elseif (is_bool($style)) {
            $style = self::BORDER_MEDIUM;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['borderStyle' => $style]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->border_style = $style;
        }
        return $this;
    }
    /**
     * Get Border Color.
     */
    public function get_color(): Color
    {
        return $this->color;
    }
    /**
     * Set Border Color.
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
        return md5($this->border_style . $this->color->get_hash_code() . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'borderStyle', $this->get_border_style());
        $this->export_array2($exported_array, 'color', $this->get_color());
        return $exported_array;
    }
}