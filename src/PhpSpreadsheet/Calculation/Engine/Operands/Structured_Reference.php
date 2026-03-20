<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine\Operands;

use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Stringable;
final class Structured_Reference implements Operand, Stringable
{
    public const NAME = 'Structured Reference';
    private const OPEN_BRACE = '[';
    private const CLOSE_BRACE = ']';
    private const ITEM_SPECIFIER_ALL = '#All';
    private const ITEM_SPECIFIER_HEADERS = '#Headers';
    private const ITEM_SPECIFIER_DATA = '#Data';
    private const ITEM_SPECIFIER_TOTALS = '#Totals';
    private const ITEM_SPECIFIER_THIS_ROW = '#This Row';
    private const ITEM_SPECIFIER_ROWS_SET = [self::ITEM_SPECIFIER_ALL, self::ITEM_SPECIFIER_HEADERS, self::ITEM_SPECIFIER_DATA, self::ITEM_SPECIFIER_TOTALS];
    private const TABLE_REFERENCE = '/([\p{L}_\\\\][\p{L}\p{N}\._]+)?(\[(?:[^\]\[]+|(?R))*+\])/miu';
    private string $table_name;
    private Table $table;
    private string $reference;
    private ?int $headers_row = null;
    private int $first_data_row;
    private int $last_data_row;
    private ?int $totals_row = null;
    /** @var mixed[] */
    private array $columns;
    public function __construct(private readonly string $value)
    {
    }
    /** @param string[] $matches */
    public static function from_parser(string $formula, int $index, array $matches): self
    {
        $val = $matches[0];
        $sr_count = substr_count($val, self::OPEN_BRACE) - substr_count($val, self::CLOSE_BRACE);
        while ($sr_count > 0) {
            $sr_index = strlen($val);
            $sr_string_remainder = substr($formula, $index + $sr_index);
            $closing_pos = strpos($sr_string_remainder, self::CLOSE_BRACE);
            if ($closing_pos === false) {
                throw new Exception("Formula Error: No closing ']' to match opening '['");
            }
            $sr_string_remainder = substr($sr_string_remainder, 0, $closing_pos + 1);
            --$sr_count;
            if (str_contains($sr_string_remainder, self::OPEN_BRACE)) {
                ++$sr_count;
            }
            $val .= $sr_string_remainder;
        }
        return new self($val);
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function parse(Cell $cell): string
    {
        $this->get_table_structure($cell);
        $cell_range = $this->is_row_reference() ? $this->get_row_reference($cell) : $this->get_column_reference();
        $sheet_name = '';
        $worksheet = $this->table->get_worksheet();
        if ($worksheet !== null && $worksheet !== $cell->get_worksheet()) {
            $sheet_name = "'" . $worksheet->get_title() . "'!";
        }
        return $sheet_name . $cell_range;
    }
    private function is_row_reference(): bool
    {
        return str_contains($this->value, '[@') || str_contains($this->value, '[' . self::ITEM_SPECIFIER_THIS_ROW . ']');
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function get_table_structure(Cell $cell): void
    {
        preg_match(self::TABLE_REFERENCE, $this->value, $matches);
        $this->table_name = $matches[1];
        $this->table = $this->table_name === '' ? $this->get_table_for_cell($cell) : $this->get_table_by_name($cell);
        $this->reference = $matches[2];
        $table_range = Coordinate::get_range_boundaries($this->table->get_range());
        $this->headers_row = $this->table->get_show_header_row() ? (int) $table_range[0][1] : null;
        $this->first_data_row = $this->table->get_show_header_row() ? (int) $table_range[0][1] + 1 : $table_range[0][1];
        $this->totals_row = $this->table->get_show_totals_row() ? (int) $table_range[1][1] : null;
        $this->last_data_row = $this->table->get_show_totals_row() ? (int) $table_range[1][1] - 1 : $table_range[1][1];
        $cell_param = $cell;
        $worksheet = $this->table->get_worksheet();
        if ($worksheet !== null && $worksheet !== $cell->get_worksheet()) {
            $cell_param = $worksheet->get_cell('A1');
        }
        $this->columns = $this->get_columns($cell_param, $table_range);
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function get_table_for_cell(Cell $cell): Table
    {
        $tables = $cell->get_worksheet()->get_table_collection();
        foreach ($tables as $table) {
            /** @var Table $table */
            $range = $table->get_range();
            if ($cell->is_in_range($range) === true) {
                $this->table_name = $table->get_name();
                return $table;
            }
        }
        throw new Exception('Table for Structured Reference cannot be identified');
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function get_table_by_name(Cell $cell): Table
    {
        $table = $cell->get_worksheet()->get_table_by_name($this->table_name);
        if ($table === null) {
            $spreadsheet = $cell->get_worksheet()->get_parent();
            if ($spreadsheet !== null) {
                $table = $spreadsheet->get_table_by_name($this->table_name);
            }
        }
        if ($table === null) {
            throw new Exception("Table {$this->table_name} for Structured Reference cannot be located");
        }
        return $table;
    }
    /**
     * @param array{array{string, int}, array{string, int}} $tableRange
     *
     * @return mixed[]
     */
    private function get_columns(Cell $cell, array $table_range): array
    {
        $worksheet = $cell->get_worksheet();
        $cell_reference = $cell->get_coordinate();
        $columns = [];
        $last_column = String_Helper::string_increment($table_range[1][0]);
        for ($column = $table_range[0][0]; $column !== $last_column; String_Helper::string_increment($column)) {
            /** @var string $column */
            $columns[$column] = $worksheet->get_cell($column . ($this->headers_row ?? $this->first_data_row - 1))->get_calculated_value();
        }
        $worksheet->get_cell($cell_reference);
        return $columns;
    }
    private function get_row_reference(Cell $cell): string
    {
        $reference = str_replace(" ", ' ', $this->reference);
        /** @var string $reference */
        $reference = str_replace('[' . self::ITEM_SPECIFIER_THIS_ROW . '],', '', $reference);
        foreach ($this->columns as $column_id => $column_name) {
            $column_name = str_replace(" ", ' ', $column_name);
            //* @phpstan-ignore-line
            $reference = $this->adjust_row_reference($column_name, $reference, $cell, $column_id);
        }
        return $this->validate_parsed_reference(trim($reference, '[]@, '));
    }
    private function adjust_row_reference(string $column_name, string $reference, Cell $cell, string $column_id): string
    {
        if ($column_name !== '') {
            $cell_reference = $column_id . $cell->get_row();
            $pattern1 = '/\[' . preg_quote($column_name, '/') . '\]/miu';
            $pattern2 = '/@' . preg_quote($column_name, '/') . '/miu';
            if (preg_match($pattern1, $reference) === 1) {
                $reference = preg_replace($pattern1, $cell_reference, $reference);
            } elseif (preg_match($pattern2, $reference) === 1) {
                $reference = preg_replace($pattern2, $cell_reference, $reference);
            }
        }
        return $reference;
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function get_column_reference(): string
    {
        $reference = str_replace(" ", ' ', $this->reference);
        $start_row = $this->totals_row ?? $this->last_data_row;
        $end_row = $this->headers_row ?? $this->first_data_row;
        [$start_row, $end_row] = $this->get_rows_for_column_reference($reference, $start_row, $end_row);
        $reference = $this->get_columns_for_column_reference($reference, $start_row, $end_row);
        $reference = trim($reference, '[]@, ');
        if (substr_count($reference, ':') > 1) {
            $cells = explode(':', $reference);
            $first_cell = array_shift($cells);
            $last_cell = array_pop($cells);
            $reference = "{$first_cell}:{$last_cell}";
        }
        return $this->validate_parsed_reference($reference);
    }
    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function validate_parsed_reference(string $reference): string
    {
        if (preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . ':' . Calculation::CALCULATION_REGEXP_CELLREF . '$/miu', $reference) !== 1) {
            if (preg_match('/^' . Calculation::CALCULATION_REGEXP_CELLREF . '$/miu', $reference) !== 1) {
                throw new Exception("Invalid Structured Reference {$this->reference} {$reference}", Exception::CALCULATION_ENGINE_PUSH_TO_STACK);
            }
        }
        return $reference;
    }
    private function full_data(int $start_row, int $end_row): string
    {
        $columns = array_keys($this->columns);
        $first_column = array_shift($columns);
        $last_column = empty($columns) ? $first_column : array_pop($columns);
        return "{$first_column}{$start_row}:{$last_column}{$end_row}";
    }
    private function get_minimum_row(string $reference): int
    {
        return match ($reference) {
            self::ITEM_SPECIFIER_ALL, self::ITEM_SPECIFIER_HEADERS => $this->headers_row ?? $this->first_data_row,
            self::ITEM_SPECIFIER_DATA => $this->first_data_row,
            self::ITEM_SPECIFIER_TOTALS => $this->totals_row ?? $this->last_data_row,
            default => $this->headers_row ?? $this->first_data_row,
        };
    }
    private function get_maximum_row(string $reference): int
    {
        return match ($reference) {
            self::ITEM_SPECIFIER_HEADERS => $this->headers_row ?? $this->first_data_row,
            self::ITEM_SPECIFIER_DATA => $this->last_data_row,
            self::ITEM_SPECIFIER_ALL, self::ITEM_SPECIFIER_TOTALS => $this->totals_row ?? $this->last_data_row,
            default => $this->totals_row ?? $this->last_data_row,
        };
    }
    public function value(): string
    {
        return $this->value;
    }
    /**
     * @return array<int, int>
     */
    private function get_rows_for_column_reference(string &$reference, int $start_row, int $end_row): array
    {
        $rows_selected = false;
        foreach (self::ITEM_SPECIFIER_ROWS_SET as $row_reference) {
            $pattern = '/\[' . $row_reference . '\]/mui';
            if (preg_match($pattern, $reference) === 1) {
                if ($row_reference === self::ITEM_SPECIFIER_HEADERS && $this->table->get_show_header_row() === false) {
                    throw new Exception('Table Headers are Hidden, and should not be Referenced', Exception::CALCULATION_ENGINE_PUSH_TO_STACK);
                }
                $rows_selected = true;
                $start_row = min($start_row, $this->get_minimum_row($row_reference));
                $end_row = max($end_row, $this->get_maximum_row($row_reference));
                $reference = preg_replace($pattern, '', $reference) ?? '';
            }
        }
        if ($rows_selected === false) {
            // If there isn't any Special Item Identifier specified, then the selection defaults to data rows only.
            $start_row = $this->first_data_row;
            $end_row = $this->last_data_row;
        }
        return [$start_row, $end_row];
    }
    private function get_columns_for_column_reference(string $reference, int $start_row, int $end_row): string
    {
        $columns_selected = false;
        foreach ($this->columns as $column_id => $column_name) {
            $column_name = str_replace(" ", ' ', $column_name ?? '');
            //* @phpstan-ignore-line
            $cell_from = "{$column_id}{$start_row}";
            $cell_to = "{$column_id}{$end_row}";
            $cell_reference = $cell_from === $cell_to ? $cell_from : "{$cell_from}:{$cell_to}";
            $pattern = '/\[' . preg_quote($column_name, '/') . '\]/mui';
            if (preg_match($pattern, (string) $reference) === 1) {
                $columns_selected = true;
                $reference = preg_replace($pattern, $cell_reference, (string) $reference);
            }
        }
        if ($columns_selected === false) {
            return $this->full_data($start_row, $end_row);
        }
        return $reference;
    }
    public function __toString(): string
    {
        return $this->value;
    }
}