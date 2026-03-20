<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Number_Formatter;
use Php_Office\Php_Spreadsheet\Exception;
class Percentage extends Number_Base implements Wizard
{
    /**
     * @param int $decimals number of decimal places to display, in the range 0-30
     * @param ?string $locale Set the locale for the percentage format; or leave as the default null.
     *          If provided, Locale values must be a valid formatted locale string (e.g. 'en-GB', 'fr', uz-Arab-AF).
     *
     * @throws Exception If a provided locale code is not a valid format
     */
    public function __construct(int $decimals = 2, ?string $locale = null)
    {
        $this->set_decimals($decimals);
        $this->set_locale($locale);
    }
    protected function get_locale_format(): string
    {
        $formatter = new Locale($this->full_locale, Number_Formatter::PERCENT);
        return $this->decimals > 0 ? str_replace('0', '0.' . str_repeat('0', $this->decimals), $formatter->format()) : $formatter->format();
    }
    public function format(): string
    {
        if ($this->locale_format !== null) {
            return $this->locale_format;
        }
        return sprintf('0%s%%', $this->decimals > 0 ? '.' . str_repeat('0', $this->decimals) : null);
    }
}