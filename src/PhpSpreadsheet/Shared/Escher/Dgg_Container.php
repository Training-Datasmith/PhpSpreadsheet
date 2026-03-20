<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
class Dgg_Container
{
    /**
     * Maximum shape index of all shapes in all drawings increased by one.
     */
    private int $sp_id_max;
    /**
     * Total number of drawings saved.
     */
    private int $c_dg_saved;
    /**
     * Total number of shapes saved (including group shapes).
     */
    private int $c_sp_saved;
    /**
     * BLIP Store Container.
     */
    private ?Dgg_Container\Bstore_Container $bstore_container = null;
    /**
     * Array of options for the drawing group.
     *
     * @var mixed[]
     */
    private array $OPT = [];
    /**
     * Array of identifier clusters containing information about the maximum shape identifiers.
     *
     * @var mixed[]
     */
    private array $idc_ls = [];
    /**
     * Get maximum shape index of all shapes in all drawings (plus one).
     */
    public function get_sp_id_max(): int
    {
        return $this->sp_id_max;
    }
    /**
     * Set maximum shape index of all shapes in all drawings (plus one).
     */
    public function set_sp_id_max(int $value): void
    {
        $this->sp_id_max = $value;
    }
    /**
     * Get total number of drawings saved.
     */
    public function get_c_dg_saved(): int
    {
        return $this->c_dg_saved;
    }
    /**
     * Set total number of drawings saved.
     */
    public function set_c_dg_saved(int $value): void
    {
        $this->c_dg_saved = $value;
    }
    /**
     * Get total number of shapes saved (including group shapes).
     */
    public function get_c_sp_saved(): int
    {
        return $this->c_sp_saved;
    }
    /**
     * Set total number of shapes saved (including group shapes).
     */
    public function set_c_sp_saved(int $value): void
    {
        $this->c_sp_saved = $value;
    }
    /**
     * Get BLIP Store Container.
     */
    public function get_bstore_container(): ?Dgg_Container\Bstore_Container
    {
        return $this->bstore_container;
    }
    /**
     * Get BLIP Store Container.
     */
    public function get_bstore_container_or_throw(): Dgg_Container\Bstore_Container
    {
        return $this->bstore_container ?? throw new Spreadsheet_Exception('bstoreContainer is unexpectedly null');
    }
    /**
     * Set BLIP Store Container.
     */
    public function set_bstore_container(Dgg_Container\Bstore_Container $bstore_container): void
    {
        $this->bstore_container = $bstore_container;
    }
    /**
     * Set an option for the drawing group.
     *
     * @param int $property The number specifies the option
     */
    public function set_opt(int $property, mixed $value): void
    {
        $this->OPT[$property] = $value;
    }
    /**
     * Get an option for the drawing group.
     *
     * @param int $property The number specifies the option
     */
    public function get_opt(int $property): mixed
    {
        return $this->OPT[$property] ?? null;
    }
    /**
     * Get identifier clusters.
     *
     * @return mixed[]
     */
    public function get_idc_ls(): array
    {
        return $this->idc_ls;
    }
    /**
     * Set identifier clusters. [<drawingId> => <max shape id>, ...].
     *
     * @param mixed[] $IDCLs
     */
    public function set_idc_ls(array $idc_ls): void
    {
        $this->idc_ls = $idc_ls;
    }
}