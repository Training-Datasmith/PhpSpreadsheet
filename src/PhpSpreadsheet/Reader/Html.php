<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader;

use Dom_Attr;
use Dom_Document;
use Dom_Element;
use Dom_Node;
use Dom_Text;
use Lib_Xml_Error;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Comment;
use Php_Office\Php_Spreadsheet\Document\Properties;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Helper\Dimension as CssDimension;
use Php_Office\Php_Spreadsheet\Helper\Html as HelperHtml;
use Php_Office\Php_Spreadsheet\Reader\Security\Xml_Scanner;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Throwable;
class Html extends Base_Reader
{
    /**
     * Sample size to read to determine if it's HTML or not.
     */
    public const TEST_SAMPLE_SIZE = 2048;
    private const STARTS_WITH_BOM = '/^(?:\xfe\xff|\xff\xfe|\xEF\xBB\xBF)/';
    private const DECLARES_CHARSET = '/\bcharset=/i';
    /**
     * Input encoding.
     */
    protected string $input_encoding = 'ANSI';
    /**
     * Sheet index to read.
     */
    protected int $sheet_index = 0;
    /**
     * Formats.
     */
    protected const FORMATS = [
        'h1' => ['font' => ['bold' => true, 'size' => 24]],
        //    Bold, 24pt
        'h2' => ['font' => ['bold' => true, 'size' => 18]],
        //    Bold, 18pt
        'h3' => ['font' => ['bold' => true, 'size' => 13.5]],
        //    Bold, 13.5pt
        'h4' => ['font' => ['bold' => true, 'size' => 12]],
        //    Bold, 12pt
        'h5' => ['font' => ['bold' => true, 'size' => 10]],
        //    Bold, 10pt
        'h6' => ['font' => ['bold' => true, 'size' => 7.5]],
        //    Bold, 7.5pt
        'a' => ['font' => ['underline' => true, 'color' => ['argb' => Color::COLOR_BLUE]]],
        //    Blue underlined
        'hr' => ['borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => [Color::COLOR_BLACK]]]],
        //    Bottom border
        'strong' => ['font' => ['bold' => true]],
        //    Bold
        'b' => ['font' => ['bold' => true]],
        //    Bold
        'i' => ['font' => ['italic' => true]],
        //    Italic
        'em' => ['font' => ['italic' => true]],
    ];
    /** @var array<string, bool> */
    protected array $rowspan = [];
    /**
     * Default setting uses current setting of libxml_use_internal_errors.
     * It will probably change to 'true' in a future release.
     */
    protected ?bool $suppress_load_warnings = null;
    /** @var LibXMLError[] */
    protected array $libxml_messages = [];
    /**
     * Suppress load warning messages, keeping them available
     * in $this->libxmlMessages().
     */
    public function set_suppress_load_warnings(?bool $suppress_load_warnings): self
    {
        $this->suppress_load_warnings = $suppress_load_warnings;
        return $this;
    }
    /** @return LibXMLError[] */
    public function get_libxml_messages(): array
    {
        return $this->libxml_messages;
    }
    /**
     * Create a new HTML Reader instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->security_scanner = Xml_Scanner::get_instance($this);
    }
    /**
     * Validate that the current file is an HTML file.
     */
    public function can_read(string $filename): bool
    {
        // Check if file exists
        try {
            $this->open_file($filename);
        } catch (Exception) {
            return false;
        }
        $beginning = preg_replace(self::STARTS_WITH_BOM, '', $this->read_beginning()) ?? '';
        $start_with_tag = self::starts_with_tag($beginning);
        $contains_tags = self::contains_tags($beginning);
        $ends_with_tag = self::ends_with_tag($this->read_ending());
        fclose($this->file_handle);
        return $start_with_tag && $contains_tags && $ends_with_tag;
    }
    private function read_beginning(): string
    {
        fseek($this->file_handle, 0);
        return (string) fread($this->file_handle, self::TEST_SAMPLE_SIZE);
    }
    private function read_ending(): string
    {
        $meta = stream_get_meta_data($this->file_handle);
        // Phpstan incorrectly flags following line for Php8.2-, corrected in 8.3
        $filename = $meta['uri'];
        //@phpstan-ignore-line
        clearstatcache(true, $filename);
        $size = (int) filesize($filename);
        if ($size === 0) {
            return '';
        }
        $block_size = self::TEST_SAMPLE_SIZE;
        if ($size < $block_size) {
            $block_size = $size;
        }
        fseek($this->file_handle, $size - $block_size);
        return (string) fread($this->file_handle, $block_size);
    }
    private static function starts_with_tag(string $data): bool
    {
        return str_starts_with(trim($data), '<');
    }
    private static function ends_with_tag(string $data): bool
    {
        return str_ends_with(trim($data), '>');
    }
    private static function contains_tags(string $data): bool
    {
        return strlen($data) !== strlen(strip_tags($data));
    }
    /**
     * Loads Spreadsheet from file.
     */
    public function load_spreadsheet_from_file(string $filename): Spreadsheet
    {
        $spreadsheet = $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        // Load into this instance
        return $this->load_into_existing($filename, $spreadsheet);
    }
    /**
     * Data Array used for testing only, should write to
     * Spreadsheet object on completion of tests.
     *
     * @deprecated 5.4.0 No replacement.
     *
     * @var mixed[][]
     */
    protected array $data_array = [];
    protected int $table_level = 0;
    /** @var string[] */
    protected array $nested_column = ['A'];
    protected function set_table_start_column(string $column): string
    {
        if ($this->table_level == 0) {
            $column = 'A';
        }
        ++$this->table_level;
        $this->nested_column[$this->table_level] = $column;
        return $this->nested_column[$this->table_level];
    }
    protected function get_table_start_column(): string
    {
        return $this->nested_column[$this->table_level];
    }
    protected function release_table_start_column(): string
    {
        --$this->table_level;
        return array_pop($this->nested_column) ?? '';
    }
    /**
     * Flush cell.
     *
     * @param string[] $attributeArray
     *
     * @param-out string $cellContentx
     */
    protected function flush_cell(Worksheet $sheet, string $column, int|string $row, mixed &$cell_contentx, array $attribute_array): void
    {
        $cell_content = $cell_contentx;
        if (is_string($cell_content)) {
            //    Simple String content
            if (trim($cell_content) > '') {
                //    Only actually write it if there's content in the string
                //    Write to worksheet to be done here...
                //    ... we return the cell, so we can mess about with styles more easily
                // Set cell value explicitly if there is data-type attribute
                if (isset($attribute_array['data-checkbox'])) {
                    $sheet->get_style($column . $row)->set_check_box(true);
                }
                if (isset($attribute_array['data-type'])) {
                    $datatype = $attribute_array['data-type'];
                    if (in_array($datatype, [Data_Type::TYPE_STRING, Data_Type::TYPE_STRING2, Data_Type::TYPE_INLINE])) {
                        //Prevent to Excel treat string with beginning equal sign or convert big numbers to scientific number
                        if (str_starts_with($cell_content, '=')) {
                            $sheet->get_cell($column . $row)->get_style()->set_quote_prefix(true);
                        }
                    }
                    if ($datatype === Data_Type::TYPE_BOOL) {
                        // This is the case where we can set cellContent to bool rather than string
                        if ($cell_content === '☑') {
                            $cell_content = true;
                            $sheet->get_style($column . $row)->set_check_box(true);
                        } elseif ($cell_content === '☐') {
                            $cell_content = false;
                            $sheet->get_style($column . $row)->set_check_box(true);
                        } else {
                            $cell_content = self::convert_boolean($cell_content);
                            if (!is_bool($cell_content)) {
                                $attribute_array['data-type'] = Data_Type::TYPE_STRING;
                            }
                        }
                    }
                    //catching the Exception and ignoring the invalid data types
                    $hyperlink = $sheet->hyperlink_exists($column . $row) ? $sheet->get_hyperlink($column . $row) : null;
                    try {
                        if (isset($attribute_array['data-formula'])) {
                            $sheet->set_cell_value_explicit($column . $row, $attribute_array['data-formula'], Data_Type::TYPE_FORMULA);
                            $sheet->get_cell($column . $row)->set_calculated_value($cell_content);
                        } else {
                            $sheet->set_cell_value_explicit($column . $row, $cell_content, $attribute_array['data-type']);
                        }
                    } catch (Spreadsheet_Exception) {
                        $sheet->set_cell_value($column . $row, $cell_content);
                    }
                    $sheet->set_hyperlink($column . $row, $hyperlink);
                } else {
                    $hyperlink = null;
                    if ($sheet->hyperlink_exists($column . $row)) {
                        $hyperlink = $sheet->get_hyperlink($column . $row);
                    }
                    $sheet->set_cell_value($column . $row, $cell_content);
                    $sheet->set_hyperlink($column . $row, $hyperlink);
                }
                $this->data_array[$row][$column] = $cell_content;
                // @phpstan-ignore-line
            }
        } else {
            //    We have a Rich Text run.
            //    I don't actually see any way to reach this line.
            //    TODO
            // @phpstan-ignore-next-line
            $this->data_array[$row][$column] = 'RICH TEXT: ' . String_Helper::convert_to_string($cell_content);
            // @codeCoverageIgnore
        }
        $cell_contentx = '';
    }
    /** @var array<int, array<int, string>> */
    private static array $false_true_array = [];
    private static function convert_boolean(?string $cell_content): bool|string
    {
        if ($cell_content === '1') {
            return true;
        }
        if ($cell_content === '0' || $cell_content === '' || $cell_content === null) {
            return false;
        }
        if (empty(self::$false_true_array)) {
            $calc = Calculation::get_instance();
            self::$false_true_array = $calc->get_false_true_array();
        }
        if (in_array(mb_strtoupper($cell_content), self::$false_true_array[1], true)) {
            return true;
        }
        if (in_array(mb_strtoupper($cell_content), self::$false_true_array[0], true)) {
            return false;
        }
        return $cell_content;
    }
    private function process_dom_element_body(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child): void
    {
        $attribute_array = [];
        /** @var DOMAttr $attribute */
        foreach ($child->attributes ?? [] as $attribute) {
            $attribute_array[$attribute->name] = $attribute->value;
        }
        if ($child->node_name === 'body') {
            $row = 1;
            $column = 'A';
            $cell_content = '';
            $this->table_level = 0;
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
        } else {
            $this->process_dom_element_title($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_title(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'title') {
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            try {
                $sheet->set_title($cell_content, true, true);
                $sheet->get_parent()?->get_properties()?->set_title($cell_content);
            } catch (Spreadsheet_Exception) {
                // leave default title if too long or illegal chars
            }
            $cell_content = '';
        } else {
            $this->process_dom_element_span_etc($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    private const SPAN_ETC = ['span', 'div', 'font', 'i', 'em', 'strong', 'b'];
    /** @param string[] $attributeArray */
    private function process_dom_element_span_etc(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if (in_array($child->node_name, self::SPAN_ETC, true)) {
            if (isset($attribute_array['class']) && $attribute_array['class'] === 'comment') {
                $sheet->get_comment($column . $row)->get_text()->create_text_run($child->text_content);
                if (isset($attribute_array['dir']) && $attribute_array['dir'] === 'rtl') {
                    $sheet->get_comment($column . $row)->set_textbox_direction(Comment::TEXTBOX_DIRECTION_RTL);
                }
                if (isset($attribute_array['style'])) {
                    $align_style = $attribute_array['style'];
                    if (preg_match('/\btext-align:\s*(left|right|center|justify)\b/', (string) $align_style, $matches) === 1) {
                        $sheet->get_comment($column . $row)->set_alignment($matches[1]);
                    }
                }
            } else {
                $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            }
            if (isset(self::FORMATS[$child->node_name])) {
                $sheet->get_style($column . $row)->apply_from_array(self::FORMATS[$child->node_name]);
            }
        } else {
            $this->process_dom_element_hr($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_hr(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'hr') {
            $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
            ++$row;
            $sheet->get_style($column . $row)->apply_from_array(self::FORMATS[$child->node_name]);
            ++$row;
        }
        // fall through to br
        $this->process_dom_element_br($sheet, $row, $column, $cell_content, $child, $attribute_array);
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_br(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'br' || $child->node_name === 'hr') {
            if ($this->table_level > 0) {
                //    If we're inside a table, replace with a newline and set the cell to wrap
                $cell_content .= "\n";
                $sheet->get_style($column . $row)->get_alignment()->set_wrap_text(true);
            } else {
                //    Otherwise flush our existing content and move the row cursor on
                $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
                ++$row;
            }
        } else {
            $this->process_dom_element_a($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_a(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'a') {
            foreach ($attribute_array as $attribute_name => $attribute_value) {
                switch ($attribute_name) {
                    case 'href':
                        $sheet->get_cell($column . $row)->get_hyperlink()->set_url($attribute_value);
                        $sheet->get_style($column . $row)->apply_from_array(self::FORMATS[$child->node_name]);
                        break;
                    case 'class':
                        if ($attribute_value === 'comment-indicator') {
                            break;
                            // Ignore - it's just a red square.
                        }
                }
            }
            // no idea why this should be needed
            //$cellContent .= ' ';
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
        } else {
            $this->process_dom_element_h1etc($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    private const H1_ETC = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ol', 'ul', 'p'];
    /** @param string[] $attributeArray */
    private function process_dom_element_h1etc(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if (in_array($child->node_name, self::H1_ETC, true)) {
            if ($this->table_level > 0) {
                //    If we're inside a table, replace with a newline
                $cell_content .= $cell_content ? "\n" : '';
                $sheet->get_style($column . $row)->get_alignment()->set_wrap_text(true);
                $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            } else {
                if ($cell_content > '') {
                    $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
                    ++$row;
                }
                $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
                $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
                if (isset(self::FORMATS[$child->node_name])) {
                    $sheet->get_style($column . $row)->apply_from_array(self::FORMATS[$child->node_name]);
                }
                ++$row;
                $column = 'A';
            }
        } else {
            $this->process_dom_element_li($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_li(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'li') {
            if ($this->table_level > 0) {
                //    If we're inside a table, replace with a newline
                $cell_content .= $cell_content ? "\n" : '';
                $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            } else {
                if ($cell_content > '') {
                    $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
                }
                ++$row;
                $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
                $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
                $column = 'A';
            }
        } else {
            $this->process_dom_element_img($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_img(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'img') {
            $this->insert_image($sheet, $column, $row, $attribute_array);
        } else {
            $this->process_dom_element_table($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    private string $current_column = 'A';
    /** @param string[] $attributeArray */
    private function process_dom_element_table(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'table') {
            if (isset($attribute_array['class'])) {
                $classes = explode(' ', $attribute_array['class']);
                $sheet->set_show_gridlines(in_array('gridlines', $classes, true));
                $sheet->set_print_gridlines(in_array('gridlinesp', $classes, true));
            }
            if (isset($attribute_array['data-printarea'])) {
                $sheet->get_page_setup()->set_print_area($attribute_array['data-printarea']);
            }
            if ('rtl' === ($attribute_array['dir'] ?? '')) {
                $sheet->set_right_to_left(true);
            }
            $this->current_column = 'A';
            $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
            $column = $this->set_table_start_column($column);
            if ($this->table_level > 1 && $row > 1) {
                --$row;
            }
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            $column = $this->release_table_start_column();
            if ($this->table_level > 1) {
                String_Helper::string_increment($column);
            } else {
                ++$row;
            }
        } else {
            $this->process_dom_element_tr($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_tr(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name === 'col') {
            $this->apply_inline_style($sheet, -1, $this->current_column, $attribute_array);
            String_Helper::string_increment($this->current_column);
        } elseif ($child->node_name === 'tr') {
            $column = $this->get_table_start_column();
            $cell_content = '';
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
            if (isset($attribute_array['height'])) {
                $sheet->get_row_dimension($row)->set_row_height((float) $attribute_array['height']);
            }
            ++$row;
        } else {
            $this->process_dom_element_th_td_other($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_th_td_other(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        if ($child->node_name !== 'td' && $child->node_name !== 'th') {
            $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
        } else {
            $this->process_dom_element_th_td($sheet, $row, $column, $cell_content, $child, $attribute_array);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_bgcolor(Worksheet $sheet, int $row, string $column, array $attribute_array): void
    {
        if (isset($attribute_array['bgcolor'])) {
            $sheet->get_style("{$column}{$row}")->apply_from_array(['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $this->get_style_color($attribute_array['bgcolor'])]]]);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_width(Worksheet $sheet, string $column, array $attribute_array): void
    {
        if (isset($attribute_array['width'])) {
            $sheet->get_column_dimension($column)->set_width((new Css_Dimension($attribute_array['width']))->width());
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_height(Worksheet $sheet, int $row, array $attribute_array): void
    {
        if (isset($attribute_array['height'])) {
            $sheet->get_row_dimension($row)->set_row_height((new Css_Dimension($attribute_array['height']))->height());
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_align(Worksheet $sheet, int $row, string $column, array $attribute_array): void
    {
        if (isset($attribute_array['align'])) {
            $sheet->get_style($column . $row)->get_alignment()->set_horizontal($attribute_array['align']);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_v_align(Worksheet $sheet, int $row, string $column, array $attribute_array): void
    {
        if (isset($attribute_array['valign'])) {
            $sheet->get_style($column . $row)->get_alignment()->set_vertical($attribute_array['valign']);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_data_format(Worksheet $sheet, int $row, string $column, array $attribute_array): void
    {
        if (isset($attribute_array['data-format'])) {
            $sheet->get_style($column . $row)->get_number_format()->set_format_code($attribute_array['data-format']);
        }
    }
    /** @param string[] $attributeArray */
    private function process_dom_element_th_td(Worksheet $sheet, int &$row, string &$column, string &$cell_content, Dom_Element $child, array &$attribute_array): void
    {
        while (isset($this->rowspan[$column . $row])) {
            $temp = $column;
            $column = String_Helper::string_increment($temp);
        }
        $this->process_dom_element($child, $sheet, $row, $column, $cell_content);
        // apply inline style
        $this->apply_inline_style($sheet, $row, $column, $attribute_array);
        /** @var string $cellContent */
        $this->flush_cell($sheet, $column, $row, $cell_content, $attribute_array);
        $this->process_dom_element_bgcolor($sheet, $row, $column, $attribute_array);
        $this->process_dom_element_width($sheet, $column, $attribute_array);
        $this->process_dom_element_height($sheet, $row, $attribute_array);
        $this->process_dom_element_align($sheet, $row, $column, $attribute_array);
        $this->process_dom_element_v_align($sheet, $row, $column, $attribute_array);
        $this->process_dom_element_data_format($sheet, $row, $column, $attribute_array);
        if (isset($attribute_array['rowspan'], $attribute_array['colspan'])) {
            //create merging rowspan and colspan
            $column_to = $column;
            for ($i = 0; $i < (int) $attribute_array['colspan'] - 1; ++$i) {
                String_Helper::string_increment($column_to);
            }
            $range = $column . $row . ':' . $column_to . ($row + (int) $attribute_array['rowspan'] - 1);
            foreach (Coordinate::extract_all_cell_references_in_range($range) as $value) {
                $this->rowspan[$value] = true;
            }
            $sheet->merge_cells($range);
            $column = $column_to;
        } elseif (isset($attribute_array['rowspan'])) {
            //create merging rowspan
            $range = $column . $row . ':' . $column . ($row + (int) $attribute_array['rowspan'] - 1);
            foreach (Coordinate::extract_all_cell_references_in_range($range) as $value) {
                $this->rowspan[$value] = true;
            }
            $sheet->merge_cells($range);
        } elseif (isset($attribute_array['colspan'])) {
            //create merging colspan
            $column_to = $column;
            for ($i = 0; $i < (int) $attribute_array['colspan'] - 1; ++$i) {
                String_Helper::string_increment($column_to);
            }
            $sheet->merge_cells($column . $row . ':' . $column_to . $row);
            $column = $column_to;
        }
        String_Helper::string_increment($column);
    }
    protected function process_dom_element(Dom_Node $element, Worksheet $sheet, int &$row, string &$column, string &$cell_content): void
    {
        foreach ($element->child_nodes as $child) {
            if ($child instanceof Dom_Text) {
                $dom_text = (string) preg_replace('/\s+/', ' ', trim($child->node_value ?? ''));
                if ($dom_text === " ") {
                    $dom_text = '';
                }
                //    simply append the text if the cell content is a plain text string
                $cell_content .= $dom_text;
                //    but if we have a rich text run instead, we need to append it correctly
                //    TODO
            } elseif ($child instanceof Dom_Element) {
                $this->process_dom_element_body($sheet, $row, $column, $cell_content, $child);
            }
        }
    }
    /**
     * Loads PhpSpreadsheet from file into PhpSpreadsheet instance.
     */
    public function load_into_existing(string $filename, Spreadsheet $spreadsheet): Spreadsheet
    {
        // Validate
        if (!$this->can_read($filename)) {
            throw new Exception($filename . ' is an Invalid HTML file.');
        }
        // Create a new DOM object
        $dom = new Dom_Document();
        // Reload the HTML file into the DOM object
        if (is_bool($this->suppress_load_warnings)) {
            $use_errors = libxml_use_internal_errors($this->suppress_load_warnings);
        } else {
            $use_errors = null;
        }
        try {
            $convert = $this->get_security_scanner_or_throw()->scan_file($filename);
            $convert = static::replace_non_ascii_if_needed($convert);
            $loaded = $convert === null ? false : $dom->load_html($convert, LIBXML_NONET);
        } catch (Throwable $e) {
            $loaded = false;
        } finally {
            $this->libxml_messages = libxml_get_errors();
            if (is_bool($use_errors)) {
                libxml_use_internal_errors($use_errors);
            }
        }
        if ($loaded === false) {
            throw new Exception('Failed to load file ' . $filename . ' as a DOM Document', 0, $e ?? null);
        }
        self::load_properties($dom, $spreadsheet);
        return $this->load_document($dom, $spreadsheet);
    }
    private static function load_properties(Dom_Document $dom, Spreadsheet $spreadsheet): void
    {
        $properties = $spreadsheet->get_properties();
        foreach ($dom->get_elements_by_tag_name('meta') as $meta) {
            $meta_content = $meta->get_attribute('content');
            if ($meta_content !== '') {
                $meta_name = $meta->get_attribute('name');
                switch ($meta_name) {
                    case 'author':
                        $properties->set_creator($meta_content);
                        break;
                    case 'category':
                        $properties->set_category($meta_content);
                        break;
                    case 'company':
                        $properties->set_company($meta_content);
                        break;
                    case 'created':
                        $properties->set_created($meta_content);
                        break;
                    case 'description':
                        $properties->set_description($meta_content);
                        break;
                    case 'keywords':
                        $properties->set_keywords($meta_content);
                        break;
                    case 'lastModifiedBy':
                        $properties->set_last_modified_by($meta_content);
                        break;
                    case 'manager':
                        $properties->set_manager($meta_content);
                        break;
                    case 'modified':
                        $properties->set_modified($meta_content);
                        break;
                    case 'subject':
                        $properties->set_subject($meta_content);
                        break;
                    case 'title':
                        $properties->set_title($meta_content);
                        break;
                    case 'viewport':
                        $properties->set_viewport($meta_content);
                        break;
                    default:
                        if (preg_match('/^custom[.](bool|date|float|int|string)[.](.+)$/', $meta_name, $matches) === 1) {
                            match ($matches[1]) {
                                'bool' => $properties->set_custom_property($matches[2], (bool) $meta_content, Properties::PROPERTY_TYPE_BOOLEAN),
                                'float' => $properties->set_custom_property($matches[2], (float) $meta_content, Properties::PROPERTY_TYPE_FLOAT),
                                'int' => $properties->set_custom_property($matches[2], (int) $meta_content, Properties::PROPERTY_TYPE_INTEGER),
                                'date' => $properties->set_custom_property($matches[2], $meta_content, Properties::PROPERTY_TYPE_DATE),
                                // string
                                default => $properties->set_custom_property($matches[2], $meta_content, Properties::PROPERTY_TYPE_STRING),
                            };
                        }
                }
            }
        }
        if (!empty($dom->base_uri)) {
            $properties->set_hyperlink_base($dom->base_uri);
        }
    }
    /** @param string[] $matches */
    private static function replace_non_ascii(array $matches): string
    {
        return '&#' . mb_ord($matches[0], 'UTF-8') . ';';
    }
    /** @internal */
    protected static function replace_non_ascii_if_needed(string $convert): ?string
    {
        if (preg_match(self::STARTS_WITH_BOM, $convert) !== 1 && preg_match(self::DECLARES_CHARSET, $convert) !== 1) {
            $lowend = "";
            $highend = "􏿿";
            $regexp = "/[{$lowend}-{$highend}]/u";
            /** @var callable $callback */
            $callback = self::replace_non_ascii(...);
            $convert = preg_replace_callback($regexp, $callback, $convert);
        }
        return $convert;
    }
    /**
     * Spreadsheet from content.
     */
    public function load_from_string(string $content, ?Spreadsheet $spreadsheet = null): Spreadsheet
    {
        //    Create a new DOM object
        $dom = new Dom_Document();
        //    Reload the HTML file into the DOM object
        if (is_bool($this->suppress_load_warnings)) {
            $use_errors = libxml_use_internal_errors($this->suppress_load_warnings);
        } else {
            $use_errors = null;
        }
        try {
            $convert = $this->get_security_scanner_or_throw()->scan($content);
            $convert = static::replace_non_ascii_if_needed($convert);
            $loaded = $convert === null ? false : $dom->load_html($convert, LIBXML_NONET);
        } catch (Throwable $e) {
            $loaded = false;
        } finally {
            $this->libxml_messages = libxml_get_errors();
            if (is_bool($use_errors)) {
                libxml_use_internal_errors($use_errors);
            }
        }
        if ($loaded === false) {
            throw new Exception('Failed to load content as a DOM Document', 0, $e ?? null);
        }
        $spreadsheet ??= $this->new_spreadsheet();
        $spreadsheet->set_value_binder($this->value_binder);
        self::load_properties($dom, $spreadsheet);
        return $this->load_document($dom, $spreadsheet);
    }
    /**
     * Loads PhpSpreadsheet from DOMDocument into PhpSpreadsheet instance.
     */
    private function load_document(Dom_Document $document, Spreadsheet $spreadsheet): Spreadsheet
    {
        while ($spreadsheet->get_sheet_count() <= $this->sheet_index) {
            $spreadsheet->create_sheet();
        }
        $spreadsheet->set_active_sheet_index($this->sheet_index);
        // Discard white space
        $document->preserve_white_space = false;
        $row = 0;
        $column = 'A';
        $content = '';
        $this->rowspan = [];
        $this->process_dom_element($document, $spreadsheet->get_active_sheet(), $row, $column, $content);
        // Return
        return $spreadsheet;
    }
    /**
     * Get sheet index.
     */
    public function get_sheet_index(): int
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
     * Apply inline css inline style.
     *
     * NOTES :
     * Currently only intended for td & th element,
     * and only takes 'background-color' and 'color'; property with HEX color
     *
     * TODO :
     * - Implement to other properties, such as border
     *
     * @param string[] $attributeArray
     */
    private function apply_inline_style(Worksheet &$sheet, int $row, string $column, array $attribute_array): void
    {
        if (!isset($attribute_array['style'])) {
            return;
        }
        if ($row <= 0 || $column === '') {
            $cell_style = new Style();
        } elseif (isset($attribute_array['rowspan'], $attribute_array['colspan'])) {
            $column_to = $column;
            for ($i = 0; $i < (int) $attribute_array['colspan'] - 1; ++$i) {
                String_Helper::string_increment($column_to);
            }
            $range = $column . $row . ':' . $column_to . ($row + (int) $attribute_array['rowspan'] - 1);
            $cell_style = $sheet->get_style($range);
        } elseif (isset($attribute_array['rowspan'])) {
            $range = $column . $row . ':' . $column . ($row + (int) $attribute_array['rowspan'] - 1);
            $cell_style = $sheet->get_style($range);
        } elseif (isset($attribute_array['colspan'])) {
            $column_to = $column;
            for ($i = 0; $i < (int) $attribute_array['colspan'] - 1; ++$i) {
                String_Helper::string_increment($column_to);
            }
            $range = $column . $row . ':' . $column_to . $row;
            $cell_style = $sheet->get_style($range);
        } else {
            $cell_style = $sheet->get_style($column . $row);
        }
        // add color styles (background & text) from dom element,currently support : td & th, using ONLY inline css style with RGB color
        $styles = explode(';', $attribute_array['style']);
        foreach ($styles as $st) {
            $value = explode(':', $st);
            $style_name = trim($value[0]);
            $style_value = isset($value[1]) ? trim($value[1]) : null;
            $style_value_string = (string) $style_value;
            if (!$style_name) {
                continue;
            }
            switch ($style_name) {
                case 'background':
                case 'background-color':
                    $style_color = $this->get_style_color($style_value_string);
                    if (!$style_color) {
                        continue 2;
                    }
                    $cell_style->apply_from_array(['fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $style_color]]]);
                    break;
                case 'color':
                    $style_color = $this->get_style_color($style_value_string);
                    if (!$style_color) {
                        continue 2;
                    }
                    $cell_style->apply_from_array(['font' => ['color' => ['rgb' => $style_color]]]);
                    break;
                case 'border':
                    $this->set_border_style($cell_style, $style_value_string, 'allBorders');
                    break;
                case 'border-top':
                    $this->set_border_style($cell_style, $style_value_string, 'top');
                    break;
                case 'border-bottom':
                    $this->set_border_style($cell_style, $style_value_string, 'bottom');
                    break;
                case 'border-left':
                    $this->set_border_style($cell_style, $style_value_string, 'left');
                    break;
                case 'border-right':
                    $this->set_border_style($cell_style, $style_value_string, 'right');
                    break;
                case 'font-size':
                    $cell_style->get_font()->set_size((float) $style_value);
                    break;
                case 'direction':
                    if ($style_value === 'rtl') {
                        $cell_style->get_alignment()->set_read_order(Alignment::READORDER_RTL);
                    } elseif ($style_value === 'ltr') {
                        $cell_style->get_alignment()->set_read_order(Alignment::READORDER_LTR);
                    }
                    break;
                case 'font-weight':
                    if ($style_value === 'bold' || $style_value >= 500) {
                        $cell_style->get_font()->set_bold(true);
                    }
                    break;
                case 'font-style':
                    if ($style_value === 'italic') {
                        $cell_style->get_font()->set_italic(true);
                    }
                    break;
                case 'font-family':
                    $cell_style->get_font()->set_name(str_replace('\'', '', $style_value_string));
                    break;
                case 'text-decoration':
                    switch ($style_value) {
                        case 'underline':
                            $cell_style->get_font()->set_underline(Font::UNDERLINE_SINGLE);
                            break;
                        case 'line-through':
                            $cell_style->get_font()->set_strikethrough(true);
                            break;
                    }
                    break;
                case 'text-align':
                    $cell_style->get_alignment()->set_horizontal($style_value_string);
                    break;
                case 'vertical-align':
                    $cell_style->get_alignment()->set_vertical($style_value_string);
                    break;
                case 'width':
                    if ($column !== '') {
                        $sheet->get_column_dimension($column)->set_width((new Css_Dimension($style_value ?? ''))->width());
                    }
                    break;
                case 'height':
                    if ($row > 0) {
                        $sheet->get_row_dimension($row)->set_row_height((new Css_Dimension($style_value ?? ''))->height());
                    }
                    break;
                case 'word-wrap':
                    $cell_style->get_alignment()->set_wrap_text($style_value === 'break-word');
                    break;
                case 'text-indent':
                    $indent_dimension = new Css_Dimension($style_value_string);
                    $indent = $indent_dimension->to_unit(Css_Dimension::UOM_PIXELS);
                    $cell_style->get_alignment()->set_indent((int) ($indent / Alignment::INDENT_UNITS_TO_PIXELS));
                    break;
            }
        }
    }
    /**
     * Check if has #, so we can get clean hex.
     */
    public function get_style_color(?string $value): string
    {
        $value = (string) $value;
        if (str_starts_with($value, '#')) {
            return substr($value, 1);
        }
        return Helper_Html::colour_name_lookup($value);
    }
    /** @param string[] $attributes */
    private function insert_image(Worksheet $sheet, string $column, int $row, array $attributes): void
    {
        if (!isset($attributes['src'])) {
            return;
        }
        $style_array = self::get_style_array($attributes);
        $src = $attributes['src'];
        if (!str_starts_with($src, 'data:')) {
            $src = urldecode($src);
        }
        $width = isset($attributes['width']) ? (float) $attributes['width'] : $style_array['width'] ?? null;
        $height = isset($attributes['height']) ? (float) $attributes['height'] : $style_array['height'] ?? null;
        $name = $attributes['alt'] ?? null;
        $drawing = new Drawing();
        $drawing->set_path($src, false, allowExternal: $this->allow_external_images, isWhitelisted: $this->is_whitelisted);
        if ($drawing->get_path() === '') {
            return;
        }
        $drawing->set_worksheet($sheet);
        $drawing->set_coordinates($column . $row);
        $drawing->set_offset_x(0);
        $drawing->set_offset_y(10);
        $drawing->set_resize_proportional(true);
        if ($name) {
            $drawing->set_name($name);
        }
        /** @var null|scalar $width */
        /** @var null|scalar $height */
        if ($width) {
            if ($height) {
                $drawing->set_width_and_height((int) $width, (int) $height);
            } else {
                $drawing->set_width((int) $width);
            }
        } elseif ($height) {
            $drawing->set_height((int) $height);
        }
        $sheet->get_column_dimension($column)->set_width($drawing->get_width() / 6);
        $sheet->get_row_dimension($row)->set_row_height($drawing->get_height() * 0.9);
        if (isset($style_array['opacity'])) {
            $opacity = $style_array['opacity'];
            if (is_numeric($opacity)) {
                $drawing->set_opacity((int) ($opacity * 100000));
            }
        }
    }
    /**
     * @param string[] $attributes
     *
     * @return mixed[]
     */
    private static function get_style_array(array $attributes): array
    {
        $style_array = [];
        if (isset($attributes['style'])) {
            $styles = explode(';', $attributes['style']);
            foreach ($styles as $style) {
                $value = explode(':', $style);
                if (count($value) === 2) {
                    $array_key = trim($value[0]);
                    $array_value = trim($value[1]);
                    if ($array_key === 'width') {
                        if (str_ends_with($array_value, 'px')) {
                            $array_value = (string) (float) substr($array_value, 0, -2);
                        } else {
                            $array_value = (new Css_Dimension($array_value))->to_unit(Css_Dimension::UOM_PIXELS);
                        }
                    } elseif ($array_key === 'height') {
                        if (str_ends_with($array_value, 'px')) {
                            $array_value = substr($array_value, 0, -2);
                        } else {
                            $array_value = (new Css_Dimension($array_value))->to_unit(Css_Dimension::UOM_PIXELS);
                        }
                    }
                    $style_array[$array_key] = $array_value;
                }
            }
        }
        return $style_array;
    }
    private const BORDER_MAPPINGS = ['dash-dot' => Border::BORDER_DASHDOT, 'dash-dot-dot' => Border::BORDER_DASHDOTDOT, 'dashed' => Border::BORDER_DASHED, 'dotted' => Border::BORDER_DOTTED, 'double' => Border::BORDER_DOUBLE, 'hair' => Border::BORDER_HAIR, 'medium' => Border::BORDER_MEDIUM, 'medium-dashed' => Border::BORDER_MEDIUMDASHED, 'medium-dash-dot' => Border::BORDER_MEDIUMDASHDOT, 'medium-dash-dot-dot' => Border::BORDER_MEDIUMDASHDOTDOT, 'none' => Border::BORDER_NONE, 'slant-dash-dot' => Border::BORDER_SLANTDASHDOT, 'solid' => Border::BORDER_THIN, 'thick' => Border::BORDER_THICK];
    /** @return array<string, string> */
    public static function get_border_mappings(): array
    {
        return self::BORDER_MAPPINGS;
    }
    /**
     * Map html border style to PhpSpreadsheet border style.
     */
    public function get_border_style(string $style): ?string
    {
        return self::BORDER_MAPPINGS[$style] ?? null;
    }
    private function set_border_style(Style $cell_style, string $style_value, string $type): void
    {
        if (trim($style_value) === Border::BORDER_NONE) {
            $border_style = Border::BORDER_NONE;
            $color = null;
        } else {
            $border_array = explode(' ', $style_value);
            $border_count = count($border_array);
            if ($border_count >= 3) {
                $border_style = $border_array[1];
                $color = $border_array[2];
            } else {
                $border_style = $border_array[0];
                $color = $border_array[1] ?? null;
            }
        }
        $cell_style->apply_from_array(['borders' => [$type => ['borderStyle' => $this->get_border_style($border_style), 'color' => ['rgb' => $this->get_style_color($color)]]]]);
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function list_worksheet_info(string $filename): array
    {
        $info = [];
        $spreadsheet = $this->new_spreadsheet();
        $this->load_into_existing($filename, $spreadsheet);
        foreach ($spreadsheet->get_all_sheets() as $sheet) {
            $new_entry = ['worksheetName' => $sheet->get_title()];
            $new_entry['lastColumnLetter'] = $sheet->get_highest_data_column();
            $new_entry['lastColumnIndex'] = Coordinate::column_index_from_string($sheet->get_highest_data_column()) - 1;
            $new_entry['totalRows'] = $sheet->get_highest_data_row();
            $new_entry['totalColumns'] = $new_entry['lastColumnIndex'] + 1;
            $new_entry['sheetState'] = Worksheet::SHEETSTATE_VISIBLE;
            $info[] = $new_entry;
        }
        $spreadsheet->disconnect_worksheets();
        return $info;
    }
}