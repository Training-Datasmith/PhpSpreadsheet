<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;
class Dg_Container
{
    /**
     * Drawing index, 1-based.
     */
    private ?int $dg_id = null;
    /**
     * Last shape index in this drawing.
     */
    private ?int $last_sp_id = null;
    private ?Spgr_Container $spgr_container = null;
    public function get_dg_id(): ?int
    {
        return $this->dg_id;
    }
    public function set_dg_id(int $value): void
    {
        $this->dg_id = $value;
    }
    public function get_last_sp_id(): ?int
    {
        return $this->last_sp_id;
    }
    public function set_last_sp_id(int $value): void
    {
        $this->last_sp_id = $value;
    }
    public function get_spgr_container(): ?Spgr_Container
    {
        return $this->spgr_container;
    }
    public function get_spgr_container_or_throw(): Spgr_Container
    {
        if ($this->spgr_container !== null) {
            return $this->spgr_container;
        }
        throw new Spreadsheet_Exception('spgrContainer is unexpectedly null');
    }
    public function set_spgr_container(Spgr_Container $spgr_container): Spgr_Container
    {
        return $this->spgr_container = $spgr_container;
    }
}