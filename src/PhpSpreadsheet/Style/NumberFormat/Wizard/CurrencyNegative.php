<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

enum Currency_Negative
{
    case minus;
    case redMinus;
    case parentheses;
    case redParentheses;
    public function start(): string
    {
        return match ($this) {
            self::minus, self::redMinus => '-',
            self::parentheses, self::redParentheses => '\(',
        };
    }
    public function end(): string
    {
        return match ($this) {
            self::minus, self::redMinus => '',
            self::parentheses, self::redParentheses => '\)',
        };
    }
    public function color(): string
    {
        return match ($this) {
            self::redParentheses, self::redMinus => '[Red]',
            self::parentheses, self::minus => '',
        };
    }
}