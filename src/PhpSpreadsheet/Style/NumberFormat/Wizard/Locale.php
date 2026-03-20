<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Number_Formatter;
use Php_Office\Php_Spreadsheet\Exception;
final class Locale
{
    /**
     * Language code: ISO-639 2 character, alpha.
     * Optional script code: ISO-15924 4 alpha.
     * Optional country code: ISO-3166-1, 2 character alpha.
     * Separated by underscores or dashes.
     */
    public const STRUCTURE = '/^(?P<language>[a-z]{2})([-_](?P<script>[a-z]{4}))?([-_](?P<country>[a-z]{2}))?$/i';
    private readonly Number_Formatter $formatter;
    public function __construct(?string $locale, int $style)
    {
        $formatter_locale = str_replace('-', '_', $locale ?? '');
        $this->formatter = new Number_Formatter($formatter_locale, $style);
        if ($this->formatter->get_locale() !== $formatter_locale) {
            throw new Exception("Unable to read locale data for '{$locale}'");
        }
    }
    public function format(bool $strip_rlm = true): string
    {
        $str = $this->formatter->get_pattern();
        return $strip_rlm && str_starts_with($str, "‏") ? substr($str, 3) : $str;
    }
}