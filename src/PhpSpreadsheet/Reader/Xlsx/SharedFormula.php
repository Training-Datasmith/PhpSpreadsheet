<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class SharedFormula
{
    public function __construct(private readonly string $master, private readonly string $formula)
    {
    }

    public function master(): string
    {
        return $this->master;
    }

    public function formula(): string
    {
        return $this->formula;
    }
}
