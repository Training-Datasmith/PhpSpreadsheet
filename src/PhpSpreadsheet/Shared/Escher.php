<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
class Escher
{
    /**
     * Drawing Group Container.
     */
    private ?Escher\Dgg_Container $dgg_container = null;
    /**
     * Drawing Container.
     */
    private ?Escher\Dg_Container $dg_container = null;
    /**
     * Get Drawing Group Container.
     */
    public function get_dgg_container(): ?Escher\Dgg_Container
    {
        return $this->dgg_container;
    }
    /**
     * Get Drawing Group Container.
     */
    public function get_dgg_container_or_throw(): Escher\Dgg_Container
    {
        return $this->dgg_container ?? throw new Spreadsheet_Exception('dggContainer is unexpectedly null');
    }
    /**
     * Set Drawing Group Container.
     */
    public function set_dgg_container(Escher\Dgg_Container $dgg_container): Escher\Dgg_Container
    {
        return $this->dgg_container = $dgg_container;
    }
    /**
     * Get Drawing Container.
     */
    public function get_dg_container(): ?Escher\Dg_Container
    {
        return $this->dg_container;
    }
    /**
     * Get Drawing Container.
     */
    public function get_dg_container_or_throw(): Escher\Dg_Container
    {
        return $this->dg_container ?? throw new Spreadsheet_Exception('dgContainer is unexpectedly null');
    }
    /**
     * Set Drawing Container.
     */
    public function set_dg_container(Escher\Dg_Container $dg_container): Escher\Dg_Container
    {
        return $this->dg_container = $dg_container;
    }
}