<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Engine;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
class Array_Argument_Helper
{
    protected int $index_start = 0;
    /** @var mixed[] */
    protected array $arguments;
    protected int $argument_count;
    /** @var int[] */
    protected array $rows;
    /** @var int[] */
    protected array $columns;
    /** @param mixed[] $arguments */
    public function initialise(array $arguments): void
    {
        $keys = array_keys($arguments);
        $this->index_start = (int) array_shift($keys);
        $this->rows = $this->rows($arguments);
        $this->columns = $this->columns($arguments);
        $this->argument_count = count($arguments);
        $this->arguments = $this->flatten_single_cell_arrays($arguments, $this->rows, $this->columns);
        $this->rows = $this->rows($arguments);
        $this->columns = $this->columns($arguments);
        if ($this->array_arguments() > 2) {
            throw new Exception('Formulae with more than two array arguments are not supported');
        }
    }
    /** @return mixed[] */
    public function arguments(): array
    {
        return $this->arguments;
    }
    public function has_array_argument(): bool
    {
        return $this->array_arguments() > 0;
    }
    public function get_first_array_argument_number(): int
    {
        $row_arrays = $this->filter_array($this->rows);
        $column_arrays = $this->filter_array($this->columns);
        for ($index = $this->index_start; $index < $this->argument_count; ++$index) {
            if (isset($row_arrays[$index]) || isset($column_arrays[$index])) {
                return ++$index;
            }
        }
        return 0;
    }
    public function get_single_row_vector(): ?int
    {
        $row_vectors = $this->get_row_vectors();
        return count($row_vectors) === 1 ? array_pop($row_vectors) : null;
    }
    /** @return int[] */
    private function get_row_vectors(): array
    {
        $row_vectors = [];
        for ($index = $this->index_start; $index < $this->index_start + $this->argument_count; ++$index) {
            if ($this->rows[$index] === 1 && $this->columns[$index] > 1) {
                $row_vectors[] = $index;
            }
        }
        return $row_vectors;
    }
    public function get_single_column_vector(): ?int
    {
        $column_vectors = $this->get_column_vectors();
        return count($column_vectors) === 1 ? array_pop($column_vectors) : null;
    }
    /** @return int[] */
    private function get_column_vectors(): array
    {
        $column_vectors = [];
        for ($index = $this->index_start; $index < $this->index_start + $this->argument_count; ++$index) {
            if ($this->rows[$index] > 1 && $this->columns[$index] === 1) {
                $column_vectors[] = $index;
            }
        }
        return $column_vectors;
    }
    /** @return int[] */
    public function get_matrix_pair(): array
    {
        for ($i = $this->index_start; $i < $this->index_start + $this->argument_count - 1; ++$i) {
            for ($j = $i + 1; $j < $this->argument_count; ++$j) {
                if (isset($this->rows[$i], $this->rows[$j])) {
                    return [$i, $j];
                }
            }
        }
        return [];
    }
    public function is_vector(int $argument): bool
    {
        return $this->rows[$argument] === 1 || $this->columns[$argument] === 1;
    }
    public function is_row_vector(int $argument): bool
    {
        return $this->rows[$argument] === 1;
    }
    public function is_column_vector(int $argument): bool
    {
        return $this->columns[$argument] === 1;
    }
    public function row_count(int $argument): int
    {
        return $this->rows[$argument];
    }
    public function column_count(int $argument): int
    {
        return $this->columns[$argument];
    }
    /**
     * @param mixed[] $arguments
     *
     * @return int[]
     */
    private function rows(array $arguments): array
    {
        return array_map(fn($argument): int => is_countable($argument) ? count($argument) : 1, $arguments);
    }
    /**
     * @param mixed[] $arguments
     *
     * @return int[]
     */
    private function columns(array $arguments): array
    {
        return array_map(fn(mixed $argument): int => is_array($argument) && is_array($argument[array_keys($argument)[0]]) ? count($argument[array_keys($argument)[0]]) : 1, $arguments);
    }
    public function array_arguments(): int
    {
        $count = 0;
        foreach (array_keys($this->arguments) as $argument) {
            if ($this->rows[$argument] > 1 || $this->columns[$argument] > 1) {
                ++$count;
            }
        }
        return $count;
    }
    /**
     * @param mixed[] $arguments
     * @param int[] $rows
     * @param int[] $columns
     *
     * @return mixed[]
     */
    private function flatten_single_cell_arrays(array $arguments, array $rows, array $columns): array
    {
        foreach ($arguments as $index => $argument) {
            if ($rows[$index] === 1 && $columns[$index] === 1) {
                while (is_array($argument)) {
                    $argument = array_pop($argument);
                }
                $arguments[$index] = $argument;
            }
        }
        return $arguments;
    }
    /**
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    private function filter_array(array $array): array
    {
        return array_filter($array, fn($value): bool => $value > 1);
    }
}