<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

class Pane
{
    public function __construct(private readonly string $position, private string $sqref = '', private string $active_cell = '')
    {
    }
    public function get_position(): string
    {
        return $this->position;
    }
    public function get_sqref(): string
    {
        return $this->sqref;
    }
    public function set_sqref(string $sqref): self
    {
        $this->sqref = $sqref;
        return $this;
    }
    public function get_active_cell(): string
    {
        return $this->active_cell;
    }
    public function set_active_cell(string $active_cell): self
    {
        $this->active_cell = $active_cell;
        return $this;
    }
}