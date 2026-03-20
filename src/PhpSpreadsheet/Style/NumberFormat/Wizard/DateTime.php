<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

class DateTime extends Date_Time_Wizard
{
    /**
     * @var array<?string>
     */
    protected array $separators;
    /**
     * @var array<DateTimeWizard|string>
     */
    protected array $format_blocks;
    /**
     * @param null|string|string[] $separators
     *          If you want to use only a single format block, then pass a null as the separator argument
     * @param DateTimeWizard|string ...$formatBlocks
     */
    public function __construct($separators, ...$format_blocks)
    {
        $this->separators = $this->pad_separator_array(is_array($separators) ? $separators : [$separators], count($format_blocks) - 1);
        $this->format_blocks = array_map($this->map_format_blocks(...), $format_blocks);
    }
    private function map_format_blocks(Date_Time_Wizard|string $value): string
    {
        // Any date masking codes are returned as lower case values
        if ($value instanceof Date_Time_Wizard) {
            return $value->__toString();
        }
        // Wrap any string literals in quotes, so that they're clearly defined as string literals
        return $this->wrap_literal($value);
    }
    public function format(): string
    {
        return implode('', array_map($this->intersperse(...), $this->format_blocks, $this->separators));
    }
}