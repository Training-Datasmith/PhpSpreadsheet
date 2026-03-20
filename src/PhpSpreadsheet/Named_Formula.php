<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Named_Formula extends Defined_Name
{
    /**
     * Create a new Named Formula.
     */
    public function __construct(string $name, ?Worksheet $worksheet = null, ?string $formula = null, bool $local_only = false, ?Worksheet $scope = null)
    {
        // Validate data
        if (!isset($formula)) {
            throw new Exception('You must specify a Formula value for a Named Formula');
        }
        parent::__construct($name, $worksheet, $formula, $local_only, $scope);
    }
    /**
     * Get the formula value.
     */
    public function get_formula(): string
    {
        return $this->value;
    }
    /**
     * Set the formula value.
     */
    public function set_formula(string $formula): self
    {
        if (!empty($formula)) {
            $this->value = $formula;
        }
        return $this;
    }
}