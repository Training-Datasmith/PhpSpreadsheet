<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
class Scientific extends Number_Base implements Wizard
{
    /**
     * @param int $decimals number of decimal places to display, in the range 0-30
     * @param ?string $locale Set the locale for the scientific format; or leave as the default null.
     *          Locale has no effect for Scientific Format values, and is retained here for compatibility
     *              with the other Wizards.
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
        return $this->format();
    }
    public function format(): string
    {
        return sprintf('0%sE+00', $this->decimals > 0 ? '.' . str_repeat('0', $this->decimals) : null);
    }
}