<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Number_Formatter;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Number_Format;
use Stringable;
abstract class Number_Base implements Stringable
{
    protected const MAX_DECIMALS = 30;
    protected int $decimals = 2;
    protected ?string $locale = null;
    protected ?string $full_locale = null;
    protected ?string $locale_format = null;
    public function set_decimals(int $decimals = 2): void
    {
        $this->decimals = $decimals > self::MAX_DECIMALS ? self::MAX_DECIMALS : max($decimals, 0);
    }
    /**
     * Setting a locale will override any settings defined in this class.
     *
     * @throws Exception If the locale code is not a valid format
     */
    public function set_locale(?string $locale = null): void
    {
        if ($locale === null) {
            $this->locale_format = $this->locale = $this->full_locale = null;
            return;
        }
        $this->locale = $this->validate_locale($locale);
        if (class_exists(Number_Formatter::class)) {
            $this->locale_format = $this->get_locale_format();
        }
    }
    /**
     * Stub: should be implemented as a concrete method in concrete wizards.
     */
    abstract protected function get_locale_format(): string;
    /**
     * @throws Exception If the locale code is not a valid format
     */
    private function validate_locale(string $locale): string
    {
        if (preg_match(Locale::STRUCTURE, $locale, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw new Exception("Invalid locale code '{$locale}'");
        }
        ['language' => $language, 'script' => $script, 'country' => $country] = $matches;
        // Set case and separator to match standardised locale case
        $language = strtolower($language);
        $script = $script === null ? null : ucfirst(strtolower($script));
        $country = $country === null ? null : strtoupper($country);
        $this->full_locale = implode('-', array_filter([$language, $script, $country]));
        return $country === null ? $language : "{$language}-{$country}";
    }
    public function format(): string
    {
        return Number_Format::FORMAT_GENERAL;
    }
    public function __toString(): string
    {
        return $this->format();
    }
}