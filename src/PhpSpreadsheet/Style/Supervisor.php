<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style;

use Php_Office\Php_Spreadsheet\I_Comparable;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
abstract class Supervisor implements I_Comparable
{
    /**
     * Parent. Only used for supervisor.
     *
     * @var Spreadsheet|Supervisor
     */
    protected $parent;
    /**
     * Parent property name.
     */
    protected ?string $parent_property_name = null;
    /**
     * Create a new Supervisor.
     *
     * @param bool $isSupervisor Flag indicating if this is a supervisor or not
     *                                    Leave this value at default unless you understand exactly what
     *                                        its ramifications are
     */
    public function __construct(protected bool $is_supervisor = false)
    {
    }
    /**
     * Bind parent. Only used for supervisor.
     *
     * @return $this
     */
    public function bind_parent(Spreadsheet|self $parent, ?string $parent_property_name = null)
    {
        $this->parent = $parent;
        $this->parent_property_name = $parent_property_name;
        return $this;
    }
    /**
     * Is this a supervisor or a cell style component?
     */
    public function get_is_supervisor(): bool
    {
        return $this->is_supervisor;
    }
    /**
     * Get the currently active sheet. Only used for supervisor.
     */
    public function get_active_sheet(): Worksheet
    {
        return $this->parent->get_active_sheet();
    }
    /**
     * Get the currently active cell coordinate in currently active sheet.
     * Only used for supervisor.
     *
     * @return string E.g. 'A1'
     */
    public function get_selected_cells(): string
    {
        return $this->get_active_sheet()->get_selected_cells();
    }
    /**
     * Get the currently active cell coordinate in currently active sheet.
     * Only used for supervisor.
     *
     * @return string E.g. 'A1'
     */
    public function get_active_cell(): string
    {
        return $this->get_active_sheet()->get_active_cell();
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value) && $key != 'parent') {
                $this->{$key} = clone $value;
            } else {
                $this->{$key} = $value;
            }
        }
    }
    /**
     * Export style as array.
     *
     * Available to anything which extends this class:
     * Alignment, Border, Borders, Color, Fill, Font,
     * NumberFormat, Protection, and Style.
     *
     * @return mixed[]
     */
    final public function export_array(): array
    {
        return $this->export_array1();
    }
    /**
     * Abstract method to be implemented in anything which
     * extends this class.
     *
     * This method invokes exportArray2 with the names and values
     * of all properties to be included in output array,
     * returning that array to exportArray, then to caller.
     *
     * @return mixed[]
     */
    abstract protected function export_array1(): array;
    /**
     * Populate array from exportArray1.
     * This method is available to anything which extends this class.
     * The parameter index is the key to be added to the array.
     * The parameter objOrValue is either a primitive type,
     * which is the value added to the array,
     * or a Style object to be recursively added via exportArray.
     *
     * @param mixed[] $exportedArray
     */
    final protected function export_array2(array &$exported_array, string $index, mixed $obj_or_value): void
    {
        if ($obj_or_value instanceof self) {
            $exported_array[$index] = $obj_or_value->export_array();
        } else {
            $exported_array[$index] = $obj_or_value;
        }
    }
    /**
     * Get the shared style component for the currently active cell in currently active sheet.
     * Only used for style supervisor.
     */
    abstract public function get_shared_component(): mixed;
    /**
     * Build style array from subcomponents.
     *
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    abstract public function get_style_array(array $array): array;
}