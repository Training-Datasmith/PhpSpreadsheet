<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Style;
interface Wizard_Interface
{
    public function get_cell_range(): string;
    public function set_cell_range(string $cell_range): void;
    public function get_style(): Style;
    public function set_style(Style $style): void;
    public function get_stop_if_true(): bool;
    public function set_stop_if_true(bool $stop_if_true): void;
    public function get_conditional(): Conditional;
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): self;
}