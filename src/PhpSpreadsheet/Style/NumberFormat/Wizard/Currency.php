<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard;

class Currency extends CurrencyBase
{
    protected ?bool $overrideSpacing = false;

    protected ?CurrencyNegative $overrideNegative = null;
}
