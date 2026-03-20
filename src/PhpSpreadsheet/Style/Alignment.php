<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Alignment extends Supervisor
{
    // Horizontal alignment styles
    public const HORIZONTAL_GENERAL = 'general';
    public const HORIZONTAL_LEFT = 'left';
    public const HORIZONTAL_RIGHT = 'right';
    public const HORIZONTAL_CENTER = 'center';
    public const HORIZONTAL_CENTER_CONTINUOUS = 'centerContinuous';
    public const HORIZONTAL_JUSTIFY = 'justify';
    public const HORIZONTAL_FILL = 'fill';
    public const HORIZONTAL_DISTRIBUTED = 'distributed';
    // Excel2007 only
    private const HORIZONTAL_CENTER_CONTINUOUS_LC = 'centercontinuous';
    // Mapping for horizontal alignment
    public const HORIZONTAL_ALIGNMENT_FOR_XLSX = [self::HORIZONTAL_LEFT => self::HORIZONTAL_LEFT, self::HORIZONTAL_RIGHT => self::HORIZONTAL_RIGHT, self::HORIZONTAL_CENTER => self::HORIZONTAL_CENTER, self::HORIZONTAL_CENTER_CONTINUOUS => self::HORIZONTAL_CENTER_CONTINUOUS, self::HORIZONTAL_JUSTIFY => self::HORIZONTAL_JUSTIFY, self::HORIZONTAL_FILL => self::HORIZONTAL_FILL, self::HORIZONTAL_DISTRIBUTED => self::HORIZONTAL_DISTRIBUTED];
    // Mapping for horizontal alignment CSS
    public const HORIZONTAL_ALIGNMENT_FOR_HTML = [
        self::HORIZONTAL_LEFT => self::HORIZONTAL_LEFT,
        self::HORIZONTAL_RIGHT => self::HORIZONTAL_RIGHT,
        self::HORIZONTAL_CENTER => self::HORIZONTAL_CENTER,
        self::HORIZONTAL_CENTER_CONTINUOUS => self::HORIZONTAL_CENTER,
        self::HORIZONTAL_JUSTIFY => self::HORIZONTAL_JUSTIFY,
        //self::HORIZONTAL_FILL => self::HORIZONTAL_FILL, // no reasonable equivalent for fill
        self::HORIZONTAL_DISTRIBUTED => self::HORIZONTAL_JUSTIFY,
    ];
    // Vertical alignment styles
    public const VERTICAL_BOTTOM = 'bottom';
    public const VERTICAL_TOP = 'top';
    public const VERTICAL_CENTER = 'center';
    public const VERTICAL_JUSTIFY = 'justify';
    public const VERTICAL_DISTRIBUTED = 'distributed';
    // Excel2007 only
    // Vertical alignment CSS
    private const VERTICAL_BASELINE = 'baseline';
    private const VERTICAL_MIDDLE = 'middle';
    private const VERTICAL_SUB = 'sub';
    private const VERTICAL_SUPER = 'super';
    private const VERTICAL_TEXT_BOTTOM = 'text-bottom';
    private const VERTICAL_TEXT_TOP = 'text-top';
    // Mapping for vertical alignment
    public const VERTICAL_ALIGNMENT_FOR_XLSX = [
        self::VERTICAL_BOTTOM => self::VERTICAL_BOTTOM,
        self::VERTICAL_TOP => self::VERTICAL_TOP,
        self::VERTICAL_CENTER => self::VERTICAL_CENTER,
        self::VERTICAL_JUSTIFY => self::VERTICAL_JUSTIFY,
        self::VERTICAL_DISTRIBUTED => self::VERTICAL_DISTRIBUTED,
        // css settings that aren't in sync with Excel
        self::VERTICAL_BASELINE => self::VERTICAL_BOTTOM,
        self::VERTICAL_MIDDLE => self::VERTICAL_CENTER,
        self::VERTICAL_SUB => self::VERTICAL_BOTTOM,
        self::VERTICAL_SUPER => self::VERTICAL_TOP,
        self::VERTICAL_TEXT_BOTTOM => self::VERTICAL_BOTTOM,
        self::VERTICAL_TEXT_TOP => self::VERTICAL_TOP,
    ];
    // Mapping for vertical alignment for Html
    public const VERTICAL_ALIGNMENT_FOR_HTML = [
        self::VERTICAL_BOTTOM => self::VERTICAL_BOTTOM,
        self::VERTICAL_TOP => self::VERTICAL_TOP,
        self::VERTICAL_CENTER => self::VERTICAL_MIDDLE,
        self::VERTICAL_JUSTIFY => self::VERTICAL_MIDDLE,
        self::VERTICAL_DISTRIBUTED => self::VERTICAL_MIDDLE,
        // css settings that aren't in sync with Excel
        self::VERTICAL_BASELINE => self::VERTICAL_BASELINE,
        self::VERTICAL_MIDDLE => self::VERTICAL_MIDDLE,
        self::VERTICAL_SUB => self::VERTICAL_SUB,
        self::VERTICAL_SUPER => self::VERTICAL_SUPER,
        self::VERTICAL_TEXT_BOTTOM => self::VERTICAL_TEXT_BOTTOM,
        self::VERTICAL_TEXT_TOP => self::VERTICAL_TEXT_TOP,
    ];
    // Read order
    public const READORDER_CONTEXT = 0;
    public const READORDER_LTR = 1;
    public const READORDER_RTL = 2;
    // Special value for Text Rotation
    public const TEXTROTATION_STACK_EXCEL = 255;
    public const TEXTROTATION_STACK_PHPSPREADSHEET = -165;
    // 90 - 255
    public const INDENT_UNITS_TO_PIXELS = 9;
    /**
     * Horizontal alignment.
     */
    protected ?string $horizontal = self::HORIZONTAL_GENERAL;
    /**
     * Justify Last Line alignment.
     */
    protected ?bool $justify_last_line = null;
    /**
     * Vertical alignment.
     */
    protected ?string $vertical = self::VERTICAL_BOTTOM;
    /**
     * Text rotation.
     */
    protected ?int $text_rotation = 0;
    /**
     * Wrap text.
     */
    protected bool $wrap_text = false;
    /**
     * Shrink to fit.
     */
    protected bool $shrink_to_fit = false;
    /**
     * Indent - only possible with horizontal alignment left and right.
     */
    protected int $indent = 0;
    /**
     * Read order.
     */
    protected int $read_order = 0;
    /**
     * Create a new Alignment.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                       Leave this value at default unless you understand exactly what
     *                                          its ramifications are
     * @param bool $isConditional Flag indicating if this is a conditional style or not
     *                                       Leave this value at default unless you understand exactly what
     *                                          its ramifications are
     */
    public function __construct(bool $is_supervisor = false, bool $is_conditional = false)
    {
        // Supervisor?
        parent::__construct($is_supervisor);
        if ($is_conditional) {
            $this->horizontal = null;
            $this->vertical = null;
            $this->text_rotation = null;
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
        return $parent->get_shared_component()->get_alignment();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{alignment: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['alignment' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getAlignment()->applyFromArray(
     *        [
     *            'horizontal'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
     *            'vertical'     => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
     *            'textRotation' => 0,
     *            'wrapText'     => TRUE
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
            /** @var array{horizontal?: string, vertical?: string, justifyLastLine?: bool, textRotation?: int, wrapText?: bool, shrinkToFit?: bool, readOrder?: int, indent?: int} $styleArray */
            if (isset($style_array['horizontal'])) {
                $this->set_horizontal($style_array['horizontal']);
            }
            if (isset($style_array['justifyLastLine'])) {
                $this->set_justify_last_line($style_array['justifyLastLine']);
            }
            if (isset($style_array['vertical'])) {
                $this->set_vertical($style_array['vertical']);
            }
            if (isset($style_array['textRotation'])) {
                $this->set_text_rotation($style_array['textRotation']);
            }
            if (isset($style_array['wrapText'])) {
                $this->set_wrap_text($style_array['wrapText']);
            }
            if (isset($style_array['shrinkToFit'])) {
                $this->set_shrink_to_fit($style_array['shrinkToFit']);
            }
            if (isset($style_array['indent'])) {
                $this->set_indent($style_array['indent']);
            }
            if (isset($style_array['readOrder'])) {
                $this->set_read_order($style_array['readOrder']);
            }
        }
        return $this;
    }
    /**
     * Get Horizontal.
     */
    public function get_horizontal(): null|string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_horizontal();
        }
        return $this->horizontal;
    }
    /**
     * Set Horizontal.
     *
     * @param string $horizontalAlignment see self::HORIZONTAL_*
     *
     * @return $this
     */
    public function set_horizontal(string $horizontal_alignment): static
    {
        $horizontal_alignment = strtolower($horizontal_alignment);
        if ($horizontal_alignment === self::HORIZONTAL_CENTER_CONTINUOUS_LC) {
            $horizontal_alignment = self::HORIZONTAL_CENTER_CONTINUOUS;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['horizontal' => $horizontal_alignment]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->horizontal = $horizontal_alignment;
        }
        return $this;
    }
    /**
     * Get Justify Last Line.
     */
    public function get_justify_last_line(): ?bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_justify_last_line();
        }
        return $this->justify_last_line;
    }
    /**
     * Set Justify Last Line.
     *
     * @return $this
     */
    public function set_justify_last_line(bool $justify_last_line): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['justifyLastLine' => $justify_last_line]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->justify_last_line = $justify_last_line;
        }
        return $this;
    }
    /**
     * Get Vertical.
     */
    public function get_vertical(): null|string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_vertical();
        }
        return $this->vertical;
    }
    /**
     * Set Vertical.
     *
     * @param string $verticalAlignment see self::VERTICAL_*
     *
     * @return $this
     */
    public function set_vertical(string $vertical_alignment): static
    {
        $vertical_alignment = strtolower($vertical_alignment);
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['vertical' => $vertical_alignment]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->vertical = $vertical_alignment;
        }
        return $this;
    }
    /**
     * Get TextRotation.
     */
    public function get_text_rotation(): null|int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_text_rotation();
        }
        return $this->text_rotation;
    }
    /**
     * Set TextRotation.
     *
     * @return $this
     */
    public function set_text_rotation(int $angle_in_degrees): static
    {
        // Excel2007 value 255 => PhpSpreadsheet value -165
        if ($angle_in_degrees == self::TEXTROTATION_STACK_EXCEL) {
            $angle_in_degrees = self::TEXTROTATION_STACK_PHPSPREADSHEET;
        }
        // Set rotation
        if ($angle_in_degrees >= -90 && $angle_in_degrees <= 90 || $angle_in_degrees == self::TEXTROTATION_STACK_PHPSPREADSHEET) {
            if ($this->is_supervisor) {
                $style_array = $this->get_style_array(['textRotation' => $angle_in_degrees]);
                $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
            } else {
                $this->text_rotation = $angle_in_degrees;
            }
        } else {
            throw new Php_Spreadsheet_Exception("Text rotation {$angle_in_degrees} should be a value between -90 and 90.");
        }
        return $this;
    }
    /**
     * Get Wrap Text.
     */
    public function get_wrap_text(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_wrap_text();
        }
        return $this->wrap_text;
    }
    /**
     * Set Wrap Text.
     *
     * @return $this
     */
    public function set_wrap_text(bool $wrapped): static
    {
        if ($wrapped == '') {
            $wrapped = false;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['wrapText' => $wrapped]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->wrap_text = $wrapped;
        }
        return $this;
    }
    /**
     * Get Shrink to fit.
     */
    public function get_shrink_to_fit(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_shrink_to_fit();
        }
        return $this->shrink_to_fit;
    }
    /**
     * Set Shrink to fit.
     *
     * @return $this
     */
    public function set_shrink_to_fit(bool $shrink): static
    {
        if ($shrink == '') {
            $shrink = false;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['shrinkToFit' => $shrink]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->shrink_to_fit = $shrink;
        }
        return $this;
    }
    /**
     * Get indent.
     */
    public function get_indent(): int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_indent();
        }
        return $this->indent;
    }
    /**
     * Set indent.
     *
     * @return $this
     */
    public function set_indent(int $indent): static
    {
        if ($indent > 0) {
            if ($this->get_horizontal() != self::HORIZONTAL_GENERAL && $this->get_horizontal() != self::HORIZONTAL_LEFT && $this->get_horizontal() != self::HORIZONTAL_RIGHT && $this->get_horizontal() != self::HORIZONTAL_DISTRIBUTED) {
                $indent = 0;
                // indent not supported
            }
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['indent' => $indent]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->indent = $indent;
        }
        return $this;
    }
    /**
     * Get read order.
     */
    public function get_read_order(): int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_read_order();
        }
        return $this->read_order;
    }
    /**
     * Set read order.
     *
     * @return $this
     */
    public function set_read_order(int $read_order): static
    {
        if ($read_order < 0 || $read_order > 2) {
            $read_order = 0;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['readOrder' => $read_order]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->read_order = $read_order;
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
        return md5($this->horizontal . ($this->justify_last_line === null ? 'null' : ($this->justify_last_line ? 't' : 'f')) . $this->vertical . $this->text_rotation . ($this->wrap_text ? 't' : 'f') . ($this->shrink_to_fit ? 't' : 'f') . $this->indent . $this->read_order . self::class);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'horizontal', $this->get_horizontal());
        $this->export_array2($exported_array, 'justifyLastLine', $this->get_justify_last_line());
        $this->export_array2($exported_array, 'indent', $this->get_indent());
        $this->export_array2($exported_array, 'readOrder', $this->get_read_order());
        $this->export_array2($exported_array, 'shrinkToFit', $this->get_shrink_to_fit());
        $this->export_array2($exported_array, 'textRotation', $this->get_text_rotation());
        $this->export_array2($exported_array, 'vertical', $this->get_vertical());
        $this->export_array2($exported_array, 'wrapText', $this->get_wrap_text());
        return $exported_array;
    }
}