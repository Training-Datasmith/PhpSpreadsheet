<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

/**
 * A dedicated Calculation singleton for formula parsing only.
 *
 * This class provides an isolated instance specifically for parsing formulas
 * without branch pruning, avoiding state conflicts with the main Calculation
 * singleton used for cell value calculations.
 *
 * @internal
 */
final class Calculation_Parser_Only extends Calculation
{
    /**
     * Instance of this class.
     */
    private static ?Calculation_Parser_Only $parser_instance = null;
    /**
     * Branch pruning is disabled by default for parsing-only operations.
     */
    protected bool $branch_pruning_enabled = false;
    /**
     * Get the singleton instance of this parser-only calculator.
     */
    public static function get_parser_instance(): self
    {
        if (!self::$parser_instance) {
            self::$parser_instance = new self();
        }
        return self::$parser_instance;
    }
    /** @param mixed $enabled Unused, property will always be false in this class */
    public function set_branch_pruning_enabled(mixed $enabled): self
    {
        return $this;
    }
}