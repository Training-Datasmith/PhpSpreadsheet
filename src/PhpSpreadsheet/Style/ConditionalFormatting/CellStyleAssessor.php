<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Style\Conditional;
use Php_Office\Php_Spreadsheet\Style\Style;
class Cell_Style_Assessor
{
    protected Cell_Matcher $cell_matcher;
    protected Style_Merger $style_merger;
    public function __construct(protected Cell $cell, string $conditional_range)
    {
        $this->cell_matcher = new Cell_Matcher($this->cell, $conditional_range);
        $this->style_merger = new Style_Merger($this->cell->get_style());
    }
    /**
     * @param Conditional[] $conditionalStyles
     */
    public function match_conditions(array $conditional_styles = []): Style
    {
        foreach ($conditional_styles as $conditional) {
            if ($this->cell_matcher->evaluate_conditional($conditional) === true) {
                // Merging the conditional style into the base style goes in here
                $this->style_merger->merge_style($conditional->get_style($this->cell->get_value()));
                if ($conditional->get_stop_if_true() === true) {
                    break;
                }
            }
        }
        return $this->style_merger->get_style();
    }
    /**
     * @param Conditional[] $conditionalStyles
     */
    public function match_conditions_return_null_if_none_matched(array $conditional_styles, string $cell_data, bool $stop_at_first_match = false): ?Style
    {
        $matched = false;
        $value = (float) $cell_data;
        foreach ($conditional_styles as $conditional) {
            if ($this->cell_matcher->evaluate_conditional($conditional) === true) {
                $matched = true;
                // Merging the conditional style into the base style goes in here
                $this->style_merger->merge_style($conditional->get_style($value));
                if ($conditional->get_stop_if_true() === true || $stop_at_first_match) {
                    break;
                }
            }
        }
        if ($matched) {
            return $this->style_merger->get_style();
        }
        return null;
    }
}