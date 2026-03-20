<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;

use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container;
class Sp_Container
{
    /**
     * Parent Shape Group Container.
     */
    private Spgr_Container $parent;
    /**
     * Is this a group shape?
     */
    private bool $spgr = false;
    /**
     * Shape type.
     */
    private int $sp_type;
    /**
     * Shape flag.
     */
    private int $sp_flag;
    /**
     * Shape index (usually group shape has index 0, and the rest: 1,2,3...).
     */
    private int $sp_id;
    /**
     * Array of options.
     *
     * @var mixed[]
     */
    private array $OPT = [];
    /**
     * Cell coordinates of upper-left corner of shape, e.g. 'A1'.
     */
    private string $start_coordinates = '';
    /**
     * Horizontal offset of upper-left corner of shape measured in 1/1024 of column width.
     */
    private int|float $start_offset_x;
    /**
     * Vertical offset of upper-left corner of shape measured in 1/256 of row height.
     */
    private int|float $start_offset_y;
    /**
     * Cell coordinates of bottom-right corner of shape, e.g. 'B2'.
     */
    private string $end_coordinates;
    /**
     * Horizontal offset of bottom-right corner of shape measured in 1/1024 of column width.
     */
    private int|float $end_offset_x;
    /**
     * Vertical offset of bottom-right corner of shape measured in 1/256 of row height.
     */
    private int|float $end_offset_y;
    /**
     * Set parent Shape Group Container.
     */
    public function set_parent(Spgr_Container $parent): void
    {
        $this->parent = $parent;
    }
    /**
     * Get the parent Shape Group Container.
     */
    public function get_parent(): Spgr_Container
    {
        return $this->parent;
    }
    /**
     * Set whether this is a group shape.
     */
    public function set_spgr(bool $value): void
    {
        $this->spgr = $value;
    }
    /**
     * Get whether this is a group shape.
     */
    public function get_spgr(): bool
    {
        return $this->spgr;
    }
    /**
     * Set the shape type.
     */
    public function set_sp_type(int $value): void
    {
        $this->sp_type = $value;
    }
    /**
     * Get the shape type.
     */
    public function get_sp_type(): int
    {
        return $this->sp_type;
    }
    /**
     * Set the shape flag.
     */
    public function set_sp_flag(int $value): void
    {
        $this->sp_flag = $value;
    }
    /**
     * Get the shape flag.
     */
    public function get_sp_flag(): int
    {
        return $this->sp_flag;
    }
    /**
     * Set the shape index.
     */
    public function set_sp_id(int $value): void
    {
        $this->sp_id = $value;
    }
    /**
     * Get the shape index.
     */
    public function get_sp_id(): int
    {
        return $this->sp_id;
    }
    /**
     * Set an option for the Shape Group Container.
     *
     * @param int $property The number specifies the option
     */
    public function set_opt(int $property, mixed $value): void
    {
        $this->OPT[$property] = $value;
    }
    /**
     * Get an option for the Shape Group Container.
     *
     * @param int $property The number specifies the option
     */
    public function get_opt(int $property): mixed
    {
        return $this->OPT[$property] ?? null;
    }
    /**
     * Get the collection of options.
     *
     * @return mixed[]
     */
    public function get_opt_collection(): array
    {
        return $this->OPT;
    }
    /**
     * Set cell coordinates of upper-left corner of shape.
     *
     * @param string $value eg: 'A1'
     */
    public function set_start_coordinates(string $value): void
    {
        $this->start_coordinates = $value;
    }
    /**
     * Get cell coordinates of upper-left corner of shape.
     */
    public function get_start_coordinates(): string
    {
        return $this->start_coordinates;
    }
    /**
     * Set offset in x-direction of upper-left corner of shape measured in 1/1024 of column width.
     */
    public function set_start_offset_x(int|float $start_offset_x): void
    {
        $this->start_offset_x = $start_offset_x;
    }
    /**
     * Get offset in x-direction of upper-left corner of shape measured in 1/1024 of column width.
     */
    public function get_start_offset_x(): int|float
    {
        return $this->start_offset_x;
    }
    /**
     * Set offset in y-direction of upper-left corner of shape measured in 1/256 of row height.
     */
    public function set_start_offset_y(int|float $start_offset_y): void
    {
        $this->start_offset_y = $start_offset_y;
    }
    /**
     * Get offset in y-direction of upper-left corner of shape measured in 1/256 of row height.
     */
    public function get_start_offset_y(): int|float
    {
        return $this->start_offset_y;
    }
    /**
     * Set cell coordinates of bottom-right corner of shape.
     *
     * @param string $value eg: 'A1'
     */
    public function set_end_coordinates(string $value): void
    {
        $this->end_coordinates = $value;
    }
    /**
     * Get cell coordinates of bottom-right corner of shape.
     */
    public function get_end_coordinates(): string
    {
        return $this->end_coordinates;
    }
    /**
     * Set offset in x-direction of bottom-right corner of shape measured in 1/1024 of column width.
     */
    public function set_end_offset_x(int|float $end_offset_x): void
    {
        $this->end_offset_x = $end_offset_x;
    }
    /**
     * Get offset in x-direction of bottom-right corner of shape measured in 1/1024 of column width.
     */
    public function get_end_offset_x(): int|float
    {
        return $this->end_offset_x;
    }
    /**
     * Set offset in y-direction of bottom-right corner of shape measured in 1/256 of row height.
     */
    public function set_end_offset_y(int|float $end_offset_y): void
    {
        $this->end_offset_y = $end_offset_y;
    }
    /**
     * Get offset in y-direction of bottom-right corner of shape measured in 1/256 of row height.
     */
    public function get_end_offset_y(): int|float
    {
        return $this->end_offset_y;
    }
    /**
     * Get the nesting level of this spContainer. This is the number of spgrContainers between this spContainer and
     * the dgContainer. A value of 1 = immediately within first spgrContainer
     * Higher nesting level occurs if and only if spContainer is part of a shape group.
     *
     * @return int Nesting level
     */
    public function get_nesting_level(): int
    {
        $nesting_level = 0;
        $parent = $this->get_parent();
        while ($parent instanceof Spgr_Container) {
            ++$nesting_level;
            $parent = $parent->get_parent();
        }
        return $nesting_level;
    }
}