<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Style;

class CellStyleAssessor
{
    protected CellMatcher $cellMatcher;

    protected StyleMerger $styleMerger;

    public function __construct(protected Cell $cell, string $conditionalRange)
    {
        $this->cellMatcher = new CellMatcher($this->cell, $conditionalRange);
        $this->styleMerger = new StyleMerger($this->cell->getStyle());
    }

    /**
     * @param Conditional[] $conditionalStyles
     */
    public function matchConditions(array $conditionalStyles = []): Style
    {
        foreach ($conditionalStyles as $conditional) {
            if ($this->cellMatcher->evaluateConditional($conditional) === true) {
                // Merging the conditional style into the base style goes in here
                $this->styleMerger->mergeStyle($conditional->getStyle($this->cell->getValue()));
                if ($conditional->getStopIfTrue() === true) {
                    break;
                }
            }
        }

        return $this->styleMerger->getStyle();
    }

    /**
     * @param Conditional[] $conditionalStyles
     */
    public function matchConditionsReturnNullIfNoneMatched(array $conditionalStyles, string $cellData, bool $stopAtFirstMatch = false): ?Style
    {
        $matched = false;
        $value = (float) $cellData;
        foreach ($conditionalStyles as $conditional) {
            if ($this->cellMatcher->evaluateConditional($conditional) === true) {
                $matched = true;
                // Merging the conditional style into the base style goes in here
                $this->styleMerger->mergeStyle($conditional->getStyle($value));
                if ($conditional->getStopIfTrue() === true || $stopAtFirstMatch) {
                    break;
                }
            }
        }
        if ($matched) {
            return $this->styleMerger->getStyle();
        }

        return null;
    }
}
