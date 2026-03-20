<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Collection;

use Php_Office\Php_Spreadsheet\Settings;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
abstract class Cells_Factory
{
    /**
     * Initialise the cache storage.
     *
     * @param Worksheet $worksheet Enable cell caching for this worksheet
     *
     * */
    public static function get_instance(Worksheet $worksheet): Cells
    {
        return new Cells($worksheet, Settings::get_cache());
    }
}