<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
class Number extends Number_Base implements Wizard
{
    public const WITH_THOUSANDS_SEPARATOR = true;
    public const WITHOUT_THOUSANDS_SEPARATOR = false;
    protected bool $thousands_separator = true;
    /**
     * @param int $decimals number of decimal places to display, in the range 0-30
     * @param bool $thousandsSeparator indicator whether the thousands separator should be used, or not
     * @param ?string $locale Set the locale for the number format; or leave as the default null.
     *          Locale has no effect for Number Format values, and is retained here only for compatibility
     *              with the other Wizards.
     *          If provided, Locale values must be a valid formatted locale string (e.g. 'en-GB', 'fr', uz-Arab-AF).
     *
     * @throws Exception If a provided locale code is not a valid format
     */
    public function __construct(int $decimals = 2, bool $thousands_separator = self::WITH_THOUSANDS_SEPARATOR, ?string $locale = null)
    {
        $this->set_decimals($decimals);
        $this->set_thousands_separator($thousands_separator);
        $this->set_locale($locale);
    }
    public function set_thousands_separator(bool $thousands_separator = self::WITH_THOUSANDS_SEPARATOR): void
    {
        $this->thousands_separator = $thousands_separator;
    }
    /**
     * As MS Excel cannot easily handle Lakh, which is the only locale-specific Number format variant,
     *       we don't use locale with Numbers.
     */
    protected function get_locale_format(): string
    {
        return $this->format();
    }
    public function format(): string
    {
        return sprintf('%s0%s', $this->thousands_separator ? '#,##' : null, $this->decimals > 0 ? '.' . str_repeat('0', $this->decimals) : null);
    }
}