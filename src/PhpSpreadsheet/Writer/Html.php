<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer;

use Composer\Pcre\Preg;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception as CalculationException;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Chart\Chart;
use Php_Office\Php_Spreadsheet\Comment;
use Php_Office\Php_Spreadsheet\Document\Properties;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Rich_Text\Run;
use Php_Office\Php_Spreadsheet\Settings;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Drawing as SharedDrawing;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\Font as SharedFont;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Merged_Cell_Style;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Base_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Page_Setup;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Html extends Base_Writer
{
    private const DEFAULT_CELL_WIDTH_POINTS = 42;
    private const DEFAULT_CELL_WIDTH_PIXELS = 56;
    /**
     * Migration aid to tell if html tags will be treated as plaintext in comments.
     *     if (
     *         defined(
     *             \PhpOffice\PhpSpreadsheet\Writer\Html::class
     *             . '::COMMENT_HTML_TAGS_PLAINTEXT'
     *         )
     *     ) {
     *         new logic with styling in TextRun elements
     *     } else {
     *         old logic with styling via Html tags
     *     }.
     */
    public const COMMENT_HTML_TAGS_PLAINTEXT = true;
    private const BRX = '<br          />';
    /**
     * Sheet index to write.
     */
    private ?int $sheet_index = 0;
    /**
     * Images root.
     */
    private string $images_root = '';
    /**
     * embed images, or link to images.
     */
    protected bool $embed_images = false;
    protected string $line_ending = PHP_EOL;
    public function get_line_ending(): string
    {
        return $this->line_ending;
    }
    public function set_line_ending(string $line_ending): self
    {
        if ($line_ending != "\n" && $line_ending !== "\r\n") {
            throw new Exception('Line ending must be \n (Unix) or \r\n (Windows)');
        }
        $this->line_ending = $line_ending;
        return $this;
    }
    protected bool $data_formula = false;
    public function set_data_formula(bool $data_formula): self
    {
        $this->data_formula = $data_formula;
        return $this;
    }
    /**
     * Use inline CSS?
     */
    private bool $use_inline_css = false;
    /**
     * Array of CSS styles.
     *
     * @var string[][]
     */
    private ?array $css_styles = null;
    /**
     * Array of column widths in points.
     *
     * @var array<array<float|int>>
     */
    private array $column_widths;
    /**
     * Default font.
     */
    private readonly Font $default_font;
    /**
     * Flag whether spans have been calculated.
     */
    private bool $spans_are_calculated = false;
    /**
     * Excel cells that should not be written as HTML cells.
     *
     * @var mixed[][][][]
     */
    private array $is_spanned_cell = [];
    /**
     * Excel cells that are upper-left corner in a cell merge.
     *
     * @var int[][][][]
     */
    private array $is_base_cell = [];
    /**
     * Is the current writer creating PDF?
     */
    protected bool $is_pdf = false;
    /**
     * Generate the Navigation block.
     */
    private bool $generate_sheet_navigation_block = true;
    /**
     * Callback for editing generated html.
     *
     * @var null|callable(string): string
     */
    private $edit_html_callback;
    /** @var BaseDrawing[] */
    private $sheet_drawings;
    /** @var Chart[] */
    private $sheet_charts;
    private bool $better_boolean = true;
    private string $get_true = 'TRUE';
    private string $get_false = 'FALSE';
    protected bool $rtl_sheets = false;
    protected bool $ltr_sheets = false;
    /**
     * Table formats
     * Enables table formats in writer, disabled here, must be enabled in writer via a setter.
     */
    protected bool $table_formats = false;
    /**
     * Table formats for unstyled tables.
     * Enables default style for builtin table formats.
     * If null, it takes on the same value as $tableFormats.
     */
    protected ?bool $table_formats_builtin = null;
    /**
     * Conditional Formatting
     * Enables conditional formatting in writer, disabled here, must be enabled in writer via a setter.
     */
    protected bool $conditional_formatting = false;
    /**
     * Create a new HTML.
     */
    public function __construct(
        /**
         * Spreadsheet object.
         */
        protected Spreadsheet $spreadsheet
    )
    {
        $this->default_font = $this->spreadsheet->get_default_style()->get_font();
        $calc = Calculation::get_instance($this->spreadsheet);
        $this->get_true = $calc->get_true();
        $this->get_false = $calc->get_false();
    }
    /**
     * Save Spreadsheet to file.
     *
     * @param resource|string $filename
     */
    public function save($filename, int $flags = 0): void
    {
        $this->process_flags($flags);
        // Open file
        $this->open_file_handle($filename);
        // Write html
        fwrite($this->file_handle, $this->generate_html_all());
        // Close file
        $this->maybe_close_file_handle();
    }
    protected function check_rtl_and_ltr(): void
    {
        $this->rtl_sheets = false;
        $this->ltr_sheets = false;
        if ($this->sheet_index === null) {
            foreach ($this->spreadsheet->get_all_sheets() as $sheet) {
                if ($sheet->get_right_to_left()) {
                    $this->rtl_sheets = true;
                } else {
                    $this->ltr_sheets = true;
                }
            }
        } else if ($this->spreadsheet->get_sheet($this->sheet_index)->get_right_to_left()) {
            $this->rtl_sheets = true;
        }
    }
    /**
     * Save Spreadsheet as html to variable.
     */
    public function generate_html_all(): string
    {
        $this->check_rtl_and_ltr();
        $sheets = $this->generate_sheet_prep();
        foreach ($sheets as $sheet) {
            $sheet->calculate_arrays($this->pre_calculate_formulas);
        }
        // garbage collect
        $this->spreadsheet->garbage_collect();
        $save_debug_log = Calculation::get_instance($this->spreadsheet)->get_debug_log()->get_write_debug_log();
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log(false);
        // Build CSS
        $this->build_css(!$this->use_inline_css);
        $html = '';
        // Write headers
        $html .= $this->generate_html_header(!$this->use_inline_css);
        // Write navigation (tabs)
        if (!$this->is_pdf && $this->generate_sheet_navigation_block) {
            $html .= $this->generate_navigation();
        }
        // Write data
        $html .= $this->generate_sheet_data();
        // Write footer
        $html .= $this->generate_html_footer();
        if ($this instanceof Pdf\Mpdf) {
            $html = str_replace(self::BRX, '<br />', $html);
        } else {
            $html = str_replace(self::BRX, '<br />' . $this->line_ending, $html);
        }
        $callback = $this->edit_html_callback;
        if ($callback) {
            $html = $callback($html);
        }
        Calculation::get_instance($this->spreadsheet)->get_debug_log()->set_write_debug_log($save_debug_log);
        return $html;
    }
    /**
     * Set a callback to edit the entire HTML.
     *
     * The callback must accept the HTML as string as first parameter,
     * and it must return the edited HTML as string.
     */
    public function set_edit_html_callback(?callable $callback): void
    {
        $this->edit_html_callback = $callback;
    }
    /**
     * Map VAlign.
     *
     * @param string $vAlign Vertical alignment
     */
    private function map_v_align(string $v_align): string
    {
        return Alignment::VERTICAL_ALIGNMENT_FOR_HTML[$v_align] ?? '';
    }
    /**
     * Map HAlign.
     *
     * @param string $hAlign Horizontal alignment
     */
    private function map_h_align(string $h_align): string
    {
        return Alignment::HORIZONTAL_ALIGNMENT_FOR_HTML[$h_align] ?? '';
    }
    public const BORDER_NONE = 'none';
    public const BORDER_ARR = [Border::BORDER_NONE => self::BORDER_NONE, Border::BORDER_DASHDOT => '1px dashed', Border::BORDER_DASHDOTDOT => '1px dotted', Border::BORDER_DASHED => '1px dashed', Border::BORDER_DOTTED => '1px dotted', Border::BORDER_DOUBLE => '3px double', Border::BORDER_HAIR => '1px solid', Border::BORDER_MEDIUM => '2px solid', Border::BORDER_MEDIUMDASHDOT => '2px dashed', Border::BORDER_MEDIUMDASHDOTDOT => '2px dotted', Border::BORDER_SLANTDASHDOT => '2px dashed', Border::BORDER_THICK => '3px solid'];
    /**
     * Map border style.
     *
     * @param int|string $borderStyle Sheet index
     */
    private function map_border_style(string $border_style): string
    {
        return self::BORDER_ARR[$border_style] ?? '1px solid';
    }
    /**
     * Get sheet index.
     */
    public function get_sheet_index(): ?int
    {
        return $this->sheet_index;
    }
    /**
     * Set sheet index.
     *
     * @param int $sheetIndex Sheet index
     *
     * @return $this
     */
    public function set_sheet_index(int $sheet_index): static
    {
        $this->sheet_index = $sheet_index;
        return $this;
    }
    /**
     * Get sheet index.
     */
    public function get_generate_sheet_navigation_block(): bool
    {
        return $this->generate_sheet_navigation_block;
    }
    /**
     * Set sheet index.
     *
     * @param bool $generateSheetNavigationBlock Flag indicating whether the sheet navigation block should be generated or not
     *
     * @return $this
     */
    public function set_generate_sheet_navigation_block(bool $generate_sheet_navigation_block): static
    {
        $this->generate_sheet_navigation_block = $generate_sheet_navigation_block;
        return $this;
    }
    /**
     * Write all sheets (resets sheetIndex to NULL).
     *
     * @return $this
     */
    public function write_all_sheets(): static
    {
        $this->sheet_index = null;
        return $this;
    }
    private function generate_meta(?string $val, string $desc): string
    {
        return $val || $val === '0' ? '      <meta name="' . $desc . '" content="' . htmlspecialchars($val, Settings::html_entity_flags()) . '" />' . $this->line_ending : '';
    }
    /** @deprecated 5.4.0 No replacement. */
    public const BODY_LINE = '  <body>' . PHP_EOL;
    private const CUSTOM_TO_META = [Properties::PROPERTY_TYPE_BOOLEAN => 'bool', Properties::PROPERTY_TYPE_DATE => 'date', Properties::PROPERTY_TYPE_FLOAT => 'float', Properties::PROPERTY_TYPE_INTEGER => 'int', Properties::PROPERTY_TYPE_STRING => 'string'];
    /**
     * Generate HTML header.
     *
     * @param bool $includeStyles Include styles?
     */
    public function generate_html_header(bool $include_styles = false): string
    {
        // Construct HTML
        $properties = $this->spreadsheet->get_properties();
        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . $this->line_ending;
        $rtl = $this->rtl_sheets && !$this->ltr_sheets ? " dir='rtl'" : '';
        $html .= '<html xmlns="http://www.w3.org/1999/xhtml"' . $rtl . '>' . $this->line_ending;
        $html .= '  <head>' . $this->line_ending;
        $html .= '      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . $this->line_ending;
        $html .= '      <meta name="generator" content="PhpSpreadsheet, https://github.com/PHPOffice/PhpSpreadsheet" />' . $this->line_ending;
        $title = $properties->get_title();
        if ($title === '') {
            $title = $this->spreadsheet->get_active_sheet()->get_title();
        }
        $html .= '      <title>' . htmlspecialchars($title, Settings::html_entity_flags()) . '</title>' . $this->line_ending;
        $html .= $this->generate_meta($properties->get_creator(), 'author');
        $html .= $this->generate_meta($properties->get_title(), 'title');
        $html .= $this->generate_meta($properties->get_description(), 'description');
        $html .= $this->generate_meta($properties->get_subject(), 'subject');
        $html .= $this->generate_meta($properties->get_keywords(), 'keywords');
        $html .= $this->generate_meta($properties->get_category(), 'category');
        $html .= $this->generate_meta($properties->get_company(), 'company');
        $html .= $this->generate_meta($properties->get_manager(), 'manager');
        $html .= $this->generate_meta($properties->get_last_modified_by(), 'lastModifiedBy');
        $html .= $this->generate_meta($properties->get_viewport(), 'viewport');
        $date = Date::date_time_from_timestamp((string) $properties->get_created());
        $date->set_time_zone(Date::get_default_or_local_time_zone());
        $html .= $this->generate_meta($date->format(DATE_W3C), 'created');
        $date = Date::date_time_from_timestamp((string) $properties->get_modified());
        $date->set_time_zone(Date::get_default_or_local_time_zone());
        $html .= $this->generate_meta($date->format(DATE_W3C), 'modified');
        $custom_properties = $properties->get_custom_properties();
        foreach ($custom_properties as $custom_property) {
            $property_value = $properties->get_custom_property_value($custom_property);
            $property_type = $properties->get_custom_property_type($custom_property);
            $property_qualifier = self::CUSTOM_TO_META[$property_type] ?? null;
            if ($property_qualifier !== null) {
                if ($property_type === Properties::PROPERTY_TYPE_BOOLEAN) {
                    $property_value = $property_value ? '1' : '0';
                } elseif ($property_type === Properties::PROPERTY_TYPE_DATE) {
                    $date = Date::date_time_from_timestamp((string) $property_value);
                    $date->set_time_zone(Date::get_default_or_local_time_zone());
                    $property_value = $date->format(DATE_W3C);
                } else {
                    $property_value = (string) $property_value;
                }
                $html .= $this->generate_meta($property_value, htmlspecialchars("custom.{$property_qualifier}.{$custom_property}"));
            }
        }
        if (!empty($properties->get_hyperlink_base())) {
            $hyperlink_base = $properties->get_hyperlink_base();
            if (Preg::is_match('/^https?:\/\//i', $hyperlink_base)) {
                $html .= '      <base href="' . htmlspecialchars($hyperlink_base) . '" />' . $this->line_ending;
            }
        }
        $html .= $include_styles ? $this->generate_styles(true) : $this->generate_page_declarations(true);
        $html .= '  </head>' . $this->line_ending;
        $html .= '' . $this->line_ending;
        return $html . ('  <body>' . $this->line_ending);
    }
    /** @return Worksheet[] */
    private function generate_sheet_prep(): array
    {
        // Fetch sheets
        if ($this->sheet_index === null) {
            return $this->spreadsheet->get_all_sheets();
        }
        return [$this->spreadsheet->get_sheet($this->sheet_index)];
    }
    /** @return array{int, int, int} */
    private function generate_sheet_starts(Worksheet $sheet, int $row_min): array
    {
        // calculate start of <tbody>, <thead>
        $tbody_start = $row_min;
        $thead_start = $thead_end = 0;
        // default: no <thead>    no </thead>
        if ($sheet->get_page_setup()->is_rows_to_repeat_at_top_set()) {
            $rows_to_repeat_at_top = $sheet->get_page_setup()->get_rows_to_repeat_at_top();
            // we can only support repeating rows that start at top row
            if ($rows_to_repeat_at_top[0] == 1) {
                $thead_start = $rows_to_repeat_at_top[0];
                $thead_end = $rows_to_repeat_at_top[1];
                $tbody_start = $rows_to_repeat_at_top[1] + 1;
            }
        }
        return [$thead_start, $thead_end, $tbody_start];
    }
    /** @return array{string, string, string} */
    private function generate_sheet_tags(int $row, int $thead_start, int $thead_end, int $tbody_start): array
    {
        // <thead> ?
        $start_tag = $row == $thead_start ? '        <thead>' . $this->line_ending : '';
        if (!$start_tag) {
            $start_tag = $row == $tbody_start ? '        <tbody>' . $this->line_ending : '';
        }
        $end_tag = $row == $thead_end ? '        </thead>' . $this->line_ending : '';
        $cell_type = $row >= $tbody_start ? 'td' : 'th';
        return [$cell_type, $start_tag, $end_tag];
    }
    private int $print_area_low_row = -1;
    private int $print_area_high_row = -1;
    private int $print_area_low_col = -1;
    private int $print_area_high_col = -1;
    /**
     * Generate sheet data.
     */
    public function generate_sheet_data(): string
    {
        // Ensure that Spans have been calculated?
        $this->calculate_spans();
        $sheets = $this->generate_sheet_prep();
        // Construct HTML
        $html = '';
        // Loop all sheets
        $sheet_id = 0;
        $active_sheet = $this->spreadsheet->get_active_sheet_index();
        foreach ($sheets as $sheet) {
            $this->print_area_low_row = -1;
            $this->print_area_high_row = -1;
            $this->print_area_low_col = -1;
            $this->print_area_high_col = -1;
            $print_area = $sheet->get_page_setup()->get_print_area();
            if (Preg::is_match('/^([a-z]+)([0-9]+):([a-z]+)([0-9]+)$/i', $print_area, $matches)) {
                $this->print_area_low_col = Coordinate::column_index_from_string($matches[1]);
                $this->print_area_high_col = Coordinate::column_index_from_string($matches[3]);
                $this->print_area_low_row = (int) $matches[2];
                $this->print_area_high_row = (int) $matches[4];
            }
            // save active cells
            $selected_cells = $sheet->get_selected_cells();
            // Write table header
            $html .= $this->generate_table_header($sheet);
            $this->sheet_charts = [];
            $this->sheet_drawings = [];
            $cond_styles_collection = $sheet->get_conditional_styles_collection();
            foreach ($cond_styles_collection as $cond_styles) {
                foreach ($cond_styles as $cs) {
                    if ($cs->get_condition_type() === Conditional::CONDITION_COLORSCALE) {
                        $cs->get_color_scale()?->set_scale_array();
                    }
                }
            }
            // Get worksheet dimension
            [$min, $max] = explode(':', $sheet->calculate_worksheet_data_dimension());
            [$min_col, $min_row, $min_col_string] = Coordinate::indexes_from_string($min);
            [$max_col, $max_row] = Coordinate::indexes_from_string($max);
            $this->extend_rows_and_columns($sheet, $max_col, $max_row);
            $this->extend_rows_and_columns_for_merge($sheet, $max_col, $max_row);
            [$thead_start, $thead_end, $tbody_start] = $this->generate_sheet_starts($sheet, $min_row);
            // Loop through cells
            $row = $min_row - 1;
            while ($row++ < $max_row) {
                [$cell_type, $start_tag, $end_tag] = $this->generate_sheet_tags($row, $thead_start, $thead_end, $tbody_start);
                $html .= String_Helper::convert_to_string($start_tag);
                // Write row if there are HTML table cells in it
                if ($this->should_generate_row($sheet, $row)) {
                    // Start a new rowData
                    $row_data = [];
                    // Loop through columns
                    $column = $min_col;
                    $col_str = $min_col_string;
                    while ($column <= $max_col) {
                        // Cell exists?
                        $cell_address = Coordinate::string_from_column_index($column) . $row;
                        if ($this->should_generate_column($sheet, $col_str)) {
                            $row_data[$column] = $sheet->get_cell_collection()->has($cell_address) ? $cell_address : '';
                        }
                        ++$column;
                        /** @var string $colStr */
                        String_Helper::string_increment($col_str);
                    }
                    $html .= $this->generate_row($sheet, $row_data, $row - 1, $cell_type);
                }
                $html .= String_Helper::convert_to_string($end_tag);
            }
            // Write table footer
            $html .= $this->generate_table_footer();
            // Writing PDF?
            if ($this instanceof Pdf\Tcpdf && $this->use_inline_css) {
                if ($this->sheet_index === null && $sheet_id + 1 < $this->spreadsheet->get_sheet_count()) {
                    $html .= '<div style="page-break-before:always" ></div>';
                }
            }
            // Next sheet
            ++$sheet_id;
            $sheet->set_selected_cells($selected_cells);
        }
        $this->spreadsheet->set_active_sheet_index($active_sheet);
        return $html;
    }
    /**
     * Generate sheet tabs.
     */
    public function generate_navigation(): string
    {
        // Fetch sheets
        $sheets = [];
        if ($this->sheet_index === null) {
            $sheets = $this->spreadsheet->get_all_sheets();
        } else {
            $sheets[] = $this->spreadsheet->get_sheet($this->sheet_index);
        }
        // Construct HTML
        $html = '';
        // Only if there are more than 1 sheets
        if (count($sheets) > 1) {
            // Loop all sheets
            $sheet_id = 0;
            $html .= '<ul class="navigation">' . $this->line_ending;
            foreach ($sheets as $sheet) {
                $html .= '  <li class="sheet' . $sheet_id . '"><a href="#sheet' . $sheet_id . '">' . htmlspecialchars($sheet->get_title()) . '</a></li>' . $this->line_ending;
                ++$sheet_id;
            }
            $html .= '</ul>' . $this->line_ending;
        }
        return $html;
    }
    private function extend_rows_and_columns(Worksheet $worksheet, int &$col_max, int &$row_max): void
    {
        if ($this->include_charts) {
            foreach ($worksheet->get_chart_collection() as $chart) {
                $chart_coordinates = $chart->get_top_left_position();
                $this->sheet_charts[$chart_coordinates['cell']] = $chart;
                $chart_tl = Coordinate::indexes_from_string($chart_coordinates['cell']);
                if ($chart_tl[1] > $row_max) {
                    $row_max = $chart_tl[1];
                }
                if ($chart_tl[0] > $col_max) {
                    $col_max = $chart_tl[0];
                }
            }
        }
        foreach ($worksheet->get_drawing_collection() as $drawing) {
            if ($drawing instanceof Drawing && $drawing->get_path() === '') {
                continue;
            }
            $image_tl = Coordinate::indexes_from_string($drawing->get_coordinates());
            $this->sheet_drawings[$drawing->get_coordinates()] = $drawing;
            if ($image_tl[1] > $row_max) {
                $row_max = $image_tl[1];
            }
            if ($image_tl[0] > $col_max) {
                $col_max = $image_tl[0];
            }
        }
    }
    /**
     * Convert Windows file name to file protocol URL.
     *
     * @param string $filename file name on local system
     */
    public static function win_file_to_url(string $filename, bool $mpdf = false): string
    {
        // Windows filename
        if (substr($filename, 1, 2) === ':\\') {
            $protocol = $mpdf ? '' : 'file:///';
            $filename = $protocol . str_replace('\\', '/', $filename);
        }
        return $filename;
    }
    /**
     * Generate image tag in cell.
     *
     * @param string $coordinates Cell coordinates
     */
    private function write_image_in_cell(string $coordinates): string
    {
        // Construct HTML
        $html = '';
        // Write images
        $drawing = $this->sheet_drawings[$coordinates] ?? null;
        if ($drawing !== null) {
            $opacity = '';
            $opacity_value = $drawing->get_opacity();
            if ($opacity_value !== null) {
                $opacity_value = $opacity_value / 100000;
                if ($opacity_value >= 0.0 && $opacity_value <= 1.0) {
                    $opacity = "opacity:{$opacity_value}; ";
                }
            }
            $filedesc = $drawing->get_description();
            $filedesc = $filedesc ? htmlspecialchars($filedesc, ENT_QUOTES) : 'Embedded image';
            if ($drawing instanceof Drawing && $drawing->get_path() !== '') {
                $filename = $drawing->get_path();
                // Strip off eventual '.'
                $filename = Preg::replace('/^[.]/', '', $filename);
                // Prepend images root
                $filename = $this->get_images_root() . $filename;
                // Strip off eventual '.' if followed by non-/
                $filename = Preg::replace('@^[.]([^/])@', '$1', $filename);
                // Convert UTF8 data to PCDATA
                $filename = htmlspecialchars($filename, Settings::html_entity_flags());
                $html .= $this->line_ending;
                $image_data = self::win_file_to_url($filename, $this instanceof Pdf\Mpdf);
                if ($this->embed_images || str_starts_with($image_data, 'zip://')) {
                    $image_data = 'data:,';
                    $picture = @file_get_contents($filename);
                    if ($picture !== false) {
                        $mime_content_type = (string) @mime_content_type($filename);
                        if (str_starts_with($mime_content_type, 'image/')) {
                            // base64 encode the binary data
                            $base64 = base64_encode($picture);
                            $image_data = 'data:' . $mime_content_type . ';base64,' . $base64;
                        }
                    }
                }
                $html .= '<img style="' . $opacity . 'position: absolute; z-index: 1; left: ' . $drawing->get_offset_x() . 'px; top: ' . $drawing->get_offset_y() . 'px; width: ' . $drawing->get_width() . 'px; height: ' . $drawing->get_height() . 'px;" src="' . $image_data . '" alt="' . $filedesc . '" />';
            } elseif ($drawing instanceof Memory_Drawing) {
                $image_resource = $drawing->get_image_resource();
                if ($image_resource) {
                    ob_start();
                    //  Let's start output buffering.
                    imagepng($image_resource);
                    //  This will normally output the image, but because of ob_start(), it won't.
                    $contents = (string) ob_get_contents();
                    //  Instead, output above is saved to $contents
                    ob_end_clean();
                    //  End the output buffer.
                    $data_uri = 'data:image/png;base64,' . base64_encode($contents);
                    //  Because of the nature of tables, width is more important than height.
                    //  max-width: 100% ensures that image doesn't overflow containing cell
                    //    However, PR #3535 broke test
                    //    25_In_memory_image, apparently because
                    //    of the use of max-with. In addition,
                    //    non-memory-drawings don't use max-width.
                    //    Its use here is suspect and is being eliminated.
                    //  width: X sets width of supplied image.
                    //  As a result, images bigger than cell will be contained and images smaller will not get stretched
                    $html .= '<img alt="' . $filedesc . '" src="' . $data_uri . '" style="' . $opacity . 'width:' . $drawing->get_width() . 'px;left: ' . $drawing->get_offset_x() . 'px; top: ' . $drawing->get_offset_y() . 'px;position: absolute; z-index: 1;" />';
                }
            }
        }
        return $html;
    }
    /**
     * Generate chart tag in cell.
     * This code should be exercised by sample:
     * Chart/32_Chart_read_write_PDF.php.
     */
    private function write_chart_in_cell(Worksheet $worksheet, string $coordinates): string
    {
        // Construct HTML
        $html = '';
        // Write charts
        $chart = $this->sheet_charts[$coordinates] ?? null;
        if ($chart !== null) {
            $chart_coordinates = $chart->get_top_left_position();
            $chart_file_name = File::sys_get_temp_dir() . '/' . uniqid('', true) . '.png';
            $rendered_width = $chart->get_rendered_width();
            $rendered_height = $chart->get_rendered_height();
            if ($rendered_width === null || $rendered_height === null) {
                $this->adjust_renderer_positions($chart, $worksheet);
            }
            $title = $chart->get_title();
            $caption = null;
            $filedesc = '';
            if ($title !== null) {
                $calculated_title = $title->get_calculated_title($worksheet->get_parent());
                if ($calculated_title !== null) {
                    $caption = $title->get_caption();
                    $title->set_caption($calculated_title);
                }
                $filedesc = $title->get_caption_text($worksheet->get_parent());
            }
            $render_successful = $chart->render($chart_file_name);
            $chart->set_rendered_width($rendered_width);
            $chart->set_rendered_height($rendered_height);
            if (isset($title, $caption)) {
                $title->set_caption($caption);
            }
            if (!$render_successful) {
                return '';
            }
            $html .= $this->line_ending;
            $image_details = getimagesize($chart_file_name) ?: ['', '', 'mime' => ''];
            $filedesc = $filedesc ? htmlspecialchars($filedesc, ENT_QUOTES) : 'Embedded chart';
            $picture = file_get_contents($chart_file_name);
            unlink($chart_file_name);
            if ($picture !== false) {
                $base64 = base64_encode($picture);
                $image_data = 'data:' . $image_details['mime'] . ';base64,' . $base64;
                $html .= '<img style="position: absolute; z-index: 1; left: ' . $chart_coordinates['xOffset'] . 'px; top: ' . $chart_coordinates['yOffset'] . 'px; width: ' . $image_details[0] . 'px; height: ' . $image_details[1] . 'px;" src="' . $image_data . '" alt="' . $filedesc . '" />' . $this->line_ending;
            }
        }
        // Return
        return $html;
    }
    private function adjust_renderer_positions(Chart $chart, Worksheet $sheet): void
    {
        $top_left = $chart->get_top_left_position();
        $bottom_right = $chart->get_bottom_right_position();
        $tl_cell = $top_left['cell'];
        /** @var string */
        $br_cell = $bottom_right['cell'];
        if ($tl_cell !== '' && $br_cell !== '') {
            $tl_coordinate = Coordinate::indexes_from_string($tl_cell);
            $br_coordinate = Coordinate::indexes_from_string($br_cell);
            $total_height = 0.0;
            $total_width = 0.0;
            $default_row_height = $sheet->get_default_row_dimension()->get_row_height();
            $default_row_height = Shared_Drawing::points_to_pixels($default_row_height >= 0 ? $default_row_height : Shared_Font::get_default_row_height_by_font($this->default_font));
            if ($tl_coordinate[1] <= $br_coordinate[1] && $tl_coordinate[0] <= $br_coordinate[0]) {
                for ($row = $tl_coordinate[1]; $row <= $br_coordinate[1]; ++$row) {
                    $height = $sheet->get_row_dimension($row)->get_row_height('pt');
                    $total_height += $height >= 0 ? $height : $default_row_height;
                }
                $right_edge = $br_coordinate[2];
                String_Helper::string_increment($right_edge);
                for ($column = $tl_coordinate[2]; $column !== $right_edge;) {
                    $width = $sheet->get_column_dimension($column)->get_width();
                    $width = $width < 0 ? self::DEFAULT_CELL_WIDTH_PIXELS : Shared_Drawing::cell_dimension_to_pixels($sheet->get_column_dimension($column)->get_width(), $this->default_font);
                    $total_width += $width;
                    String_Helper::string_increment($column);
                }
                $chart->set_rendered_width($total_width);
                $chart->set_rendered_height($total_height);
            }
        }
    }
    /**
     * Generate CSS styles.
     *
     * @param bool $generateSurroundingHTML Generate surrounding HTML tags? (&lt;style&gt; and &lt;/style&gt;)
     */
    public function generate_styles(bool $generate_surrounding_html = true): string
    {
        // Build CSS
        $css = $this->build_css($generate_surrounding_html);
        // Construct HTML
        $html = '';
        // Start styles
        if ($generate_surrounding_html) {
            $html .= '    <style type="text/css">' . $this->line_ending;
            $html .= array_key_exists('html', $css) ? '      html { ' . $this->assemble_css($css['html']) . ' }' . $this->line_ending : '';
        }
        // Write all other styles
        foreach ($css as $style_name => $style_definition) {
            if ($style_name != 'html') {
                $html .= '      ' . $style_name . ' { ' . $this->assemble_css($style_definition) . ' }' . $this->line_ending;
            }
        }
        $html .= $this->generate_page_declarations(false);
        // End styles
        if ($generate_surrounding_html) {
            $html .= '    </style>' . $this->line_ending;
        }
        // Return
        return $html;
    }
    /** @param string[][] $css */
    private function build_css_row_heights(Worksheet $sheet, array &$css, int $sheet_index): void
    {
        // Calculate row heights
        foreach ($sheet->get_row_dimensions() as $row_dimension) {
            $row = $row_dimension->get_row_index() - 1;
            // table.sheetN tr.rowYYYYYY { }
            $css['table.sheet' . $sheet_index . ' tr.row' . $row] = [];
            if ($row_dimension->get_row_height() != -1) {
                $pt_height = $row_dimension->get_row_height();
                $css['table.sheet' . $sheet_index . ' tr.row' . $row]['height'] = $pt_height . 'pt';
            }
            if ($row_dimension->get_visible() === false) {
                $css['table.sheet' . $sheet_index . ' tr.row' . $row]['display'] = 'none';
                $css['table.sheet' . $sheet_index . ' tr.row' . $row]['visibility'] = 'hidden';
            }
        }
    }
    /** @param string[][] $css */
    private function build_css_per_sheet(Worksheet $sheet, array &$css): void
    {
        // Calculate hash code
        $sheet_index = $sheet->get_parent_or_throw()->get_index($sheet);
        $setup = $sheet->get_page_setup();
        if ($setup->get_fit_to_page() && $setup->get_fit_to_height() === 1) {
            $css["table.sheet{$sheet_index}"]['page-break-inside'] = 'avoid';
            $css["table.sheet{$sheet_index}"]['break-inside'] = 'avoid';
        }
        $picture = $sheet->get_background_image();
        if ($picture !== '') {
            $base64 = base64_encode($picture);
            $css["table.sheet{$sheet_index}"]['background-image'] = 'url(data:' . $sheet->get_background_mime() . ';base64,' . $base64 . ')';
        }
        // Build styles
        // Calculate column widths
        $sheet->calculate_column_widths();
        // col elements, initialize
        $highest_column_index = Coordinate::column_index_from_string($sheet->get_highest_column()) - 1;
        $column = -1;
        $col_str = 'A';
        while ($column++ < $highest_column_index) {
            $this->column_widths[$sheet_index][$column] = self::DEFAULT_CELL_WIDTH_POINTS;
            // approximation
            if ($this->should_generate_column($sheet, $col_str)) {
                $css['table.sheet' . $sheet_index . ' col.col' . $column]['width'] = self::DEFAULT_CELL_WIDTH_POINTS . 'pt';
            }
            String_Helper::string_increment($col_str);
        }
        // col elements, loop through columnDimensions and set width
        foreach ($sheet->get_column_dimensions() as $column_dimension) {
            $column = Coordinate::column_index_from_string($column_dimension->get_column_index()) - 1;
            $width = Shared_Drawing::cell_dimension_to_pixels($column_dimension->get_width(), $this->default_font);
            $width = Shared_Drawing::pixels_to_points($width);
            if ($column_dimension->get_visible() === false) {
                $css['table.sheet' . $sheet_index . ' .column' . $column]['display'] = 'none';
                // This would be better but Firefox has an 11-year-old bug.
                // https://bugzilla.mozilla.org/show_bug.cgi?id=819045
                //$css['table.sheet' . $sheetIndex . ' col.col' . $column]['visibility'] = 'collapse';
            }
            if ($width >= 0) {
                $this->column_widths[$sheet_index][$column] = $width;
                $css['table.sheet' . $sheet_index . ' col.col' . $column]['width'] = $width . 'pt';
            }
        }
        // Default row height
        $row_dimension = $sheet->get_default_row_dimension();
        // table.sheetN tr { }
        $css['table.sheet' . $sheet_index . ' tr'] = [];
        if ($row_dimension->get_row_height() == -1) {
            $pt_height = Shared_Font::get_default_row_height_by_font($this->spreadsheet->get_default_style()->get_font());
        } else {
            $pt_height = $row_dimension->get_row_height();
        }
        $css['table.sheet' . $sheet_index . ' tr']['height'] = $pt_height . 'pt';
        if ($row_dimension->get_visible() === false) {
            $css['table.sheet' . $sheet_index . ' tr']['display'] = 'none';
            $css['table.sheet' . $sheet_index . ' tr']['visibility'] = 'hidden';
        }
        $this->build_css_row_heights($sheet, $css, $sheet_index);
    }
    /**
     * Build CSS styles.
     *
     * @param bool $generateSurroundingHTML Generate surrounding HTML style? (html { })
     *
     * @return string[][]
     */
    public function build_css(bool $generate_surrounding_html = true): array
    {
        // Cached?
        if ($this->css_styles !== null) {
            return $this->css_styles;
        }
        // Ensure that spans have been calculated
        $this->calculate_spans();
        // Construct CSS
        /** @var string[][] */
        $css = [];
        // Start styles
        if ($generate_surrounding_html) {
            // html { }
            $css['html']['font-family'] = 'Calibri, Arial, Helvetica, sans-serif';
            $css['html']['font-size'] = '11pt';
            $css['html']['background-color'] = 'white';
        }
        // CSS for comments as found in LibreOffice
        $css['a.comment-indicator:hover + div.comment'] = ['background' => '#ffd', 'position' => 'absolute', 'display' => 'block', 'border' => '1px solid black', 'padding' => '0.5em'];
        $css['a.comment-indicator'] = ['background' => 'red', 'display' => 'inline-block', 'border' => '1px solid black', 'width' => '0.5em', 'height' => '0.5em'];
        $css['div.comment']['display'] = 'none';
        // table { }
        $css['table']['border-collapse'] = 'collapse';
        // .b {}
        $css['.b']['text-align'] = 'center';
        // BOOL
        // .e {}
        $css['.e']['text-align'] = 'center';
        // ERROR
        // .f {}
        $css['.f']['text-align'] = 'right';
        // FORMULA
        // .inlineStr {}
        $css['.inlineStr']['text-align'] = 'left';
        // INLINE
        // .n {}
        $css['.n']['text-align'] = 'right';
        // NUMERIC
        // .s {}
        $css['.s']['text-align'] = 'left';
        // STRING
        $css['.floatright']['float'] = 'right';
        $css['.floatleft']['float'] = 'left';
        // Calculate cell style hashes
        foreach ($this->spreadsheet->get_cell_xf_collection() as $index => $style) {
            $css['td.style' . $index . ', th.style' . $index] = $this->create_css_style($style);
            //$css['th.style' . $index] = $this->createCSSStyle($style);
        }
        // Fetch sheets
        $sheets = [];
        if ($this->sheet_index === null) {
            $sheets = $this->spreadsheet->get_all_sheets();
        } else {
            $sheets[] = $this->spreadsheet->get_sheet($this->sheet_index);
        }
        // Build styles per sheet
        foreach ($sheets as $sheet) {
            $this->build_css_per_sheet($sheet, $css);
        }
        // Cache
        if ($this->css_styles === null) {
            $this->css_styles = $css;
        }
        // Return
        return $css;
    }
    /**
     * Create CSS style.
     *
     * @return string[]
     */
    private function create_css_style(Style $style, bool $conditional = false): array
    {
        // Create CSS
        return array_merge($conditional ? [] : $this->create_css_style_alignment($style->get_alignment()), $this->create_css_style_borders($style->get_borders()), $this->create_css_style_font($style->get_font(), conditional: $conditional), $this->create_css_style_fill($style->get_fill()));
    }
    /**
     * Create CSS style.
     *
     * @return string[]
     */
    private function create_css_style_alignment(Alignment $alignment): array
    {
        // Construct CSS
        $css = [];
        // Create CSS
        $vertical_align = $this->map_v_align($alignment->get_vertical() ?? '');
        if ($vertical_align) {
            $css['vertical-align'] = $vertical_align;
        }
        $text_align = $this->map_h_align($alignment->get_horizontal() ?? '');
        if ($text_align) {
            $css['text-align'] = $text_align;
            if (in_array($text_align, ['left', 'right'])) {
                $css['padding-' . $text_align] = $alignment->get_indent() * Alignment::INDENT_UNITS_TO_PIXELS . 'px';
            }
        } else {
            $indent = $alignment->get_indent();
            if ($indent !== 0) {
                $css['text-indent'] = $alignment->get_indent() * Alignment::INDENT_UNITS_TO_PIXELS . 'px';
            }
        }
        $rotation = $alignment->get_text_rotation();
        if ($rotation !== 0 && $rotation !== Alignment::TEXTROTATION_STACK_PHPSPREADSHEET) {
            if ($this instanceof Pdf\Mpdf) {
                $css['text-rotate'] = "{$rotation}";
            } else {
                $css['transform'] = "rotate({$rotation}deg)";
            }
        }
        $direction = $alignment->get_read_order();
        if ($direction === Alignment::READORDER_LTR) {
            $css['direction'] = 'ltr';
        } elseif ($direction === Alignment::READORDER_RTL) {
            $css['direction'] = 'rtl';
        }
        return $css;
    }
    /**
     * Create CSS style.
     *
     * @return string[]
     */
    private function create_css_style_font(Font $font, bool $use_defaults = false, bool $conditional = false): array
    {
        // Construct CSS
        $css = [];
        // Create CSS
        if ($font->get_bold()) {
            $css['font-weight'] = 'bold';
        } elseif ($use_defaults) {
            $css['font-weight'] = 'normal';
        }
        if ($font->get_underline() != Font::UNDERLINE_NONE && $font->get_strikethrough()) {
            $css['text-decoration'] = 'underline line-through';
        } elseif ($font->get_underline() != Font::UNDERLINE_NONE) {
            $css['text-decoration'] = 'underline';
        } elseif ($font->get_strikethrough()) {
            $css['text-decoration'] = 'line-through';
        } elseif ($use_defaults) {
            $css['text-decoration'] = 'normal';
        }
        if ($font->get_italic()) {
            $css['font-style'] = 'italic';
        } elseif ($use_defaults) {
            $css['font-style'] = 'normal';
        }
        $css['color'] = '#' . $font->get_color()->get_rgb();
        if (!$conditional) {
            $css['font-family'] = '\'' . htmlspecialchars((string) $font->get_name(), ENT_QUOTES) . '\'';
            $css['font-size'] = $font->get_size() . 'pt';
        }
        return $css;
    }
    /**
     * @param string[] $css
     */
    private function style_border(array &$css, string $index, Border $border): void
    {
        $border_style = $border->get_border_style();
        // Mpdf doesn't process !important, so omit unimportant border none
        if ($border_style === Border::BORDER_NONE && $this instanceof Pdf\Mpdf) {
            return;
        }
        if ($border_style !== Border::BORDER_OMIT) {
            $css[$index] = $this->create_css_style_border($border);
        }
    }
    /**
     * Create CSS style.
     *
     * @param Borders $borders Borders
     *
     * @return string[]
     */
    private function create_css_style_borders(Borders $borders): array
    {
        // Construct CSS
        $css = [];
        // Create CSS
        $this->style_border($css, 'border-bottom', $borders->get_bottom());
        $this->style_border($css, 'border-top', $borders->get_top());
        $this->style_border($css, 'border-left', $borders->get_left());
        $this->style_border($css, 'border-right', $borders->get_right());
        return $css;
    }
    /**
     * Create CSS style.
     *
     * @param Border $border Border
     */
    private function create_css_style_border(Border $border): string
    {
        //    Create CSS - add !important to non-none border styles for merged cells
        $border_style = $this->map_border_style($border->get_border_style());
        return $border_style . ' #' . $border->get_color()->get_rgb() . ($border_style === self::BORDER_NONE ? '' : ' !important');
    }
    /**
     * Create CSS style (Fill).
     *
     * @param Fill $fill Fill
     *
     * @return string[]
     */
    private function create_css_style_fill(Fill $fill): array
    {
        // Construct HTML
        $css = [];
        // Create CSS
        if ($fill->get_fill_type() !== Fill::FILL_NONE) {
            if ((in_array($fill->get_fill_type(), ['', Fill::FILL_SOLID], true) || !$fill->get_end_color()->get_rgb()) && $fill->get_start_color()->get_rgb()) {
                $value = '#' . $fill->get_start_color()->get_rgb();
                $css['background-color'] = $value;
            } elseif ($fill->get_end_color()->get_rgb()) {
                $value = '#' . $fill->get_end_color()->get_rgb();
                $css['background-color'] = $value;
            }
        }
        return $css;
    }
    /**
     * Generate HTML footer.
     */
    public function generate_html_footer(): string
    {
        // Construct HTML
        $html = '';
        $html .= '  </body>' . $this->line_ending;
        return $html . ('</html>' . $this->line_ending);
    }
    private function get_dir(Worksheet $worksheet): string
    {
        if ($worksheet->get_right_to_left()) {
            return " dir='rtl'";
        }
        if ($this->rtl_sheets) {
            return " dir='ltr'";
        }
        return '';
    }
    private function get_float(Worksheet $worksheet): string
    {
        $float = '';
        if ($worksheet->get_right_to_left()) {
            if ($this->ltr_sheets) {
                $float = ' floatright';
            }
        } else if ($this->rtl_sheets) {
            $float = ' floatleft';
        }
        return $float;
    }
    private function generate_table_tag_inline(Worksheet $worksheet, string $id): string
    {
        $style = isset($this->css_styles['table']) ? $this->assemble_css($this->css_styles['table']) : '';
        $rtl = $this->get_dir($worksheet);
        $float = $this->get_float($worksheet);
        if (str_ends_with($float, 'right')) {
            $style .= '; float:right';
        } elseif (str_ends_with($float, 'left')) {
            $style .= '; float:left';
        }
        $prntgrid = $worksheet->get_print_gridlines();
        $viewgrid = $this->is_pdf ? $prntgrid : $worksheet->get_show_gridlines();
        $print_area = $worksheet->get_page_setup()->get_print_area();
        $data_print = $print_area === '' ? '' : " data-printarea='" . htmlspecialchars($print_area) . "'";
        if ($viewgrid && $prntgrid) {
            $html = "    <table{$rtl}{$data_print} {$id} style='{$style}' class='gridlines gridlinesp'>" . $this->line_ending;
        } elseif ($viewgrid) {
            $html = "    <table{$rtl}{$data_print} {$id} style='{$style}' class='gridlines'>" . $this->line_ending;
        } elseif ($prntgrid) {
            $html = "    <table{$rtl}{$data_print} {$id} style='{$style}' class='gridlinesp'>" . $this->line_ending;
        } else {
            $html = "    <table{$rtl}{$data_print} {$id} style='{$style}'>" . $this->line_ending;
        }
        return $html;
    }
    private function generate_table_tag(Worksheet $worksheet, string $id, string &$html, int $sheet_index): void
    {
        if (!$this->use_inline_css) {
            $rtl = $this->get_dir($worksheet);
            $print_area = $worksheet->get_page_setup()->get_print_area();
            $data_print = $print_area === '' ? '' : " data-printarea='" . htmlspecialchars($print_area) . "'";
            $float = $this->get_float($worksheet);
            if ($this instanceof Pdf\Dompdf) {
                $gridlines = $worksheet->get_print_gridlines() ? ' gridlines' : '';
                $gridlinesp = $worksheet->get_print_gridlines() ? ' gridlinesp' : '';
            } else {
                $gridlines = $worksheet->get_show_gridlines() ? ' gridlines' : '';
                $gridlinesp = $worksheet->get_print_gridlines() ? ' gridlinesp' : '';
            }
            $html .= "    <table{$rtl}{$data_print} {$id} class='sheet{$sheet_index}{$gridlines}{$gridlinesp}{$float}'>" . $this->line_ending;
        } else {
            $html .= $this->generate_table_tag_inline($worksheet, $id);
        }
    }
    /**
     * Generate table header.
     *
     * @param Worksheet $worksheet The worksheet for the table we are writing
     * @param bool $showid whether or not to add id to table tag
     */
    private function generate_table_header(Worksheet $worksheet, bool $showid = true): string
    {
        $sheet_index = $worksheet->get_parent_or_throw()->get_index($worksheet);
        // Construct HTML
        $html = '';
        $id = $showid ? "id='sheet{$sheet_index}'" : '';
        $clear = $this->rtl_sheets && $this->ltr_sheets ? '; clear:both' : '';
        if ($showid) {
            $html .= "<div style='page: page{$sheet_index}{$clear}'>" . $this->line_ending;
        } else {
            $html .= "<div style='page: page{$sheet_index}{$clear}' class='scrpgbrk'>" . $this->line_ending;
        }
        $this->generate_table_tag($worksheet, $id, $html, $sheet_index);
        // Write <col> elements
        $highest_column_index = Coordinate::column_index_from_string($worksheet->get_highest_column()) - 1;
        $i = -1;
        while ($i++ < $highest_column_index) {
            if (!$this->use_inline_css) {
                $html .= '        <col class="col' . $i . '" />' . $this->line_ending;
            } else {
                $style = isset($this->css_styles['table.sheet' . $sheet_index . ' col.col' . $i]) ? $this->assemble_css($this->css_styles['table.sheet' . $sheet_index . ' col.col' . $i]) : '';
                $html .= '        <col style="' . $style . '" />' . $this->line_ending;
            }
        }
        return $html;
    }
    /**
     * Generate table footer.
     */
    private function generate_table_footer(): string
    {
        return '    </tbody></table>' . $this->line_ending . '</div>' . $this->line_ending;
    }
    /**
     * Generate row start.
     *
     * @param int $sheetIndex Sheet index (0-based)
     * @param int $row row number
     */
    private function generate_row_start(Worksheet $worksheet, int $sheet_index, int $row): string
    {
        $html = '';
        if (count($worksheet->get_breaks()) > 0) {
            $breaks = $worksheet->get_row_breaks();
            // check if a break is needed before this row
            if (isset($breaks['A' . $row])) {
                // close table: </table>
                $html .= $this->generate_table_footer();
                if ($this->is_pdf && $this->use_inline_css) {
                    $html .= '<div style="page-break-before:always" />';
                }
                // open table again: <table> + <col> etc.
                $html .= $this->generate_table_header($worksheet, false);
                $html .= '<tbody>' . $this->line_ending;
            }
        }
        // Write row start
        if (!$this->use_inline_css) {
            $html .= '          <tr class="row' . $row . '">' . $this->line_ending;
        } else {
            $style = isset($this->css_styles['table.sheet' . $sheet_index . ' tr.row' . $row]) ? $this->assemble_css($this->css_styles['table.sheet' . $sheet_index . ' tr.row' . $row]) : '';
            if ($style === '') {
                $html .= '          <tr>' . $this->line_ending;
            } else {
                $html .= '          <tr style="' . $style . '">' . $this->line_ending;
            }
        }
        return $html;
    }
    /** @return array{null|''|Cell, array{}|string, non-empty-string} */
    private function generate_row_cell_css(Worksheet $worksheet, string $cell_address, int $row, int $column_number): array
    {
        $cell = $cell_address > '' ? $worksheet->get_cell_collection()->get($cell_address) : '';
        $coordinate = Coordinate::string_from_column_index($column_number + 1) . ($row + 1);
        if (!$this->use_inline_css) {
            $css_class = 'column' . $column_number;
        } else {
            $css_class = [];
        }
        return [$cell, $css_class, $coordinate];
    }
    private function generate_row_cell_data_value_rich(Rich_Text $rich_text, ?Font $default_font = null): string
    {
        $cell_data = '';
        // Loop through rich text elements
        $elements = $rich_text->get_rich_text_elements();
        foreach ($elements as $element) {
            // Rich text start?
            $font = $element instanceof Run ? $element->get_font() : $default_font;
            if ($element instanceof Run || $font !== null) {
                $cell_end = '';
                if ($font !== null) {
                    $cell_data .= '<span style="' . $this->assemble_css($this->create_css_style_font($font, true)) . '">';
                    if ($font->get_superscript()) {
                        $cell_data .= '<sup>';
                        $cell_end = '</sup>';
                    } elseif ($font->get_subscript()) {
                        $cell_data .= '<sub>';
                        $cell_end = '</sub>';
                    }
                } else {
                    $cell_data .= '<span>';
                }
                // Convert UTF8 data to PCDATA
                $cell_text = $element->get_text();
                $cell_data .= htmlspecialchars($cell_text, Settings::html_entity_flags());
                $cell_data .= $cell_end;
                $cell_data .= '</span>';
            } else {
                // Convert UTF8 data to PCDATA
                $cell_text = $element->get_text();
                $cell_data .= htmlspecialchars($cell_text, Settings::html_entity_flags());
            }
        }
        return self::nl2brx($cell_data);
    }
    private function generate_row_cell_data_value(Worksheet $worksheet, Cell $cell, string &$cell_data): void
    {
        if ($cell->get_value() instanceof Rich_Text) {
            $cell_data .= $this->generate_row_cell_data_value_rich($cell->get_value(), $cell->get_style()->get_font());
        } else {
            if ($this->pre_calculate_formulas) {
                try {
                    $orig_data = $cell->get_calculated_value();
                } catch (Calculation_Exception) {
                    $orig_data = '#ERROR';
                    // mark as error, rather than crash everything
                }
                if ($this->better_boolean && is_bool($orig_data)) {
                    if ($cell->get_style()->get_checkbox()) {
                        $orig_data2 = $orig_data ? '☑' : '☐';
                    } else {
                        $orig_data2 = $orig_data ? $this->get_true : $this->get_false;
                    }
                } else {
                    try {
                        $orig_data2 = $cell->get_calculated_value_string();
                    } catch (Calculation_Exception) {
                        $orig_data2 = '#ERROR';
                        // mark as error, rather than crash everything
                    }
                }
            } else {
                $orig_data = $cell->get_value();
                if ($this->better_boolean && is_bool($orig_data)) {
                    if ($cell->get_style()->get_checkbox()) {
                        $orig_data2 = $orig_data ? '☑' : '☐';
                    } else {
                        $orig_data2 = $orig_data ? $this->get_true : $this->get_false;
                    }
                } else {
                    $orig_data2 = $cell->get_value_string();
                }
            }
            $format_code = $worksheet->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_number_format()->get_format_code();
            $cell_data = Number_Format::to_formatted_string($orig_data2, $format_code ?? Number_Format::FORMAT_GENERAL, $this->format_color(...));
            if ($cell_data === $orig_data) {
                $cell_data = htmlspecialchars($cell_data, Settings::html_entity_flags());
            }
            if ($worksheet->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_font()->get_superscript()) {
                $cell_data = '<sup>' . $cell_data . '</sup>';
            } elseif ($worksheet->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index())->get_font()->get_subscript()) {
                $cell_data = '<sub>' . $cell_data . '</sub>';
            }
        }
    }
    /** @param string|string[] $cssClass */
    private function generate_row_cell_data(Worksheet $worksheet, null|Cell|string $cell, array|string &$css_class): string
    {
        if ($cell instanceof Cell) {
            $cell_data = '';
            // Don't know what this does, and no test cases.
            //if ($cell->getParent() === null) {
            //    $cell->attach($worksheet);
            //}
            // Value
            $this->generate_row_cell_data_value($worksheet, $cell, $cell_data);
            // Converts the cell content so that spaces occuring at beginning of each new line are replaced by &nbsp;
            // Example: "  Hello\n to the world" is converted to "&nbsp;&nbsp;Hello\n&nbsp;to the world"
            $cell_data = Preg::replace('/(?m)(?:^|\G) /', '&nbsp;', $cell_data);
            // convert newline "\n" to '<br>'
            $cell_data = self::nl2brx($cell_data);
            // Extend CSS class?
            $data_type = $cell->get_data_type();
            if ($this->better_boolean && $this->pre_calculate_formulas && $data_type === Data_Type::TYPE_FORMULA) {
                try {
                    $calculated_value = $cell->get_calculated_value();
                    if (is_bool($calculated_value)) {
                        $data_type = Data_Type::TYPE_BOOL;
                    } elseif (is_numeric($calculated_value)) {
                        $data_type = Data_Type::TYPE_NUMERIC;
                    } elseif (is_string($calculated_value)) {
                        $data_type = Data_Type::TYPE_STRING;
                    }
                } catch (Calculation_Exception) {
                    $calculated_value = '#ERROR';
                    $data_type = Data_Type::TYPE_ERROR;
                }
            }
            if (!$this->use_inline_css && is_string($css_class)) {
                $css_class .= ' style' . $cell->get_xf_index();
                $css_class .= ' ' . $data_type;
            } elseif (is_array($css_class)) {
                $index = $cell->get_xf_index();
                $style_index = 'td.style' . $index . ', th.style' . $index;
                if (isset($this->css_styles[$style_index])) {
                    $css_class = array_merge($css_class, $this->css_styles[$style_index]);
                }
                // General horizontal alignment: Actual horizontal alignment depends on dataType
                $shared_style = $worksheet->get_parent_or_throw()->get_cell_xf_by_index($cell->get_xf_index());
                if ($shared_style->get_alignment()->get_horizontal() == Alignment::HORIZONTAL_GENERAL && isset($this->css_styles['.' . $cell->get_data_type()]['text-align'])) {
                    $css_class['text-align'] = $this->css_styles['.' . $data_type]['text-align'];
                }
            }
        } else {
            $cell_data = "{$cell}";
            // Use default borders for empty cell
            if (is_string($css_class)) {
                $css_class .= ' style0';
            }
        }
        /*
         * Browsers may remove an entirely empty row.
         * An interesting option is to leave an empty cell empty using css.
         * td:empty::after{content: "\00a0";}
         * This works well in modern browsers.
         * Alas, none of our Pdf writers can handle it.
         */
        return trim($cell_data) === '' ? '&nbsp;' : $cell_data;
    }
    private function generate_row_include_charts(Worksheet $worksheet, string $coordinate): string
    {
        return $this->include_charts ? $this->write_chart_in_cell($worksheet, $coordinate) : '';
    }
    private function generate_row_spans(string $html, int $row_span, int $col_span): string
    {
        $html .= $col_span > 1 ? ' colspan="' . $col_span . '"' : '';
        $html .= $row_span > 1 ? ' rowspan="' . $row_span . '"' : '';
        return $html;
    }
    /**
     * @param string|string[] $cssClass
     */
    private function generate_row_write_cell(string &$html, Worksheet $worksheet, string $coordinate, string $cell_type, string $cell_data, int $col_span, int $row_span, array|string $css_class, int $col_num, int $sheet_index, int $row): void
    {
        // Image?
        $htmlx = $this->write_image_in_cell($coordinate);
        // Chart?
        $htmlx .= $this->generate_row_include_charts($worksheet, $coordinate);
        // Column start
        $html .= '            <' . $cell_type;
        if ($worksheet->get_style($coordinate)->get_checkbox()) {
            $html .= ' data-checkbox="1"';
        }
        $data_type = $worksheet->get_cell($coordinate)->get_data_type();
        if ($this->better_boolean) {
            if ($data_type === Data_Type::TYPE_BOOL) {
                $html .= ' data-type="' . Data_Type::TYPE_BOOL . '"';
            } elseif ($data_type === Data_Type::TYPE_FORMULA && $this->pre_calculate_formulas) {
                try {
                    $calculated_value = $worksheet->get_cell($coordinate)->get_calculated_value();
                    if (is_bool($calculated_value)) {
                        $html .= ' data-type="' . Data_Type::TYPE_BOOL . '"';
                    } elseif ($this->data_formula && is_string($calculated_value)) {
                        $html .= ' data-type="' . Data_Type::TYPE_STRING . '"';
                    } elseif ($this->data_formula && (is_int($calculated_value) || is_float($calculated_value))) {
                        $html .= ' data-type="' . Data_Type::TYPE_NUMERIC . '"';
                    }
                } catch (Calculation_Exception) {
                    $html .= ' data-type="' . Data_Type::TYPE_ERROR . '"';
                }
            } elseif (is_numeric($cell_data) && $worksheet->get_cell($coordinate)->get_data_type() === Data_Type::TYPE_STRING) {
                $html .= ' data-type="' . Data_Type::TYPE_STRING . '"';
            }
        }
        if ($data_type === Data_Type::TYPE_FORMULA && $this->data_formula) {
            if ($this->pre_calculate_formulas) {
                $html .= ' data-formula="' . htmlspecialchars($worksheet->get_cell($coordinate)->get_value_string()) . '"';
            }
        }
        $hold_css = '';
        if (!$this->use_inline_css && !$this->is_pdf && is_string($css_class)) {
            $html .= ' class="' . $css_class . '"';
            if ($htmlx) {
                $html .= " style='position: relative;'";
            }
        } else {
            //** Necessary redundant code for the sake of \PhpOffice\PhpSpreadsheet\Writer\Pdf **
            // We must explicitly write the width of the <td> element because TCPDF
            // does not recognize e.g. <col style="width:42pt">
            if ($this->use_inline_css) {
                $xcss_class = is_array($css_class) ? $css_class : [];
            } else {
                if (is_string($css_class)) {
                    $html .= ' class="' . $css_class . '"';
                }
                $xcss_class = [];
            }
            $width = 0;
            $i = $col_num - 1;
            $e = $col_num + $col_span - 1;
            while ($i++ < $e) {
                if (isset($this->column_widths[$sheet_index][$i])) {
                    $width += $this->column_widths[$sheet_index][$i];
                }
            }
            $xcss_class['width'] = $width . 'pt';
            // We must also explicitly write the height of the <td> element because TCPDF
            // does not recognize e.g. <tr style="height:50pt">
            if (isset($this->css_styles['table.sheet' . $sheet_index . ' tr.row' . $row]['height'])) {
                $height = $this->css_styles['table.sheet' . $sheet_index . ' tr.row' . $row]['height'];
                $xcss_class['height'] = $height;
            }
            //** end of redundant code **
            if ($this->use_inline_css) {
                foreach (['border-top', 'border-bottom', 'border-right', 'border-left'] as $border_type) {
                    if (($xcss_class[$border_type] ?? '') === 'none #000000') {
                        unset($xcss_class[$border_type]);
                    }
                }
                $found_border = false;
                if ($this instanceof Pdf\Tcpdf && $worksheet->get_print_grid_lines()) {
                    foreach (['border-top', 'border-bottom', 'border-right', 'border-left'] as $border_type) {
                        if (isset($xcss_class[$border_type])) {
                            $found_border = true;
                        }
                    }
                    if (!$found_border) {
                        $xcss_class['border'] = '0.1px solid black';
                    }
                }
            }
            if ($htmlx) {
                $xcss_class['position'] = 'relative';
            }
            /** @var string[] $xcssClass */
            $hold_css = $this->assemble_css($xcss_class);
            if ($this->use_inline_css) {
                $prntgrid = $worksheet->get_print_gridlines();
                $viewgrid = $this->is_pdf ? $prntgrid : $worksheet->get_show_gridlines();
                if ($viewgrid && $prntgrid) {
                    $html .= ' class="gridlines gridlinesp"';
                } elseif ($viewgrid) {
                    $html .= ' class="gridlines"';
                } elseif ($prntgrid) {
                    $html .= ' class="gridlinesp"';
                }
            }
        }
        $html = $this->generate_row_spans($html, $row_span, $col_span);
        $merged_cell_style = new Merged_Cell_Style();
        $merged_style = $merged_cell_style->get_merged_style($worksheet, $coordinate, $this->table_formats, $this->conditional_formatting, $this->table_formats_builtin);
        if ($merged_cell_style->get_matched()) {
            $styles = $this->create_css_style($merged_style, true);
            $html .= ' style="';
            if ($hold_css !== '') {
                $html .= "{$hold_css}; ";
                $hold_css = '';
            }
            foreach ($styles as $key => $value) {
                if (!str_starts_with((string) $key, 'border-') || $value !== 'none #000000') {
                    $html .= $key . ':' . $value . ';';
                }
            }
            $html .= '"';
        }
        if ($hold_css !== '') {
            $html .= ' style="' . $hold_css . '"';
        }
        $html .= '>';
        $html .= $htmlx;
        $html .= $this->write_comment($worksheet, $coordinate);
        // Cell data
        $html .= $cell_data;
        // Column end
        $html .= '</' . $cell_type . '>' . $this->line_ending;
    }
    /**
     * Generate row.
     *
     * @param array<int, string> $values Array containing cells in a row
     * @param int $row Row number (0-based)
     * @param string $cellType eg: 'td'
     */
    private function generate_row(Worksheet $worksheet, array $values, int $row, string $cell_type): string
    {
        // Sheet index
        $sheet_index = $worksheet->get_parent_or_throw()->get_index($worksheet);
        $html = $this->generate_row_start($worksheet, $sheet_index, $row);
        // Write cells
        $col_num = 0;
        $tcpdf_inited = false;
        foreach ($values as $key => $cell_address) {
            if ($this instanceof Pdf\Mpdf) {
                $col_num = $key - 1;
            } elseif ($this instanceof Pdf\Tcpdf) {
                // It appears that Tcpdf requires first cell in tr.
                $col_num = $key - 1;
                if (!$tcpdf_inited && $key !== 1) {
                    $tempspan = $col_num > 1 ? " colspan='{$col_num}'" : '';
                    $html .= "<td{$tempspan}></td>" . $this->line_ending;
                }
                $tcpdf_inited = true;
            }
            [$cell, $css_class, $coordinate] = $this->generate_row_cell_css($worksheet, $cell_address, $row, $col_num);
            // Cell Data
            $cell_data = $this->generate_row_cell_data($worksheet, $cell, $css_class);
            // Get an array of all styles
            $cond_styles = $worksheet->get_style($coordinate)->get_conditional_styles();
            // Hyperlink?
            if ($worksheet->hyperlink_exists($coordinate) && !$worksheet->get_hyperlink($coordinate)->is_internal()) {
                $url = $worksheet->get_hyperlink($coordinate)->get_url();
                $url_decode1 = html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $url_trim = Preg::replace('/^\s+/u', '', $url_decode1);
                $parse_scheme = Preg::is_match('/^([\w\s\x00-\x1f]+):/u', strtolower($url_trim), $matches);
                if ($parse_scheme && !in_array($matches[1], ['http', 'https', 'mailto'], true)) {
                    $cell_data = htmlspecialchars($url, Settings::html_entity_flags());
                    $cell_data = self::replace_control_chars($cell_data);
                } else {
                    $tooltip = $worksheet->get_hyperlink($coordinate)->get_tooltip();
                    $tooltip_out = empty($tooltip) ? '' : ' title="' . htmlspecialchars($tooltip) . '"';
                    $cell_data = '<a href="' . htmlspecialchars($url) . '"' . $tooltip_out . '>' . $cell_data . '</a>';
                }
            }
            // Should the cell be written or is it swallowed by a rowspan or colspan?
            $write_cell = !(isset($this->is_spanned_cell[$worksheet->get_parent_or_throw()->get_index($worksheet)][$row + 1][$col_num]) && $this->is_spanned_cell[$worksheet->get_parent_or_throw()->get_index($worksheet)][$row + 1][$col_num]);
            // Colspan and Rowspan
            $col_span = 1;
            $row_span = 1;
            if (isset($this->is_base_cell[$worksheet->get_parent_or_throw()->get_index($worksheet)][$row + 1][$col_num])) {
                /** @var array<string, int> */
                $spans = $this->is_base_cell[$worksheet->get_parent_or_throw()->get_index($worksheet)][$row + 1][$col_num];
                $row_span = $spans['rowspan'];
                $col_span = $spans['colspan'];
                //    Also apply style from last cell in merge to fix borders -
                //        relies on !important for non-none border declarations in createCSSStyleBorder
                $end_cell_coord = Coordinate::string_from_column_index($col_num + $col_span) . ($row + $row_span);
                if (!$this->use_inline_css && is_string($css_class)) {
                    $css_class .= ' style' . $worksheet->get_cell($end_cell_coord)->get_xf_index();
                } else {
                    $end_borders = $this->spreadsheet->get_cell_xf_by_index($worksheet->get_cell($end_cell_coord)->get_xf_index())->get_borders();
                    $alt_borders = $this->create_css_style_borders($end_borders);
                    foreach ($alt_borders as $alt_key => $alt_value) {
                        if (str_contains($alt_value, '!important')) {
                            $css_class[$alt_key] = $alt_value;
                        }
                    }
                }
            }
            // Write
            if ($write_cell) {
                $this->generate_row_write_cell($html, $worksheet, $coordinate, $cell_type, $cell_data, $col_span, $row_span, $css_class, $col_num, $sheet_index, $row);
            }
            // Next column
            ++$col_num;
        }
        if ($this instanceof Pdf\Tcpdf) {
            if (str_ends_with($html, '<tr>' . $this->line_ending)) {
                $html .= '<td>&nbsp;</td>' . $this->line_ending;
            }
        }
        // Write row end
        $html .= '          </tr>' . $this->line_ending;
        // Return
        return $html;
    }
    private static function replace_control_chars(string $convert): string
    {
        return Preg::replace_callback('/[\x00-\x1f]/', fn(array $matches): string => '&#' . ord($matches[0]) . ';', $convert);
    }
    /**
     * Takes array where of CSS properties / values and converts to CSS string.
     *
     * @param string[] $values
     */
    private function assemble_css(array $values = []): string
    {
        $pairs = [];
        foreach ($values as $property => $value) {
            $pairs[] = $property . ':' . $value;
        }
        return implode('; ', $pairs);
    }
    /**
     * Get images root.
     */
    public function get_images_root(): string
    {
        return $this->images_root;
    }
    /**
     * Set images root.
     *
     * @return $this
     */
    public function set_images_root(string $images_root): static
    {
        $this->images_root = $images_root;
        return $this;
    }
    /**
     * Get embed images.
     */
    public function get_embed_images(): bool
    {
        return $this->embed_images;
    }
    /**
     * Set embed images.
     *
     * @return $this
     */
    public function set_embed_images(bool $embed_images): static
    {
        $this->embed_images = $embed_images;
        return $this;
    }
    /**
     * Get use inline CSS?
     */
    public function get_use_inline_css(): bool
    {
        return $this->use_inline_css;
    }
    /**
     * Set use inline CSS?
     *
     * @return $this
     */
    public function set_use_inline_css(bool $use_inline_css): static
    {
        $this->use_inline_css = $use_inline_css;
        return $this;
    }
    public function get_table_formats(): bool
    {
        return $this->table_formats;
    }
    public function set_table_formats(bool $table_formats, ?bool $table_formats_builtin = null): self
    {
        $this->table_formats = $table_formats;
        $this->table_formats_builtin = $table_formats_builtin;
        return $this;
    }
    public function get_conditional_formatting(): bool
    {
        return $this->conditional_formatting;
    }
    public function set_conditional_formatting(bool $conditional_formatting): self
    {
        $this->conditional_formatting = $conditional_formatting;
        return $this;
    }
    /**
     * Add color to formatted string as inline style.
     *
     * @param string $value Plain formatted value without color
     * @param string $format Format code
     */
    public function format_color(string $value, string $format): string
    {
        return self::format_color_static($value, $format);
    }
    /**
     * Add color to formatted string as inline style.
     *
     * @param string $value Plain formatted value without color
     * @param string $format Format code
     */
    public static function format_color_static(string $value, string $format): string
    {
        // Color information, e.g. [Red] is always at the beginning
        $color = null;
        // initialize
        $matches = [];
        $color_regex = '/^\[[a-zA-Z]+\]/';
        if (Preg::is_match($color_regex, $format, $matches)) {
            $color = str_replace(['[', ']'], '', $matches[0]);
            $color = strtolower($color);
        }
        // convert to PCDATA
        $result = htmlspecialchars($value, Settings::html_entity_flags());
        // color span tag
        if ($color !== null) {
            return '<span style="color:' . $color . '">' . $result . '</span>';
        }
        return $result;
    }
    /**
     * Calculate information about HTML colspan and rowspan which is not always the same as Excel's.
     */
    private function calculate_spans(): void
    {
        if ($this->spans_are_calculated) {
            return;
        }
        // Identify all cells that should be omitted in HTML due to cell merge.
        // In HTML only the upper-left cell should be written and it should have
        //   appropriate rowspan / colspan attribute
        $sheet_indexes = $this->sheet_index !== null ? [$this->sheet_index] : range(0, $this->spreadsheet->get_sheet_count() - 1);
        foreach ($sheet_indexes as $sheet_index) {
            $sheet = $this->spreadsheet->get_sheet($sheet_index);
            $candidate_spanned_row = [];
            // loop through all Excel merged cells
            foreach ($sheet->get_merge_cells() as $cells) {
                [$cells] = Coordinate::split_range($cells);
                $first = $cells[0];
                $last = $cells[1];
                [$fc, $fr] = Coordinate::indexes_from_string($first);
                $fc = $fc - 1;
                [$lc, $lr] = Coordinate::indexes_from_string($last);
                $lc = $lc - 1;
                // loop through the individual cells in the individual merge
                $r = $fr - 1;
                while ($r++ < $lr) {
                    // also, flag this row as a HTML row that is candidate to be omitted
                    $candidate_spanned_row[$r] = $r;
                    $c = $fc - 1;
                    while ($c++ < $lc) {
                        if (!($c == $fc && $r == $fr)) {
                            // not the upper-left cell (should not be written in HTML)
                            $this->is_spanned_cell[$sheet_index][$r][$c] = ['baseCell' => [$fr, $fc]];
                        } else {
                            // upper-left is the base cell that should hold the colspan/rowspan attribute
                            $this->is_base_cell[$sheet_index][$r][$c] = [
                                'xlrowspan' => $lr - $fr + 1,
                                // Excel rowspan
                                'rowspan' => $lr - $fr + 1,
                                // HTML rowspan, value may change
                                'xlcolspan' => $lc - $fc + 1,
                                // Excel colspan
                                'colspan' => $lc - $fc + 1,
                            ];
                        }
                    }
                }
            }
        }
        // We have calculated the spans
        $this->spans_are_calculated = true;
    }
    /**
     * Write a comment in the same format as LibreOffice.
     *
     * @see https://github.com/LibreOffice/core/blob/9fc9bf3240f8c62ad7859947ab8a033ac1fe93fa/sc/source/filter/html/htmlexp.cxx#L1073-L1092
     */
    private function write_comment(Worksheet $worksheet, string $coordinate): string
    {
        $result = '';
        if (!$this->is_pdf && isset($worksheet->get_comments()[$coordinate])) {
            $sanitized_string = $this->generate_row_cell_data_value_rich($worksheet->get_comment($coordinate)->get_text());
            $dir = $worksheet->get_comment($coordinate)->get_textbox_direction() === Comment::TEXTBOX_DIRECTION_RTL ? ' dir="rtl"' : '';
            $align = strtolower($worksheet->get_comment($coordinate)->get_alignment());
            $alignment = Alignment::HORIZONTAL_ALIGNMENT_FOR_HTML[$align] ?? '';
            if ($alignment !== '') {
                $alignment = " style=\"text-align:{$alignment}\"";
            }
            if ($sanitized_string !== '') {
                $result .= '<a class="comment-indicator"></a>';
                $result .= "<div class=\"comment\"{$dir}{$alignment}>" . $sanitized_string . '</div>';
                $result .= $this->line_ending;
            }
        }
        return $result;
    }
    public function get_orientation(): ?string
    {
        // Expect Pdf classes to override this method.
        return $this->is_pdf ? Page_Setup::ORIENTATION_PORTRAIT : null;
    }
    /**
     * Generate @page declarations.
     */
    private function generate_page_declarations(bool $generate_surrounding_html): string
    {
        // Ensure that Spans have been calculated?
        $this->calculate_spans();
        // Fetch sheets
        $sheets = [];
        if ($this->sheet_index === null) {
            $sheets = $this->spreadsheet->get_all_sheets();
        } else {
            $sheets[] = $this->spreadsheet->get_sheet($this->sheet_index);
        }
        // Construct HTML
        $html_page = $generate_surrounding_html ? '<style type="text/css">' . $this->line_ending : '';
        // Loop all sheets
        $sheet_id = 0;
        foreach ($sheets as $worksheet) {
            $html_page .= "@page page{$sheet_id} { ";
            $left = String_Helper::format_number($worksheet->get_page_margins()->get_left()) . 'in; ';
            $html_page .= 'margin-left: ' . $left;
            $right = String_Helper::format_number($worksheet->get_page_margins()->get_right()) . 'in; ';
            $html_page .= 'margin-right: ' . $right;
            $top = String_Helper::format_number($worksheet->get_page_margins()->get_top()) . 'in; ';
            $html_page .= 'margin-top: ' . $top;
            $bottom = String_Helper::format_number($worksheet->get_page_margins()->get_bottom()) . 'in; ';
            $html_page .= 'margin-bottom: ' . $bottom;
            $orientation = $this->get_orientation() ?? $worksheet->get_page_setup()->get_orientation();
            if ($orientation === Page_Setup::ORIENTATION_LANDSCAPE) {
                $html_page .= 'size: landscape; ';
            } elseif ($orientation === Page_Setup::ORIENTATION_PORTRAIT) {
                $html_page .= 'size: portrait; ';
            }
            $html_page .= '}' . $this->line_ending;
            if (!$this->is_pdf) {
                $html_page .= $this->print_area_styles($sheet_id, $worksheet);
            }
            ++$sheet_id;
        }
        $html_page .= implode($this->line_ending, ['.navigation {page-break-after: always;}', '.scrpgbrk, div + div {page-break-before: always;}', '@media screen {', '  .gridlines td {border: 1px solid black;}', '  .gridlines th {border: 1px solid black;}', '  body>div {margin-top: 5px;}', '  body>div:first-child {margin-top: 0;}', '  .scrpgbrk {margin-top: 1px;}', '}', '@media print {', '  .gridlinesp td {border: 1px solid black;}', '  .gridlinesp th {border: 1px solid black;}', '  .navigation {display: none;}', '}', '']);
        $html_page .= $generate_surrounding_html ? '</style>' . $this->line_ending : '';
        return $html_page;
    }
    private function print_area_styles(int $sheet_id, Worksheet $worksheet): string
    {
        $ret_val = '';
        $print_area = $worksheet->get_page_setup()->get_print_area();
        if (Preg::is_match('/^([a-z]+)([0-9]+):([a-z]+)([0-9]+)$/i', $print_area, $matches)) {
            $low_col = Coordinate::column_index_from_string($matches[1]) - 1;
            $high_col = Coordinate::column_index_from_string($matches[3]) - 1;
            $low_row = (int) $matches[2] - 1;
            $high_row = (int) $matches[4] - 1;
            $ret_val = '@media print {' . $this->line_ending;
            $high_data_row = $worksheet->get_highest_data_row();
            for ($row = 0; $row < $high_data_row; ++$row) {
                if ($row < $low_row || $row > $high_row) {
                    $ret_val .= "    table.sheet{$sheet_id} tr.row{$row} td { display:none }" . $this->line_ending;
                }
            }
            $high_data_column = $worksheet->get_highest_data_column();
            $high_data_col = Coordinate::column_index_from_string($high_data_column);
            for ($col = 0; $col < $high_data_col; ++$col) {
                if ($col < $low_col || $col > $high_col) {
                    $ret_val .= "    table.sheet{$sheet_id} td.column{$col} { display:none }" . $this->line_ending;
                }
            }
            $ret_val .= '}' . $this->line_ending;
        }
        return $ret_val;
    }
    private function should_generate_row(Worksheet $sheet, int $row): bool
    {
        if ($this->is_pdf) {
            if ($this->print_area_low_row >= 0) {
                if ($row < $this->print_area_low_row || $row > $this->print_area_high_row) {
                    return false;
                }
            }
        }
        if (!($this instanceof Pdf\Mpdf || $this instanceof Pdf\Tcpdf)) {
            return true;
        }
        return $sheet->is_row_visible($row);
    }
    private function should_generate_column(Worksheet $sheet, string $col_str): bool
    {
        if ($this->is_pdf) {
            if ($this->print_area_low_col >= 0) {
                $col = Coordinate::column_index_from_string($col_str);
                if ($col < $this->print_area_low_col || $col > $this->print_area_high_col) {
                    return false;
                }
            }
        }
        if (!($this instanceof Pdf\Mpdf || $this instanceof Pdf\Tcpdf)) {
            return true;
        }
        if (!$sheet->column_dimension_exists($col_str)) {
            return true;
        }
        return $sheet->get_column_dimension($col_str)->get_visible();
    }
    public function get_better_boolean(): bool
    {
        return $this->better_boolean;
    }
    public function set_better_boolean(bool $better_boolean): self
    {
        $this->better_boolean = $better_boolean;
        return $this;
    }
    private static function nl2brx(string $string): string
    {
        return str_replace(["\r\n", "\n\r", "\r", "\n"], self::BRX, $string);
    }
    private function extend_rows_and_columns_for_merge(Worksheet $worksheet, int &$col_max, int &$row_max): void
    {
        foreach ($worksheet->get_merge_cells() as $cell_range) {
            if (Preg::is_match('/[a-z]{1,3}\d+:([a-z]{1,3})(\d+)/i', $cell_range, $matches)) {
                $col = Coordinate::column_index_from_string($matches[1]);
                if ($col_max < $col) {
                    $col_max = $col;
                    $worksheet->get_column_dimension($matches[1]);
                }
                $row = (int) $matches[2];
                if ($row_max < $row) {
                    $row_max = $row;
                    $worksheet->get_row_dimension($row);
                }
            }
        }
    }
}