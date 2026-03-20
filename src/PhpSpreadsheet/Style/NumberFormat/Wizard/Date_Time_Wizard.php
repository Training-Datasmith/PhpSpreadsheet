<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

use Stringable;
abstract class Date_Time_Wizard implements Stringable, Wizard
{
    protected const NO_ESCAPING_NEEDED = "\$+-/():!^&'~{}<>= ";
    /**
     * @param array<?string> $separators
     *
     * @return array<?string>
     */
    protected function pad_separator_array(array $separators, int $count): array
    {
        $last_separator = (string) array_pop($separators);
        return $separators + array_fill(0, $count, $last_separator);
    }
    protected function escape_single_character(string $value): string
    {
        if (str_contains(self::NO_ESCAPING_NEEDED, $value)) {
            return $value;
        }
        return "\\{$value}";
    }
    protected function wrap_literal(string $value): string
    {
        if (mb_strlen($value, 'UTF-8') === 1) {
            return $this->escape_single_character($value);
        }
        // Wrap any other string literals in quotes, so that they're clearly defined as string literals
        return '"' . str_replace('"', '""', $value) . '"';
    }
    protected function intersperse(string $format_block, ?string $separator): string
    {
        return "{$format_block}{$separator}";
    }
    public function __toString(): string
    {
        return $this->format();
    }
}