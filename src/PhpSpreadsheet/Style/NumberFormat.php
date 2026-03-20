<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
class Number_Format extends Supervisor
{
    // Pre-defined formats
    public const FORMAT_GENERAL = 'General';
    public const FORMAT_TEXT = '@';
    public const FORMAT_NUMBER = '0';
    public const FORMAT_NUMBER_0 = '0.0';
    public const FORMAT_NUMBER_00 = '0.00';
    public const FORMAT_NUMBER_COMMA_SEPARATED1 = '#,##0.00';
    public const FORMAT_NUMBER_COMMA_SEPARATED2 = '#,##0.00_-';
    public const FORMAT_PERCENTAGE = '0%';
    public const FORMAT_PERCENTAGE_0 = '0.0%';
    public const FORMAT_PERCENTAGE_00 = '0.00%';
    public const FORMAT_DATE_YYYYMMDD = 'yyyy-mm-dd';
    public const FORMAT_DATE_DDMMYYYY = 'dd/mm/yyyy';
    public const FORMAT_DATE_DMYSLASH = 'd"/"m"/"yy';
    public const FORMAT_DATE_DMYMINUS = 'd-m-yy';
    public const FORMAT_DATE_DMMINUS = 'd-m';
    public const FORMAT_DATE_MYMINUS = 'm-yy';
    public const FORMAT_DATE_XLSX14 = 'mm-dd-yy';
    public const FORMAT_DATE_XLSX14_ACTUAL = 'm/d/yyyy';
    public const FORMAT_DATE_XLSX15 = 'd-mmm-yy';
    public const FORMAT_DATE_XLSX15_YYYY = 'd-mmm-yyyy';
    public const FORMAT_DATE_XLSX16 = 'd-mmm';
    public const FORMAT_DATE_XLSX17 = 'mmm-yy';
    public const FORMAT_DATE_XLSX22 = 'm/d/yy h:mm';
    public const FORMAT_DATE_XLSX22_ACTUAL = 'm/d/yyyy h:mm';
    public const FORMAT_DATE_DATETIME = 'd/m/yy h:mm';
    public const FORMAT_DATE_DATETIME_BETTER = 'yyyy-mm-dd hh:mm';
    public const FORMAT_DATE_TIME1 = 'h:mm AM/PM';
    public const FORMAT_DATE_TIME2 = 'h:mm:ss AM/PM';
    public const FORMAT_DATE_TIME3 = 'h:mm';
    public const FORMAT_DATE_TIME4 = 'h:mm:ss';
    public const FORMAT_DATE_TIME5 = 'mm:ss';
    public const FORMAT_DATE_TIME6 = 'h:mm:ss';
    public const FORMAT_DATE_TIME7 = 'i:s.S';
    public const FORMAT_DATE_TIME8 = 'h:mm:ss;@';
    public const FORMAT_DATE_TIME_INTERVAL_HMS = '[hh]:mm:ss';
    public const FORMAT_DATE_YYYYMMDDSLASH = 'yyyy"/"mm"/"dd;@';
    public const FORMAT_DATE_LONG_DATE = 'dddd, mmmm d, yyyy';
    public const DATE_TIME_OR_DATETIME_ARRAY = [self::FORMAT_DATE_YYYYMMDD, self::FORMAT_DATE_DDMMYYYY, self::FORMAT_DATE_DMYSLASH, self::FORMAT_DATE_DMYMINUS, self::FORMAT_DATE_DMMINUS, self::FORMAT_DATE_MYMINUS, self::FORMAT_DATE_XLSX14, self::FORMAT_DATE_XLSX14_ACTUAL, self::FORMAT_DATE_XLSX15, self::FORMAT_DATE_XLSX16, self::FORMAT_DATE_XLSX17, self::FORMAT_DATE_XLSX22, self::FORMAT_DATE_XLSX22_ACTUAL, self::FORMAT_DATE_DATETIME, self::FORMAT_DATE_DATETIME_BETTER, self::FORMAT_DATE_TIME1, self::FORMAT_DATE_TIME2, self::FORMAT_DATE_TIME3, self::FORMAT_DATE_TIME4, self::FORMAT_DATE_TIME5, self::FORMAT_DATE_TIME6, self::FORMAT_DATE_TIME7, self::FORMAT_DATE_TIME8, self::FORMAT_DATE_TIME_INTERVAL_HMS, self::FORMAT_DATE_YYYYMMDDSLASH, self::FORMAT_DATE_LONG_DATE];
    public const TIME_OR_DATETIME_ARRAY = [self::FORMAT_DATE_XLSX22, self::FORMAT_DATE_DATETIME, self::FORMAT_DATE_DATETIME_BETTER, self::FORMAT_DATE_TIME1, self::FORMAT_DATE_TIME2, self::FORMAT_DATE_TIME3, self::FORMAT_DATE_TIME4, self::FORMAT_DATE_TIME5, self::FORMAT_DATE_TIME6, self::FORMAT_DATE_TIME7, self::FORMAT_DATE_TIME8, self::FORMAT_DATE_TIME_INTERVAL_HMS];
    private const FORMAT_CURRENCY_AMOUNT_INTEGER = '#,##0_-';
    private const FORMAT_CURRENCY_AMOUNT_FLOAT = '#,##0.00_-';
    public const FORMAT_CURRENCY_USD_INTEGER = '$' . self::FORMAT_CURRENCY_AMOUNT_INTEGER;
    public const FORMAT_CURRENCY_USD = '$' . self::FORMAT_CURRENCY_AMOUNT_FLOAT;
    public const FORMAT_CURRENCY_GBP_INTEGER = '£' . self::FORMAT_CURRENCY_AMOUNT_INTEGER;
    public const FORMAT_CURRENCY_GBP = '£' . self::FORMAT_CURRENCY_AMOUNT_FLOAT;
    public const FORMAT_CURRENCY_YEN_YUAN_INTEGER = '￥' . self::FORMAT_CURRENCY_AMOUNT_INTEGER;
    public const FORMAT_CURRENCY_YEN_YUAN = '￥' . self::FORMAT_CURRENCY_AMOUNT_FLOAT;
    public const FORMAT_CURRENCY_EUR_INTEGER = '#,##0_-[$€]';
    public const FORMAT_CURRENCY_EUR = '#,##0.00_-[$€]';
    public const FORMAT_ACCOUNTING_USD = '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)';
    public const FORMAT_ACCOUNTING_EUR = '_("€"* #,##0.00_);_("€"* \(#,##0.00\);_("€"* "-"??_);_(@_)';
    public const SHORT_DATE_INDEX = 14;
    public const DATE_TIME_INDEX = 22;
    public const FORMAT_SYSDATE_X = '[$-x-sysdate]';
    public const FORMAT_SYSDATE_F800 = '[$-F800]';
    public const FORMAT_SYSTIME_X = '[$-x-systime]';
    public const FORMAT_SYSTIME_F400 = '[$-F400]';
    protected static string $short_date_format = self::FORMAT_DATE_XLSX14_ACTUAL;
    protected static string $long_date_format = self::FORMAT_DATE_LONG_DATE;
    protected static string $date_time_format = self::FORMAT_DATE_XLSX22_ACTUAL;
    protected static string $time_format = self::FORMAT_DATE_TIME2;
    /**
     * Excel built-in number formats.
     *
     * @var string[]
     */
    protected static array $built_in_formats;
    /**
     * Excel built-in number formats (flipped, for faster lookups).
     *
     * @var int[]
     */
    protected static array $flipped_built_in_formats;
    /**
     * Format Code.
     */
    protected ?string $format_code = self::FORMAT_GENERAL;
    /**
     * Built-in format Code.
     *
     * @var false|int
     */
    protected $built_in_format_code = 0;
    /**
     * Create a new NumberFormat.
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
        if ($is_conditional) {
            $this->format_code = null;
            $this->built_in_format_code = false;
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
        return $parent->get_shared_component()->get_number_format();
    }
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return array{numberFormat: mixed[]}
     */
    public function get_style_array(array $array): array
    {
        return ['numberFormat' => $array];
    }
    /**
     * Apply styles from array.
     *
     * <code>
     * $spreadsheet->getActiveSheet()->getStyle('B2')->getNumberFormat()->applyFromArray(
     *     [
     *         'formatCode' => NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE
     *     ]
     * );
     * </code>
     *
     * @param string[] $styleArray Array containing style information
     *
     * @return $this
     */
    public function apply_from_array(array $style_array): static
    {
        if ($this->is_supervisor) {
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($this->get_style_array($style_array));
        } else if (isset($style_array['formatCode'])) {
            $this->set_format_code($style_array['formatCode']);
        }
        return $this;
    }
    /**
     * Get Format Code.
     */
    public function get_format_code(bool $extended = false): ?string
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_format_code($extended);
        }
        $builtin = $this->get_built_in_format_code();
        if (is_int($builtin)) {
            if ($extended) {
                if ($builtin === self::SHORT_DATE_INDEX) {
                    return self::$short_date_format;
                }
                if ($builtin === self::DATE_TIME_INDEX) {
                    return self::$date_time_format;
                }
            }
            return self::built_in_format_code($builtin);
        }
        return $extended ? self::convert_system_formats($this->format_code) : $this->format_code;
    }
    public static function convert_system_formats(?string $format_code): ?string
    {
        if (is_string($format_code)) {
            if (stripos($format_code, self::FORMAT_SYSDATE_F800) !== false || stripos($format_code, self::FORMAT_SYSDATE_X) !== false) {
                return self::$long_date_format;
            }
            if (stripos($format_code, self::FORMAT_SYSTIME_F400) !== false || stripos($format_code, self::FORMAT_SYSTIME_X) !== false) {
                return self::$time_format;
            }
        }
        return $format_code;
    }
    /**
     * Set Format Code.
     *
     * @param string $formatCode see self::FORMAT_*
     *
     * @return $this
     */
    public function set_format_code(string $format_code): static
    {
        if ($format_code == '') {
            $format_code = self::FORMAT_GENERAL;
        }
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['formatCode' => $format_code]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->format_code = $format_code;
            $this->built_in_format_code = self::built_in_format_code_index($format_code);
        }
        return $this;
    }
    /**
     * Get Built-In Format Code.
     *
     * @return false|int
     */
    public function get_built_in_format_code()
    {
        if ($this->is_supervisor) {
            return $this->get_shared_component()->get_built_in_format_code();
        }
        return $this->built_in_format_code;
    }
    /**
     * Set Built-In Format Code.
     *
     * @param int $formatCodeIndex Id of the built-in format code to use
     *
     * @return $this
     */
    public function set_built_in_format_code(int $format_code_index): static
    {
        if ($this->is_supervisor) {
            $style_array = $this->get_style_array(['formatCode' => self::built_in_format_code($format_code_index)]);
            $this->get_active_sheet()->get_style($this->get_selected_cells())->apply_from_array($style_array);
        } else {
            $this->built_in_format_code = $format_code_index;
            $this->format_code = self::built_in_format_code($format_code_index);
        }
        return $this;
    }
    /**
     * Fill built-in format codes.
     */
    private static function fill_built_in_format_codes(): void
    {
        //  [MS-OI29500: Microsoft Office Implementation Information for ISO/IEC-29500 Standard Compliance]
        //  18.8.30. numFmt (Number Format)
        //
        //  The ECMA standard defines built-in format IDs
        //      14: "mm-dd-yy"
        //      22: "m/d/yy h:mm"
        //      37: "#,##0 ;(#,##0)"
        //      38: "#,##0 ;[Red](#,##0)"
        //      39: "#,##0.00;(#,##0.00)"
        //      40: "#,##0.00;[Red](#,##0.00)"
        //      47: "mmss.0"
        //      KOR fmt 55: "yyyy-mm-dd"
        //  Excel defines built-in format IDs
        //      14: "m/d/yyyy"
        //      22: "m/d/yyyy h:mm"
        //      37: "#,##0_);(#,##0)"
        //      38: "#,##0_);[Red](#,##0)"
        //      39: "#,##0.00_);(#,##0.00)"
        //      40: "#,##0.00_);[Red](#,##0.00)"
        //      47: "mm:ss.0"
        //      KOR fmt 55: "yyyy/mm/dd"
        // Built-in format codes
        if (empty(self::$built_in_formats)) {
            self::$built_in_formats = [];
            // General
            self::$built_in_formats[0] = self::FORMAT_GENERAL;
            self::$built_in_formats[1] = '0';
            self::$built_in_formats[2] = '0.00';
            self::$built_in_formats[3] = '#,##0';
            self::$built_in_formats[4] = '#,##0.00';
            self::$built_in_formats[9] = '0%';
            self::$built_in_formats[10] = '0.00%';
            self::$built_in_formats[11] = '0.00E+00';
            self::$built_in_formats[12] = '# ?/?';
            self::$built_in_formats[13] = '# ??/??';
            self::$built_in_formats[14] = self::FORMAT_DATE_XLSX14_ACTUAL;
            // Despite ECMA 'mm-dd-yy';
            self::$built_in_formats[15] = self::FORMAT_DATE_XLSX15;
            self::$built_in_formats[16] = 'd-mmm';
            self::$built_in_formats[17] = 'mmm-yy';
            self::$built_in_formats[18] = 'h:mm AM/PM';
            self::$built_in_formats[19] = 'h:mm:ss AM/PM';
            self::$built_in_formats[20] = 'h:mm';
            self::$built_in_formats[21] = 'h:mm:ss';
            self::$built_in_formats[22] = self::FORMAT_DATE_XLSX22_ACTUAL;
            // Despite ECMA 'm/d/yy h:mm';
            self::$built_in_formats[37] = '#,##0_);(#,##0)';
            //  Despite ECMA '#,##0 ;(#,##0)';
            self::$built_in_formats[38] = '#,##0_);[Red](#,##0)';
            //  Despite ECMA '#,##0 ;[Red](#,##0)';
            self::$built_in_formats[39] = '#,##0.00_);(#,##0.00)';
            //  Despite ECMA '#,##0.00;(#,##0.00)';
            self::$built_in_formats[40] = '#,##0.00_);[Red](#,##0.00)';
            //  Despite ECMA '#,##0.00;[Red](#,##0.00)';
            self::$built_in_formats[44] = '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)';
            self::$built_in_formats[45] = 'mm:ss';
            self::$built_in_formats[46] = '[h]:mm:ss';
            self::$built_in_formats[47] = 'mm:ss.0';
            //  Despite ECMA 'mmss.0';
            self::$built_in_formats[48] = '##0.0E+0';
            self::$built_in_formats[49] = '@';
            // CHT
            self::$built_in_formats[27] = '[$-404]e/m/d';
            self::$built_in_formats[30] = 'm/d/yy';
            self::$built_in_formats[36] = '[$-404]e/m/d';
            self::$built_in_formats[50] = '[$-404]e/m/d';
            self::$built_in_formats[57] = '[$-404]e/m/d';
            // THA
            self::$built_in_formats[59] = 't0';
            self::$built_in_formats[60] = 't0.00';
            self::$built_in_formats[61] = 't#,##0';
            self::$built_in_formats[62] = 't#,##0.00';
            self::$built_in_formats[67] = 't0%';
            self::$built_in_formats[68] = 't0.00%';
            self::$built_in_formats[69] = 't# ?/?';
            self::$built_in_formats[70] = 't# ??/??';
            // JPN
            self::$built_in_formats[28] = '[$-411]ggge"年"m"月"d"日"';
            self::$built_in_formats[29] = '[$-411]ggge"年"m"月"d"日"';
            self::$built_in_formats[31] = 'yyyy"年"m"月"d"日"';
            self::$built_in_formats[32] = 'h"時"mm"分"';
            self::$built_in_formats[33] = 'h"時"mm"分"ss"秒"';
            self::$built_in_formats[34] = 'yyyy"年"m"月"';
            self::$built_in_formats[35] = 'm"月"d"日"';
            self::$built_in_formats[51] = '[$-411]ggge"年"m"月"d"日"';
            self::$built_in_formats[52] = 'yyyy"年"m"月"';
            self::$built_in_formats[53] = 'm"月"d"日"';
            self::$built_in_formats[54] = '[$-411]ggge"年"m"月"d"日"';
            self::$built_in_formats[55] = 'yyyy"年"m"月"';
            self::$built_in_formats[56] = 'm"月"d"日"';
            self::$built_in_formats[58] = '[$-411]ggge"年"m"月"d"日"';
            // Flip array (for faster lookups)
            self::$flipped_built_in_formats = array_flip(self::$built_in_formats);
        }
    }
    /**
     * Get built-in format code.
     */
    public static function built_in_format_code(int $index): string
    {
        // Ensure built-in format codes are available
        self::fill_built_in_format_codes();
        return self::$built_in_formats[$index] ?? '';
    }
    /**
     * Get built-in format code index.
     *
     * @return false|int
     */
    public static function built_in_format_code_index(string $format_code_index): int|false
    {
        // Ensure built-in format codes are available
        self::fill_built_in_format_codes();
        // Lookup format code
        if (array_key_exists($format_code_index, self::$flipped_built_in_formats)) {
            return self::$flipped_built_in_formats[$format_code_index];
        }
        return false;
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
        return md5($this->format_code . $this->built_in_format_code . self::class);
    }
    /**
     * Convert a value in a pre-defined format to a PHP string.
     *
     * @param null|bool|float|int|RichText|string $value Value to format
     * @param string $format Format code: see = self::FORMAT_* for predefined values;
     *                          or can be any valid MS Excel custom format string
     * @param ?mixed[] $callBack Callback function for additional formatting of string
     * @param bool $lessFloatPrecision If true, unstyled floats will be converted to a more human-friendly but less computationally accurate value
     *
     * @return string Formatted string
     */
    public static function to_formatted_string(mixed $value, string $format, ?array $call_back = null, bool $less_float_precision = false): string
    {
        return Number_Format\Formatter::to_formatted_string($value, $format, $call_back, $less_float_precision);
    }
    /** @return mixed[] */
    protected function export_array1(): array
    {
        $exported_array = [];
        $this->export_array2($exported_array, 'formatCode', $this->get_format_code());
        return $exported_array;
    }
    public static function get_short_date_format(): string
    {
        return self::$short_date_format;
    }
    public static function set_short_date_format(string $short_date_format): void
    {
        self::$short_date_format = $short_date_format;
    }
    public static function get_long_date_format(): string
    {
        return self::$long_date_format;
    }
    public static function set_long_date_format(string $long_date_format): void
    {
        self::$long_date_format = $long_date_format;
    }
    public static function get_date_time_format(): string
    {
        return self::$date_time_format;
    }
    public static function set_date_time_format(string $date_time_format): void
    {
        self::$date_time_format = $date_time_format;
    }
    public static function get_time_format(): string
    {
        return self::$time_format;
    }
    public static function set_time_format(string $time_format): void
    {
        self::$time_format = $time_format;
    }
}