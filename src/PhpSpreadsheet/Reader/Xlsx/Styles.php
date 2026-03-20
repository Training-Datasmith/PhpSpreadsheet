<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Border;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Style\Fill;
use Php_Office\Php_Spreadsheet\Style\Font;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Php_Office\Php_Spreadsheet\Style\Protection;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Table\Table_Dxfs_Style;
use Simple_Xml_Element;
use stdClass;
class Styles extends Base_Parser_Class
{
    /**
     * Theme instance.
     */
    private ?Theme $theme = null;
    /** @var string[] */
    private array $workbook_palette = [];
    /** @var mixed[] */
    private array $styles = [];
    /** @var array<SimpleXMLElement|stdClass> */
    private array $cell_styles = [];
    private Simple_Xml_Element $style_xml;
    private string $namespace = '';
    /** @var array<string, int> */
    private array $font_charsets = [];
    /** @return array<string, int> */
    public function get_font_charsets(): array
    {
        return $this->font_charsets;
    }
    public function set_namespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }
    /** @param string[] $palette */
    public function set_workbook_palette(array $palette): void
    {
        $this->workbook_palette = $palette;
    }
    private function get_style_attributes(Simple_Xml_Element $value): Simple_Xml_Element
    {
        $attr = $value->attributes('');
        if ($attr === null || count($attr) === 0) {
            $attr = $value->attributes($this->namespace);
        }
        return Xlsx::test_simple_xml($attr);
    }
    public function set_style_xml(Simple_Xml_Element $style_xml): void
    {
        $this->style_xml = $style_xml;
    }
    public function set_theme(Theme $theme): void
    {
        $this->theme = $theme;
    }
    /**
     * @param mixed[] $styles
     * @param array<SimpleXMLElement|stdClass> $cellStyles
     */
    public function set_style_base_data(?Theme $theme = null, array $styles = [], array $cell_styles = []): void
    {
        $this->theme = $theme;
        $this->styles = $styles;
        $this->cell_styles = $cell_styles;
    }
    public function read_font_style(Font $font_style, Simple_Xml_Element $font_style_xml): void
    {
        if (isset($font_style_xml->name)) {
            $attr = $this->get_style_attributes($font_style_xml->name);
            if (isset($attr['val'])) {
                $font_style->set_name((string) $attr['val']);
            }
            if (isset($font_style_xml->charset)) {
                $charset_attr = $this->get_style_attributes($font_style_xml->charset);
                if (isset($charset_attr['val'])) {
                    $charset_val = (int) $charset_attr['val'];
                    $this->font_charsets[$font_style->get_name()] = $charset_val;
                }
            }
        }
        if (isset($font_style_xml->sz)) {
            $attr = $this->get_style_attributes($font_style_xml->sz);
            if (isset($attr['val'])) {
                $font_style->set_size((float) $attr['val']);
            }
        }
        if (isset($font_style_xml->b)) {
            $attr = $this->get_style_attributes($font_style_xml->b);
            $font_style->set_bold(!isset($attr['val']) || self::boolean((string) $attr['val']));
        }
        if (isset($font_style_xml->i)) {
            $attr = $this->get_style_attributes($font_style_xml->i);
            $font_style->set_italic(!isset($attr['val']) || self::boolean((string) $attr['val']));
        }
        if (isset($font_style_xml->strike)) {
            $attr = $this->get_style_attributes($font_style_xml->strike);
            $font_style->set_strikethrough(!isset($attr['val']) || self::boolean((string) $attr['val']));
        }
        $font_style->get_color()->set_argb($this->read_color($font_style_xml->color));
        $theme = $this->read_color_theme($font_style_xml->color);
        if ($theme >= 0) {
            $font_style->get_color()->set_theme($theme);
        }
        if (isset($font_style_xml->u)) {
            $attr = $this->get_style_attributes($font_style_xml->u);
            if (!isset($attr['val'])) {
                $font_style->set_underline(Font::UNDERLINE_SINGLE);
            } else {
                $font_style->set_underline((string) $attr['val']);
            }
        }
        if (isset($font_style_xml->vert_align)) {
            $attr = $this->get_style_attributes($font_style_xml->vert_align);
            if (isset($attr['val'])) {
                $vertical_align = strtolower((string) $attr['val']);
                if ($vertical_align === 'superscript') {
                    $font_style->set_superscript(true);
                } elseif ($vertical_align === 'subscript') {
                    $font_style->set_subscript(true);
                }
            }
        }
        if (isset($font_style_xml->scheme)) {
            $attr = $this->get_style_attributes($font_style_xml->scheme);
            $font_style->set_scheme((string) $attr['val']);
        }
        if (isset($font_style_xml->auto)) {
            $attr = $this->get_style_attributes($font_style_xml->auto);
            if (isset($attr['val'])) {
                $font_style->set_auto_color(self::boolean((string) $attr['val']));
            }
        }
    }
    private function read_number_format(Number_Format $numfmt_style, Simple_Xml_Element $numfmt_style_xml): void
    {
        if ((string) $numfmt_style_xml['formatCode'] !== '') {
            $numfmt_style->set_format_code(self::format_general((string) $numfmt_style_xml['formatCode']));
            return;
        }
        $numfmt = $this->get_style_attributes($numfmt_style_xml);
        if (isset($numfmt['formatCode'])) {
            $numfmt_style->set_format_code(self::format_general((string) $numfmt['formatCode']));
        }
    }
    public function read_fill_style(Fill $fill_style, Simple_Xml_Element $fill_style_xml): void
    {
        if ($fill_style_xml->gradient_fill) {
            /** @var SimpleXMLElement $gradientFill */
            $gradient_fill = $fill_style_xml->gradient_fill[0];
            $attr = $this->get_style_attributes($gradient_fill);
            if (!empty($attr['type'])) {
                $fill_style->set_fill_type((string) $attr['type']);
            }
            $fill_style->set_rotation((float) $attr['degree']);
            $gradient_fill->register_x_path_namespace('sml', Namespaces::MAIN);
            $fill_style->get_start_color()->set_argb($this->read_color(self::get_array_item($gradient_fill->xpath('sml:stop[@position=0]'))->color));
            //* @phpstan-ignore-line
            $fill_style->get_end_color()->set_argb($this->read_color(self::get_array_item($gradient_fill->xpath('sml:stop[@position=1]'))->color));
            //* @phpstan-ignore-line
        } elseif ($fill_style_xml->pattern_fill) {
            $default_fill_style = $fill_style->get_fill_type() !== null ? Fill::FILL_NONE : '';
            $fg_found = false;
            $bg_found = false;
            if ($fill_style_xml->pattern_fill->fg_color) {
                $fill_style->get_start_color()->set_argb($this->read_color($fill_style_xml->pattern_fill->fg_color, true));
                if ($fill_style->get_fill_type() !== null) {
                    $default_fill_style = Fill::FILL_SOLID;
                }
                $fg_found = true;
            }
            if ($fill_style_xml->pattern_fill->bg_color) {
                $fill_style->get_end_color()->set_argb($this->read_color($fill_style_xml->pattern_fill->bg_color, true));
                if ($fill_style->get_fill_type() !== null) {
                    $default_fill_style = Fill::FILL_SOLID;
                }
                $bg_found = true;
            }
            $type = '';
            if ((string) $fill_style_xml->pattern_fill['patternType'] !== '') {
                $type = (string) $fill_style_xml->pattern_fill['patternType'];
            } else {
                $attr = $this->get_style_attributes($fill_style_xml->pattern_fill);
                $type = (string) $attr['patternType'];
            }
            $pattern_type = $type === '' ? $default_fill_style : $type;
            $fill_style->set_fill_type($pattern_type);
            if (!$fg_found && !in_array($pattern_type, [Fill::FILL_NONE, Fill::FILL_SOLID], true) && $fill_style->get_start_color()->get_argb()) {
                $fill_style->get_start_color()->set_argb('', true);
            }
            if (!$bg_found && !in_array($pattern_type, [Fill::FILL_NONE, Fill::FILL_SOLID], true) && $fill_style->get_end_color()->get_argb()) {
                $fill_style->get_end_color()->set_argb('', true);
            }
        }
    }
    public function read_border_style(Borders $border_style, Simple_Xml_Element $border_style_xml): void
    {
        $diagonal_up = $this->get_attribute($border_style_xml, 'diagonalUp');
        $diagonal_up = self::boolean($diagonal_up);
        $diagonal_down = $this->get_attribute($border_style_xml, 'diagonalDown');
        $diagonal_down = self::boolean($diagonal_down);
        if ($diagonal_up === false) {
            if ($diagonal_down === false) {
                $border_style->set_diagonal_direction(Borders::DIAGONAL_NONE);
            } else {
                $border_style->set_diagonal_direction(Borders::DIAGONAL_DOWN);
            }
        } elseif ($diagonal_down === false) {
            $border_style->set_diagonal_direction(Borders::DIAGONAL_UP);
        } else {
            $border_style->set_diagonal_direction(Borders::DIAGONAL_BOTH);
        }
        if (isset($border_style_xml->left)) {
            $this->read_border($border_style->get_left(), $border_style_xml->left);
        }
        if (isset($border_style_xml->right)) {
            $this->read_border($border_style->get_right(), $border_style_xml->right);
        }
        if (isset($border_style_xml->top)) {
            $this->read_border($border_style->get_top(), $border_style_xml->top);
        }
        if (isset($border_style_xml->bottom)) {
            $this->read_border($border_style->get_bottom(), $border_style_xml->bottom);
        }
        if (isset($border_style_xml->diagonal)) {
            $this->read_border($border_style->get_diagonal(), $border_style_xml->diagonal);
        }
    }
    private function get_attribute(Simple_Xml_Element $xml, string $attribute): string
    {
        $style = '';
        if ((string) $xml[$attribute] !== '') {
            $style = (string) $xml[$attribute];
        } else {
            $attr = $this->get_style_attributes($xml);
            if (isset($attr[$attribute])) {
                $style = (string) $attr[$attribute];
            }
        }
        return $style;
    }
    private function read_border(Border $border, Simple_Xml_Element $border_xml): void
    {
        $style = $this->get_attribute($border_xml, 'style');
        if ($style !== '') {
            $border->set_border_style($style);
        } else {
            $border->set_border_style(Border::BORDER_NONE);
        }
        if (isset($border_xml->color)) {
            $border->get_color()->set_argb($this->read_color($border_xml->color));
        }
    }
    public function read_alignment_style(Alignment $alignment, Simple_Xml_Element $alignment_xml): void
    {
        $horizontal = $this->get_attribute($alignment_xml, 'horizontal');
        if ($horizontal !== '') {
            $alignment->set_horizontal($horizontal);
        }
        $justify_last_line = $this->get_attribute($alignment_xml, 'justifyLastLine');
        if ($justify_last_line !== '') {
            $alignment->set_justify_last_line(self::boolean($justify_last_line));
        }
        $vertical = $this->get_attribute($alignment_xml, 'vertical');
        if ($vertical !== '') {
            $alignment->set_vertical($vertical);
        }
        $text_rotation = (int) $this->get_attribute($alignment_xml, 'textRotation');
        if ($text_rotation > 90) {
            $text_rotation = 90 - $text_rotation;
        }
        $alignment->set_text_rotation($text_rotation);
        $wrap_text = $this->get_attribute($alignment_xml, 'wrapText');
        $alignment->set_wrap_text(self::boolean($wrap_text));
        $shrink_to_fit = $this->get_attribute($alignment_xml, 'shrinkToFit');
        $alignment->set_shrink_to_fit(self::boolean($shrink_to_fit));
        $indent = (int) $this->get_attribute($alignment_xml, 'indent');
        $alignment->set_indent(max($indent, 0));
        $reading_order = (int) $this->get_attribute($alignment_xml, 'readingOrder');
        $alignment->set_read_order(max($reading_order, 0));
    }
    private static function format_general(string $format_string): string
    {
        if ($format_string === 'GENERAL') {
            return Number_Format::FORMAT_GENERAL;
        }
        return $format_string;
    }
    /**
     * Read style.
     */
    public function read_style(Style $doc_style, Simple_Xml_Element|stdClass $style): void
    {
        if ($style instanceof Simple_Xml_Element) {
            $this->read_number_format($doc_style->get_number_format(), $style->num_fmt);
        } else {
            /** @var SimpleXMLElement */
            $temp = $style->num_fmt;
            $doc_style->get_number_format()->set_format_code(self::format_general((string) $temp));
        }
        /** @var SimpleXMLElement $style */
        if (isset($style->font)) {
            $this->read_font_style($doc_style->get_font(), $style->font);
        }
        if (isset($style->fill)) {
            $this->read_fill_style($doc_style->get_fill(), $style->fill);
        }
        if (isset($style->border)) {
            $this->read_border_style($doc_style->get_borders(), $style->border);
        }
        if (isset($style->alignment)) {
            $this->read_alignment_style($doc_style->get_alignment(), $style->alignment);
        }
        // protection
        if (isset($style->protection)) {
            $this->read_protection_locked($doc_style, $style->protection);
            $this->read_protection_hidden($doc_style, $style->protection);
        }
        // top-level style settings
        if (isset($style->quote_prefix)) {
            $doc_style->set_quote_prefix((bool) $style->quote_prefix);
        }
    }
    /**
     * Read protection locked attribute.
     */
    public function read_protection_locked(Style $doc_style, Simple_Xml_Element $style): void
    {
        $locked = '';
        if ((string) $style['locked'] !== '') {
            $locked = (string) $style['locked'];
        } else {
            $attr = $this->get_style_attributes($style);
            if (isset($attr['locked'])) {
                $locked = (string) $attr['locked'];
            }
        }
        if ($locked !== '') {
            if (self::boolean($locked)) {
                $doc_style->get_protection()->set_locked(Protection::PROTECTION_PROTECTED);
            } else {
                $doc_style->get_protection()->set_locked(Protection::PROTECTION_UNPROTECTED);
            }
        }
    }
    /**
     * Read protection hidden attribute.
     */
    public function read_protection_hidden(Style $doc_style, Simple_Xml_Element $style): void
    {
        $hidden = '';
        if ((string) $style['hidden'] !== '') {
            $hidden = (string) $style['hidden'];
        } else {
            $attr = $this->get_style_attributes($style);
            if (isset($attr['hidden'])) {
                $hidden = (string) $attr['hidden'];
            }
        }
        if ($hidden !== '') {
            if (self::boolean($hidden)) {
                $doc_style->get_protection()->set_hidden(Protection::PROTECTION_PROTECTED);
            } else {
                $doc_style->get_protection()->set_hidden(Protection::PROTECTION_UNPROTECTED);
            }
        }
    }
    public function read_color_theme(Simple_Xml_Element $color): int
    {
        $attr = $this->get_style_attributes($color);
        if (isset($attr['theme']) && is_numeric((string) $attr['theme']) && !isset($attr['tint'])) {
            return (int) $attr['theme'];
        }
        return -1;
    }
    public function read_color(Simple_Xml_Element $color, bool $background = false): string
    {
        $attr = $this->get_style_attributes($color);
        if (isset($attr['rgb'])) {
            return (string) $attr['rgb'];
        }
        if (isset($attr['indexed'])) {
            $indexed_color = (int) $attr['indexed'];
            if ($indexed_color >= count($this->workbook_palette)) {
                return Color::indexed_color($indexed_color - 7, $background)->get_argb() ?? '';
            }
            return Color::indexed_color($indexed_color, $background, $this->workbook_palette)->get_argb() ?? '';
        }
        if (isset($attr['theme'])) {
            if ($this->theme !== null) {
                $return_colour = $this->theme->get_colour_by_index((int) $attr['theme']);
                if (isset($attr['tint'])) {
                    $tint_adjust = (float) $attr['tint'];
                    $return_colour = Color::change_brightness($return_colour ?? '', $tint_adjust);
                }
                return 'FF' . $return_colour;
            }
        }
        return $background ? 'FFFFFFFF' : 'FF000000';
    }
    /** @return Style[] */
    public function dxfs(bool $read_data_only = false): array
    {
        $dxfs = [];
        if (!$read_data_only && $this->style_xml) {
            //    Conditional Styles
            if ($this->style_xml->dxfs) {
                foreach ($this->style_xml->dxfs->dxf as $dxf) {
                    $style = new Style(false, true);
                    $this->read_style($style, $dxf);
                    $dxfs[] = $style;
                }
            }
            //    Cell Styles
            if ($this->style_xml->cell_styles) {
                foreach ($this->style_xml->cell_styles->cell_style as $cell_stylex) {
                    $cell_style = Xlsx::get_attributes($cell_stylex);
                    if ((int) $cell_style['builtinId'] == 0) {
                        if (isset($this->cell_styles[(int) $cell_style['xfId']])) {
                            // Set default style
                            $style = new Style();
                            $this->read_style($style, $this->cell_styles[(int) $cell_style['xfId']]);
                            // normal style, currently not using it for anything
                        }
                    }
                }
            }
        }
        return $dxfs;
    }
    /** @return TableDxfsStyle[] */
    public function table_styles(bool $read_data_only = false): array
    {
        $table_styles = [];
        if (!$read_data_only && $this->style_xml) {
            //    Conditional Styles
            if ($this->style_xml->table_styles) {
                foreach ($this->style_xml->table_styles->table_style as $s) {
                    $attrs = Xlsx::get_attributes($s);
                    if (isset($attrs['name'][0])) {
                        $style = new Table_Dxfs_Style((string) $attrs['name'][0]);
                        foreach ($s->table_style_element as $e) {
                            $a = Xlsx::get_attributes($e);
                            if (isset($a['dxfId'][0], $a['type'][0])) {
                                switch ($a['type'][0]) {
                                    case 'headerRow':
                                        $style->set_header_row((int) $a['dxfId'][0]);
                                        break;
                                    case 'firstRowStripe':
                                        $style->set_first_row_stripe((int) $a['dxfId'][0]);
                                        break;
                                    case 'secondRowStripe':
                                        $style->set_second_row_stripe((int) $a['dxfId'][0]);
                                        break;
                                    default:
                                }
                            }
                        }
                        $table_styles[] = $style;
                    }
                }
            }
        }
        return $table_styles;
    }
    /** @return mixed[] */
    public function styles(): array
    {
        return $this->styles;
    }
    /**
     * Get array item.
     *
     * @param false|mixed[] $array (usually array, in theory can be false)
     */
    private static function get_array_item(mixed $array): ?Simple_Xml_Element
    {
        return is_array($array) ? $array[0] ?? null : null;
        // @phpstan-ignore-line
    }
}