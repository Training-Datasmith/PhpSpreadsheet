<?php

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet;

interface IComparable
{
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function getHashCode(): string;
}
