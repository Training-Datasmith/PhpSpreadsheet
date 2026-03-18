<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard;

interface Wizard
{
    public function format(): string;
}
