<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use ArrayObject;
use Composer\Pcre\Preg;
use Generator;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Cell_Address;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
use Php_Office\Php_Spreadsheet\Cell\Hyperlink;
use Php_Office\Php_Spreadsheet\Cell\I_Value_Binder;
use Php_Office\Php_Spreadsheet\Chart\Chart;
use Php_Office\Php_Spreadsheet\Collection\Cells;
use Php_Office\Php_Spreadsheet\Collection\Cells_Factory;
use Php_Office\Php_Spreadsheet\Comment;
use Php_Office\Php_Spreadsheet\Defined_Name;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Reference_Helper;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection as StyleProtection;
use Php_Office\Php_Spreadsheet\Style\Style;
class Worksheet
{
    // Break types
    public const BREAK_NONE = 0;
    public const BREAK_ROW = 1;
    public const BREAK_COLUMN = 2;
    // Maximum column for row break
    public const BREAK_ROW_MAX_COLUMN = 16383;
    // Sheet state
    public const SHEETSTATE_VISIBLE = 'visible';
    public const SHEETSTATE_HIDDEN = 'hidden';
    public const SHEETSTATE_VERYHIDDEN = 'veryHidden';
    public const MERGE_CELL_CONTENT_EMPTY = 'empty';
    public const MERGE_CELL_CONTENT_HIDE = 'hide';
    public const MERGE_CELL_CONTENT_MERGE = 'merge';
    public const FUNCTION_LIKE_GROUPBY = '/\b(groupby|_xleta)\b/i';
    // weird new syntax
    protected const SHEET_NAME_REQUIRES_NO_QUOTES = '/^[_\p{L}][_\p{L}\p{N}]*$/mui';
    /**
     * Maximum 31 characters allowed for sheet title.
     *
     * @var int
     */
    public const SHEET_TITLE_MAXIMUM_LENGTH = 31;
    /**
     * Invalid characters in sheet title.
     */
    private const INVALID_CHARACTERS = ['*', ':', '/', '\\', '?', '[', ']'];
    /**
     * Collection of cells.
     */
    private Cells $cell_collection;
    /**
     * Collection of row dimensions.
     *
     * @var RowDimension[]
     */
    private array $row_dimensions = [];
    /**
     * Default row dimension.
     */
    private Row_Dimension $default_row_dimension;
    /**
     * Collection of column dimensions.
     *
     * @var ColumnDimension[]
     */
    private array $column_dimensions = [];
    /**
     * Default column dimension.
     */
    private Column_Dimension $default_column_dimension;
    /**
     * Collection of drawings.
     *
     * @var ArrayObject<int, BaseDrawing>
     */
    private ArrayObject $drawing_collection;
    /**
     * Collection of drawings.
     *
     * @var ArrayObject<int, BaseDrawing>
     */
    private ArrayObject $in_cell_drawing_collection;
    /**
     * Collection of Chart objects.
     *
     * @var ArrayObject<int, Chart>
     */
    private ArrayObject $chart_collection;
    /**
     * Collection of Table objects.
     *
     * @var ArrayObject<int, Table>
     */
    private ArrayObject $table_collection;
    /**
     * Worksheet title.
     */
    private string $title = '';
    /**
     * Sheet state.
     */
    private string $sheet_state;
    /**
     * Page setup.
     */
    private Page_Setup $page_setup;
    /**
     * Page margins.
     */
    private Page_Margins $page_margins;
    /**
     * Page header/footer.
     */
    private Header_Footer $header_footer;
    /**
     * Sheet view.
     */
    private Sheet_View $sheet_view;
    /**
     * Protection.
     */
    private Protection $protection;
    /**
     * Conditional styles. Indexed by cell coordinate, e.g. 'A1'.
     *
     * @var Conditional[][]
     */
    private array $conditional_styles_collection = [];
    /**
     * Collection of row breaks.
     *
     * @var PageBreak[]
     */
    private array $row_breaks = [];
    /**
     * Collection of column breaks.
     *
     * @var PageBreak[]
     */
    private array $column_breaks = [];
    /**
     * Collection of merged cell ranges.
     *
     * @var string[]
     */
    private array $merge_cells = [];
    /**
     * Collection of protected cell ranges.
     *
     * @var ProtectedRange[]
     */
    private array $protected_cells = [];
    /**
     * Autofilter Range and selection.
     */
    private Auto_Filter $auto_filter;
    /**
     * Freeze pane.
     */
    private ?string $freeze_pane = null;
    /**
     * Default position of the right bottom pane.
     */
    private ?string $top_left_cell = null;
    private string $pane_top_left_cell = '';
    private string $active_pane = '';
    private int $x_split = 0;
    private int $y_split = 0;
    private string $pane_state = '';
    /**
     * Properties of the 4 panes.
     *
     * @var (null|Pane)[]
     */
    private array $panes = ['bottomRight' => null, 'bottomLeft' => null, 'topRight' => null, 'topLeft' => null];
    /**
     * Show gridlines?
     */
    private bool $show_gridlines = true;
    /**
     * Print gridlines?
     */
    private bool $print_gridlines = false;
    /**
     * Show row and column headers?
     */
    private bool $show_row_col_headers = true;
    /**
     * Show summary below? (Row/Column outline).
     */
    private bool $show_summary_below = true;
    /**
     * Show summary right? (Row/Column outline).
     */
    private bool $show_summary_right = true;
    /**
     * Collection of comments.
     *
     * @var Comment[]
     */
    private array $comments = [];
    /**
     * Active cell. (Only one!).
     */
    private string $active_cell = 'A1';
    /**
     * Selected cells.
     */
    private string $selected_cells = 'A1';
    /**
     * Cached highest column.
     */
    private int $cached_highest_column = 1;
    /**
     * Cached highest row.
     */
    private int $cached_highest_row = 1;
    /**
     * Right-to-left?
     */
    private bool $right_to_left = false;
    /**
     * Hyperlinks. Indexed by cell coordinate, e.g. 'A1'.
     *
     * @var Hyperlink[]
     */
    private array $hyperlink_collection = [];
    /**
     * Data validation objects. Indexed by cell coordinate, e.g. 'A1'.
     * Index can include ranges, and multiple cells/ranges.
     *
     * @var DataValidation[]
     */
    private array $data_validation_collection = [];
    /**
     * Tab color.
     */
    private ?Color $tab_color = null;
    /**
     * CodeName.
     */
    private ?string $code_name = null;
    /**
     * Create a new worksheet.
     */
    public function __construct(
        /**
         * Parent spreadsheet.
         */
        private ?Spreadsheet $parent = null,
        string $title = 'Worksheet'
    )
    {
        $this->set_title($title, false);
        // setTitle can change $pTitle
        $this->set_code_name($this->get_title());
        $this->set_sheet_state(self::SHEETSTATE_VISIBLE);
        $this->cell_collection = Cells_Factory::get_instance($this);
        // Set page setup
        $this->page_setup = new Page_Setup();
        // Set page margins
        $this->page_margins = new Page_Margins();
        // Set page header/footer
        $this->header_footer = new Header_Footer();
        // Set sheet view
        $this->sheet_view = new Sheet_View();
        // Drawing collection
        $this->drawing_collection = new ArrayObject();
        // In Cell Drawing collection
        $this->in_cell_drawing_collection = new ArrayObject();
        // Chart collection
        $this->chart_collection = new ArrayObject();
        // Protection
        $this->protection = new Protection();
        // Default row dimension
        $this->default_row_dimension = new Row_Dimension(null);
        // Default column dimension
        $this->default_column_dimension = new Column_Dimension(null);
        // AutoFilter
        $this->auto_filter = new Auto_Filter('', $this);
        // Table collection
        $this->table_collection = new ArrayObject();
    }
    /**
     * Disconnect all cells from this Worksheet object,
     * typically so that the worksheet object can be unset.
     */
    public function disconnect_cells(): void
    {
        if (isset($this->cell_collection)) {
            //* @phpstan-ignore-line
            $this->cell_collection->unset_worksheet_cells();
            unset($this->cell_collection);
        }
        //    detach ourself from the workbook, so that it can then delete this worksheet successfully
        $this->parent = null;
    }
    /**
     * Code to execute when this worksheet is unset().
     */
    public function __destruct()
    {
        Calculation::get_instance_or_null($this->parent)?->clear_calculation_cache_for_worksheet($this->title);
        $this->disconnect_cells();
        unset($this->row_dimensions, $this->column_dimensions, $this->table_collection, $this->drawing_collection, $this->in_cell_drawing_collection, $this->chart_collection, $this->auto_filter);
    }
    /**
     * Return the cell collection.
     */
    public function get_cell_collection(): Cells
    {
        return $this->cell_collection;
    }
    /**
     * Get array of invalid characters for sheet title.
     *
     * @return string[]
     */
    public static function get_invalid_characters(): array
    {
        return self::INVALID_CHARACTERS;
    }
    /**
     * Check sheet code name for valid Excel syntax.
     *
     * @param string $sheetCodeName The string to check
     *
     * @return string The valid string
     */
    private static function check_sheet_code_name(string $sheet_code_name): string
    {
        $char_count = String_Helper::count_characters($sheet_code_name);
        if ($char_count == 0) {
            throw new Exception('Sheet code name cannot be empty.');
        }
        // Some of the printable ASCII characters are invalid:  * : / \ ? [ ] and  first and last characters cannot be a "'"
        if (str_replace(self::INVALID_CHARACTERS, '', $sheet_code_name) !== $sheet_code_name || String_Helper::substring($sheet_code_name, -1, 1) == '\'' || String_Helper::substring($sheet_code_name, 0, 1) == '\'') {
            throw new Exception('Invalid character found in sheet code name');
        }
        // Enforce maximum characters allowed for sheet title
        if ($char_count > self::SHEET_TITLE_MAXIMUM_LENGTH) {
            throw new Exception('Maximum ' . self::SHEET_TITLE_MAXIMUM_LENGTH . ' characters allowed in sheet code name.');
        }
        return $sheet_code_name;
    }
    /**
     * Check sheet title for valid Excel syntax.
     *
     * @param string $sheetTitle The string to check
     *
     * @return string The valid string
     */
    private static function check_sheet_title(string $sheet_title): string
    {
        // Some of the printable ASCII characters are invalid:  * : / \ ? [ ]
        if (str_replace(self::INVALID_CHARACTERS, '', $sheet_title) !== $sheet_title) {
            throw new Exception('Invalid character found in sheet title');
        }
        // Enforce maximum characters allowed for sheet title
        if (String_Helper::count_characters($sheet_title) > self::SHEET_TITLE_MAXIMUM_LENGTH) {
            throw new Exception('Maximum ' . self::SHEET_TITLE_MAXIMUM_LENGTH . ' characters allowed in sheet title.');
        }
        return $sheet_title;
    }
    /**
     * Get a sorted list of all cell coordinates currently held in the collection by row and column.
     *
     * @param bool $sorted Also sort the cell collection?
     *
     * @return string[]
     */
    public function get_coordinates(bool $sorted = true): array
    {
        if (!isset($this->cell_collection)) {
            //* @phpstan-ignore-line
            return [];
        }
        if ($sorted) {
            return $this->cell_collection->get_sorted_coordinates();
        }
        return $this->cell_collection->get_coordinates();
    }
    /**
     * Get collection of row dimensions.
     *
     * @return RowDimension[]
     */
    public function get_row_dimensions(): array
    {
        return $this->row_dimensions;
    }
    /**
     * Get default row dimension.
     */
    public function get_default_row_dimension(): Row_Dimension
    {
        return $this->default_row_dimension;
    }
    /**
     * Get collection of column dimensions.
     *
     * @return ColumnDimension[]
     */
    public function get_column_dimensions(): array
    {
        /** @var callable $callable */
        $callable = self::column_dimension_compare(...);
        uasort($this->column_dimensions, $callable);
        return $this->column_dimensions;
    }
    private static function column_dimension_compare(Column_Dimension $a, Column_Dimension $b): int
    {
        return $a->get_column_numeric() - $b->get_column_numeric();
    }
    /**
     * Get default column dimension.
     */
    public function get_default_column_dimension(): Column_Dimension
    {
        return $this->default_column_dimension;
    }
    /**
     * Get collection of drawings.
     *
     * @return ArrayObject<int, BaseDrawing>
     */
    public function get_drawing_collection(): ArrayObject
    {
        return $this->drawing_collection;
    }
    /**
     * Get collection of drawings.
     *
     * @return ArrayObject<int, BaseDrawing>
     */
    public function get_in_cell_drawing_collection(): ArrayObject
    {
        return $this->in_cell_drawing_collection;
    }
    /**
     * Get collection of charts.
     *
     * @return ArrayObject<int, Chart>
     */
    public function get_chart_collection(): ArrayObject
    {
        return $this->chart_collection;
    }
    public function add_chart(Chart $chart): Chart
    {
        $chart->set_worksheet($this);
        $this->chart_collection[] = $chart;
        return $chart;
    }
    /**
     * Return the count of charts on this worksheet.
     *
     * @return int The number of charts
     */
    public function get_chart_count(): int
    {
        return count($this->chart_collection);
    }
    /**
     * Get a chart by its index position.
     *
     * @param ?string $index Chart index position
     *
     * @return Chart|false
     */
    public function get_chart_by_index(?string $index): false|\Php_Office\Php_Spreadsheet\Chart\Chart
    {
        $chart_count = count($this->chart_collection);
        if ($chart_count == 0) {
            return false;
        }
        if ($index === null) {
            $index = --$chart_count;
        }
        if (!isset($this->chart_collection[$index])) {
            return false;
        }
        return $this->chart_collection[$index];
    }
    /**
     * Return an array of the names of charts on this worksheet.
     *
     * @return string[] The names of charts
     */
    public function get_chart_names(): array
    {
        $chart_names = [];
        foreach ($this->chart_collection as $chart) {
            $chart_names[] = $chart->get_name();
        }
        return $chart_names;
    }
    /**
     * Get a chart by name.
     *
     * @param string $chartName Chart name
     *
     * @return Chart|false
     */
    public function get_chart_by_name(string $chart_name)
    {
        foreach ($this->chart_collection as $chart) {
            if ($chart->get_name() == $chart_name) {
                return $chart;
            }
        }
        return false;
    }
    public function get_chart_by_name_or_throw(string $chart_name): Chart
    {
        $chart = $this->get_chart_by_name($chart_name);
        if ($chart !== false) {
            return $chart;
        }
        throw new Exception("Sheet does not have a chart named {$chart_name}.");
    }
    /**
     * Refresh column dimensions.
     *
     * @return $this
     */
    public function refresh_column_dimensions(): static
    {
        $new_column_dimensions = [];
        foreach ($this->get_column_dimensions() as $obj_column_dimension) {
            $new_column_dimensions[$obj_column_dimension->get_column_index()] = $obj_column_dimension;
        }
        $this->column_dimensions = $new_column_dimensions;
        return $this;
    }
    /**
     * Refresh row dimensions.
     *
     * @return $this
     */
    public function refresh_row_dimensions(): static
    {
        $new_row_dimensions = [];
        foreach ($this->get_row_dimensions() as $obj_row_dimension) {
            $new_row_dimensions[$obj_row_dimension->get_row_index()] = $obj_row_dimension;
        }
        $this->row_dimensions = $new_row_dimensions;
        return $this;
    }
    /**
     * Calculate worksheet dimension.
     *
     * @return string String containing the dimension of this worksheet
     */
    public function calculate_worksheet_dimension(): string
    {
        // Return
        return 'A1:' . $this->get_highest_column() . $this->get_highest_row();
    }
    /**
     * Calculate worksheet data dimension.
     *
     * @return string String containing the dimension of this worksheet that actually contain data
     */
    public function calculate_worksheet_data_dimension(): string
    {
        // Return
        return 'A1:' . $this->get_highest_data_column() . $this->get_highest_data_row();
    }
    /**
     * Calculate widths for auto-size columns.
     *
     * @return $this
     */
    public function calculate_column_widths(): static
    {
        $active_sheet = $this->get_parent()?->get_active_sheet_index();
        $selected_cells = $this->selected_cells;
        // initialize $autoSizes array
        $auto_sizes = [];
        foreach ($this->get_column_dimensions() as $col_dimension) {
            if ($col_dimension->get_auto_size()) {
                $auto_sizes[$col_dimension->get_column_index()] = -1;
            }
        }
        // There is only something to do if there are some auto-size columns
        if (!empty($auto_sizes)) {
            $hold_active_pane = $this->active_pane;
            // build list of cells references that participate in a merge
            $is_merge_cell = [];
            foreach ($this->get_merge_cells() as $cells) {
                foreach (Coordinate::extract_all_cell_references_in_range($cells) as $cell_reference) {
                    $is_merge_cell[$cell_reference] = true;
                }
            }
            $auto_filter_indent_ranges = (new Auto_Fit($this))->get_auto_filter_indent_ranges();
            // loop through all cells in the worksheet
            foreach ($this->get_coordinates(false) as $coordinate) {
                $cell = $this->get_cell_or_null($coordinate);
                if ($cell !== null && isset($auto_sizes[$this->cell_collection->get_current_column()])) {
                    //Determine if cell is in merge range
                    $is_merged = isset($is_merge_cell[$this->cell_collection->get_current_coordinate()]);
                    //By default merged cells should be ignored
                    $is_merged_but_proceed = false;
                    //The only exception is if it's a merge range value cell of a 'vertical' range (1 column wide)
                    if ($is_merged && $cell->is_merge_range_value_cell()) {
                        $range = (string) $cell->get_merge_range();
                        $range_boundaries = Coordinate::range_dimension($range);
                        if ($range_boundaries[0] === 1) {
                            $is_merged_but_proceed = true;
                        }
                    }
                    // Determine width if cell is not part of a merge or does and is a value cell of 1-column wide range
                    if (!$is_merged || $is_merged_but_proceed) {
                        // Determine if we need to make an adjustment for the first row in an AutoFilter range that
                        //    has a column filter dropdown
                        $filter_adjustment = false;
                        foreach ($auto_filter_indent_ranges as $auto_filter_first_row_range) {
                            /** @var string $autoFilterFirstRowRange */
                            if ($cell->is_in_range($auto_filter_first_row_range)) {
                                $filter_adjustment = true;
                                break;
                            }
                        }
                        $indent_adjustment = $cell->get_style()->get_alignment()->get_indent();
                        $indent_adjustment += (int) ($cell->get_style()->get_alignment()->get_horizontal() === Alignment::HORIZONTAL_CENTER);
                        // Calculated value
                        // To formatted string
                        $cell_value = Number_Format::to_formatted_string($cell->get_calculated_value_string(), (string) $this->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_number_format()->get_format_code(true));
                        if ($cell_value !== '') {
                            $auto_sizes[$this->cell_collection->get_current_column()] = max($auto_sizes[$this->cell_collection->get_current_column()], round(Shared\Font::calculate_column_width($this->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_font(), $cell_value, (int) $this->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_alignment()->get_text_rotation(), $this->get_parent_or_throw()->get_default_style()->get_font(), $filter_adjustment, $indent_adjustment), 3));
                        }
                    }
                }
            }
            // adjust column widths
            foreach ($auto_sizes as $column_index => $width) {
                if ($width == -1) {
                    $width = $this->get_default_column_dimension()->get_width();
                }
                $this->get_column_dimension($column_index)->set_width($width);
            }
            $this->active_pane = $hold_active_pane;
        }
        if ($active_sheet !== null && $active_sheet >= 0) {
            $this->get_parent()?->set_active_sheet_index($active_sheet);
        }
        $this->set_selected_cells($selected_cells);
        return $this;
    }
    /**
     * Get parent or null.
     */
    public function get_parent(): ?Spreadsheet
    {
        return $this->parent;
    }
    /**
     * Get parent, throw exception if null.
     */
    public function get_parent_or_throw(): Spreadsheet
    {
        if ($this->parent !== null) {
            return $this->parent;
        }
        throw new Exception('Sheet does not have a parent.');
    }
    /**
     * Re-bind parent.
     *
     * @return $this
     */
    public function rebind_parent(Spreadsheet $parent): static
    {
        if ($this->parent !== null) {
            $defined_names = $this->parent->get_defined_names();
            foreach ($defined_names as $defined_name) {
                $parent->add_defined_name($defined_name);
            }
            $this->parent->remove_sheet_by_index($this->parent->get_index($this));
        }
        $this->parent = $parent;
        return $this;
    }
    public function set_parent(Spreadsheet $parent): self
    {
        $this->parent = $parent;
        return $this;
    }
    /**
     * Get title.
     */
    public function get_title(): string
    {
        return $this->title;
    }
    /**
     * Set title.
     *
     * @param string $title String containing the dimension of this worksheet
     * @param bool $updateFormulaCellReferences Flag indicating whether cell references in formulae should
     *            be updated to reflect the new sheet name.
     *          This should be left as the default true, unless you are
     *          certain that no formula cells on any worksheet contain
     *          references to this worksheet
     * @param bool $validate False to skip validation of new title. WARNING: This should only be set
     *                       at parse time (by Readers), where titles can be assumed to be valid.
     *
     * @return $this
     */
    public function set_title(string $title, bool $update_formula_cell_references = true, bool $validate = true): static
    {
        // Is this a 'rename' or not?
        if ($this->get_title() == $title) {
            return $this;
        }
        // Old title
        $old_title = $this->get_title();
        if ($validate) {
            // Syntax check
            self::check_sheet_title($title);
            if ($this->parent && $this->parent->get_index($this, true) >= 0) {
                // Is there already such sheet name?
                if ($this->parent->sheet_name_exists($title)) {
                    // Use name, but append with lowest possible integer
                    if (String_Helper::count_characters($title) > 29) {
                        $title = String_Helper::substring($title, 0, 29);
                    }
                    $i = 1;
                    while ($this->parent->sheet_name_exists($title . ' ' . $i)) {
                        ++$i;
                        if ($i == 10) {
                            if (String_Helper::count_characters($title) > 28) {
                                $title = String_Helper::substring($title, 0, 28);
                            }
                        } elseif ($i == 100) {
                            if (String_Helper::count_characters($title) > 27) {
                                $title = String_Helper::substring($title, 0, 27);
                            }
                        }
                    }
                    $title .= " {$i}";
                }
            }
        }
        // Set title
        $this->title = $title;
        if ($this->parent && $this->parent->get_index($this, true) >= 0) {
            // New title
            $new_title = $this->get_title();
            $this->parent->get_calculation_engine()->rename_calculation_cache_for_worksheet($old_title, $new_title);
            if ($update_formula_cell_references) {
                Reference_Helper::get_instance()->update_named_formulae($this->parent, $old_title, $new_title);
            }
        }
        return $this;
    }
    /**
     * Get sheet state.
     *
     * @return string Sheet state (visible, hidden, veryHidden)
     */
    public function get_sheet_state(): string
    {
        return $this->sheet_state;
    }
    /**
     * Set sheet state.
     *
     * @param string $value Sheet state (visible, hidden, veryHidden)
     *
     * @return $this
     */
    public function set_sheet_state(string $value): static
    {
        $this->sheet_state = $value;
        return $this;
    }
    /**
     * Get page setup.
     */
    public function get_page_setup(): Page_Setup
    {
        return $this->page_setup;
    }
    /**
     * Set page setup.
     *
     * @return $this
     */
    public function set_page_setup(Page_Setup $page_setup): static
    {
        $this->page_setup = $page_setup;
        return $this;
    }
    /**
     * Get page margins.
     */
    public function get_page_margins(): Page_Margins
    {
        return $this->page_margins;
    }
    /**
     * Set page margins.
     *
     * @return $this
     */
    public function set_page_margins(Page_Margins $page_margins): static
    {
        $this->page_margins = $page_margins;
        return $this;
    }
    /**
     * Get page header/footer.
     */
    public function get_header_footer(): Header_Footer
    {
        return $this->header_footer;
    }
    /**
     * Set page header/footer.
     *
     * @return $this
     */
    public function set_header_footer(Header_Footer $header_footer): static
    {
        $this->header_footer = $header_footer;
        return $this;
    }
    /**
     * Get sheet view.
     */
    public function get_sheet_view(): Sheet_View
    {
        return $this->sheet_view;
    }
    /**
     * Set sheet view.
     *
     * @return $this
     */
    public function set_sheet_view(Sheet_View $sheet_view): static
    {
        $this->sheet_view = $sheet_view;
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
     * Set Protection.
     *
     * @return $this
     */
    public function set_protection(Protection $protection): static
    {
        $this->protection = $protection;
        return $this;
    }
    /**
     * Get highest worksheet column.
     *
     * @param null|int|string $row Return the data highest column for the specified row,
     *                                     or the highest column of any row if no row number is passed
     *
     * @return string Highest column name
     */
    public function get_highest_column($row = null): string
    {
        if ($row === null) {
            return Coordinate::string_from_column_index($this->cached_highest_column);
        }
        return $this->get_highest_data_column($row);
    }
    /**
     * Get highest worksheet column that contains data.
     *
     * @param null|int|string $row Return the highest data column for the specified row,
     *                                     or the highest data column of any row if no row number is passed
     *
     * @return string Highest column name that contains data
     */
    public function get_highest_data_column($row = null): string
    {
        return $this->cell_collection->get_highest_column($row);
    }
    /**
     * Get highest worksheet row.
     *
     * @param null|string $column Return the highest data row for the specified column,
     *                                     or the highest row of any column if no column letter is passed
     *
     * @return int Highest row number
     */
    public function get_highest_row(?string $column = null): int
    {
        if ($column === null) {
            return $this->cached_highest_row;
        }
        return $this->get_highest_data_row($column);
    }
    /**
     * Get highest worksheet row that contains data.
     *
     * @param null|string $column Return the highest data row for the specified column,
     *                                     or the highest data row of any column if no column letter is passed
     *
     * @return int Highest row number that contains data
     */
    public function get_highest_data_row(?string $column = null): int
    {
        return $this->cell_collection->get_highest_row($column);
    }
    /**
     * Get highest worksheet column and highest row that have cell records.
     *
     * @return array{row: int, column: string} Highest column name and highest row number
     */
    public function get_highest_row_and_column(): array
    {
        return $this->cell_collection->get_highest_row_and_column();
    }
    /**
     * Set a cell value.
     *
     * @param array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     * @param mixed $value Value for the cell
     * @param null|IValueBinder $binder Value Binder to override the currently set Value Binder
     *
     * @return $this
     */
    public function set_cell_value(Cell_Address|string|array $coordinate, mixed $value, ?I_Value_Binder $binder = null): static
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate));
        $this->get_cell($cell_address)->set_value($value, $binder);
        return $this;
    }
    /**
     * Set a cell value.
     *
     * @param array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     * @param mixed $value Value of the cell
     * @param string $dataType Explicit data type, see DataType::TYPE_*
     *        Note that PhpSpreadsheet does not validate that the value and datatype are consistent, in using this
     *             method, then it is your responsibility as an end-user developer to validate that the value and
     *             the datatype match.
     *       If you do mismatch value and datatpe, then the value you enter may be changed to match the datatype
     *          that you specify.
     *
     * @see DataType
     *
     * @return $this
     */
    public function set_cell_value_explicit(Cell_Address|string|array $coordinate, mixed $value, string $data_type): static
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate));
        $this->get_cell($cell_address)->set_value_explicit($value, $data_type);
        return $this;
    }
    /**
     * Get cell at a specific coordinate.
     *
     * @param array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     *
     * @return Cell Cell that was found or created
     *              WARNING: Because the cell collection can be cached to reduce memory, it only allows one
     *              "active" cell at a time in memory. If you assign that cell to a variable, then select
     *              another cell using getCell() or any of its variants, the newly selected cell becomes
     *              the "active" cell, and any previous assignment becomes a disconnected reference because
     *              the active cell has changed.
     */
    public function get_cell(Cell_Address|string|array $coordinate): Cell
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate));
        // Shortcut for increased performance for the vast majority of simple cases
        if ($this->cell_collection->has($cell_address)) {
            /** @var Cell $cell */
            $cell = $this->cell_collection->get($cell_address);
            return $cell;
        }
        /** @var Worksheet $sheet */
        [$sheet, $final_coordinate] = $this->get_worksheet_and_coordinate($cell_address);
        $cell = $sheet->get_cell_collection()->get($final_coordinate);
        return $cell ?? $sheet->create_new_cell($final_coordinate);
    }
    /**
     * Get the correct Worksheet and coordinate from a coordinate that may
     * contains reference to another sheet or a named range.
     *
     * @return array{0: Worksheet, 1: string}
     */
    private function get_worksheet_and_coordinate(string $coordinate): array
    {
        $sheet = null;
        $final_coordinate = null;
        // Worksheet reference?
        if (str_contains($coordinate, '!')) {
            $worksheet_reference = self::extract_sheet_title($coordinate, true, true);
            $sheet = $this->get_parent_or_throw()->get_sheet_by_name($worksheet_reference[0]);
            $final_coordinate = strtoupper($worksheet_reference[1]);
            if ($sheet === null) {
                throw new Exception('Sheet not found for name: ' . $worksheet_reference[0]);
            }
        } elseif (!Preg::is_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/i', $coordinate) && Preg::is_match('/^' . Calculation::CALCULATION_REGEXP_DEFINEDNAME . '$/iu', $coordinate)) {
            // Named range?
            $named_range = $this->validate_named_range($coordinate, true);
            if ($named_range !== null) {
                $sheet = $named_range->get_worksheet();
                if ($sheet === null) {
                    throw new Exception('Sheet not found for named range: ' . $named_range->get_name());
                }
                /** @phpstan-ignore-next-line */
                $cell_coordinate = ltrim(substr($named_range->get_value(), strrpos($named_range->get_value(), '!')), '!');
                $final_coordinate = str_replace('$', '', $cell_coordinate);
            }
        }
        if ($sheet === null || $final_coordinate === null) {
            $sheet = $this;
            $final_coordinate = strtoupper($coordinate);
        }
        if (Coordinate::coordinate_is_range($final_coordinate)) {
            throw new Exception('Cell coordinate string can not be a range of cells.');
        }
        $final_coordinate = str_replace('$', '', $final_coordinate);
        return [$sheet, $final_coordinate];
    }
    /**
     * Get an existing cell at a specific coordinate, or null.
     *
     * @param string $coordinate Coordinate of the cell, eg: 'A1'
     *
     * @return null|Cell Cell that was found or null
     */
    private function get_cell_or_null(string $coordinate): ?Cell
    {
        // Check cell collection
        if ($this->cell_collection->has($coordinate)) {
            return $this->cell_collection->get($coordinate);
        }
        return null;
    }
    /**
     * Create a new cell at the specified coordinate.
     *
     * @param string $coordinate Coordinate of the cell
     *
     * @return Cell Cell that was created
     *              WARNING: Because the cell collection can be cached to reduce memory, it only allows one
     *              "active" cell at a time in memory. If you assign that cell to a variable, then select
     *              another cell using getCell() or any of its variants, the newly selected cell becomes
     *              the "active" cell, and any previous assignment becomes a disconnected reference because
     *              the active cell has changed.
     */
    public function create_new_cell(string $coordinate): Cell
    {
        [$column, $row, $column_string] = Coordinate::indexes_from_string($coordinate);
        $cell = new Cell(null, Data_Type::TYPE_NULL, $this);
        $this->cell_collection->add($coordinate, $cell);
        // Coordinates
        if ($column > $this->cached_highest_column) {
            $this->cached_highest_column = $column;
        }
        if ($row > $this->cached_highest_row) {
            $this->cached_highest_row = $row;
        }
        // Cell needs appropriate xfIndex from dimensions records
        //    but don't create dimension records if they don't already exist
        $row_dimension = $this->row_dimensions[$row] ?? null;
        $column_dimension = $this->column_dimensions[$column_string] ?? null;
        $xf_set = false;
        if ($row_dimension !== null) {
            $row_xf = (int) $row_dimension->get_xf_index();
            if ($row_xf > 0) {
                // then there is a row dimension with explicit style, assign it to the cell
                $cell->set_xf_index($row_xf);
                $xf_set = true;
            }
        }
        if (!$xf_set && $column_dimension !== null) {
            $col_xf = (int) $column_dimension->get_xf_index();
            if ($col_xf > 0) {
                // then there is a column dimension, assign it to the cell
                $cell->set_xf_index($col_xf);
            }
        }
        return $cell;
    }
    /**
     * Does the cell at a specific coordinate exist?
     *
     * @param array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     */
    public function cell_exists(Cell_Address|string|array $coordinate): bool
    {
        $cell_address = Validations::validate_cell_address($coordinate);
        [$sheet, $final_coordinate] = $this->get_worksheet_and_coordinate($cell_address);
        return $sheet->get_cell_collection()->has($final_coordinate);
    }
    /**
     * Get row dimension at a specific row.
     *
     * @param int $row Numeric index of the row
     */
    public function get_row_dimension(int $row): Row_Dimension
    {
        // Get row dimension
        if (!isset($this->row_dimensions[$row])) {
            $this->row_dimensions[$row] = new Row_Dimension($row);
            $this->cached_highest_row = max($this->cached_highest_row, $row);
        }
        return $this->row_dimensions[$row];
    }
    public function get_row_style(int $row): ?Style
    {
        return $this->parent?->get_cell_xf_by_index_or_null(($this->row_dimensions[$row] ?? null)?->get_xf_index());
    }
    public function row_dimension_exists(int $row): bool
    {
        return isset($this->row_dimensions[$row]);
    }
    public function column_dimension_exists(string $column): bool
    {
        return isset($this->column_dimensions[$column]);
    }
    /**
     * Get column dimension at a specific column.
     *
     * @param string $column String index of the column eg: 'A'
     */
    public function get_column_dimension(string $column): Column_Dimension
    {
        // Uppercase coordinate
        $column = strtoupper($column);
        // Fetch dimensions
        if (!isset($this->column_dimensions[$column])) {
            $this->column_dimensions[$column] = new Column_Dimension($column);
            $column_index = Coordinate::column_index_from_string($column);
            if ($this->cached_highest_column < $column_index) {
                $this->cached_highest_column = $column_index;
            }
        }
        return $this->column_dimensions[$column];
    }
    /**
     * Get column dimension at a specific column by using numeric cell coordinates.
     *
     * @param int $columnIndex Numeric column coordinate of the cell
     */
    public function get_column_dimension_by_column(int $column_index): Column_Dimension
    {
        return $this->get_column_dimension(Coordinate::string_from_column_index($column_index));
    }
    public function get_column_style(string $column): ?Style
    {
        return $this->parent?->get_cell_xf_by_index_or_null(($this->column_dimensions[$column] ?? null)?->get_xf_index());
    }
    /**
     * Get style for cell.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|CellAddress|int|string $cellCoordinate
     *              A simple string containing a cell address like 'A1' or a cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or a CellAddress or AddressRange object.
     */
    public function get_style(Address_Range|Cell_Address|int|string|array $cell_coordinate): Style
    {
        if (is_string($cell_coordinate)) {
            $cell_coordinate = Validations::defined_name_to_coordinate($cell_coordinate, $this);
        }
        $cell_coordinate = Validations::validate_cell_or_cell_range($cell_coordinate);
        $cell_coordinate = str_replace('$', '', $cell_coordinate);
        // set this sheet as active
        $this->get_parent_or_throw()->set_active_sheet_index($this->get_parent_or_throw()->get_index($this));
        // set cell coordinate as active
        $this->set_selected_cells($cell_coordinate);
        return $this->get_parent_or_throw()->get_cell_xf_supervisor();
    }
    /**
     * Get table styles set for the for given cell.
     *
     * @param Cell $cell
     *              The Cell for which the tables are retrieved
     *
     * @return Table[]
     */
    public function get_tables_with_styles_for_cell(Cell $cell): array
    {
        $ret_val = [];
        foreach ($this->table_collection as $table) {
            $dxfs_table_style = $table->get_style()->get_table_dxfs_style();
            if ($dxfs_table_style !== null) {
                if ($dxfs_table_style->get_header_row_style() !== null || $dxfs_table_style->get_first_row_stripe_style() !== null || $dxfs_table_style->get_second_row_stripe_style() !== null) {
                    $range = $table->get_range();
                    if ($cell->is_in_range($range)) {
                        $ret_val[] = $table;
                    }
                }
            }
        }
        return $ret_val;
    }
    /**
     * Get tables without styles set for the for given cell.
     *
     * @param Cell $cell
     *              The Cell for which the tables are retrieved
     *
     * @return Table[]
     */
    public function get_tables_without_styles_for_cell(Cell $cell): array
    {
        $ret_val = [];
        foreach ($this->table_collection as $table) {
            $range = $table->get_range();
            if ($cell->is_in_range($range)) {
                $dxfs_table_style = $table->get_style()->get_table_dxfs_style();
                if ($dxfs_table_style === null || $dxfs_table_style->get_header_row_style() === null && $dxfs_table_style->get_first_row_stripe_style() === null && $dxfs_table_style->get_second_row_stripe_style() === null) {
                    $ret_val[] = $table;
                }
            }
        }
        return $ret_val;
    }
    /**
     * Get conditional styles for a cell.
     *
     * @param string $coordinate eg: 'A1' or 'A1:A3'.
     *          If a single cell is referenced, then the array of conditional styles will be returned if the cell is
     *               included in a conditional style range.
     *          If a range of cells is specified, then the styles will only be returned if the range matches the entire
     *               range of the conditional.
     * @param bool $firstOnly default true, return all matching
     *          conditionals ordered by priority if false, first only if true
     *
     * @return Conditional[]
     */
    public function get_conditional_styles(string $coordinate, bool $first_only = true): array
    {
        $coordinate = strtoupper($coordinate);
        if (Preg::is_match('/[: ,]/', $coordinate)) {
            return $this->conditional_styles_collection[$coordinate] ?? [];
        }
        $conditional_styles = [];
        foreach ($this->conditional_styles_collection as $key_styles_orig => $conditional_range) {
            $key_styles = Coordinate::resolve_union_and_intersection($key_styles_orig);
            $key_parts = explode(',', $key_styles);
            foreach ($key_parts as $key_part) {
                if ($key_part === $coordinate) {
                    if ($first_only) {
                        return $conditional_range;
                    }
                    $conditional_styles[$key_styles_orig] = $conditional_range;
                    break;
                } elseif (str_contains($key_part, ':')) {
                    if (Coordinate::coordinate_is_inside_range($key_part, $coordinate)) {
                        if ($first_only) {
                            return $conditional_range;
                        }
                        $conditional_styles[$key_styles_orig] = $conditional_range;
                        break;
                    }
                }
            }
        }
        $out_array = [];
        foreach ($conditional_styles as $conditional_array) {
            foreach ($conditional_array as $conditional) {
                $out_array[] = $conditional;
            }
        }
        usort($out_array, self::compare_priority(...));
        return $out_array;
    }
    private static function compare_priority(Conditional $cond_a, Conditional $cond_b): int
    {
        $a = $cond_a->get_priority();
        $b = $cond_b->get_priority();
        if ($a === $b) {
            return 0;
        }
        if ($a === 0) {
            return 1;
        }
        if ($b === 0) {
            return -1;
        }
        return $a < $b ? -1 : 1;
    }
    public function get_conditional_range(string $coordinate): ?string
    {
        $coordinate = strtoupper($coordinate);
        $cell = $this->get_cell($coordinate);
        foreach (array_keys($this->conditional_styles_collection) as $conditional_range) {
            $cell_blocks = explode(',', Coordinate::resolve_union_and_intersection($conditional_range));
            foreach ($cell_blocks as $cell_block) {
                if ($cell->is_in_range($cell_block)) {
                    return $conditional_range;
                }
            }
        }
        return null;
    }
    /**
     * Do conditional styles exist for this cell?
     *
     * @param string $coordinate eg: 'A1' or 'A1:A3'.
     *          If a single cell is specified, then this method will return true if that cell is included in a
     *               conditional style range.
     *          If a range of cells is specified, then true will only be returned if the range matches the entire
     *               range of the conditional.
     */
    public function conditional_styles_exists(string $coordinate): bool
    {
        return !empty($this->get_conditional_styles($coordinate));
    }
    /**
     * Removes conditional styles for a cell.
     *
     * @param string $coordinate eg: 'A1'
     *
     * @return $this
     */
    public function remove_conditional_styles(string $coordinate): static
    {
        unset($this->conditional_styles_collection[strtoupper($coordinate)]);
        return $this;
    }
    /**
     * Get collection of conditional styles.
     *
     * @return Conditional[][]
     */
    public function get_conditional_styles_collection(): array
    {
        return $this->conditional_styles_collection;
    }
    /**
     * Set conditional styles.
     *
     * @param string $coordinate eg: 'A1'
     * @param Conditional[] $styles
     *
     * @return $this
     */
    public function set_conditional_styles(string $coordinate, array $styles): static
    {
        $this->conditional_styles_collection[strtoupper($coordinate)] = $styles;
        return $this;
    }
    /**
     * Duplicate cell style to a range of cells.
     *
     * Please note that this will overwrite existing cell styles for cells in range!
     *
     * @param Style $style Cell style to duplicate
     * @param string $range Range of cells (i.e. "A1:B10"), or just one cell (i.e. "A1")
     *
     * @return $this
     */
    public function duplicate_style(Style $style, string $range): static
    {
        // Add the style to the workbook if necessary
        $workbook = $this->get_parent_or_throw();
        if ($existing_style = $workbook->get_cell_xf_by_hash_code($style->get_hash_code())) {
            // there is already such cell Xf in our collection
            $xf_index = $existing_style->get_index();
        } else {
            // we don't have such a cell Xf, need to add
            $workbook->add_cell_xf($style);
            $xf_index = $style->get_index();
        }
        // Calculate range outer borders
        [$range_start, $range_end] = Coordinate::range_boundaries($range . ':' . $range);
        // Make sure we can loop upwards on rows and columns
        if ($range_start[0] > $range_end[0] && $range_start[1] > $range_end[1]) {
            $tmp = $range_start;
            $range_start = $range_end;
            $range_end = $tmp;
        }
        // Loop through cells and apply styles
        for ($col = $range_start[0]; $col <= $range_end[0]; ++$col) {
            for ($row = $range_start[1]; $row <= $range_end[1]; ++$row) {
                $this->get_cell(Coordinate::string_from_column_index($col) . $row)->set_xf_index($xf_index);
            }
        }
        return $this;
    }
    /**
     * Duplicate conditional style to a range of cells.
     *
     * Please note that this will overwrite existing cell styles for cells in range!
     *
     * @param Conditional[] $styles Cell style to duplicate
     * @param string $range Range of cells (i.e. "A1:B10"), or just one cell (i.e. "A1")
     *
     * @return $this
     */
    public function duplicate_conditional_style(array $styles, string $range = ''): static
    {
        foreach ($styles as $cell_style) {
            if (!$cell_style instanceof Conditional) {
                // @phpstan-ignore-line
                throw new Exception('Style is not a conditional style');
            }
        }
        // Calculate range outer borders
        [$range_start, $range_end] = Coordinate::range_boundaries($range . ':' . $range);
        // Make sure we can loop upwards on rows and columns
        if ($range_start[0] > $range_end[0] && $range_start[1] > $range_end[1]) {
            $tmp = $range_start;
            $range_start = $range_end;
            $range_end = $tmp;
        }
        // Loop through cells and apply styles
        for ($col = $range_start[0]; $col <= $range_end[0]; ++$col) {
            for ($row = $range_start[1]; $row <= $range_end[1]; ++$row) {
                $this->set_conditional_styles(Coordinate::string_from_column_index($col) . $row, $styles);
            }
        }
        return $this;
    }
    /**
     * Set break on a cell.
     *
     * @param array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     * @param int $break Break type (type of Worksheet::BREAK_*)
     *
     * @return $this
     */
    public function set_break(Cell_Address|string|array $coordinate, int $break, int $max = -1): static
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate));
        if ($break === self::BREAK_NONE) {
            unset($this->row_breaks[$cell_address], $this->column_breaks[$cell_address]);
        } elseif ($break === self::BREAK_ROW) {
            $this->row_breaks[$cell_address] = new Page_Break($break, $cell_address, $max);
        } elseif ($break === self::BREAK_COLUMN) {
            $this->column_breaks[$cell_address] = new Page_Break($break, $cell_address, $max);
        }
        return $this;
    }
    /**
     * Get breaks.
     *
     * @return int[]
     */
    public function get_breaks(): array
    {
        $breaks = [];
        /** @var callable $compareFunction */
        $compare_function = self::compare_row_breaks(...);
        uksort($this->row_breaks, $compare_function);
        foreach ($this->row_breaks as $break) {
            $breaks[$break->get_coordinate()] = self::BREAK_ROW;
        }
        /** @var callable $compareFunction */
        $compare_function = self::compare_column_breaks(...);
        uksort($this->column_breaks, $compare_function);
        foreach ($this->column_breaks as $break) {
            $breaks[$break->get_coordinate()] = self::BREAK_COLUMN;
        }
        return $breaks;
    }
    /**
     * Get row breaks.
     *
     * @return PageBreak[]
     */
    public function get_row_breaks(): array
    {
        /** @var callable $compareFunction */
        $compare_function = self::compare_row_breaks(...);
        uksort($this->row_breaks, $compare_function);
        return $this->row_breaks;
    }
    protected static function compare_row_breaks(string $coordinate1, string $coordinate2): int
    {
        $row1 = Coordinate::indexes_from_string($coordinate1)[1];
        $row2 = Coordinate::indexes_from_string($coordinate2)[1];
        return $row1 - $row2;
    }
    protected static function compare_column_breaks(string $coordinate1, string $coordinate2): int
    {
        $column1 = Coordinate::indexes_from_string($coordinate1)[0];
        $column2 = Coordinate::indexes_from_string($coordinate2)[0];
        return $column1 - $column2;
    }
    /**
     * Get column breaks.
     *
     * @return PageBreak[]
     */
    public function get_column_breaks(): array
    {
        /** @var callable $compareFunction */
        $compare_function = self::compare_column_breaks(...);
        uksort($this->column_breaks, $compare_function);
        return $this->column_breaks;
    }
    /**
     * Set merge on a cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range A simple string containing a Cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange.
     * @param string $behaviour How the merged cells should behave.
     *               Possible values are:
     *                   MERGE_CELL_CONTENT_EMPTY - Empty the content of the hidden cells
     *                   MERGE_CELL_CONTENT_HIDE - Keep the content of the hidden cells
     *                   MERGE_CELL_CONTENT_MERGE - Move the content of the hidden cells into the first cell
     *
     * @return $this
     */
    public function merge_cells(Address_Range|string|array $range, string $behaviour = self::MERGE_CELL_CONTENT_EMPTY): static
    {
        $range = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_range($range));
        if (!str_contains($range, ':')) {
            $range .= ":{$range}";
        }
        if (!Preg::is_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $range, $matches)) {
            throw new Exception('Merge must be on a valid range of cells.');
        }
        $this->merge_cells[$range] = $range;
        $first_row = (int) $matches[2];
        $last_row = (int) $matches[4];
        $first_column = $matches[1];
        $last_column = $matches[3];
        $first_column_index = Coordinate::column_index_from_string($first_column);
        $last_column_index = Coordinate::column_index_from_string($last_column);
        $number_rows = $last_row - $first_row;
        $number_columns = $last_column_index - $first_column_index;
        if ($number_rows === 1 && $number_columns === 1) {
            return $this;
        }
        // create upper left cell if it does not already exist
        $upper_left = "{$first_column}{$first_row}";
        if (!$this->cell_exists($upper_left)) {
            $this->get_cell($upper_left)->set_value_explicit(null, Data_Type::TYPE_NULL);
        }
        if ($behaviour !== self::MERGE_CELL_CONTENT_HIDE) {
            // Blank out the rest of the cells in the range (if they exist)
            if ($number_rows > $number_columns) {
                $this->clear_merge_cells_by_column($first_column, $last_column, $first_row, $last_row, $upper_left, $behaviour);
            } else {
                $this->clear_merge_cells_by_row($first_column, $last_column_index, $first_row, $last_row, $upper_left, $behaviour);
            }
        }
        return $this;
    }
    private function clear_merge_cells_by_column(string $first_column, string $last_column, int $first_row, int $last_row, string $upper_left, string $behaviour): void
    {
        $left_cell_value = $behaviour === self::MERGE_CELL_CONTENT_MERGE ? [$this->get_cell($upper_left)->get_formatted_value()] : [];
        foreach ($this->get_column_iterator($first_column, $last_column) as $column) {
            $iterator = $column->get_cell_iterator($first_row);
            $iterator->set_iterate_only_existing_cells(true);
            foreach ($iterator as $cell) {
                $row = $cell->get_row();
                if ($row > $last_row) {
                    break;
                }
                $left_cell_value = $this->merge_cell_behaviour($cell, $upper_left, $behaviour, $left_cell_value);
            }
        }
        if ($behaviour === self::MERGE_CELL_CONTENT_MERGE) {
            $this->get_cell($upper_left)->set_value_explicit(implode(' ', $left_cell_value), Data_Type::TYPE_STRING);
        }
    }
    private function clear_merge_cells_by_row(string $first_column, int $last_column_index, int $first_row, int $last_row, string $upper_left, string $behaviour): void
    {
        $left_cell_value = $behaviour === self::MERGE_CELL_CONTENT_MERGE ? [$this->get_cell($upper_left)->get_formatted_value()] : [];
        foreach ($this->get_row_iterator($first_row, $last_row) as $row) {
            $iterator = $row->get_cell_iterator($first_column);
            $iterator->set_iterate_only_existing_cells(true);
            foreach ($iterator as $cell) {
                $column = $cell->get_column();
                $column_index = Coordinate::column_index_from_string($column);
                if ($column_index > $last_column_index) {
                    break;
                }
                $left_cell_value = $this->merge_cell_behaviour($cell, $upper_left, $behaviour, $left_cell_value);
            }
        }
        if ($behaviour === self::MERGE_CELL_CONTENT_MERGE) {
            $this->get_cell($upper_left)->set_value_explicit(implode(' ', $left_cell_value), Data_Type::TYPE_STRING);
        }
    }
    /**
     * @param mixed[] $leftCellValue
     *
     * @return mixed[]
     */
    public function merge_cell_behaviour(Cell $cell, string $upper_left, string $behaviour, array $left_cell_value): array
    {
        if ($cell->get_coordinate() !== $upper_left) {
            Calculation::get_instance($cell->get_worksheet()->get_parent_or_throw())->flush_instance();
            if ($behaviour === self::MERGE_CELL_CONTENT_MERGE) {
                $cell_value = $cell->get_formatted_value();
                if ($cell_value !== '') {
                    $left_cell_value[] = $cell_value;
                }
            }
            $cell->set_value_explicit(null, Data_Type::TYPE_NULL);
        }
        return $left_cell_value;
    }
    /**
     * Remove merge on a cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|string $range A simple string containing a Cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange.
     *
     * @return $this
     */
    public function unmerge_cells(Address_Range|string|array $range): static
    {
        $range = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_range($range));
        if (str_contains($range, ':')) {
            if (isset($this->merge_cells[$range])) {
                unset($this->merge_cells[$range]);
            } else {
                throw new Exception('Cell range ' . $range . ' not known as merged.');
            }
        } else {
            throw new Exception('Merge can only be removed from a range of cells.');
        }
        return $this;
    }
    /**
     * Get merge cells array.
     *
     * @return string[]
     */
    public function get_merge_cells(): array
    {
        return $this->merge_cells;
    }
    /**
     * Set merge cells array for the entire sheet. Use instead mergeCells() to merge
     * a single cell range.
     *
     * @param string[] $mergeCells
     *
     * @return $this
     */
    public function set_merge_cells(array $merge_cells): static
    {
        $this->merge_cells = $merge_cells;
        return $this;
    }
    /**
     * Set protection on a cell or cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|CellAddress|int|string $range A simple string containing a Cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or a CellAddress or AddressRange object.
     * @param string $password Password to unlock the protection
     * @param bool $alreadyHashed If the password has already been hashed, set this to true
     *
     * @return $this
     */
    public function protect_cells(Address_Range|Cell_Address|int|string|array $range, string $password = '', bool $already_hashed = false, string $name = '', string $security_descriptor = ''): static
    {
        $range = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_or_cell_range($range));
        if (!$already_hashed && $password !== '') {
            $password = Shared\Password_Hasher::hash_password($password);
        }
        $this->protected_cells[$range] = new Protected_Range($range, $password, $name, $security_descriptor);
        return $this;
    }
    /**
     * Remove protection on a cell or cell range.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|CellAddress|int|string $range A simple string containing a Cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or a CellAddress or AddressRange object.
     *
     * @return $this
     */
    public function unprotect_cells(Address_Range|Cell_Address|int|string|array $range): static
    {
        $range = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_or_cell_range($range));
        if (isset($this->protected_cells[$range])) {
            unset($this->protected_cells[$range]);
        } else {
            throw new Exception('Cell range ' . $range . ' not known as protected.');
        }
        return $this;
    }
    /**
     * Get protected cells.
     *
     * @return ProtectedRange[]
     */
    public function get_protected_cell_ranges(): array
    {
        return $this->protected_cells;
    }
    /**
     * Get Autofilter.
     */
    public function get_auto_filter(): Auto_Filter
    {
        return $this->auto_filter;
    }
    /**
     * Set AutoFilter.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|AutoFilter|string $autoFilterOrRange
     *            A simple string containing a Cell range like 'A1:E10' is permitted for backward compatibility
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or an AddressRange.
     *
     * @return $this
     */
    public function set_auto_filter(Address_Range|string|array|Auto_Filter $auto_filter_or_range): static
    {
        if (is_object($auto_filter_or_range) && $auto_filter_or_range instanceof Auto_Filter) {
            $this->auto_filter = $auto_filter_or_range;
        } else {
            $cell_range = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_range($auto_filter_or_range));
            $this->auto_filter->set_range($cell_range);
        }
        return $this;
    }
    /**
     * Remove autofilter.
     */
    public function remove_auto_filter(): self
    {
        $this->auto_filter->set_range('');
        return $this;
    }
    /**
     * Get collection of Tables.
     *
     * @return ArrayObject<int, Table>
     */
    public function get_table_collection(): ArrayObject
    {
        return $this->table_collection;
    }
    /**
     * Add Table.
     *
     * @return $this
     */
    public function add_table(Table $table): self
    {
        $table->set_worksheet($this);
        $this->table_collection[] = $table;
        return $this;
    }
    /**
     * @return string[] array of Table names
     */
    public function get_table_names(): array
    {
        $table_names = [];
        foreach ($this->table_collection as $table) {
            /** @var Table $table */
            $table_names[] = $table->get_name();
        }
        return $table_names;
    }
    /**
     * @param string $name the table name to search
     *
     * @return null|Table The table from the tables collection, or null if not found
     */
    public function get_table_by_name(string $name): ?Table
    {
        $table_index = $this->get_table_index_by_name($name);
        return $table_index === null ? null : $this->table_collection[$table_index];
    }
    /**
     * @param string $name the table name to search
     *
     * @return null|int The index of the located table in the tables collection, or null if not found
     */
    protected function get_table_index_by_name(string $name): ?int
    {
        $name = String_Helper::str_to_upper($name);
        foreach ($this->table_collection as $index => $table) {
            /** @var Table $table */
            if (String_Helper::str_to_upper($table->get_name()) === $name) {
                return $index;
            }
        }
        return null;
    }
    /**
     * Remove Table by name.
     *
     * @param string $name Table name
     *
     * @return $this
     */
    public function remove_table_by_name(string $name): self
    {
        $table_index = $this->get_table_index_by_name($name);
        if ($table_index !== null) {
            unset($this->table_collection[$table_index]);
        }
        return $this;
    }
    /**
     * Remove collection of Tables.
     */
    public function remove_table_collection(): self
    {
        $this->table_collection = new ArrayObject();
        return $this;
    }
    /**
     * Get Freeze Pane.
     */
    public function get_freeze_pane(): ?string
    {
        return $this->freeze_pane;
    }
    /**
     * Freeze Pane.
     *
     * Examples:
     *
     *     - A2 will freeze the rows above cell A2 (i.e row 1)
     *     - B1 will freeze the columns to the left of cell B1 (i.e column A)
     *     - B2 will freeze the rows above and to the left of cell B2 (i.e row 1 and column A)
     *
     * @param null|array{0: int, 1: int}|CellAddress|string $coordinate Coordinate of the cell as a string, eg: 'C5';
     *            or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     *        Passing a null value for this argument will clear any existing freeze pane for this worksheet.
     * @param null|array{0: int, 1: int}|CellAddress|string $topLeftCell default position of the right bottom pane
     *            Coordinate of the cell as a string, eg: 'C5'; or as an array of [$columnIndex, $row] (e.g. [3, 5]),
     *            or a CellAddress object.
     *
     * @return $this
     */
    public function freeze_pane(null|Cell_Address|string|array $coordinate, null|Cell_Address|string|array $top_left_cell = null, bool $frozen_split = false): static
    {
        $this->panes = ['bottomRight' => null, 'bottomLeft' => null, 'topRight' => null, 'topLeft' => null];
        $cell_address = $coordinate !== null ? Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($coordinate)) : null;
        if ($cell_address !== null && Coordinate::coordinate_is_range($cell_address)) {
            throw new Exception('Freeze pane can not be set on a range of cells.');
        }
        $top_left_cell = $top_left_cell !== null ? Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($top_left_cell)) : null;
        if ($cell_address !== null && $top_left_cell === null) {
            $coordinate = Coordinate::coordinate_from_string($cell_address);
            $top_left_cell = $coordinate[0] . $coordinate[1];
        }
        $top_left_cell = "{$top_left_cell}";
        $this->pane_top_left_cell = $top_left_cell;
        $this->freeze_pane = $cell_address;
        $this->top_left_cell = $top_left_cell;
        if ($cell_address === null) {
            $this->pane_state = '';
            $this->x_split = $this->y_split = 0;
            $this->active_pane = '';
        } else {
            $coordinates = Coordinate::indexes_from_string($cell_address);
            $this->x_split = $coordinates[0] - 1;
            $this->y_split = $coordinates[1] - 1;
            if ($this->x_split > 0 || $this->y_split > 0) {
                $this->pane_state = $frozen_split ? self::PANE_FROZENSPLIT : self::PANE_FROZEN;
                $this->set_selected_cells_active_pane();
            } else {
                $this->pane_state = '';
                $this->freeze_pane = null;
                $this->active_pane = '';
            }
        }
        return $this;
    }
    public function set_top_left_cell(string $top_left_cell): self
    {
        $this->top_left_cell = $top_left_cell;
        return $this;
    }
    /**
     * Unfreeze Pane.
     *
     * @return $this
     */
    public function unfreeze_pane(): static
    {
        return $this->freeze_pane(null);
    }
    /**
     * Get the default position of the right bottom pane.
     */
    public function get_top_left_cell(): ?string
    {
        return $this->top_left_cell;
    }
    public function get_pane_top_left_cell(): string
    {
        return $this->pane_top_left_cell;
    }
    public function set_pane_top_left_cell(string $pane_top_left_cell): self
    {
        $this->pane_top_left_cell = $pane_top_left_cell;
        return $this;
    }
    public function uses_panes(): bool
    {
        return $this->x_split > 0 || $this->y_split > 0;
    }
    public function get_pane(string $position): ?Pane
    {
        return $this->panes[$position] ?? null;
    }
    public function set_pane(string $position, ?Pane $pane): self
    {
        if (array_key_exists($position, $this->panes)) {
            $this->panes[$position] = $pane;
        }
        return $this;
    }
    /** @return (null|Pane)[] */
    public function get_panes(): array
    {
        return $this->panes;
    }
    public function get_active_pane(): string
    {
        return $this->active_pane;
    }
    public function set_active_pane(string $active_pane): self
    {
        $this->active_pane = array_key_exists($active_pane, $this->panes) ? $active_pane : '';
        return $this;
    }
    public function get_x_split(): int
    {
        return $this->x_split;
    }
    public function set_x_split(int $x_split): self
    {
        $this->x_split = $x_split;
        if (in_array($this->pane_state, self::VALIDFROZENSTATE, true)) {
            $this->freeze_pane([$this->x_split + 1, $this->y_split + 1], $this->top_left_cell, $this->pane_state === self::PANE_FROZENSPLIT);
        }
        return $this;
    }
    public function get_y_split(): int
    {
        return $this->y_split;
    }
    public function set_y_split(int $y_split): self
    {
        $this->y_split = $y_split;
        if (in_array($this->pane_state, self::VALIDFROZENSTATE, true)) {
            $this->freeze_pane([$this->x_split + 1, $this->y_split + 1], $this->top_left_cell, $this->pane_state === self::PANE_FROZENSPLIT);
        }
        return $this;
    }
    public function get_pane_state(): string
    {
        return $this->pane_state;
    }
    public const PANE_FROZEN = 'frozen';
    public const PANE_FROZENSPLIT = 'frozenSplit';
    public const PANE_SPLIT = 'split';
    private const VALIDPANESTATE = [self::PANE_FROZEN, self::PANE_SPLIT, self::PANE_FROZENSPLIT];
    private const VALIDFROZENSTATE = [self::PANE_FROZEN, self::PANE_FROZENSPLIT];
    public function set_pane_state(string $pane_state): self
    {
        $this->pane_state = in_array($pane_state, self::VALIDPANESTATE, true) ? $pane_state : '';
        if (in_array($this->pane_state, self::VALIDFROZENSTATE, true)) {
            $this->freeze_pane([$this->x_split + 1, $this->y_split + 1], $this->top_left_cell, $this->pane_state === self::PANE_FROZENSPLIT);
        } else {
            $this->freeze_pane = null;
        }
        return $this;
    }
    /**
     * Insert a new row, updating all possible related data.
     *
     * @param int $before Insert before this row number
     * @param int $numberOfRows Number of new rows to insert
     *
     * @return $this
     */
    public function insert_new_row_before(int $before, int $number_of_rows = 1): static
    {
        if ($before >= 1) {
            $obj_reference_helper = Reference_Helper::get_instance();
            $obj_reference_helper->insert_new_before('A' . $before, 0, $number_of_rows, $this);
        } else {
            throw new Exception('Rows can only be inserted before at least row 1.');
        }
        return $this;
    }
    /**
     * Insert a new column, updating all possible related data.
     *
     * @param string $before Insert before this column Name, eg: 'A'
     * @param int $numberOfColumns Number of new columns to insert
     *
     * @return $this
     */
    public function insert_new_column_before(string $before, int $number_of_columns = 1): static
    {
        if (!is_numeric($before)) {
            $obj_reference_helper = Reference_Helper::get_instance();
            $obj_reference_helper->insert_new_before($before . '1', $number_of_columns, 0, $this);
        } else {
            throw new Exception('Column references should not be numeric.');
        }
        return $this;
    }
    /**
     * Insert a new column, updating all possible related data.
     *
     * @param int $beforeColumnIndex Insert before this column ID (numeric column coordinate of the cell)
     * @param int $numberOfColumns Number of new columns to insert
     *
     * @return $this
     */
    public function insert_new_column_before_by_index(int $before_column_index, int $number_of_columns = 1): static
    {
        if ($before_column_index >= 1) {
            return $this->insert_new_column_before(Coordinate::string_from_column_index($before_column_index), $number_of_columns);
        }
        throw new Exception('Columns can only be inserted before at least column A (1).');
    }
    /**
     * Delete a row, updating all possible related data.
     *
     * @param int $row Remove rows, starting with this row number
     * @param int $numberOfRows Number of rows to remove
     *
     * @return $this
     */
    public function remove_row(int $row, int $number_of_rows = 1): static
    {
        if ($row < 1) {
            throw new Exception('Rows to be deleted should at least start from row 1.');
        }
        $start_row = $row;
        $end_row = $start_row + $number_of_rows - 1;
        $remove_keys = [];
        $add_keys = [];
        foreach ($this->merge_cells as $key => $value) {
            if (Preg::is_match('/^([a-z]{1,3})(\d+):([a-z]{1,3})(\d+)/i', $key, $matches)) {
                $start_merge_int = (int) $matches[2];
                $end_merge_int = (int) $matches[4];
                if ($start_merge_int >= $start_row) {
                    if ($start_merge_int <= $end_row) {
                        $remove_keys[] = $key;
                    }
                } elseif ($end_merge_int >= $start_row) {
                    if ($end_merge_int <= $end_row) {
                        $temp = $end_merge_int - 1;
                        $remove_keys[] = $key;
                        if ($temp !== $start_merge_int) {
                            $temp3 = $matches[1] . $matches[2] . ':' . $matches[3] . $temp;
                            $add_keys[] = $temp3;
                        }
                    }
                }
            }
        }
        foreach ($remove_keys as $key) {
            unset($this->merge_cells[$key]);
        }
        foreach ($add_keys as $key) {
            $this->merge_cells[$key] = $key;
        }
        $hold_row_dimensions = $this->remove_row_dimensions($row, $number_of_rows);
        $highest_row = $this->get_highest_data_row();
        $removed_rows_counter = 0;
        for ($r = 0; $r < $number_of_rows; ++$r) {
            if ($row + $r <= $highest_row) {
                $this->cell_collection->remove_row($row + $r);
                ++$removed_rows_counter;
            }
        }
        $obj_reference_helper = Reference_Helper::get_instance();
        $obj_reference_helper->insert_new_before('A' . ($row + $number_of_rows), 0, -$number_of_rows, $this);
        for ($r = 0; $r < $removed_rows_counter; ++$r) {
            $this->cell_collection->remove_row($highest_row);
            --$highest_row;
        }
        $this->row_dimensions = $hold_row_dimensions;
        return $this;
    }
    /** @return RowDimension[] */
    private function remove_row_dimensions(int $row, int $number_of_rows): array
    {
        $high_row = $row + $number_of_rows - 1;
        $hold_row_dimensions = [];
        foreach ($this->row_dimensions as $row_dimension) {
            $num = $row_dimension->get_row_index();
            if ($num < $row) {
                $hold_row_dimensions[$num] = $row_dimension;
            } elseif ($num > $high_row) {
                $num -= $number_of_rows;
                $clone_dimension = clone $row_dimension;
                $clone_dimension->set_row_index($num);
                $hold_row_dimensions[$num] = $clone_dimension;
            }
        }
        return $hold_row_dimensions;
    }
    /**
     * Remove a column, updating all possible related data.
     *
     * @param string $column Remove columns starting with this column name, eg: 'A'
     * @param int $numberOfColumns Number of columns to remove
     *
     * @return $this
     */
    public function remove_column(string $column, int $number_of_columns = 1): static
    {
        if (is_numeric($column)) {
            throw new Exception('Column references should not be numeric.');
        }
        $start_column_int = Coordinate::column_index_from_string($column);
        $end_column_int = $start_column_int + $number_of_columns - 1;
        $remove_keys = [];
        $add_keys = [];
        foreach ($this->merge_cells as $key => $value) {
            if (Preg::is_match('/^([a-z]{1,3})(\d+):([a-z]{1,3})(\d+)/i', $key, $matches)) {
                $start_merge_int = Coordinate::column_index_from_string($matches[1]);
                $end_merge_int = Coordinate::column_index_from_string($matches[3]);
                if ($start_merge_int >= $start_column_int) {
                    if ($start_merge_int <= $end_column_int) {
                        $remove_keys[] = $key;
                    }
                } elseif ($end_merge_int >= $start_column_int) {
                    if ($end_merge_int <= $end_column_int) {
                        $temp = Coordinate::column_index_from_string($matches[3]) - 1;
                        $temp2 = Coordinate::string_from_column_index($temp);
                        $remove_keys[] = $key;
                        if ($temp2 !== $matches[1]) {
                            $temp3 = $matches[1] . $matches[2] . ':' . $temp2 . $matches[4];
                            $add_keys[] = $temp3;
                        }
                    }
                }
            }
        }
        foreach ($remove_keys as $key) {
            unset($this->merge_cells[$key]);
        }
        foreach ($add_keys as $key) {
            $this->merge_cells[$key] = $key;
        }
        $highest_column = $this->get_highest_data_column();
        $highest_column_index = Coordinate::column_index_from_string($highest_column);
        $p_column_index = Coordinate::column_index_from_string($column);
        $hold_column_dimensions = $this->remove_column_dimensions($p_column_index, $number_of_columns);
        $column = Coordinate::string_from_column_index($p_column_index + $number_of_columns);
        $obj_reference_helper = Reference_Helper::get_instance();
        $obj_reference_helper->insert_new_before($column . '1', -$number_of_columns, 0, $this);
        $this->column_dimensions = $hold_column_dimensions;
        if ($p_column_index > $highest_column_index) {
            return $this;
        }
        $max_possible_columns_to_be_removed = $highest_column_index - $p_column_index + 1;
        for ($c = 0, $n = min($max_possible_columns_to_be_removed, $number_of_columns); $c < $n; ++$c) {
            $this->cell_collection->remove_column($highest_column);
            $highest_column = Coordinate::string_from_column_index(Coordinate::column_index_from_string($highest_column) - 1);
        }
        $this->garbage_collect();
        return $this;
    }
    /** @return ColumnDimension[] */
    private function remove_column_dimensions(int $p_column_index, int $number_of_columns): array
    {
        $high_col = $p_column_index + $number_of_columns - 1;
        $hold_column_dimensions = [];
        foreach ($this->column_dimensions as $column_dimension) {
            $num = $column_dimension->get_column_numeric();
            if ($num < $p_column_index) {
                $str = $column_dimension->get_column_index();
                $hold_column_dimensions[$str] = $column_dimension;
            } elseif ($num > $high_col) {
                $clone_dimension = clone $column_dimension;
                $clone_dimension->set_column_numeric($num - $number_of_columns);
                $str = $clone_dimension->get_column_index();
                $hold_column_dimensions[$str] = $clone_dimension;
            }
        }
        return $hold_column_dimensions;
    }
    /**
     * Remove a column, updating all possible related data.
     *
     * @param int $columnIndex Remove starting with this column Index (numeric column coordinate)
     * @param int $numColumns Number of columns to remove
     *
     * @return $this
     */
    public function remove_column_by_index(int $column_index, int $num_columns = 1): static
    {
        if ($column_index >= 1) {
            return $this->remove_column(Coordinate::string_from_column_index($column_index), $num_columns);
        }
        throw new Exception('Columns to be deleted should at least start from column A (1)');
    }
    /**
     * Show gridlines?
     */
    public function get_show_gridlines(): bool
    {
        return $this->show_gridlines;
    }
    /**
     * Set show gridlines.
     *
     * @param bool $showGridLines Show gridlines (true/false)
     *
     * @return $this
     */
    public function set_show_gridlines(bool $show_grid_lines): self
    {
        $this->show_gridlines = $show_grid_lines;
        return $this;
    }
    /**
     * Print gridlines?
     */
    public function get_print_gridlines(): bool
    {
        return $this->print_gridlines;
    }
    /**
     * Set print gridlines.
     *
     * @param bool $printGridLines Print gridlines (true/false)
     *
     * @return $this
     */
    public function set_print_gridlines(bool $print_grid_lines): self
    {
        $this->print_gridlines = $print_grid_lines;
        return $this;
    }
    /**
     * Show row and column headers?
     */
    public function get_show_row_col_headers(): bool
    {
        return $this->show_row_col_headers;
    }
    /**
     * Set show row and column headers.
     *
     * @param bool $showRowColHeaders Show row and column headers (true/false)
     *
     * @return $this
     */
    public function set_show_row_col_headers(bool $show_row_col_headers): self
    {
        $this->show_row_col_headers = $show_row_col_headers;
        return $this;
    }
    /**
     * Show summary below? (Row/Column outlining).
     */
    public function get_show_summary_below(): bool
    {
        return $this->show_summary_below;
    }
    /**
     * Set show summary below.
     *
     * @param bool $showSummaryBelow Show summary below (true/false)
     *
     * @return $this
     */
    public function set_show_summary_below(bool $show_summary_below): self
    {
        $this->show_summary_below = $show_summary_below;
        return $this;
    }
    /**
     * Show summary right? (Row/Column outlining).
     */
    public function get_show_summary_right(): bool
    {
        return $this->show_summary_right;
    }
    /**
     * Set show summary right.
     *
     * @param bool $showSummaryRight Show summary right (true/false)
     *
     * @return $this
     */
    public function set_show_summary_right(bool $show_summary_right): self
    {
        $this->show_summary_right = $show_summary_right;
        return $this;
    }
    /**
     * Get comments.
     *
     * @return Comment[]
     */
    public function get_comments(): array
    {
        return $this->comments;
    }
    /**
     * Set comments array for the entire sheet.
     *
     * @param Comment[] $comments
     *
     * @return $this
     */
    public function set_comments(array $comments): self
    {
        $this->comments = $comments;
        return $this;
    }
    /**
     * Remove comment from cell.
     *
     * @param array{0: int, 1: int}|CellAddress|string $cellCoordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     *
     * @return $this
     */
    public function remove_comment(Cell_Address|string|array $cell_coordinate): self
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($cell_coordinate));
        if (Coordinate::coordinate_is_range($cell_address)) {
            throw new Exception('Cell coordinate string can not be a range of cells.');
        }
        if (str_contains($cell_address, '$')) {
            throw new Exception('Cell coordinate string must not be absolute.');
        }
        if ($cell_address == '') {
            throw new Exception('Cell coordinate can not be zero-length string.');
        }
        // Check if we have a comment for this cell and delete it
        if (isset($this->comments[$cell_address])) {
            unset($this->comments[$cell_address]);
        }
        return $this;
    }
    /**
     * Get comment for cell.
     *
     * @param array{0: int, 1: int}|CellAddress|string $cellCoordinate Coordinate of the cell as a string, eg: 'C5';
     *               or as an array of [$columnIndex, $row] (e.g. [3, 5]), or a CellAddress object.
     */
    public function get_comment(Cell_Address|string|array $cell_coordinate, bool $attach_new = true): Comment
    {
        $cell_address = Functions::trim_sheet_from_cell_reference(Validations::validate_cell_address($cell_coordinate));
        if (Coordinate::coordinate_is_range($cell_address)) {
            throw new Exception('Cell coordinate string can not be a range of cells.');
        }
        if (str_contains($cell_address, '$')) {
            throw new Exception('Cell coordinate string must not be absolute.');
        }
        if ($cell_address == '') {
            throw new Exception('Cell coordinate can not be zero-length string.');
        }
        // Check if we already have a comment for this cell.
        if (isset($this->comments[$cell_address])) {
            return $this->comments[$cell_address];
        }
        // If not, create a new comment.
        $new_comment = new Comment();
        if ($attach_new) {
            $this->comments[$cell_address] = $new_comment;
        }
        return $new_comment;
    }
    /**
     * Get active cell.
     *
     * @return string Example: 'A1'
     */
    public function get_active_cell(): string
    {
        return $this->active_cell;
    }
    /**
     * Get selected cells.
     */
    public function get_selected_cells(): string
    {
        return $this->selected_cells;
    }
    /**
     * Selected cell.
     *
     * @param string $coordinate Cell (i.e. A1)
     *
     * @return $this
     */
    public function set_selected_cell(string $coordinate): static
    {
        return $this->set_selected_cells($coordinate);
    }
    /**
     * Select a range of cells.
     *
     * @param AddressRange<CellAddress>|AddressRange<int>|AddressRange<string>|array{0: int, 1: int, 2: int, 3: int}|array{0: int, 1: int}|CellAddress|int|string $coordinate A simple string containing a Cell range like 'A1:E10'
     *              or passing in an array of [$fromColumnIndex, $fromRow, $toColumnIndex, $toRow] (e.g. [3, 5, 6, 8]),
     *              or a CellAddress or AddressRange object.
     *
     * @return $this
     */
    public function set_selected_cells(Address_Range|Cell_Address|int|string|array $coordinate): static
    {
        if (is_string($coordinate)) {
            $coordinate = Validations::defined_name_to_coordinate($coordinate, $this);
        }
        $coordinate = Validations::validate_cell_or_cell_range($coordinate);
        if (Coordinate::coordinate_is_range($coordinate)) {
            [$first] = Coordinate::split_range($coordinate);
            $this->active_cell = $first[0];
        } else {
            $this->active_cell = $coordinate;
        }
        $this->selected_cells = $coordinate;
        $this->set_selected_cells_active_pane();
        return $this;
    }
    private function set_selected_cells_active_pane(): void
    {
        if (!empty($this->freeze_pane)) {
            $coordinate_c = Coordinate::indexes_from_string($this->freeze_pane);
            $coordinate_t = Coordinate::indexes_from_string($this->active_cell);
            if ($coordinate_c[0] === 1) {
                $active_pane = $coordinate_t[1] <= $coordinate_c[1] ? 'topLeft' : 'bottomLeft';
            } elseif ($coordinate_c[1] === 1) {
                $active_pane = $coordinate_t[0] <= $coordinate_c[0] ? 'topLeft' : 'topRight';
            } elseif ($coordinate_t[1] <= $coordinate_c[1]) {
                $active_pane = $coordinate_t[0] <= $coordinate_c[0] ? 'topLeft' : 'topRight';
            } else {
                $active_pane = $coordinate_t[0] <= $coordinate_c[0] ? 'bottomLeft' : 'bottomRight';
            }
            $this->set_active_pane($active_pane);
            $this->panes[$active_pane] = new Pane($active_pane, $this->selected_cells, $this->active_cell);
        }
    }
    /**
     * Get right-to-left.
     */
    public function get_right_to_left(): bool
    {
        return $this->right_to_left;
    }
    /**
     * Set right-to-left.
     *
     * @param bool $value Right-to-left true/false
     *
     * @return $this
     */
    public function set_right_to_left(bool $value): static
    {
        $this->right_to_left = $value;
        return $this;
    }
    /**
     * Fill worksheet from values in array.
     *
     * @param mixed[]|mixed[][] $source Source array
     * @param mixed $nullValue Value in source array that stands for blank cell
     * @param string $startCell Insert array starting from this cell address as the top left coordinate
     * @param bool $strictNullComparison Apply strict comparison when testing for null values in the array
     *
     * @return $this
     */
    public function from_array(array $source, mixed $null_value = null, string $start_cell = 'A1', bool $strict_null_comparison = false): static
    {
        //    Convert a 1-D array to 2-D (for ease of looping)
        if (!is_array(end($source))) {
            $source = [$source];
        }
        /** @var mixed[][] $source */
        // start coordinate
        [$start_column, $start_row] = Coordinate::coordinate_from_string($start_cell);
        $start_row = (int) $start_row;
        // Loop through $source
        if ($strict_null_comparison) {
            foreach ($source as $row_data) {
                /** @var string */
                $current_column = $start_column;
                foreach ($row_data as $cell_value) {
                    if ($cell_value !== $null_value) {
                        $this->get_cell($current_column . $start_row)->set_value($cell_value);
                    }
                    String_Helper::string_increment($current_column);
                }
                ++$start_row;
            }
        } else {
            foreach ($source as $row_data) {
                $current_column = $start_column;
                foreach ($row_data as $cell_value) {
                    if ($cell_value != $null_value) {
                        $this->get_cell($current_column . $start_row)->set_value($cell_value);
                    }
                    String_Helper::string_increment($current_column);
                }
                ++$start_row;
            }
        }
        return $this;
    }
    /**
     * @param bool $calculateFormulas Whether to calculate cell's value if it is a formula.
     * @param null|bool|float|int|RichText|string $nullValue value to use when null
     * @param bool $formatData Whether to format data according to cell's style.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Calculation\Exception
     */
    protected function cell_to_array(Cell $cell, bool $calculate_formulas, bool $format_data, mixed $null_value, bool $less_float_precision = false, $old_calculated_value = false): mixed
    {
        $return_value = $null_value;
        if ($cell->get_value() !== null) {
            if ($cell->get_value() instanceof Rich_Text) {
                $return_value = $cell->get_value()->get_plain_text();
            } elseif ($calculate_formulas) {
                $return_value = $cell->get_calculated_value();
            } elseif ($old_calculated_value && $cell->get_data_type() === Data_Type::TYPE_FORMULA) {
                $return_value = $cell->get_old_calculated_value() ?? $cell->get_value();
            } else {
                $return_value = $cell->get_value();
            }
            if ($format_data) {
                $style = $this->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index());
                /** @var null|bool|float|int|RichText|string */
                $return_valuex = $return_value;
                $return_value = Number_Format::to_formatted_string($return_valuex, $style->get_number_format()->get_format_code() ?? Number_Format::FORMAT_GENERAL, lessFloatPrecision: $less_float_precision);
            }
        }
        return $return_value;
    }
    /**
     * Create array from a range of cells.
     *
     * @param null|bool|float|int|RichText|string $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool $calculateFormulas Should formulas be calculated?
     * @param bool $formatData Should formatting be applied to cell values?
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                             True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @param bool $reduceArrays If true and result is a formula which evaluates to an array, reduce it to the top leftmost value.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @return mixed[][]
     */
    public function range_to_array(string $range, mixed $null_value = null, bool $calculate_formulas = true, bool $format_data = true, bool $return_cell_ref = false, bool $ignore_hidden = false, bool $reduce_arrays = false, bool $less_float_precision = false, bool $old_calculated_value = false): array
    {
        $return_value = [];
        // Loop through rows
        foreach ($this->range_to_array_yield_rows($range, $null_value, $calculate_formulas, $format_data, $return_cell_ref, $ignore_hidden, $reduce_arrays, $less_float_precision, $old_calculated_value) as $row_ref => $row_array) {
            /** @var int $rowRef */
            $return_value[$row_ref] = $row_array;
        }
        // Return
        return $return_value;
    }
    /**
     * Create array from a multiple ranges of cells. (such as A1:A3,A15,B17:C17).
     *
     * @param null|bool|float|int|RichText|string $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool $calculateFormulas Should formulas be calculated?
     * @param bool $formatData Should formatting be applied to cell values?
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                             True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @param bool $reduceArrays If true and result is a formula which evaluates to an array, reduce it to the top leftmost value.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @return mixed[][]
     */
    public function ranges_to_array(string $ranges, mixed $null_value = null, bool $calculate_formulas = true, bool $format_data = true, bool $return_cell_ref = false, bool $ignore_hidden = false, bool $reduce_arrays = false, bool $less_float_precision = false, bool $old_calculated_value = false): array
    {
        $return_value = [];
        $parts = explode(',', $ranges);
        foreach ($parts as $part) {
            // Loop through rows
            foreach ($this->range_to_array_yield_rows($part, $null_value, $calculate_formulas, $format_data, $return_cell_ref, $ignore_hidden, $reduce_arrays, $less_float_precision, $old_calculated_value) as $row_ref => $row_array) {
                /** @var int $rowRef */
                $return_value[$row_ref] = $row_array;
            }
        }
        // Return
        return $return_value;
    }
    /**
     * Create array from a range of cells, yielding each row in turn.
     *
     * @param null|bool|float|int|RichText|string $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool $calculateFormulas Should formulas be calculated?
     * @param bool $formatData Should formatting be applied to cell values?
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                             True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @param bool $reduceArrays If true and result is a formula which evaluates to an array, reduce it to the top leftmost value.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @return Generator<array<mixed>>
     */
    public function range_to_array_yield_rows(string $range, mixed $null_value = null, bool $calculate_formulas = true, bool $format_data = true, bool $return_cell_ref = false, bool $ignore_hidden = false, bool $reduce_arrays = false, bool $less_float_precision = false, bool $old_calculated_value = false)
    {
        $range = Validations::validate_cell_or_cell_range($range);
        //    Identify the range that we need to extract from the worksheet
        [$range_start, $range_end] = Coordinate::range_boundaries($range);
        $min_col = Coordinate::string_from_column_index($range_start[0]);
        $min_row = $range_start[1];
        $max_col = Coordinate::string_from_column_index($range_end[0]);
        $max_row = $range_end[1];
        $min_col_int = $range_start[0];
        $max_col_int = $range_end[0];
        String_Helper::string_increment($max_col);
        /** @var array<string, bool> */
        $hidden_columns = [];
        $null_row = $this->build_null_row($null_value, $min_col, $max_col, $return_cell_ref, $ignore_hidden, $hidden_columns);
        $hide_columns = !empty($hidden_columns);
        $keys = $this->cell_collection->get_sorted_coordinates_int();
        $key_index = 0;
        $keys_count = count($keys);
        // Loop through rows
        for ($row = $min_row; $row <= $max_row; ++$row) {
            if ($ignore_hidden === true && $this->is_row_visible($row) === false) {
                continue;
            }
            $row_ref = $return_cell_ref ? $row : $row - $min_row;
            $return_value = $null_row;
            $index = ($row - 1) * Address_Range::MAX_COLUMN_INT + 1;
            $index_plus = $index + Address_Range::MAX_COLUMN_INT - 1;
            // Binary search to quickly approach the correct index
            $key_index = intdiv($keys_count, 2);
            $bound_low = 0;
            $bound_high = $keys_count - 1;
            while ($bound_low <= $bound_high) {
                $key_index = intdiv($bound_low + $bound_high, 2);
                if ($keys[$key_index] < $index) {
                    $bound_low = $key_index + 1;
                } elseif ($keys[$key_index] > $index) {
                    $bound_high = $key_index - 1;
                } else {
                    break;
                }
            }
            // Realign to the proper index value
            while ($key_index > 0 && $keys[$key_index] > $index) {
                --$key_index;
            }
            while ($key_index < $keys_count && $keys[$key_index] < $index) {
                ++$key_index;
            }
            while ($key_index < $keys_count && $keys[$key_index] <= $index_plus) {
                $key = $keys[$key_index];
                $this_row = intdiv($key - 1, Address_Range::MAX_COLUMN_INT) + 1;
                $this_col = $key % Address_Range::MAX_COLUMN_INT ?: Address_Range::MAX_COLUMN_INT;
                if ($this_col >= $min_col_int && $this_col <= $max_col_int) {
                    $col = Coordinate::string_from_column_index($this_col);
                    if ($hide_columns === false || !isset($hidden_columns[$col])) {
                        $column_ref = $return_cell_ref ? $col : $this_col - $min_col_int;
                        $cell = $this->cell_collection->get("{$col}{$this_row}");
                        if ($cell !== null) {
                            $value = $this->cell_to_array($cell, $calculate_formulas, $format_data, $null_value, lessFloatPrecision: $less_float_precision, oldCalculatedValue: $old_calculated_value);
                            if ($reduce_arrays) {
                                while (is_array($value)) {
                                    $value = array_shift($value);
                                }
                            }
                            if ($value !== $null_value) {
                                $return_value[$column_ref] = $value;
                            }
                        }
                    }
                }
                ++$key_index;
            }
            yield $row_ref => $return_value;
        }
    }
    /**
     * Prepare a row data filled with null values to deduplicate the memory areas for empty rows.
     *
     * @param mixed $nullValue Value returned in the array entry if a cell doesn't exist
     * @param string $minCol Start column of the range
     * @param string $maxCol End column of the range
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                              True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                             True - Don't return values for rows/columns that are defined as hidden.
     * @param array<string, bool> $hiddenColumns
     *
     * @return mixed[]
     */
    private function build_null_row(mixed $null_value, string $min_col, string $max_col, bool $return_cell_ref, bool $ignore_hidden, array &$hidden_columns): array
    {
        $null_row = [];
        $c = -1;
        for ($col = $min_col; $col !== $max_col; String_Helper::string_increment($col)) {
            if ($ignore_hidden === true && $this->column_dimension_exists($col) && $this->get_column_dimension($col)->get_visible() === false) {
                $hidden_columns[$col] = true;
            } else {
                $column_ref = $return_cell_ref ? $col : ++$c;
                $null_row[$column_ref] = $null_value;
            }
        }
        return $null_row;
    }
    private function validate_named_range(string $defined_name, bool $return_null_if_invalid = false): ?Defined_Name
    {
        $named_range = Defined_Name::resolve_name($defined_name, $this);
        if ($named_range === null) {
            if ($return_null_if_invalid) {
                return null;
            }
            throw new Exception('Named Range ' . $defined_name . ' does not exist.');
        }
        if ($named_range->is_formula()) {
            if ($return_null_if_invalid) {
                return null;
            }
            throw new Exception('Defined Named ' . $defined_name . ' is a formula, not a range or cell.');
        }
        if ($named_range->get_local_only()) {
            $worksheet = $named_range->get_worksheet();
            if ($worksheet === null || $this !== $worksheet) {
                if ($return_null_if_invalid) {
                    return null;
                }
                throw new Exception('Named range ' . $defined_name . ' is not accessible from within sheet ' . $this->get_title());
            }
        }
        return $named_range;
    }
    /**
     * Create array from a range of cells.
     *
     * @param string $definedName The Named Range that should be returned
     * @param null|bool|float|int|RichText|string $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool $calculateFormulas Should formulas be calculated?
     * @param bool $formatData Should formatting be applied to cell values?
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                             True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @param bool $reduceArrays If true and result is a formula which evaluates to an array, reduce it to the top leftmost value.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @return mixed[][]
     */
    public function named_range_to_array(string $defined_name, mixed $null_value = null, bool $calculate_formulas = true, bool $format_data = true, bool $return_cell_ref = false, bool $ignore_hidden = false, bool $reduce_arrays = false, bool $less_float_precision = false, bool $old_calculated_value = false): array
    {
        $ret_val = [];
        $named_range = $this->validate_named_range($defined_name);
        if ($named_range !== null) {
            $cell_range = ltrim(substr($named_range->get_value(), (int) strrpos($named_range->get_value(), '!')), '!');
            $cell_range = str_replace('$', '', $cell_range);
            $work_sheet = $named_range->get_worksheet();
            if ($work_sheet !== null) {
                $ret_val = $work_sheet->range_to_array($cell_range, $null_value, $calculate_formulas, $format_data, $return_cell_ref, $ignore_hidden, $reduce_arrays, $less_float_precision, $old_calculated_value);
            }
        }
        return $ret_val;
    }
    /**
     * Create array from worksheet.
     *
     * @param null|bool|float|int|RichText|string $nullValue Value returned in the array entry if a cell doesn't exist
     * @param bool $calculateFormulas Should formulas be calculated?
     * @param bool $formatData Should formatting be applied to cell values?
     * @param bool $returnCellRef False - Return a simple array of rows and columns indexed by number counting from zero
     *                             True - Return rows and columns indexed by their actual row and column IDs
     * @param bool $ignoreHidden False - Return values for rows/columns even if they are defined as hidden.
     *                            True - Don't return values for rows/columns that are defined as hidden.
     * @param bool $reduceArrays If true and result is a formula which evaluates to an array, reduce it to the top leftmost value.
     * @param bool $lessFloatPrecision If true, formatting unstyled floats will convert them to a more human-friendly but less computationally accurate value
     * @param bool $oldCalculatedValue If calculateFormulas is false and this is true, use oldCalculatedFormula instead.
     *
     * @return mixed[][]
     */
    public function to_array(mixed $null_value = null, bool $calculate_formulas = true, bool $format_data = true, bool $return_cell_ref = false, bool $ignore_hidden = false, bool $reduce_arrays = false, bool $less_float_precision = false, bool $old_calculated_value = false): array
    {
        // Garbage collect...
        $this->garbage_collect();
        $this->calculate_arrays($calculate_formulas);
        //    Identify the range that we need to extract from the worksheet
        $max_col = $this->get_highest_column();
        $max_row = $this->get_highest_row();
        // Return
        return $this->range_to_array("A1:{$max_col}{$max_row}", $null_value, $calculate_formulas, $format_data, $return_cell_ref, $ignore_hidden, $reduce_arrays, $less_float_precision, $old_calculated_value);
    }
    /**
     * Get row iterator.
     *
     * @param int $startRow The row number at which to start iterating
     * @param ?int $endRow The row number at which to stop iterating
     */
    public function get_row_iterator(int $start_row = 1, ?int $end_row = null): Row_Iterator
    {
        return new Row_Iterator($this, $start_row, $end_row);
    }
    /**
     * Get column iterator.
     *
     * @param string $startColumn The column address at which to start iterating
     * @param ?string $endColumn The column address at which to stop iterating
     */
    public function get_column_iterator(string $start_column = 'A', ?string $end_column = null): Column_Iterator
    {
        return new Column_Iterator($this, $start_column, $end_column);
    }
    /**
     * Run PhpSpreadsheet garbage collector.
     *
     * @return $this
     */
    public function garbage_collect(): static
    {
        // Flush cache
        $this->cell_collection->get('A1');
        // Lookup highest column and highest row if cells are cleaned
        $col_row = $this->cell_collection->get_highest_row_and_column();
        $highest_row = $col_row['row'];
        $highest_column = Coordinate::column_index_from_string($col_row['column']);
        // Loop through column dimensions
        foreach ($this->column_dimensions as $dimension) {
            $highest_column = max($highest_column, Coordinate::column_index_from_string($dimension->get_column_index()));
        }
        // Loop through row dimensions
        foreach ($this->row_dimensions as $dimension) {
            $highest_row = max($highest_row, $dimension->get_row_index());
        }
        // Cache values
        $this->cached_highest_column = max(1, $highest_column);
        /** @var int $highestRow */
        $this->cached_highest_row = $highest_row;
        // Return
        return $this;
    }
    /**
     * @deprecated 5.2.0 Serves no useful purpose. No replacement.
     *
     * @codeCoverageIgnore
     */
    public function get_hash_int(): int
    {
        return spl_object_id($this);
    }
    /**
     * Extract worksheet title from range.
     *
     * Example: extractSheetTitle("testSheet!A1") ==> 'A1'
     * Example: extractSheetTitle("testSheet!A1:C3") ==> 'A1:C3'
     * Example: extractSheetTitle("'testSheet 1'!A1", true) ==> ['testSheet 1', 'A1'];
     * Example: extractSheetTitle("'testSheet 1'!A1:C3", true) ==> ['testSheet 1', 'A1:C3'];
     * Example: extractSheetTitle("A1", true) ==> ['', 'A1'];
     * Example: extractSheetTitle("A1:C3", true) ==> ['', 'A1:C3']
     *
     * @param ?string $range Range to extract title from
     * @param bool $returnRange Return range? (see example)
     *
     * @return ($range is non-empty-string ? ($returnRange is true ? array{0: string, 1: string} : string) : ($returnRange is true ? array{0: null, 1: null} : null))
     */
    public static function extract_sheet_title(?string $range, bool $return_range = false, bool $unapostrophize = false): array|null|string
    {
        if (empty($range)) {
            return $return_range ? [null, null] : null;
        }
        // Sheet title included?
        if (($sep = strrpos($range, '!')) === false) {
            return $return_range ? ['', $range] : '';
        }
        if ($return_range) {
            $title = substr($range, 0, $sep);
            if ($unapostrophize) {
                $title = self::un_apostrophize_title($title);
            }
            return [$title, substr($range, $sep + 1)];
        }
        return substr($range, $sep + 1);
    }
    public static function un_apostrophize_title(?string $title): string
    {
        $title ??= '';
        if (str_starts_with($title, "'") && str_ends_with($title, "'")) {
            return str_replace("''", "'", substr($title, 1, -1));
        }
        return $title;
    }
    /**
     * Get hyperlink.
     *
     * @param string $cellCoordinate Cell coordinate to get hyperlink for, eg: 'A1'
     */
    public function get_hyperlink(string $cell_coordinate): Hyperlink
    {
        $this->get_cell($cell_coordinate)->set_had_hyperlink(true);
        // return hyperlink if we already have one
        if (isset($this->hyperlink_collection[$cell_coordinate])) {
            return $this->hyperlink_collection[$cell_coordinate];
        }
        // else create hyperlink
        $this->hyperlink_collection[$cell_coordinate] = new Hyperlink();
        return $this->hyperlink_collection[$cell_coordinate];
    }
    /**
     * Set hyperlink.
     *
     * @param string $cellCoordinate Cell coordinate to insert hyperlink, eg: 'A1'
     *
     * @return $this
     */
    public function set_hyperlink(string $cell_coordinate, ?Hyperlink $hyperlink = null, bool $reset = true): static
    {
        if ($hyperlink === null) {
            unset($this->hyperlink_collection[$cell_coordinate]);
            if ($reset) {
                $this->get_cell($cell_coordinate)->set_had_hyperlink(false);
            }
        } else {
            $this->hyperlink_collection[$cell_coordinate] = $hyperlink;
            $this->get_cell($cell_coordinate)->set_had_hyperlink(true);
        }
        return $this;
    }
    /**
     * Hyperlink at a specific coordinate exists?
     *
     * @param string $coordinate eg: 'A1'
     */
    public function hyperlink_exists(string $coordinate): bool
    {
        return isset($this->hyperlink_collection[$coordinate]);
    }
    /**
     * Get collection of hyperlinks.
     *
     * @return Hyperlink[]
     */
    public function get_hyperlink_collection(): array
    {
        return $this->hyperlink_collection;
    }
    /**
     * Get data validation.
     *
     * @param string $cellCoordinate Cell coordinate to get data validation for, eg: 'A1'
     */
    public function get_data_validation(string $cell_coordinate): Data_Validation
    {
        // return data validation if we already have one
        if (isset($this->data_validation_collection[$cell_coordinate])) {
            return $this->data_validation_collection[$cell_coordinate];
        }
        // or if cell is part of a data validation range
        foreach ($this->data_validation_collection as $key => $data_validation) {
            $key_parts = explode(' ', (string) $key);
            foreach ($key_parts as $key_part) {
                if ($key_part === $cell_coordinate) {
                    return $data_validation;
                }
                if (str_contains($key_part, ':')) {
                    if (Coordinate::coordinate_is_inside_range($key_part, $cell_coordinate)) {
                        return $data_validation;
                    }
                }
            }
        }
        // else create data validation
        $data_validation = new Data_Validation();
        $data_validation->set_sqref($cell_coordinate);
        $this->data_validation_collection[$cell_coordinate] = $data_validation;
        return $data_validation;
    }
    /**
     * Set data validation.
     *
     * @param string $cellCoordinate Cell coordinate to insert data validation, eg: 'A1'
     *
     * @return $this
     */
    public function set_data_validation(string $cell_coordinate, ?Data_Validation $data_validation = null): static
    {
        if ($data_validation === null) {
            unset($this->data_validation_collection[$cell_coordinate]);
        } else {
            $data_validation->set_sqref($cell_coordinate);
            $this->data_validation_collection[$cell_coordinate] = $data_validation;
        }
        return $this;
    }
    /**
     * Data validation at a specific coordinate exists?
     *
     * @param string $coordinate eg: 'A1'
     */
    public function data_validation_exists(string $coordinate): bool
    {
        if (isset($this->data_validation_collection[$coordinate])) {
            return true;
        }
        foreach ($this->data_validation_collection as $key => $data_validation) {
            $key_parts = explode(' ', (string) $key);
            foreach ($key_parts as $key_part) {
                if ($key_part === $coordinate) {
                    return true;
                }
                if (str_contains($key_part, ':')) {
                    if (Coordinate::coordinate_is_inside_range($key_part, $coordinate)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    /**
     * Get collection of data validations.
     *
     * @return DataValidation[]
     */
    public function get_data_validation_collection(): array
    {
        $collection_cells = [];
        $collection_ranges = [];
        foreach ($this->data_validation_collection as $key => $data_validation) {
            if (Preg::is_match('/[: ]/', $key)) {
                $collection_ranges[$key] = $data_validation;
            } else {
                $collection_cells[$key] = $data_validation;
            }
        }
        return array_merge($collection_cells, $collection_ranges);
    }
    /**
     * Accepts a range, returning it as a range that falls within the current highest row and column of the worksheet.
     *
     * @return string Adjusted range value
     */
    public function shrink_range_to_fit(string $range): string
    {
        $max_col = $this->get_highest_column();
        $max_row = $this->get_highest_row();
        $max_col = Coordinate::column_index_from_string($max_col);
        $range_blocks = explode(' ', $range);
        foreach ($range_blocks as &$range_set) {
            $range_boundaries = Coordinate::get_range_boundaries($range_set);
            if (Coordinate::column_index_from_string($range_boundaries[0][0]) > $max_col) {
                $range_boundaries[0][0] = Coordinate::string_from_column_index($max_col);
            }
            if ($range_boundaries[0][1] > $max_row) {
                $range_boundaries[0][1] = $max_row;
            }
            if (Coordinate::column_index_from_string($range_boundaries[1][0]) > $max_col) {
                $range_boundaries[1][0] = Coordinate::string_from_column_index($max_col);
            }
            if ($range_boundaries[1][1] > $max_row) {
                $range_boundaries[1][1] = $max_row;
            }
            $range_set = $range_boundaries[0][0] . $range_boundaries[0][1] . ':' . $range_boundaries[1][0] . $range_boundaries[1][1];
        }
        unset($range_set);
        return implode(' ', $range_blocks);
    }
    /**
     * Get tab color.
     */
    public function get_tab_color(): Color
    {
        if ($this->tab_color === null) {
            $this->tab_color = new Color();
        }
        return $this->tab_color;
    }
    /**
     * Reset tab color.
     *
     * @return $this
     */
    public function reset_tab_color(): static
    {
        $this->tab_color = null;
        return $this;
    }
    /**
     * Tab color set?
     */
    public function is_tab_color_set(): bool
    {
        return $this->tab_color !== null;
    }
    /**
     * Copy worksheet (!= clone!).
     */
    public function copy(): static
    {
        return clone $this;
    }
    /**
     * Returns a boolean true if the specified row contains no cells. By default, this means that no cell records
     *          exist in the collection for this row. false will be returned otherwise.
     *     This rule can be modified by passing a $definitionOfEmptyFlags value:
     *          1 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL If the only cells in the collection are null value
     *                  cells, then the row will be considered empty.
     *          2 - CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL If the only cells in the collection are empty
     *                  string value cells, then the row will be considered empty.
     *          3 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     *                  If the only cells in the collection are null value or empty string value cells, then the row
     *                  will be considered empty.
     *
     * @param int $definitionOfEmptyFlags
     *              Possible Flag Values are:
     *                  CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL
     *                  CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     */
    public function is_empty_row(int $row_id, int $definition_of_empty_flags = 0): bool
    {
        try {
            $iterator = new Row_Iterator($this, $row_id, $row_id);
            $iterator->seek($row_id);
            $row = $iterator->current();
        } catch (Exception) {
            return true;
        }
        return $row->is_empty($definition_of_empty_flags);
    }
    /**
     * Returns a boolean true if the specified column contains no cells. By default, this means that no cell records
     *          exist in the collection for this column. false will be returned otherwise.
     *     This rule can be modified by passing a $definitionOfEmptyFlags value:
     *          1 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL If the only cells in the collection are null value
     *                  cells, then the column will be considered empty.
     *          2 - CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL If the only cells in the collection are empty
     *                  string value cells, then the column will be considered empty.
     *          3 - CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL | CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     *                  If the only cells in the collection are null value or empty string value cells, then the column
     *                  will be considered empty.
     *
     * @param int $definitionOfEmptyFlags
     *              Possible Flag Values are:
     *                  CellIterator::TREAT_NULL_VALUE_AS_EMPTY_CELL
     *                  CellIterator::TREAT_EMPTY_STRING_AS_EMPTY_CELL
     */
    public function is_empty_column(string $column_id, int $definition_of_empty_flags = 0): bool
    {
        try {
            $iterator = new Column_Iterator($this, $column_id, $column_id);
            $iterator->seek($column_id);
            $column = $iterator->current();
        } catch (Exception) {
            return true;
        }
        return $column->is_empty($definition_of_empty_flags);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        foreach (get_object_vars($this) as $key => $val) {
            if ($key == 'parent') {
                continue;
            }
            if (is_object($val) || is_array($val)) {
                if ($key === 'cellCollection') {
                    $new_collection = $this->cell_collection->clone_cell_collection($this);
                    $this->cell_collection = $new_collection;
                } elseif ($key === 'drawingCollection') {
                    $current_collection = $this->drawing_collection;
                    $this->drawing_collection = new ArrayObject();
                    foreach ($current_collection as $item) {
                        $new_drawing = clone $item;
                        $new_drawing->set_worksheet($this);
                    }
                } elseif ($key === 'inCellDrawingCollection') {
                    $current_collection = $this->in_cell_drawing_collection;
                    $this->in_cell_drawing_collection = new ArrayObject();
                    foreach ($current_collection as $item) {
                        $new_drawing = clone $item;
                        $new_drawing->set_worksheet($this);
                    }
                } elseif ($key === 'tableCollection') {
                    $current_collection = $this->table_collection;
                    $this->table_collection = new ArrayObject();
                    foreach ($current_collection as $item) {
                        $new_table = clone $item;
                        $new_table->set_name($item->get_name() . 'clone');
                        $this->add_table($new_table);
                    }
                } elseif ($key === 'chartCollection') {
                    $current_collection = $this->chart_collection;
                    $this->chart_collection = new ArrayObject();
                    foreach ($current_collection as $item) {
                        $new_chart = clone $item;
                        $this->add_chart($new_chart);
                    }
                } elseif ($key === 'autoFilter') {
                    $new_auto_filter = clone $this->auto_filter;
                    $this->auto_filter = $new_auto_filter;
                    $this->auto_filter->set_parent($this);
                } else {
                    $this->{$key} = unserialize(serialize($val));
                }
            }
        }
    }
    /**
     * Define the code name of the sheet.
     *
     * @param string $codeName Same rule as Title minus space not allowed (but, like Excel, change
     *                       silently space to underscore)
     * @param bool $validate False to skip validation of new title. WARNING: This should only be set
     *                       at parse time (by Readers), where titles can be assumed to be valid.
     *
     * @return $this
     */
    public function set_code_name(string $code_name, bool $validate = true): static
    {
        // Is this a 'rename' or not?
        if ($this->get_code_name() == $code_name) {
            return $this;
        }
        if ($validate) {
            $code_name = str_replace(' ', '_', $code_name);
            //Excel does this automatically without flinching, we are doing the same
            // Syntax check
            // throw an exception if not valid
            self::check_sheet_code_name($code_name);
            // We use the same code that setTitle to find a valid codeName else not using a space (Excel don't like) but a '_'
            if ($this->parent !== null) {
                // Is there already such sheet name?
                if ($this->parent->sheet_code_name_exists($code_name)) {
                    // Use name, but append with lowest possible integer
                    if (String_Helper::count_characters($code_name) > 29) {
                        $code_name = String_Helper::substring($code_name, 0, 29);
                    }
                    $i = 1;
                    while ($this->get_parent_or_throw()->sheet_code_name_exists($code_name . '_' . $i)) {
                        ++$i;
                        if ($i == 10) {
                            if (String_Helper::count_characters($code_name) > 28) {
                                $code_name = String_Helper::substring($code_name, 0, 28);
                            }
                        } elseif ($i == 100) {
                            if (String_Helper::count_characters($code_name) > 27) {
                                $code_name = String_Helper::substring($code_name, 0, 27);
                            }
                        }
                    }
                    $code_name .= '_' . $i;
                    // ok, we have a valid name
                }
            }
        }
        $this->code_name = $code_name;
        return $this;
    }
    /**
     * Return the code name of the sheet.
     */
    public function get_code_name(): ?string
    {
        return $this->code_name;
    }
    /**
     * Sheet has a code name ?
     */
    public function has_code_name(): bool
    {
        return $this->code_name !== null;
    }
    public static function name_requires_quotes(string $sheet_name): bool
    {
        return !Preg::is_match(self::SHEET_NAME_REQUIRES_NO_QUOTES, $sheet_name);
    }
    public function is_row_visible(int $row): bool
    {
        if (!$this->row_dimension_exists($row)) {
            return true;
        }
        return $this->get_row_dimension($row)->get_visible();
    }
    /**
     * Same as Cell->isLocked, but without creating cell if it doesn't exist.
     */
    public function is_cell_locked(string $coordinate): bool
    {
        if ($this->get_protection()->getsheet() !== true) {
            return false;
        }
        if ($this->cell_exists($coordinate)) {
            return $this->get_cell($coordinate)->is_locked();
        }
        $spreadsheet = $this->parent;
        $xf_index = $this->get_xf_index($coordinate);
        if ($spreadsheet === null || $xf_index === null) {
            return true;
        }
        return $spreadsheet->get_cell_xf_by_index($xf_index)->get_protection()->get_locked() !== Style_Protection::PROTECTION_UNPROTECTED;
    }
    /**
     * Same as Cell->isHiddenOnFormulaBar, but without creating cell if it doesn't exist.
     */
    public function is_cell_hidden_on_formula_bar(string $coordinate): bool
    {
        if ($this->cell_exists($coordinate)) {
            return $this->get_cell($coordinate)->is_hidden_on_formula_bar();
        }
        // cell doesn't exist, therefore isn't a formula,
        // therefore isn't hidden on formula bar.
        return false;
    }
    private function get_xf_index(string $coordinate): ?int
    {
        [$column, $row] = Coordinate::coordinate_from_string($coordinate);
        $row = (int) $row;
        $xf_index = null;
        if ($this->row_dimension_exists($row)) {
            $xf_index = $this->get_row_dimension($row)->get_xf_index();
        }
        if ($xf_index === null && $this->column_dimension_exists($column)) {
            return $this->get_column_dimension($column)->get_xf_index();
        }
        return $xf_index;
    }
    private string $background_image = '';
    private string $background_mime = '';
    private string $background_extension = '';
    public function get_background_image(): string
    {
        return $this->background_image;
    }
    public function get_background_mime(): string
    {
        return $this->background_mime;
    }
    public function get_background_extension(): string
    {
        return $this->background_extension;
    }
    /**
     * Set background image.
     * Used on read/write for Xlsx.
     * Used on write for Html.
     *
     * @param string $backgroundImage Image represented as a string, e.g. results of file_get_contents
     */
    public function set_background_image(string $background_image): self
    {
        $image_array = getimagesizefromstring($background_image) ?: ['mime' => ''];
        $mime = $image_array['mime'];
        if ($mime !== '') {
            $extension = explode('/', $mime);
            $extension = $extension[1];
            $this->background_image = $background_image;
            $this->background_mime = $mime;
            $this->background_extension = $extension;
        }
        return $this;
    }
    /**
     * Copy cells, adjusting relative cell references in formulas.
     * Acts similarly to Excel "fill handle" feature.
     *
     * @param string $fromCell Single source cell, e.g. C3
     * @param string $toCells Single cell or cell range, e.g. C4 or C4:C10
     * @param bool $copyStyle Copy styles as well as values, defaults to true
     */
    public function copy_cells(string $from_cell, string $to_cells, bool $copy_style = true): void
    {
        $to_array = Coordinate::extract_all_cell_references_in_range($to_cells);
        $value_string = $this->get_cell($from_cell)->get_value_string();
        /** @var mixed[][] */
        $style = $this->get_style($from_cell)->export_array();
        $from_indexes = Coordinate::indexes_from_string($from_cell);
        $reference_helper = Reference_Helper::get_instance();
        foreach ($to_array as $destination) {
            if ($destination !== $from_cell) {
                $to_indexes = Coordinate::indexes_from_string($destination);
                $this->get_cell($destination)->set_value($reference_helper->update_formula_references($value_string, 'A1', $to_indexes[0] - $from_indexes[0], $to_indexes[1] - $from_indexes[1]));
                if ($copy_style) {
                    $this->get_cell($destination)->get_style()->apply_from_array($style);
                }
            }
        }
    }
    public function calculate_arrays(bool $pre_calculate_formulas = true): void
    {
        if ($pre_calculate_formulas && Calculation::get_instance($this->parent)->get_instance_array_return_type() === Calculation::RETURN_ARRAY_AS_ARRAY) {
            $keys = $this->cell_collection->get_coordinates();
            foreach ($keys as $key) {
                if ($this->get_cell($key)->get_data_type() !== Data_Type::TYPE_FORMULA) {
                    continue;
                }
                if (Preg::is_match(self::FUNCTION_LIKE_GROUPBY, $this->get_cell($key)->get_value_string())) {
                    continue;
                }
                $this->get_cell($key)->get_calculated_value();
            }
        }
    }
    public function is_cell_in_spill_range(string $coordinate): bool
    {
        if (Calculation::get_instance($this->parent)->get_instance_array_return_type() !== Calculation::RETURN_ARRAY_AS_ARRAY) {
            return false;
        }
        $this->calculate_arrays();
        $keys = $this->cell_collection->get_coordinates();
        foreach ($keys as $key) {
            $attributes = $this->get_cell($key)->get_formula_attributes();
            if (isset($attributes['ref'])) {
                if (Coordinate::coordinate_is_inside_range($attributes['ref'], $coordinate)) {
                    // false for first cell in range, true otherwise
                    return $coordinate !== $key;
                }
            }
        }
        return false;
    }
    /** @param mixed[][] $styleArray */
    public function apply_styles_from_array(string $coordinate, array $style_array): bool
    {
        $spreadsheet = $this->parent;
        if ($spreadsheet === null) {
            return false;
        }
        $active_sheet_index = $spreadsheet->get_active_sheet_index();
        $original_selected = $this->selected_cells;
        $this->get_style($coordinate)->apply_from_array($style_array);
        $this->set_selected_cells($original_selected);
        if ($active_sheet_index >= 0) {
            $spreadsheet->set_active_sheet_index($active_sheet_index);
        }
        return true;
    }
    public function copy_formula(string $from_cell, string $to_cell): void
    {
        $formula = $this->get_cell($from_cell)->get_value();
        $new_formula = $formula;
        if (is_string($formula) && $this->get_cell($from_cell)->get_data_type() === Data_Type::TYPE_FORMULA) {
            [$from_col_int, $from_row] = Coordinate::indexes_from_string($from_cell);
            [$to_col_int, $to_row] = Coordinate::indexes_from_string($to_cell);
            $helper = Reference_Helper::get_instance();
            $new_formula = $helper->update_formula_references($formula, 'A1', $to_col_int - $from_col_int, $to_row - $from_row);
        }
        $this->set_cell_value($to_cell, $new_formula);
    }
}