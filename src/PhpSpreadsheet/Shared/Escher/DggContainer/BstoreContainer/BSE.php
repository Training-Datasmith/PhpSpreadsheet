<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container;

use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container;
class BSE
{
    public const BLIPTYPE_ERROR = 0x0;
    public const BLIPTYPE_UNKNOWN = 0x1;
    public const BLIPTYPE_EMF = 0x2;
    public const BLIPTYPE_WMF = 0x3;
    public const BLIPTYPE_PICT = 0x4;
    public const BLIPTYPE_JPEG = 0x5;
    public const BLIPTYPE_PNG = 0x6;
    public const BLIPTYPE_DIB = 0x7;
    public const BLIPTYPE_TIFF = 0x11;
    public const BLIPTYPE_CMYKJPEG = 0x12;
    /**
     * The parent BLIP Store Entry Container.
     * Property is currently unused.
     */
    private Bstore_Container $parent;
    /**
     * The BLIP (Big Large Image or Picture).
     */
    private ?BSE\Blip $blip = null;
    /**
     * The BLIP type.
     */
    private int $blip_type;
    /**
     * Set parent BLIP Store Entry Container.
     */
    public function set_parent(Bstore_Container $parent): void
    {
        $this->parent = $parent;
    }
    public function get_parent(): Bstore_Container
    {
        return $this->parent;
    }
    /**
     * Get the BLIP.
     */
    public function get_blip(): ?BSE\Blip
    {
        return $this->blip;
    }
    /**
     * Set the BLIP.
     */
    public function set_blip(BSE\Blip $blip): void
    {
        $this->blip = $blip;
        $blip->set_parent($this);
    }
    /**
     * Get the BLIP type.
     */
    public function get_blip_type(): int
    {
        return $this->blip_type;
    }
    /**
     * Set the BLIP type.
     */
    public function set_blip_type(int $blip_type): void
    {
        $this->blip_type = $blip_type;
    }
}