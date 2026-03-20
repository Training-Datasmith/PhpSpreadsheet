<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Validations;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
/**
 * Helper class to manipulate cell coordinates.
 *
 * Columns indexes and rows are always based on 1, **not** on 0. This match the behavior
 * that Excel users are used to, and also match the Excel functions `COLUMN()` and `ROW()`.
 */
abstract class Coordinate
{
    public const A1_COORDINATE_REGEX = '/^(?<col>\$?[A-Z]{1,3})(?<row>\$?\d{1,7})$/i';
    public const FULL_REFERENCE_REGEX = '/^(?:(?<worksheet>[^!]*)!)?(?<localReference>(?<firstCoordinate>[$]?[A-Z]{1,3}[$]?\d{1,7})(?:\:(?<secondCoordinate>[$]?[A-Z]{1,3}[$]?\d{1,7}))?)$/i';
    /**
     * Default range variable constant.
     *
     * @var string
     */
    public const DEFAULT_RANGE = 'A1:A1';
    /**
     * Convert string coordinate to [0 => int column index, 1 => int row index].
     *
     * @param string $cellAddress eg: 'A1'
     *
     * @return array{0: string, 1: string} Array containing column and row (indexes 0 and 1)
     */
    public static function coordinate_from_string(string $cell_address): array
    {
        if (preg_match(self::A1_COORDINATE_REGEX, $cell_address, $matches)) {
            $row = (int) ltrim($matches['row'], '$');
            // reluctantly allow row 0 due to regression problems
            if ($row <= Address_Range::MAX_ROW) {
                return [$matches['col'], $matches['row']];
            }
        } elseif (self::coordinate_is_range($cell_address)) {
            throw new Exception('Cell coordinate string can not be a range of cells');
        } elseif ($cell_address == '') {
            throw new Exception('Cell coordinate can not be zero-length string');
        }
        throw new Exception('Invalid cell coordinate ' . $cell_address);
    }
    /**
     * Convert string coordinate to [0 => int column index, 1 => int row index, 2 => string column string].
     *
     * @param string $coordinates eg: 'A1', '$B$12'
     *
     * @return array{0: int, 1: int, 2: string} Array containing column and row index, and column string
     */
    public static function indexes_from_string(string $coordinates): array
    {
        [$column, $row] = self::coordinate_from_string($coordinates);
        $column = ltrim($column, '$');
        return [self::column_index_from_string($column), (int) ltrim($row, '$'), $column];
    }
    /**
     * Checks if a Cell Address represents a range of cells.
     *
     * @param string $cellAddress eg: 'A1' or 'A1:A2' or 'A1:A2,C1:C2'
     *
     * @return bool Whether the coordinate represents a range of cells
     */
    public static function coordinate_is_range(string $cell_address): bool
    {
        return str_contains($cell_address, ':') || str_contains($cell_address, ',');
    }
    /**
     * Make string row, column or cell coordinate absolute.
     *
     * @param int|string $cellAddress e.g. 'A' or '1' or 'A1'
     *                    Note that this value can be a row or column reference as well as a cell reference
     *
     * @return string Absolute coordinate        e.g. '$A' or '$1' or '$A$1'
     */
    public static function absolute_reference(int|string $cell_address): string
    {
        $cell_address = (string) $cell_address;
        if (self::coordinate_is_range($cell_address)) {
            throw new Exception('Cell coordinate string can not be a range of cells');
        }
        // Split out any worksheet name from the reference
        [$worksheet, $cell_address] = Worksheet::extract_sheet_title($cell_address, true);
        if ($worksheet > '') {
            $worksheet .= '!';
        }
        // Create absolute coordinate
        $cell_address = "{$cell_address}";
        if (ctype_digit($cell_address)) {
            return $worksheet . '$' . $cell_address;
        }
        if (ctype_alpha($cell_address)) {
            return $worksheet . '$' . strtoupper($cell_address);
        }
        return $worksheet . self::absolute_coordinate($cell_address);
    }
    /**
     * Make string coordinate absolute.
     *
     * @param string $cellAddress e.g. 'A1'
     *
     * @return string Absolute coordinate        e.g. '$A$1'
     */
    public static function absolute_coordinate(string $cell_address): string
    {
        if (self::coordinate_is_range($cell_address)) {
            throw new Exception('Cell coordinate string can not be a range of cells');
        }
        // Split out any worksheet name from the coordinate
        [$worksheet, $cell_address] = Worksheet::extract_sheet_title($cell_address, true);
        if ($worksheet > '') {
            $worksheet .= '!';
        }
        // Create absolute coordinate
        [$column, $row] = self::coordinate_from_string($cell_address ?? 'A1');
        $column = ltrim($column, '$');
        $row = ltrim($row, '$');
        return $worksheet . '$' . $column . '$' . $row;
    }
    /**
     * Split range into coordinate strings, using comma for union
     * and ignoring intersection (space).
     *
     * @param string $range e.g. 'B4:D9' or 'B4:D9,H2:O11' or 'B4'
     *
     * @return array<array<string>> Array containing one or more arrays containing one or two coordinate strings
     *                                e.g. ['B4','D9'] or [['B4','D9'], ['H2','O11']]
     *                                        or ['B4']
     */
    public static function split_range(string $range): array
    {
        // Ensure $pRange is a valid range
        if (empty($range)) {
            $range = self::DEFAULT_RANGE;
        }
        $exploded = explode(',', $range);
        $out_array = [];
        foreach ($exploded as $value) {
            $out_array[] = explode(':', $value);
        }
        return $out_array;
    }
    /**
     * Split range into coordinate strings, resolving unions and intersections.
     *
     * @param string $range e.g. 'B4:D9' or 'B4:D9,H2:O11' or 'B4'
     * @param bool $unionIsComma true=comma is union, space is intersection
     *                           false=space is union, comma is intersection
     *
     * @return array<array<string>> Array containing one or more arrays containing one or two coordinate strings
     *                                e.g. ['B4','D9'] or [['B4','D9'], ['H2','O11']]
     *                                        or ['B4']
     */
    public static function all_ranges(string $range, bool $union_is_comma = true): array
    {
        if (!$union_is_comma) {
            $range = str_replace([',', ' ', "\x00"], ["\x00", ',', ' '], $range);
        }
        return self::split_range(self::resolve_union_and_intersection($range));
    }
    /**
     * Build range from coordinate strings.
     *
     * @param mixed[] $range Array containing one or more arrays containing one or two coordinate strings
     *
     * @return string String representation of $pRange
     */
    public static function build_range(array $range): string
    {
        // Verify range
        if (empty($range)) {
            throw new Exception('Range does not contain any information');
        }
        // Build range
        $counter = count($range);
        for ($i = 0; $i < $counter; ++$i) {
            if (!is_array($range[$i])) {
                throw new Exception('Each array entry must be an array');
            }
            $range[$i] = implode(':', $range[$i]);
        }
        return implode(',', $range);
    }
    /**
     * Calculate range boundaries.
     *
     * @param string $range Cell range, Single Cell, Row/Column Range (e.g. A1:A1, B2, B:C, 2:3)
     *
     * @return array{array{int, int}, array{int, int}} Range coordinates [Start Cell, End Cell]
     *                    where Start Cell and End Cell are arrays (Column Number, Row Number)
     */
    public static function range_boundaries(string $range): array
    {
        // Ensure $pRange is a valid range
        if (empty($range)) {
            $range = self::DEFAULT_RANGE;
        }
        // Uppercase coordinate
        $range = strtoupper($range);
        // Extract range
        if (!str_contains($range, ':')) {
            $range_a = $range_b = $range;
        } else {
            [$range_a, $range_b] = explode(':', $range);
        }
        if (is_numeric($range_a) && is_numeric($range_b)) {
            $range_a = 'A' . $range_a;
            $range_b = Address_Range::MAX_COLUMN . $range_b;
        }
        if (ctype_alpha($range_a) && ctype_alpha($range_b)) {
            $range_a = $range_a . '1';
            $range_b = $range_b . Address_Range::MAX_ROW;
        }
        // Calculate range outer borders
        $range_start = self::coordinate_from_string($range_a);
        $range_end = self::coordinate_from_string($range_b);
        // Translate column into index
        $range_start[0] = self::column_index_from_string($range_start[0]);
        $range_end[0] = self::column_index_from_string($range_end[0]);
        $range_start[1] = (int) $range_start[1];
        $range_end[1] = (int) $range_end[1];
        return [$range_start, $range_end];
    }
    /**
     * Calculate range dimension.
     *
     * @param string $range Cell range, Single Cell, Row/Column Range (e.g. A1:A1, B2, B:C, 2:3)
     *
     * @return array{int, int} Range dimension (width, height)
     */
    public static function range_dimension(string $range): array
    {
        // Calculate range outer borders
        [$range_start, $range_end] = self::range_boundaries($range);
        return [$range_end[0] - $range_start[0] + 1, $range_end[1] - $range_start[1] + 1];
    }
    /**
     * Calculate range boundaries.
     *
     * @param string $range Cell range, Single Cell, Row/Column Range (e.g. A1:A1, B2, B:C, 2:3)
     *
     * @return array{array{string, int}, array{string, int}} Range coordinates [Start Cell, End Cell]
     *                    where Start Cell and End Cell are arrays [Column ID, Row Number]
     */
    public static function get_range_boundaries(string $range): array
    {
        [$range_a, $range_b] = self::range_boundaries($range);
        return [[self::string_from_column_index($range_a[0]), $range_a[1]], [self::string_from_column_index($range_b[0]), $range_b[1]]];
    }
    /**
     * Check if cell or range reference is valid and return an array with type of reference (cell or range), worksheet (if it was given)
     * and the coordinate or the first coordinate and second coordinate if it is a range.
     *
     * @param string $reference Coordinate or Range (e.g. A1:A1, B2, B:C, 2:3)
     *
     * @return array{type: string, firstCoordinate?: string, secondCoordinate?: string, coordinate?: string, worksheet?: string, localReference?: string} reference data
     */
    private static function validate_reference_and_get_data(string $reference): array
    {
        $data = [];
        if (1 !== preg_match(self::FULL_REFERENCE_REGEX, $reference, $matches)) {
            return ['type' => 'invalid'];
        }
        if (isset($matches['secondCoordinate'])) {
            $data['type'] = 'range';
            $data['firstCoordinate'] = str_replace('$', '', $matches['firstCoordinate']);
            $data['secondCoordinate'] = str_replace('$', '', $matches['secondCoordinate']);
        } else {
            $data['type'] = 'coordinate';
            $data['coordinate'] = str_replace('$', '', $matches['firstCoordinate']);
        }
        $worksheet = $matches['worksheet'];
        if ($worksheet !== '') {
            if (str_starts_with($worksheet, "'") && str_ends_with($worksheet, "'")) {
                $worksheet = substr($worksheet, 1, -1);
            }
            $data['worksheet'] = strtolower($worksheet);
        }
        $data['localReference'] = str_replace('$', '', $matches['localReference']);
        return $data;
    }
    /**
     * Check if coordinate is inside a range.
     *
     * @param string $range Cell range, Single Cell, Row/Column Range (e.g. A1:A1, B2, B:C, 2:3)
     * @param string $coordinate Cell coordinate (e.g. A1)
     *
     * @return bool true if coordinate is inside range
     */
    public static function coordinate_is_inside_range(string $range, string $coordinate): bool
    {
        $range = Validations::convert_whole_row_column($range);
        $range_data = self::validate_reference_and_get_data($range);
        if ($range_data['type'] === 'invalid') {
            throw new Exception('First argument needs to be a range');
        }
        $coordinate_data = self::validate_reference_and_get_data($coordinate);
        if ($coordinate_data['type'] === 'invalid') {
            throw new Exception('Second argument needs to be a single coordinate');
        }
        if (isset($coordinate_data['worksheet']) && !isset($range_data['worksheet'])) {
            return false;
        }
        if (!isset($coordinate_data['worksheet']) && isset($range_data['worksheet'])) {
            return false;
        }
        if (isset($coordinate_data['worksheet'], $range_data['worksheet'])) {
            if ($coordinate_data['worksheet'] !== $range_data['worksheet']) {
                return false;
            }
        }
        if (!isset($range_data['localReference'])) {
            return false;
        }
        $boundaries = self::range_boundaries($range_data['localReference']);
        if (!isset($coordinate_data['localReference'])) {
            return false;
        }
        $coordinates = self::indexes_from_string($coordinate_data['localReference']);
        $column_is_inside = $boundaries[0][0] <= $coordinates[0] && $coordinates[0] <= $boundaries[1][0];
        if (!$column_is_inside) {
            return false;
        }
        $row_is_inside = $boundaries[0][1] <= $coordinates[1] && $coordinates[1] <= $boundaries[1][1];
        if (!$row_is_inside) {
            return false;
        }
        return true;
    }
    /**
     * Column index from string.
     *
     * @param ?string $columnAddress eg 'A'
     *
     * @return int Column index (A = 1)
     */
    public static function column_index_from_string(?string $column_address): int
    {
        //    Using a lookup cache adds a slight memory overhead, but boosts speed
        //    caching using a static within the method is faster than a class static,
        //        though it's additional memory overhead
        /** @var int[] */
        static $index_cache = [];
        $column_address ??= '';
        if (isset($index_cache[$column_address])) {
            return $index_cache[$column_address];
        }
        //    It's surprising how costly the strtoupper() and ord() calls actually are, so we use a lookup array
        //        rather than use ord() and make it case-insensitive to get rid of the strtoupper() as well.
        //        Because it's a static, there's no significant memory overhead either.
        /** @var array<string, int> */
        static $column_lookup = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8, 'I' => 9, 'J' => 10, 'K' => 11, 'L' => 12, 'M' => 13, 'N' => 14, 'O' => 15, 'P' => 16, 'Q' => 17, 'R' => 18, 'S' => 19, 'T' => 20, 'U' => 21, 'V' => 22, 'W' => 23, 'X' => 24, 'Y' => 25, 'Z' => 26, 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8, 'i' => 9, 'j' => 10, 'k' => 11, 'l' => 12, 'm' => 13, 'n' => 14, 'o' => 15, 'p' => 16, 'q' => 17, 'r' => 18, 's' => 19, 't' => 20, 'u' => 21, 'v' => 22, 'w' => 23, 'x' => 24, 'y' => 25, 'z' => 26];
        //    We also use the language construct isset() rather than the more costly strlen() function to match the
        //       length of $columnAddress for improved performance
        if (isset($column_address[0])) {
            if (!isset($column_address[1])) {
                $index_cache[$column_address] = $column_lookup[$column_address];
                return $index_cache[$column_address];
            }
            if (!isset($column_address[2])) {
                $index_cache[$column_address] = $column_lookup[$column_address[0]] * 26 + $column_lookup[$column_address[1]];
                return $index_cache[$column_address];
            }
            if (!isset($column_address[3])) {
                $temp = $column_lookup[$column_address[0]] * 676 + $column_lookup[$column_address[1]] * 26 + $column_lookup[$column_address[2]];
                if ($temp <= Address_Range::MAX_COLUMN_INT) {
                    $index_cache[$column_address] = $temp;
                    return $temp;
                }
            }
        }
        throw new Exception('Column string index can not be ' . (isset($column_address[0]) ? 'beyond ' . Address_Range::MAX_COLUMN : 'empty'));
    }
    private const LOOKUP_CACHE = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    /**
     * String from column index.
     *
     * @param int|numeric-string $columnIndex Column index (A = 1)
     */
    public static function string_from_column_index(int|string $column_index, bool $tolerate_zero = false): string
    {
        /** @var string[] */
        static $index_cache = [];
        $column_index2 = (int) $column_index;
        if ($column_index2 === 0 && $tolerate_zero) {
            return '';
        }
        if ($column_index2 < 1 || $column_index2 > Address_Range::MAX_COLUMN_INT) {
            throw new Exception("Invalid column index {$column_index}");
        }
        $column_index = $column_index2;
        if (!isset($index_cache[$column_index])) {
            $index_value = $column_index;
            $base26 = '';
            do {
                $character_value = $index_value % 26 ?: 26;
                $index_value = ($index_value - $character_value) / 26;
                $base26 = self::LOOKUP_CACHE[$character_value] . $base26;
            } while ($index_value > 0);
            $index_cache[$column_index] = $base26;
        }
        return $index_cache[$column_index];
    }
    /**
     * Extract all cell references in range, which may be comprised of multiple cell ranges.
     *
     * @param string $cellRange Range: e.g. 'A1' or 'A1:C10' or 'A1:E10,A20:E25' or 'A1:E5 C3:G7' or 'A1:C1,A3:C3 B1:C3'
     *
     * @return string[] Array containing single cell references
     */
    public static function extract_all_cell_references_in_range(string $cell_range): array
    {
        if (substr_count($cell_range, '!') > 1) {
            throw new Exception('3-D Range References are not supported');
        }
        [$worksheet, $cell_range] = Worksheet::extract_sheet_title($cell_range, true);
        $quoted = '';
        if ($worksheet) {
            $quoted = Worksheet::name_requires_quotes($worksheet) ? "'" : '';
            if (str_starts_with($worksheet, "'") && str_ends_with($worksheet, "'")) {
                $worksheet = substr($worksheet, 1, -1);
            }
            $worksheet = str_replace("'", "''", $worksheet);
        }
        [$ranges, $operators] = self::get_cell_blocks_from_range_string($cell_range ?? 'A1');
        $cells = [];
        foreach ($ranges as $range) {
            /** @var string $range */
            $cells[] = self::get_references_for_cell_block($range);
        }
        /** @var mixed[] */
        $cells = self::process_range_set_operators($operators, $cells);
        if (empty($cells)) {
            return [];
        }
        /** @var string[] */
        $cell_list = array_merge(...$cells);
        //* @phpstan-ignore-line
        // Unsure how to satisfy phpstan in line above
        $ret_val = array_map(fn(string $cell_address): string => $worksheet !== '' ? "{$quoted}{$worksheet}{$quoted}!{$cell_address}" : $cell_address, self::sort_cell_reference_array($cell_list));
        return $ret_val;
    }
    /**
     * @param mixed[] $operators
     * @param mixed[][] $cells
     *
     * @return mixed[]
     */
    private static function process_range_set_operators(array $operators, array $cells): array
    {
        $operator_count = count($operators);
        for ($offset = 0; $offset < $operator_count; ++$offset) {
            $operator = $operators[$offset];
            if ($operator !== ' ') {
                continue;
            }
            $cells[$offset] = array_intersect($cells[$offset], $cells[$offset + 1]);
            unset($operators[$offset], $cells[$offset + 1]);
            $operators = array_values($operators);
            $cells = array_values($cells);
            --$offset;
            --$operator_count;
        }
        return $cells;
    }
    /**
     * @param string[] $cellList
     *
     * @return string[]
     */
    private static function sort_cell_reference_array(array $cell_list): array
    {
        //    Sort the result by column and row
        $sort_keys = [];
        foreach ($cell_list as $coordinate) {
            $column = '';
            $row = 0;
            sscanf($coordinate, '%[A-Z]%d', $column, $row);
            /** @var int $row */
            $key = --$row * Address_Range::MAX_COLUMN_INT + self::column_index_from_string((string) $column);
            $sort_keys[$key] = $coordinate;
        }
        ksort($sort_keys);
        return array_values($sort_keys);
    }
    /**
     * Get all cell references applying union and intersection.
     *
     * @param string $cellBlock A cell range e.g. A1:B5,D1:E5 B2:C4
     *
     * @return string A string without intersection operator.
     *   If there was no intersection to begin with, return original argument.
     *   Otherwise, return cells and/or cell ranges in that range separated by comma.
     */
    public static function resolve_union_and_intersection(string $cell_block, string $implode_character = ','): string
    {
        $cell_block = preg_replace('/  +/', ' ', trim($cell_block)) ?? $cell_block;
        $cell_block = preg_replace('/ ,/', ',', $cell_block) ?? $cell_block;
        $cell_block = preg_replace('/, /', ',', $cell_block) ?? $cell_block;
        $array1 = [];
        $blocks = explode(',', $cell_block);
        foreach ($blocks as $block) {
            $block0 = explode(' ', $block);
            if (count($block0) === 1) {
                $array1 = array_merge($array1, $block0);
            } else {
                $block_idx = -1;
                $array2 = [];
                foreach ($block0 as $block00) {
                    ++$block_idx;
                    if ($block_idx === 0) {
                        $array2 = self::get_references_for_cell_block($block00);
                    } else {
                        $array2 = array_intersect($array2, self::get_references_for_cell_block($block00));
                    }
                }
                $array1 = array_merge($array1, $array2);
            }
        }
        return implode($implode_character, $array1);
    }
    /**
     * Get all cell references for an individual cell block.
     *
     * @param string $cellBlock A cell range e.g. A4:B5
     *
     * @return string[] All individual cells in that range
     */
    private static function get_references_for_cell_block(string $cell_block): array
    {
        $return_value = [];
        // Single cell?
        if (!self::coordinate_is_range($cell_block)) {
            return (array) $cell_block;
        }
        // Range...
        $ranges = self::split_range($cell_block);
        foreach ($ranges as $range) {
            // Single cell?
            if (!isset($range[1])) {
                $return_value[] = $range[0];
                continue;
            }
            // Range...
            [$range_start, $range_end] = $range;
            [$start_column, $start_row] = self::coordinate_from_string($range_start);
            [$end_column, $end_row] = self::coordinate_from_string($range_end);
            $start_column_index = self::column_index_from_string($start_column);
            $end_column_index = self::column_index_from_string($end_column);
            ++$end_column_index;
            // Current data
            $current_column_index = $start_column_index;
            $current_row = $start_row;
            self::validate_range($cell_block, $start_column_index, $end_column_index, (int) $current_row, (int) $end_row);
            // Loop cells
            while ($current_column_index < $end_column_index) {
                /** @var int $currentRow */
                /** @var int $endRow */
                while ($current_row <= $end_row) {
                    $return_value[] = self::string_from_column_index($current_column_index) . $current_row;
                    ++$current_row;
                }
                ++$current_column_index;
                $current_row = $start_row;
            }
        }
        return $return_value;
    }
    /**
     * Convert an associative array of single cell coordinates to values to an associative array
     * of cell ranges to values.  Only adjacent cell coordinates with the same
     * value will be merged.  If the value is an object, it must implement the method getHashCode().
     *
     * For example, this function converts:
     *
     *    [ 'A1' => 'x', 'A2' => 'x', 'A3' => 'x', 'A4' => 'y' ]
     *
     * to:
     *
     *    [ 'A1:A3' => 'x', 'A4' => 'y' ]
     *
     * @param array<string, mixed> $coordinateCollection associative array mapping coordinates to values
     *
     * @return array<string, mixed> associative array mapping coordinate ranges to values
     */
    public static function merge_ranges_in_collection(array $coordinate_collection): array
    {
        $hashed_values = [];
        $merged_coord_collection = [];
        foreach ($coordinate_collection as $coord => $value) {
            if (self::coordinate_is_range($coord)) {
                $merged_coord_collection[$coord] = $value;
                continue;
            }
            [$column, $row] = self::coordinate_from_string($coord);
            $row = (int) ltrim($row, '$');
            $hash_code = $column . '-' . String_Helper::convert_to_string(is_object($value) && method_exists($value, 'getHashCode') ? $value->get_hash_code() : $value);
            if (!isset($hashed_values[$hash_code])) {
                $hashed_values[$hash_code] = (object) ['value' => $value, 'col' => $column, 'rows' => [$row]];
            } else {
                $hashed_values[$hash_code]->rows[] = $row;
            }
        }
        ksort($hashed_values);
        foreach ($hashed_values as $hashed_value) {
            sort($hashed_value->rows);
            $row_start = null;
            $row_end = null;
            $ranges = [];
            foreach ($hashed_value->rows as $row) {
                if ($row_start === null) {
                    $row_start = $row;
                    $row_end = $row;
                } elseif ($row_end === $row - 1) {
                    $row_end = $row;
                } else {
                    if ($row_start == $row_end) {
                        $ranges[] = $hashed_value->col . $row_start;
                    } else {
                        $ranges[] = $hashed_value->col . $row_start . ':' . $hashed_value->col . $row_end;
                    }
                    $row_start = $row;
                    $row_end = $row;
                }
            }
            if ($row_start !== null) {
                // @phpstan-ignore-line
                if ($row_start == $row_end) {
                    $ranges[] = $hashed_value->col . $row_start;
                } else {
                    $ranges[] = $hashed_value->col . $row_start . ':' . $hashed_value->col . $row_end;
                }
            }
            foreach ($ranges as $range) {
                $merged_coord_collection[$range] = $hashed_value->value;
            }
        }
        return $merged_coord_collection;
    }
    /**
     * Get the individual cell blocks from a range string, removing any $ characters.
     *      then splitting by operators and returning an array with ranges and operators.
     *
     * @return mixed[][]
     */
    private static function get_cell_blocks_from_range_string(string $range_string): array
    {
        $range_string = str_replace('$', '', strtoupper($range_string));
        // split range sets on intersection (space) or union (,) operators
        $tokens = preg_split('/([ ,])/', $range_string, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $split = array_chunk($tokens, 2);
        $ranges = array_column($split, 0);
        $operators = array_column($split, 1);
        return [$ranges, $operators];
    }
    /**
     * Check that the given range is valid, i.e. that the start column and row are not greater than the end column and
     * row.
     *
     * @param string $cellBlock The original range, for displaying a meaningful error message
     */
    private static function validate_range(string $cell_block, int $start_column_index, int $end_column_index, int $current_row, int $end_row): void
    {
        if ($start_column_index >= $end_column_index || $current_row > $end_row) {
            throw new Exception('Invalid range: "' . $cell_block . '"');
        }
    }
}