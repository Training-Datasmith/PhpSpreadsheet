<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;

use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;
class Blip
{
    /**
     * The parent BSE.
     */
    private BSE $parent;
    /**
     * Raw image data.
     */
    private string $data;
    /**
     * Get the raw image data.
     */
    public function get_data(): string
    {
        return $this->data;
    }
    /**
     * Set the raw image data.
     */
    public function set_data(string $data): void
    {
        $this->data = $data;
    }
    /**
     * Set parent BSE.
     */
    public function set_parent(BSE $parent): void
    {
        $this->parent = $parent;
    }
    /**
     * Get parent BSE.
     */
    public function get_parent(): BSE
    {
        return $this->parent;
    }
}