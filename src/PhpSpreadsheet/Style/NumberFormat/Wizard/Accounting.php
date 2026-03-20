<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Number_Formatter;
use Php_Office\Php_Spreadsheet\Exception;
class Accounting extends Currency_Base
{
    protected ?bool $override_spacing = true;
    protected ?Currency_Negative $override_negative = Currency_Negative::parentheses;
    /**
     * @throws Exception if the Intl extension and ICU version don't support Accounting formats
     */
    protected function get_locale_format(): string
    {
        if (self::icu_version() < 53.0) {
            // @codeCoverageIgnoreStart
            throw new Exception('The Intl extension does not support Accounting Formats without ICU 53');
            // @codeCoverageIgnoreEnd
        }
        // Scrutinizer does not recognize CURRENCY_ACCOUNTING
        $formatter = new Locale($this->full_locale, Number_Formatter::CURRENCY_ACCOUNTING);
        $mask = $formatter->format($this->strip_leading_rlm);
        if ($this->decimals === 0) {
            $mask = (string) preg_replace('/\.0+/miu', '', $mask);
        }
        return str_replace('¤', $this->format_currency_code(), $mask);
    }
    public static function icu_version(): float
    {
        [$major, $minor] = explode('.', INTL_ICU_VERSION);
        return (float) "{$major}.{$minor}";
    }
    private function format_currency_code(): string
    {
        if ($this->locale === null) {
            return $this->currency_code . '*';
        }
        return "[\${$this->currency_code}-{$this->locale}]";
    }
}