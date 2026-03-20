<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

interface I_Comparable
{
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string;
}