<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
class Borders extends Supervisor
{
    // Diagonal directions
    public const DIAGONAL_NONE = 0;
    public const DIAGONAL_UP = 1;
    public const DIAGONAL_DOWN = 2;
    public const DIAGONAL_BOTH = 3;
    /**
     * Left.
     */
    protected Border $left;
    /**
     * Right.
     */
    protected Border $right;
    /**
     * Top.
     */
    protected Border $top;
    /**
     * Bottom.
     */
    protected Border $bottom;
    /**
     * Diagonal.
     */
    protected Border $diagonal;
    /**
     * DiagonalDirection.
     */
    protected int $diagonal_direction;
    /**
     * All borders pseudo-border. Only applies to supervisor.
     */
    protected Border $all_borders;
    /**
     * Outline pseudo-border. Only applies to supervisor.
     */
    protected Border $outline;
    /**
     * Inside pseudo-border. Only applies to supervisor.
     */
    protected Border $inside;
    /**
     * Vertical pseudo-border. Only applies to supervisor.
     */
    protected Border $vertical;
    /**
     * Horizontal pseudo-border. Only applies to supervisor.
     */
    protected Border $horizontal;
    /**
     * Create a new Borders.
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
        $this->left = new Border($is_supervisor, $is_conditional);
        $this->right = new Border($is_supervisor, $is_conditional);
        $this->top = new Border($is_supervisor, $is_conditional);
        $this->bottom = new Border($is_supervisor, $is_conditional);
        $this->diagonal = new Border($is_supervisor, $is_conditional);
        $this->diagonal_direction = self::DIAGONAL_NONE;
        // Specially for supervisor
        if ($is_supervisor) {
            // Initialize pseudo-borders
            $this->all_borders = new Border(true, $is_conditional);
            $this->outline = new Border(true, $is_conditional);
            $this->inside = new Border(true, $is_conditional);
            $this->vertical = new Border(true, $is_conditional);
            $this->horizontal = new Border(true, $is_conditional);
            // bind parent if we are a supervisor
            $this->left->bind_parent($this, 'left');
            $this->right->bind_parent($this, 'right');
            $this->top->bind_parent($this, 'top');
            $this->bottom->bind_parent($this, 'bottom');
            $this->diagonal->bind_parent($this, 'diagonal');
            $this->all_borders->bind_parent($this, 'allBorders');
            $this->outline->bind_parent($this, 'outline');
            $this->inside->bind_parent($this, 'inside');
            $this->vertical->bind_parent($this, 'vertical');
            $this->horizontal->bind_parent($this, 'horizontal');
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
        return $parent->get_shared_component()->get_borders();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{borders: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['borders' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getBorders()->applyFromArray(
     *         [
     *             'bottom' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ],
     *             'top' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ]
     *         ]
     * );
     * </code>
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getBorders()->applyFromArray(
     *         [
     *             'allBorders' => [
     *                 'borderStyle' => Border::BORDER_DASHDOT,
     *                 'color' => [
     *                     'rgb' => '808080'
     *                 ]
     *             ]
     *         ]
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
            /** @var array{left?: float[], right?: float[], top?: float[], bottom?: float[], diagonal?: mixed[], diagonalDirection?: int, allBorders?: mixed[][]} $styleArray */
            if (isset($style_array['left'])) {
                $this->get_left()->apply_from_array($style_array['left']);
            }
            if (isset($style_array['right'])) {
                $this->get_right()->apply_from_array($style_array['right']);
            }
            if (isset($style_array['top'])) {
                $this->get_top()->apply_from_array($style_array['top']);
            }
            if (isset($style_array['bottom'])) {
                $this->get_bottom()->apply_from_array($style_array['bottom']);
            }
            if (isset($style_array['diagonal'])) {
                $this->get_diagonal()->apply_from_array($style_array['diagonal']);
            }
            if (isset($style_array['diagonalDirection'])) {
                $this->set_diagonal_direction($style_array['diagonalDirection']);
            }
            if (isset($style_array['allBorders'])) {
                $this->get_left()->apply_from_array($style_array['allBorders']);
                $this->get_right()->apply_from_array($style_array['allBorders']);
                $this->get_top()->apply_from_array($style_array['allBorders']);
                $this->get_bottom()->apply_from_array($style_array['allBorders']);
            }
        }
        return $this;
    }
    /**
     * Get Left.
     */
    public function get_left(): Border
    {
        return $this->left;
    }
    /**
     * Get Right.
     */
    public function get_right(): Border
    {
        return $this->right;
    }
    /**
     * Get Top.
     */
    public function get_top(): Border
    {
        return $this->top;
    }
    /**
     * Get Bottom.
     */
    public function get_bottom(): Border
    {
        return $this->bottom;
    }
    /**
     * Get Diagonal.
     */
    public function get_diagonal(): Border
    {
        return $this->diagonal;
    }
    /**
     * Get AllBorders (pseudo-border). Only applies to supervisor.
     */
    public function get_all_borders(): Border
    {
        if (!$this->is_supervisor) {
            throw new Php_Spreadsheet_Exception('Can only get pseudo-border for supervisor.');
        }
        return $this->all_borders;
    }
    /**
     * Get Outline (pseudo-border). Only applies to supervisor.
     */
    public function get_outline(): Border
    {
        if (!$this->is_supervisor) {
            throw new Php_Spreadsheet_Exception('Can only get pseudo-border for supervisor.');
        }
        return $this->outline;
    }
    /**
     * Get Inside (pseudo-border). Only applies to supervisor.
     */
    public function get_inside(): Border
    {
        if (!$this->is_supervisor) {
            throw new Php_Spreadsheet_Exception('Can only get pseudo-border for supervisor.');
        }
        return $this->inside;
    }
    /**
     * Get Vertical (pseudo-border). Only applies to supervisor.
     */
    public function get_vertical(): Border
    {
        if (!$this->is_supervisor) {
            throw new Php_Spreadsheet_Exception('Can only get pseudo-border for supervisor.');
        }
        return $this->vertical;
    }
    /**
     * Get Horizontal (pseudo-border). Only applies to supervisor.
     */
    public function get_horizontal(): Border
    {
        if (!$this->is_supervisor) {
            throw new Php_Spreadsheet_Exception('Can only get pseudo-border for supervisor.');
        }
        return $this->horizontal;
    }
    /**
     * Get DiagonalDirection.
     */
    public function get_diagonal_direction(): int
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_diagonal_direction();
        }
        return $this->diagonal_direction;
    }
    /**
     * Set DiagonalDirection.
     *
     * @param int $direction see self::DIAGONAL_*
     *
     * @return $this
     */
    public function set_diagonal_direction(int $direction): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['diagonalDirection' => $direction]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->diagonal_direction = $direction;
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
            return $this->get_shared_component()->get_hashcode();
        }
        return md5($this->get_left()->get_hash_code() . $this->get_right()->get_hash_code() . $this->get_top()->get_hash_code() . $this->get_bottom()->get_hash_code() . $this->get_diagonal()->get_hash_code() . $this->get_diagonal_direction() . self::class);
    }
    /** @return mixed[][] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'bottom', $this->get_bottom());
        $this->export_array2($exported_array, 'diagonal', $this->get_diagonal());
        $this->export_array2($exported_array, 'diagonalDirection', $this->get_diagonal_direction());
        $this->export_array2($exported_array, 'left', $this->get_left());
        $this->export_array2($exported_array, 'right', $this->get_right());
        $this->export_array2($exported_array, 'top', $this->get_top());
        /** @var mixed[][] $exportedArray */
        return $exported_array;
    }
}