<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Chart\Chart_Color;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Style extends Supervisor
{
    /**
     * Font.
     */
    protected Font $font;
    /**
     * Fill.
     */
    protected Fill $fill;
    /**
     * Borders.
     */
    protected Borders $borders;
    /**
     * Alignment.
     */
    protected Alignment $alignment;
    /**
     * Number Format.
     */
    protected Number_Format $number_format;
    /**
     * Protection.
     */
    protected Protection $protection;
    /**
     * Index of style in collection. Only used for real style.
     */
    protected int $index;
    /**
     * Use Quote Prefix when displaying in cell editor. Only used for real style.
     */
    protected bool $quote_prefix = false;
    protected bool $check_box = false;
    /**
     * Internal cache for styles
     * Used when applying style on range of cells (column or row) and cleared when
     * all cells in range is styled.
     *
     * PhpSpreadsheet will always minimize the amount of styles used. So cells with
     * same styles will reference the same Style instance. To check if two styles
     * are similar Style::getHashCode() is used. This call is expensive. To minimize
     * the need to call this method we can cache the internal PHP object id of the
     * Style in the range. Style::getHashCode() will then only be called when we
     * encounter a unique style.
     *
     * @see Style::applyFromArray()
     * @see Style::getHashCode()
     *
     * @var null|array<string, mixed[]>
     */
    private static ?array $cached_styles = null;
    /**
     * Create a new Style.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *         Leave this value at default unless you understand exactly what
     *    its ramifications are
     * @param bool $isConditional Flag indicating if this is a conditional style or not
     *       Leave this value at default unless you understand exactly what
     *    its ramifications are
     */
    public function __construct(bool $is_supervisor = false, bool $is_conditional = false)
    {
        parent::__construct($is_supervisor);
        // Initialise values
        $this->font = new Font($is_supervisor, $is_conditional);
        $this->fill = new Fill($is_supervisor, $is_conditional);
        $this->borders = new Borders($is_supervisor, $is_conditional);
        $this->alignment = new Alignment($is_supervisor, $is_conditional);
        $this->number_format = new Number_Format($is_supervisor, $is_conditional);
        $this->protection = new Protection($is_supervisor, $is_conditional);
        // bind parent if we are a supervisor
        if ($is_supervisor) {
            $this->font->bind_parent($this);
            $this->fill->bind_parent($this);
            $this->borders->bind_parent($this);
            $this->alignment->bind_parent($this);
            $this->number_format->bind_parent($this);
            $this->protection->bind_parent($this);
        }
    }
    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    public function get_shared_component(): self
    {
        $active_sheet = $this->get_active_sheet();
        $selected_cell = Functions::trim_sheet_from_cell_reference($this->get_active_cell());
        // e.g. 'A1'
        if ($active_sheet->cell_exists($selected_cell)) {
            $xf_index = $active_sheet->get_cell($selected_cell)->get_xf_index();
        } else {
            $xf_index = 0;
        }
        return $active_sheet->get_parent_or_throw()->get_cell_xf_by_index($xf_index);
    }
    /**
     * Get parent. Only used for style supervisor.
     */
    public function get_parent(): Spreadsheet
    {
        return $this->get_active_sheet()->get_parent_or_throw();
    }
    private const REGEX_WHOLE_COLUMN = '/^[A-Z]+1:[A-Z]+' . Address_Range::MAX_ROW . '$/';
    private const REGEX_WHOLE_ROW = '/^A\d+:' . Address_Range::MAX_COLUMN . '\d+$/';
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{quotePrefix: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['quotePrefix' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->applyFromArray(
     *     [
     *         'font' => [
     *             'name' => 'Arial',
     *             'bold' => true,
     *             'italic' => false,
     *             'underline' => Font::UNDERLINE_DOUBLE,
     *             'strikethrough' => false,
     *             'color' => [
     *                 'rgb' => '808080'
     *             ]
     *         ],
     *         'borders' => [
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
     *         ],
     *         'alignment' => [
     *             'horizontal' => Alignment::HORIZONTAL_CENTER,
     *             'vertical' => Alignment::VERTICAL_CENTER,
     *             'wrapText' => true,
     *         ],
     *         'quotePrefix'    => true
     *     ]
     * );
     * </code>
     *
     * @param mixed[] $styleArray Array containing style information
     * @param bool $advancedBorders advanced mode for setting borders
     *
     * @return $this
     */
    public function apply_from_array(array $style_array, bool $advanced_borders = true): static
    {
        if ($this->is_supervisor) {
            $p_range = $this->get_selected_cells();
            // Uppercase coordinate and strip any Worksheet reference from the selected range
            $p_range = strtoupper($p_range);
            if (str_contains($p_range, '!')) {
                $p_range_worksheet = String_Helper::str_to_upper(substr($p_range, 0, (int) strrpos($p_range, '!')));
                $p_range_worksheet = Worksheet::un_apostrophize_title($p_range_worksheet);
                if ($p_range_worksheet !== '' && String_Helper::str_to_upper($this->get_active_sheet()->get_title()) !== $p_range_worksheet) {
                    throw new Exception('Invalid Worksheet for specified Range');
                }
                $p_range = strtoupper(Functions::trim_sheet_from_cell_reference($p_range));
            }
            // Is it a cell range or a single cell?
            if (!str_contains($p_range, ':')) {
                $range_a = $p_range;
                $range_b = $p_range;
            } else {
                [$range_a, $range_b] = explode(':', $p_range);
            }
            // Calculate range outer borders
            $range_start = Coordinate::coordinate_from_string($range_a);
            $range_end = Coordinate::coordinate_from_string($range_b);
            $range_start_indexes = Coordinate::indexes_from_string($range_a);
            $range_end_indexes = Coordinate::indexes_from_string($range_b);
            $column_start = $range_start[0];
            $column_end = $range_end[0];
            // Make sure we can loop upwards on rows and columns
            if ($range_start_indexes[0] > $range_end_indexes[0] && $range_start_indexes[1] > $range_end_indexes[1]) {
                $tmp = $range_start_indexes;
                $range_start_indexes = $range_end_indexes;
                $range_end_indexes = $tmp;
            }
            // ADVANCED MODE:
            if ($advanced_borders && isset($style_array['borders'])) {
                // 'allBorders' is a shorthand property for 'outline' and 'inside' and
                //        it applies to components that have not been set explicitly
                /** @var mixed[][] $styleArray */
                if (isset($style_array['borders']['allBorders'])) {
                    foreach (['outline', 'inside'] as $component) {
                        if (!isset($style_array['borders'][$component])) {
                            $style_array['borders'][$component] = $style_array['borders']['allBorders'];
                        }
                    }
                    unset($style_array['borders']['allBorders']);
                    // not needed any more
                }
                // 'outline' is a shorthand property for 'top', 'right', 'bottom', 'left'
                //        it applies to components that have not been set explicitly
                if (isset($style_array['borders']['outline'])) {
                    foreach (['top', 'right', 'bottom', 'left'] as $component) {
                        if (!isset($style_array['borders'][$component])) {
                            $style_array['borders'][$component] = $style_array['borders']['outline'];
                        }
                    }
                    unset($style_array['borders']['outline']);
                    // not needed any more
                }
                // 'inside' is a shorthand property for 'vertical' and 'horizontal'
                //        it applies to components that have not been set explicitly
                if (isset($style_array['borders']['inside'])) {
                    foreach (['vertical', 'horizontal'] as $component) {
                        if (!isset($style_array['borders'][$component])) {
                            $style_array['borders'][$component] = $style_array['borders']['inside'];
                        }
                    }
                    unset($style_array['borders']['inside']);
                    // not needed any more
                }
                // width and height characteristics of selection, 1, 2, or 3 (for 3 or more)
                $x_max = min($range_end_indexes[0] - $range_start_indexes[0] + 1, 3);
                $y_max = min($range_end_indexes[1] - $range_start_indexes[1] + 1, 3);
                // loop through up to 3 x 3 = 9 regions
                for ($x = 1; $x <= $x_max; ++$x) {
                    // start column index for region
                    $col_start = $x == 3 ? Coordinate::string_from_column_index($range_end_indexes[0]) : Coordinate::string_from_column_index($range_start_indexes[0] + $x - 1);
                    // end column index for region
                    $col_end = $x == 1 ? Coordinate::string_from_column_index($range_start_indexes[0]) : Coordinate::string_from_column_index($range_end_indexes[0] - $x_max + $x);
                    for ($y = 1; $y <= $y_max; ++$y) {
                        // which edges are touching the region
                        $edges = [];
                        if ($x == 1) {
                            // are we at left edge
                            $edges[] = 'left';
                        }
                        if ($x == $x_max) {
                            // are we at right edge
                            $edges[] = 'right';
                        }
                        if ($y == 1) {
                            // are we at top edge?
                            $edges[] = 'top';
                        }
                        if ($y == $y_max) {
                            // are we at bottom edge?
                            $edges[] = 'bottom';
                        }
                        // start row index for region
                        $row_start = $y == 3 ? $range_end_indexes[1] : $range_start_indexes[1] + $y - 1;
                        // end row index for region
                        $row_end = $y == 1 ? $range_start_indexes[1] : $range_end_indexes[1] - $y_max + $y;
                        // build range for region
                        $range = $col_start . $row_start . ':' . $col_end . $row_end;
                        // retrieve relevant style array for region
                        $region_styles = $style_array;
                        unset($region_styles['borders']['inside']);
                        // what are the inner edges of the region when looking at the selection
                        $inner_edges = array_diff(['top', 'right', 'bottom', 'left'], $edges);
                        // inner edges that are not touching the region should take the 'inside' border properties if they have been set
                        foreach ($inner_edges as $inner_edge) {
                            switch ($inner_edge) {
                                case 'top':
                                case 'bottom':
                                    /** @var mixed[][] $styleArray */
                                    // should pick up 'horizontal' border property if set
                                    if (isset($style_array['borders']['horizontal'])) {
                                        /** @var mixed[][] $regionStyles */
                                        $region_styles['borders'][$inner_edge] = $style_array['borders']['horizontal'];
                                    } else {
                                        /** @var mixed[][] $regionStyles */
                                        unset($region_styles['borders'][$inner_edge]);
                                    }
                                    break;
                                case 'left':
                                case 'right':
                                    // should pick up 'vertical' border property if set
                                    if (isset($style_array['borders']['vertical'])) {
                                        $region_styles['borders'][$inner_edge] = $style_array['borders']['vertical'];
                                    } else {
                                        unset($region_styles['borders'][$inner_edge]);
                                    }
                                    break;
                            }
                        }
                        // apply region style to region by calling applyFromArray() in simple mode
                        $this->get_active_sheet()->get_style($range)->apply_from_array($region_styles, false);
                    }
                }
                // restore initial cell selection range
                $this->get_active_sheet()->get_style($p_range);
                return $this;
            }
            // SIMPLE MODE:
            // Selection type, inspect
            if (preg_match(self::REGEX_WHOLE_COLUMN, $p_range)) {
                $selection_type = 'COLUMN';
                // Enable caching of styles
                self::$cached_styles = ['hashByObjId' => [], 'styleByHash' => []];
            } elseif (preg_match(self::REGEX_WHOLE_ROW, $p_range)) {
                $selection_type = 'ROW';
                // Enable caching of styles
                self::$cached_styles = ['hashByObjId' => [], 'styleByHash' => []];
            } else {
                $selection_type = 'CELL';
            }
            // First loop through columns, rows, or cells to find out which styles are affected by this operation
            $old_xf_indexes = $this->get_old_xf_indexes($selection_type, $range_start_indexes, $range_end_indexes, $column_start, $column_end, $style_array);
            // clone each of the affected styles, apply the style array, and add the new styles to the workbook
            $workbook = $this->get_active_sheet()->get_parent_or_throw();
            $new_xf_indexes = [];
            foreach ($old_xf_indexes as $old_xf_index => $dummy) {
                $style = $workbook->get_cell_xf_by_index($old_xf_index);
                // $cachedStyles is set when applying style for a range of cells, either column or row
                if (self::$cached_styles === null) {
                    // Clone the old style and apply style-array
                    $new_style = clone $style;
                    $new_style->apply_from_array($style_array);
                    // Look for existing style we can use instead (reduce memory usage)
                    $existing_style = $workbook->get_cell_xf_by_hash_code($new_style->get_hash_code());
                } else {
                    // Style cache is stored by Style::getHashCode(). But calling this method is
                    // expensive. So we cache the php obj id -> hash.
                    $obj_id = spl_object_id($style);
                    // Look for the original HashCode
                    $style_hash = self::$cached_styles['hashByObjId'][$obj_id] ?? null;
                    if ($style_hash === null) {
                        // This object_id is not cached, store the hashcode in case encounter again
                        $style_hash = self::$cached_styles['hashByObjId'][$obj_id] = $style->get_hash_code();
                    }
                    // Find existing style by hash.
                    /** @var string $styleHash */
                    $existing_style = self::$cached_styles['styleByHash'][$style_hash] ?? null;
                    if (!$existing_style) {
                        // The old style combined with the new style array is not cached, so we create it now
                        $new_style = clone $style;
                        $new_style->apply_from_array($style_array);
                        // Look for similar style in workbook to reduce memory usage
                        $existing_style = $workbook->get_cell_xf_by_hash_code($new_style->get_hash_code());
                        // Cache the new style by original hashcode
                        self::$cached_styles['styleByHash'][$style_hash] = $existing_style instanceof self ? $existing_style : $new_style;
                    }
                }
                if ($existing_style) {
                    // there is already such cell Xf in our collection
                    /** @var Style $existingStyle */
                    $new_xf_indexes[$old_xf_index] = $existing_style->get_index();
                } else {
                    if (!isset($new_style)) {
                        // Handle bug in PHPStan, see https://github.com/phpstan/phpstan/issues/5805
                        // $newStyle should always be defined.
                        // This block might not be needed in the future
                        // @codeCoverageIgnoreStart
                        $new_style = clone $style;
                        $new_style->apply_from_array($style_array);
                        // @codeCoverageIgnoreEnd
                    }
                    // we don't have such a cell Xf, need to add
                    $workbook->add_cell_xf($new_style);
                    $new_xf_indexes[$old_xf_index] = $new_style->get_index();
                }
            }
            // Loop through columns, rows, or cells again and update the XF index
            switch ($selection_type) {
                case 'COLUMN':
                    for ($col = $range_start_indexes[0]; $col <= $range_end_indexes[0]; ++$col) {
                        $column_dimension = $this->get_active_sheet()->get_column_dimension_by_column($col);
                        $old_xf_index = $column_dimension->get_xf_index();
                        /** @var int[] $newXfIndexes */
                        $column_dimension->set_xf_index($new_xf_indexes[$old_xf_index]);
                    }
                    // Disable caching of styles
                    self::$cached_styles = null;
                    break;
                case 'ROW':
                    for ($row = $range_start_indexes[1]; $row <= $range_end_indexes[1]; ++$row) {
                        $row_dimension = $this->get_active_sheet()->get_row_dimension($row);
                        // row without explicit style should be formatted based on default style
                        $old_xf_index = $row_dimension->get_xf_index() ?? 0;
                        /** @var int[] $newXfIndexes */
                        $row_dimension->set_xf_index($new_xf_indexes[$old_xf_index]);
                    }
                    // Disable caching of styles
                    self::$cached_styles = null;
                    break;
                case 'CELL':
                    for ($col = $range_start_indexes[0]; $col <= $range_end_indexes[0]; ++$col) {
                        for ($row = $range_start_indexes[1]; $row <= $range_end_indexes[1]; ++$row) {
                            $cell = $this->get_active_sheet()->get_cell([$col, $row]);
                            $old_xf_index = $cell->get_xf_index();
                            $cell->set_xf_index($new_xf_indexes[$old_xf_index]);
                        }
                    }
                    break;
            }
        } else {
            // not a supervisor, just apply the style array directly on style object
            /** @var array{
             * alignment?: mixed[],
             * fill?: array{fillType?: string, rotation?: float, startColor?: array{rgb?: string, argb?: string}, endColor?: array{rgb?: string, argb?: string}, color?: array{rgb?: string, argb?: string}},
             * font?: array{name?: string, latin?: string, eastAsian?: string, complexScript?: string, bold?: bool, italic?: bool, superscript?: bool, subscript?: bool, underline?: bool|string, strikethrough?: bool, color?: string[], size?: ?int, chartColor?: ChartColor, scheme?: string, cap?: string},
             * borders?: mixed[][],
             * numberFormat?: string[],
             * protection?: array{locked?: string, hidden?: string},
             * checkBox?: bool,
             * quotePrefix?: bool} $styleArray */
            if (isset($style_array['checkBox'])) {
                $this->check_box = $style_array['checkBox'];
            }
            if (isset($style_array['fill'])) {
                $this->get_fill()->apply_from_array($style_array['fill']);
            }
            if (isset($style_array['font'])) {
                $this->get_font()->apply_from_array($style_array['font']);
            }
            if (isset($style_array['borders'])) {
                $this->get_borders()->apply_from_array($style_array['borders']);
            }
            if (isset($style_array['alignment'])) {
                $temp = $style_array['alignment'];
                $this->get_alignment()->apply_from_array($temp);
            }
            if (isset($style_array['numberFormat'])) {
                $this->get_number_format()->apply_from_array($style_array['numberFormat']);
            }
            if (isset($style_array['protection'])) {
                $this->get_protection()->apply_from_array($style_array['protection']);
            }
            if (isset($style_array['quotePrefix'])) {
                $this->quote_prefix = $style_array['quotePrefix'];
            }
        }
        return $this;
    }
    /**
     * @param mixed[] $rangeStart
     * @param mixed[] $rangeEnd
     * @param mixed[] $styleArray
     *
     * @return mixed[]
     */
    private function get_old_xf_indexes(string $selection_type, array $range_start, array $range_end, string $column_start, string $column_end, array $style_array): array
    {
        $old_xf_indexes = [];
        switch ($selection_type) {
            case 'COLUMN':
                for ($col = $range_start[0]; $col <= $range_end[0]; ++$col) {
                    /** @var int $col */
                    $old_xf_indexes[$this->get_active_sheet()->get_column_dimension_by_column($col)->get_xf_index()] = true;
                }
                foreach ($this->get_active_sheet()->get_column_iterator($column_start, $column_end) as $column_iterator) {
                    $cell_iterator = $column_iterator->get_cell_iterator();
                    $cell_iterator->set_iterate_only_existing_cells(true);
                    foreach ($cell_iterator as $column_cell) {
                        $column_cell->get_style()->apply_from_array($style_array);
                    }
                }
                break;
            case 'ROW':
                for ($row = $range_start[1]; $row <= $range_end[1]; ++$row) {
                    /** @var int $row */
                    if ($this->get_active_sheet()->get_row_dimension($row)->get_xf_index() === null) {
                        $old_xf_indexes[0] = true;
                        // row without explicit style should be formatted based on default style
                    } else {
                        $old_xf_indexes[$this->get_active_sheet()->get_row_dimension($row)->get_xf_index()] = true;
                    }
                }
                /** @var float|int */
                $temp1 = $range_start[1];
                /** @var float|int */
                $temp2 = $range_end[1];
                foreach ($this->get_active_sheet()->get_row_iterator((int) $temp1, (int) $temp2) as $row_iterator) {
                    $cell_iterator = $row_iterator->get_cell_iterator();
                    $cell_iterator->set_iterate_only_existing_cells(true);
                    foreach ($cell_iterator as $row_cell) {
                        $row_cell->get_style()->apply_from_array($style_array);
                    }
                }
                break;
            case 'CELL':
                for ($col = $range_start[0]; $col <= $range_end[0]; ++$col) {
                    /** @var int $col */
                    for ($row = $range_start[1]; $row <= $range_end[1]; ++$row) {
                        /** @var int $row */
                        $old_xf_indexes[$this->get_active_sheet()->get_cell([$col, $row])->get_xf_index()] = true;
                    }
                }
                break;
        }
        return $old_xf_indexes;
    }
    /**
     * Get Fill.
     */
    public function get_fill(): Fill
    {
        return $this->fill;
    }
    /**
     * Get Font.
     */
    public function get_font(): Font
    {
        return $this->font;
    }
    /**
     * Set font.
     *
     * @return $this
     */
    public function set_font(Font $font): static
    {
        $this->font = $font;
        return $this;
    }
    /**
     * Get Borders.
     */
    public function get_borders(): Borders
    {
        return $this->borders;
    }
    /**
     * Get Alignment.
     */
    public function get_alignment(): Alignment
    {
        return $this->alignment;
    }
    /**
     * Get Number Format.
     */
    public function get_number_format(): Number_Format
    {
        return $this->number_format;
    }
    /**
     * Get Conditional Styles. Only used on supervisor.
     *
     * @return Conditional[]
     */
    public function get_conditional_styles(): array
    {
        return $this->get_active_sheet()->get_conditional_styles($this->get_active_cell());
    }
    /**
     * Set Conditional Styles. Only used on supervisor.
     *
     * @param Conditional[] $conditionalStyleArray Array of conditional styles
     *
     * @return $this
     */
    public function set_conditional_styles(array $conditional_style_array): static
    {
        $this->get_active_sheet()->set_conditional_styles($this->get_selected_cells(), $conditional_style_array);
        return $this;
    }
    /**
     * Get Protection.
     */
    public function get_protection(): Protection
    {
        return $this->protection;
    }
    /**
     * Get quote prefix.
     */
    public function get_quote_prefix(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_quote_prefix();
        }
        return $this->quote_prefix;
    }
    /**
     * Set quote prefix.
     *
     * @return $this
     */
    public function set_quote_prefix(bool $quote_prefix): static
    {
        if ($quote_prefix == '') {
            $quote_prefix = false;
        }
        if ($this->is_supervisor) {
            $style_array = ['quotePrefix' => $quote_prefix];
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->quote_prefix = $quote_prefix;
        }
        return $this;
    }
    public function get_check_box(): bool
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_check_box();
        }
        return $this->check_box;
    }
    public function set_check_box(bool $check_box): static
    {
        if ($this->is_supervisor) {
            $style_array = ['checkBox' => $check_box];
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->check_box = $check_box;
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
        return md5($this->fill->get_hash_code() . $this->font->get_hash_code() . $this->borders->get_hash_code() . $this->alignment->get_hash_code() . $this->number_format->get_hash_code() . $this->protection->get_hash_code() . ($this->quote_prefix ? 't' : 'f') . ($this->check_box ? 't' : 'f') . self::class);
    }
    /**
     * Get own index in style collection.
     */
    public function get_index(): int
    {
        return $this->index;
    }
    /**
     * Set own index in style collection.
     */
    public function set_index(int $index): void
    {
        $this->index = $index;
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'alignment', $this->get_alignment());
        $this->export_array2($exported_array, 'borders', $this->get_borders());
        $this->export_array2($exported_array, 'fill', $this->get_fill());
        $this->export_array2($exported_array, 'font', $this->get_font());
        $this->export_array2($exported_array, 'numberFormat', $this->get_number_format());
        $this->export_array2($exported_array, 'protection', $this->get_protection());
        $this->export_array2($exported_array, 'quotePrefix', $this->get_quote_prefix());
        return $exported_array;
    }
}