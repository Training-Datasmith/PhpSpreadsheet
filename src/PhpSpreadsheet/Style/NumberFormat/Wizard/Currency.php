<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

class Currency extends Currency_Base
{
    protected ?bool $override_spacing = false;
    protected ?Currency_Negative $override_negative = null;
}