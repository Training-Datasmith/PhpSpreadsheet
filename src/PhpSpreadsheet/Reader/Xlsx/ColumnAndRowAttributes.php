<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Default_Read_Filter;
use Php_Office\Php_Spreadsheet\Reader\I_Read_Filter;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Column_And_Row_Attributes extends Base_Parser_Class
{
    public function __construct(private readonly Worksheet $worksheet, private readonly ?Simple_Xml_Element $worksheet_xml = null)
    {
    }
    /**
     * Set Worksheet column attributes by attributes array passed.
     *
     * @param string $columnAddress A, B, ... DX, ...
     * @param array{xfIndex?: int, visible?: bool, collapsed?: bool, collapsed?: bool, outlineLevel?: int, rowHeight?: float, width?: int} $columnAttributes array of attributes (indexes are attribute name, values are value)
     */
    private function set_column_attributes(string $column_address, array $column_attributes): void
    {
        if (isset($column_attributes['xfIndex'])) {
            $this->worksheet->get_column_dimension($column_address)->set_xf_index($column_attributes['xfIndex']);
        }
        if (isset($column_attributes['visible'])) {
            $this->worksheet->get_column_dimension($column_address)->set_visible($column_attributes['visible']);
        }
        if (isset($column_attributes['collapsed'])) {
            $this->worksheet->get_column_dimension($column_address)->set_collapsed($column_attributes['collapsed']);
        }
        if (isset($column_attributes['outlineLevel'])) {
            $this->worksheet->get_column_dimension($column_address)->set_outline_level($column_attributes['outlineLevel']);
        }
        if (isset($column_attributes['width'])) {
            $this->worksheet->get_column_dimension($column_address)->set_width($column_attributes['width']);
        }
    }
    /**
     * Set Worksheet row attributes by attributes array passed.
     *
     * @param int $rowNumber 1, 2, 3, ... 99, ...
     * @param array{xfIndex?: int, visible?: bool, collapsed?: bool, collapsed?: bool, outlineLevel?: int, rowHeight?: float, customFormat?: bool, ht?: float} $rowAttributes array of attributes (indexes are attribute name, values are value)
     *                               'xfIndex', 'visible', 'collapsed', 'outlineLevel', 'rowHeight', ... ?
     */
    private function set_row_attributes(int $row_number, array $row_attributes): void
    {
        if (isset($row_attributes['xfIndex'])) {
            $this->worksheet->get_row_dimension($row_number)->set_xf_index($row_attributes['xfIndex']);
        }
        if (isset($row_attributes['visible'])) {
            $this->worksheet->get_row_dimension($row_number)->set_visible($row_attributes['visible']);
        }
        if (isset($row_attributes['collapsed'])) {
            $this->worksheet->get_row_dimension($row_number)->set_collapsed($row_attributes['collapsed']);
        }
        if (isset($row_attributes['outlineLevel'])) {
            $this->worksheet->get_row_dimension($row_number)->set_outline_level($row_attributes['outlineLevel']);
        }
        if (isset($row_attributes['customFormat'], $row_attributes['rowHeight'])) {
            $this->worksheet->get_row_dimension($row_number)->set_custom_format($row_attributes['customFormat'], $row_attributes['rowHeight']);
        } elseif (isset($row_attributes['rowHeight'])) {
            $this->worksheet->get_row_dimension($row_number)->set_row_height($row_attributes['rowHeight']);
        }
    }
    public function load(?I_Read_Filter $read_filter = null, bool $read_data_only = false, bool $ignore_rows_with_no_cells = false): bool
    {
        if ($this->worksheet_xml === null) {
            return false;
        }
        if ($read_filter !== null && $read_filter::class === Default_Read_Filter::class) {
            $read_filter = null;
        }
        $columns_attributes = [];
        $rows_attributes = [];
        if (isset($this->worksheet_xml->cols)) {
            $columns_attributes = $this->read_column_attributes($this->worksheet_xml->cols, $read_data_only);
        }
        if ($this->worksheet_xml->sheet_data && $this->worksheet_xml->sheet_data->row) {
            $rows_attributes = $this->read_row_attributes($this->worksheet_xml->sheet_data->row, $read_data_only, $ignore_rows_with_no_cells, $read_filter !== null);
        }
        // set columns/rows attributes
        $columns_attributes_are_set = [];
        foreach ($columns_attributes as $column_coordinate => $column_attributes) {
            if ($read_filter === null || !$this->is_filtered_column($read_filter, $column_coordinate, $rows_attributes)) {
                if (!isset($columns_attributes_are_set[$column_coordinate])) {
                    /** @var array{xfIndex?: int, visible?: bool, collapsed?: bool, collapsed?: bool, outlineLevel?: int, rowHeight?: float, width?: int} $columnAttributes */
                    $this->set_column_attributes($column_coordinate, $column_attributes);
                    $columns_attributes_are_set[$column_coordinate] = true;
                }
            }
        }
        $rows_attributes_are_set = [];
        foreach ($rows_attributes as $row_coordinate => $row_attributes) {
            if ($read_filter === null || !$this->is_filtered_row($read_filter, $row_coordinate, $columns_attributes)) {
                if (!isset($rows_attributes_are_set[$row_coordinate])) {
                    /** @var array{xfIndex?: int, visible?: bool, collapsed?: bool, collapsed?: bool, outlineLevel?: int, rowHeight?: float} $rowAttributes */
                    $this->set_row_attributes($row_coordinate, $row_attributes);
                    $rows_attributes_are_set[$row_coordinate] = true;
                }
            }
        }
        return true;
    }
    /** @param mixed[] $rowsAttributes */
    private function is_filtered_column(I_Read_Filter $read_filter, string $column_coordinate, array $rows_attributes): bool
    {
        foreach ($rows_attributes as $row_coordinate => $row_attributes) {
            if ($read_filter->read_cell($column_coordinate, $row_coordinate, $this->worksheet->get_title())) {
                return false;
            }
        }
        return true;
    }
    /** @return mixed[] */
    private function read_column_attributes(Simple_Xml_Element $worksheet_cols, bool $read_data_only): array
    {
        $column_attributes = [];
        foreach ($worksheet_cols->col as $columnx) {
            $column = $columnx->attributes();
            if ($column !== null) {
                $start_column = Coordinate::string_from_column_index((int) $column['min']);
                $end_column = Coordinate::string_from_column_index((int) $column['max']);
                String_Helper::string_increment($end_column);
                for ($column_address = $start_column; $column_address !== $end_column; String_Helper::string_increment($column_address)) {
                    $column_attributes[$column_address] = $this->read_column_range_attributes($column, $read_data_only);
                    if ((int) $column['max'] === Address_Range::MAX_COLUMN_INT) {
                        break;
                    }
                }
            }
        }
        return $column_attributes;
    }
    /** @return mixed[] */
    private function read_column_range_attributes(?Simple_Xml_Element $column, bool $read_data_only): array
    {
        $column_attributes = [];
        if ($column !== null) {
            if (isset($column['style']) && !$read_data_only) {
                $column_attributes['xfIndex'] = (int) $column['style'];
            }
            if (isset($column['hidden']) && self::boolean($column['hidden'])) {
                $column_attributes['visible'] = false;
            }
            if (isset($column['collapsed']) && self::boolean($column['collapsed'])) {
                $column_attributes['collapsed'] = true;
            }
            if (isset($column['outlineLevel']) && (int) $column['outlineLevel'] > 0) {
                $column_attributes['outlineLevel'] = (int) $column['outlineLevel'];
            }
            if (isset($column['width'])) {
                $column_attributes['width'] = (float) $column['width'];
            }
        }
        return $column_attributes;
    }
    /** @param mixed[] $columnsAttributes */
    private function is_filtered_row(I_Read_Filter $read_filter, int $row_coordinate, array $columns_attributes): bool
    {
        foreach ($columns_attributes as $column_coordinate => $column_attributes) {
            if (!$read_filter->read_cell($column_coordinate, $row_coordinate, $this->worksheet->get_title())) {
                return true;
            }
        }
        return false;
    }
    /** @return mixed[] */
    private function read_row_attributes(Simple_Xml_Element $worksheet_row, bool $read_data_only, bool $ignore_rows_with_no_cells, bool $read_filter_is_not_null): array
    {
        $row_attributes = [];
        foreach ($worksheet_row as $rowx) {
            $row = $rowx->attributes();
            if ($row !== null && (!$ignore_rows_with_no_cells || isset($rowx->c))) {
                $row_index = (int) $row['r'];
                if (!$read_data_only) {
                    if (isset($row['ht'])) {
                        $row_attributes[$row_index]['rowHeight'] = (float) $row['ht'];
                    }
                    if (isset($row['customFormat']) && self::boolean($row['customFormat'])) {
                        $row_attributes[$row_index]['customFormat'] = true;
                    }
                    if (isset($row['hidden']) && self::boolean($row['hidden'])) {
                        $row_attributes[$row_index]['visible'] = false;
                    }
                    if (isset($row['collapsed']) && self::boolean($row['collapsed'])) {
                        $row_attributes[$row_index]['collapsed'] = true;
                    }
                    if (isset($row['outlineLevel']) && (int) $row['outlineLevel'] > 0) {
                        $row_attributes[$row_index]['outlineLevel'] = (int) $row['outlineLevel'];
                    }
                    if (isset($row['s'])) {
                        $row_attributes[$row_index]['xfIndex'] = (int) $row['s'];
                    }
                }
                if ($read_filter_is_not_null && empty($row_attributes[$row_index])) {
                    $row_attributes[$row_index]['exists'] = true;
                }
            }
        }
        return $row_attributes;
    }
}