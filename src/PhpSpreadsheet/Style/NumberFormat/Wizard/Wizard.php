<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

interface Wizard
{
    public function format(): string;
}