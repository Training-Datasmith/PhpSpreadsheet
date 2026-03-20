<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

class Header_Footer_Drawing extends Drawing
{
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->get_path() . $this->name . $this->offset_x . $this->offset_y . $this->width . $this->height . self::class);
    }
}