<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine\Operands;

interface Operand
{
    /** @param string[] $matches */
    public static function from_parser(string $formula, int $index, array $matches): self;
    public function value(): string;
}