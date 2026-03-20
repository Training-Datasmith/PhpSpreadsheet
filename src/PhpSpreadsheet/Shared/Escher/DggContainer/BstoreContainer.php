<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container;

class Bstore_Container
{
    /**
     * BLIP Store Entries. Each of them holds one BLIP (Big Large Image or Picture).
     *
     * @var BstoreContainer\BSE[]
     */
    private array $bse_collection = [];
    /**
     * Add a BLIP Store Entry.
     */
    public function add_bse(Bstore_Container\BSE $BSE): void
    {
        $this->bse_collection[] = $BSE;
        $BSE->set_parent($this);
    }
    /**
     * Get the collection of BLIP Store Entries.
     *
     * @return BstoreContainer\BSE[]
     */
    public function get_bse_collection(): array
    {
        return $this->bse_collection;
    }
}