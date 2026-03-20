<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Writer\Xlsx;
abstract class Writer_Part
{
    /**
     * Get parent Xlsx object.
     */
    public function get_parent_writer(): Xlsx
    {
        return $this->parent_writer;
    }
    /**
     * Set parent Xlsx object.
     */
    public function __construct(
        /**
         * Parent Xlsx object.
         */
        private readonly Xlsx $parent_writer
    )
    {
    }
}