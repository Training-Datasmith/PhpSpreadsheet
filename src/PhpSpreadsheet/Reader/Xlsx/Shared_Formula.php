<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

class Shared_Formula
{
    public function __construct(private readonly string $master, private readonly string $formula)
    {
    }
    public function master(): string
    {
        return $this->master;
    }
    public function formula(): string
    {
        return $this->formula;
    }
}