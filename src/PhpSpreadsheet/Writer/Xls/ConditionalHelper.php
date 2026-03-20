<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;
class Conditional_Helper
{
    protected mixed $condition;
    protected string $cell_range;
    protected ?string $tokens = null;
    protected int $size;
    public function __construct(
        /**
         * Formula parser.
         */
        protected Parser $parser
    )
    {
    }
    public function process_condition(mixed $condition, string $cell_range): void
    {
        $this->condition = $condition;
        $this->cell_range = $cell_range;
        if (is_int($condition) && $condition >= 0 && $condition <= 65535) {
            $this->size = 3;
            $this->tokens = pack('Cv', 0x1e, $condition);
        } else {
            try {
                $formula = Wizard\Wizard_Abstract::reverse_adjust_cell_ref(String_Helper::convert_to_string($condition), $cell_range);
                $this->parser->parse($formula);
                $this->tokens = $this->parser->to_reverse_polish();
                $this->size = strlen($this->tokens ?? '');
            } catch (Php_Spreadsheet_Exception) {
                // In the event of a parser error with a formula value, we set the expression to ptgInt + 0
                $this->tokens = pack('Cv', 0x1e, 0);
                $this->size = 3;
            }
        }
    }
    public function tokens(): ?string
    {
        return $this->tokens;
    }
    public function size(): int
    {
        return $this->size;
    }
}