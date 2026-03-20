<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
class Protected_Range
{
    /**
     * No setters aside from constructor.
     */
    public function __construct(private readonly string $sqref, private readonly string $password = '', private readonly string $name = '', private readonly string $security_descriptor = '')
    {
    }
    public function get_sqref(): string
    {
        return $this->sqref;
    }
    public function get_name(): string
    {
        return $this->name ?: 'p' . md5($this->sqref);
    }
    public function get_password(): string
    {
        return $this->password;
    }
    public function get_security_descriptor(): string
    {
        return $this->security_descriptor;
    }
    /**
     * Split range into coordinate strings.
     *
     * @return array<array<string>> Array containing one or more arrays containing one or two coordinate strings
     *                                e.g. ['B4','D9'] or [['B4','D9'], ['H2','O11']]
     *                                        or ['B4']
     */
    public function all_ranges(): array
    {
        return Coordinate::all_ranges($this->sqref, false);
    }
}