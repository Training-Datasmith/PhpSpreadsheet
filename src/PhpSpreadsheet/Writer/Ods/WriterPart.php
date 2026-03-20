<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Ods;

use Php_Office\Php_Spreadsheet\Writer\Ods;
abstract class Writer_Part
{
    /**
     * Get Ods writer.
     */
    public function get_parent_writer(): Ods
    {
        return $this->parent_writer;
    }
    /**
     * Set parent Ods writer.
     */
    public function __construct(
        /**
         * Parent Ods object.
         */
        private readonly Ods $parent_writer
    )
    {
    }
    abstract public function write(): string;
}