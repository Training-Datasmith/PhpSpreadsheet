<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Number_Formatter;
use Php_Office\Php_Spreadsheet\Exception;
class Currency_Base extends Number
{
    public const LEADING_SYMBOL = true;
    public const TRAILING_SYMBOL = false;
    public const SYMBOL_WITH_SPACING = true;
    public const SYMBOL_WITHOUT_SPACING = false;
    protected string $currency_code = '$';
    protected bool $currency_symbol_position = self::LEADING_SYMBOL;
    protected bool $currency_symbol_spacing = self::SYMBOL_WITHOUT_SPACING;
    protected const DEFAULT_STRIP_LEADING_RLM = false;
    public const DEFAULT_NEGATIVE = Currency_Negative::minus;
    protected ?bool $override_spacing = null;
    protected ?Currency_Negative $override_negative = null;
    // Not sure why original code uses nbsp
    private string $space_or_nbsp = ' ';
    // or "\u{a0}"
    /**
     * @param string $currencyCode the currency symbol or code to display for this mask
     * @param int $decimals number of decimal places to display, in the range 0-30
     * @param bool $thousandsSeparator indicator whether the thousands separator should be used, or not
     * @param bool $currencySymbolPosition indicates whether the currency symbol comes before or after the value
     *              Possible values are Currency::LEADING_SYMBOL and Currency::TRAILING_SYMBOL
     * @param bool $currencySymbolSpacing indicates whether there is spacing between the currency symbol and the value
     *              Possible values are Currency::SYMBOL_WITH_SPACING and Currency::SYMBOL_WITHOUT_SPACING
     *              However, Currency always uses WITHOUT and Accounting always uses WITH
     * @param ?string $locale Set the locale for the currency format; or leave as the default null.
     *          If provided, Locale values must be a valid formatted locale string (e.g. 'en-GB', 'fr', uz-Arab-AF).
     *          Note that setting a locale will override any other settings defined in this class
     *          other than the currency code; or decimals (unless the decimals value is set to 0).
     * @param bool $stripLeadingRLM remove leading RLM added with
     *          ICU 72.1+.
     * @param CurrencyNegative $negative How to display negative numbers.
     *                         Always use parentheses for Accounting.
     *                         4 options for Currency.
     *
     * @throws Exception If a provided locale code is not a valid format
     */
    public function __construct(string $currency_code = '$', int $decimals = 2, bool $thousands_separator = true, bool $currency_symbol_position = self::LEADING_SYMBOL, bool $currency_symbol_spacing = self::SYMBOL_WITHOUT_SPACING, ?string $locale = null, protected bool $strip_leading_rlm = self::DEFAULT_STRIP_LEADING_RLM, protected Currency_Negative $negative = Currency_Negative::minus)
    {
        $this->set_currency_code($currency_code);
        $this->set_thousands_separator($thousands_separator);
        $this->set_decimals($decimals);
        $this->set_currency_symbol_position($currency_symbol_position);
        $this->set_currency_symbol_spacing($currency_symbol_spacing);
        $this->set_locale($locale);
    }
    public function set_currency_code(string $currency_code): void
    {
        $this->currency_code = $currency_code;
    }
    public function set_currency_symbol_position(bool $currency_symbol_position = self::LEADING_SYMBOL): void
    {
        $this->currency_symbol_position = $currency_symbol_position;
    }
    public function set_currency_symbol_spacing(bool $currency_symbol_spacing = self::SYMBOL_WITHOUT_SPACING): void
    {
        $this->currency_symbol_spacing = $currency_symbol_spacing;
    }
    public function set_strip_leading_rlm(bool $strip_leading_rlm): void
    {
        $this->strip_leading_rlm = $strip_leading_rlm;
    }
    public function set_negative(Currency_Negative $negative): void
    {
        $this->negative = $negative;
    }
    protected function get_locale_format(): string
    {
        $formatter = new Locale($this->full_locale, Number_Formatter::CURRENCY);
        $mask = $formatter->format($this->strip_leading_rlm);
        if ($this->decimals === 0) {
            $mask = (string) preg_replace('/\.0+/miu', '', $mask);
        }
        return str_replace('¤', $this->format_currency_code(), $mask);
    }
    private function format_currency_code(): string
    {
        if ($this->locale === null) {
            return $this->currency_code;
        }
        return "[\${$this->currency_code}-{$this->locale}]";
    }
    public function format(): string
    {
        if ($this->locale_format !== null) {
            return $this->locale_format;
        }
        $symbol_with_spacing = $this->override_spacing ?? $this->currency_symbol_spacing === self::SYMBOL_WITH_SPACING;
        $negative = $this->override_negative ?? $this->negative;
        // format if positive
        $format = '_(';
        if ($this->currency_symbol_position === self::LEADING_SYMBOL) {
            $format .= '"' . $this->currency_code . '"';
            if (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            if (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            if ($symbol_with_spacing) {
                $format .= '*' . $this->space_or_nbsp;
            }
        }
        $format .= $this->thousands_separator ? '#,##0' : '0';
        if ($this->decimals > 0) {
            $format .= '.' . str_repeat('0', $this->decimals);
        }
        if ($this->currency_symbol_position === self::TRAILING_SYMBOL) {
            if ($symbol_with_spacing) {
                $format .= $this->space_or_nbsp;
            } elseif (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            $format .= '[$' . $this->currency_code . ']';
        }
        $format .= '_)';
        // format if negative
        $format .= ';_(';
        $format .= $negative->color();
        $negative_start = $negative->start();
        if ($this->currency_symbol_position === self::LEADING_SYMBOL) {
            if ($negative_start === '-' && !$symbol_with_spacing) {
                $format .= $negative_start;
            }
            $format .= '"' . $this->currency_code . '"';
            if (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            if ($symbol_with_spacing) {
                $format .= '*' . $this->space_or_nbsp;
            }
            if ($negative_start === '\(' || $symbol_with_spacing && $negative_start === '-') {
                $format .= $negative_start;
            }
        } else {
            $format .= $negative->start();
        }
        $format .= $this->thousands_separator ? '#,##0' : '0';
        if ($this->decimals > 0) {
            $format .= '.' . str_repeat('0', $this->decimals);
        }
        $format .= $negative->end();
        if ($this->currency_symbol_position === self::TRAILING_SYMBOL) {
            if ($symbol_with_spacing) {
                // Do nothing - I can't figure out how to get
                // everything to align if I put any kind of space here.
                //$format .= "\u{2009}";
            } elseif (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            $format .= '[$' . $this->currency_code . ']';
        }
        if ($this->currency_symbol_position === self::TRAILING_SYMBOL) {
            $format .= '_)';
        } elseif ($symbol_with_spacing && $negative_start === '-') {
            $format .= ' ';
        }
        // format if zero
        $format .= ';_(';
        if ($this->currency_symbol_position === self::LEADING_SYMBOL) {
            $format .= '"' . $this->currency_code . '"';
        }
        if ($symbol_with_spacing) {
            if ($this->currency_symbol_position === self::LEADING_SYMBOL) {
                $format .= '*' . $this->space_or_nbsp;
            }
            $format .= '"-"';
            if ($this->decimals > 0) {
                $format .= str_repeat('?', $this->decimals);
            }
        } else {
            if (preg_match('/^[A-Z]{3}$/i', $this->currency_code) === 1) {
                $format .= $this->space_or_nbsp;
            }
            $format .= '0';
            if ($this->decimals > 0) {
                $format .= '.' . str_repeat('0', $this->decimals);
            }
        }
        if ($this->currency_symbol_position === self::TRAILING_SYMBOL) {
            if ($symbol_with_spacing) {
                $format .= $this->space_or_nbsp;
            }
            $format .= '[$' . $this->currency_code . ']';
        }
        $format .= '_)';
        // format if text
        $format .= ';_(@_)';
        return $format;
    }
}