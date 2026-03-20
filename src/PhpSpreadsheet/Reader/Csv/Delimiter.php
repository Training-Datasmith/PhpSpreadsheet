<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Csv;

class Delimiter
{
    protected const POTENTIAL_DELIMITERS = [',', ';', "\t", '|', ':', ' ', '~'];
    /** @var array<string, int[]> */
    protected array $counts = [];
    protected int $number_lines = 0;
    protected ?string $delimiter = null;
    /**
     * @param resource $fileHandle
     */
    public function __construct(protected $file_handle, protected string $escape_character, protected string $enclosure)
    {
        $this->count_potential_delimiters();
    }
    public function get_default_delimiter(): string
    {
        return self::POTENTIAL_DELIMITERS[0];
    }
    public function lines_counted(): int
    {
        return $this->number_lines;
    }
    protected function count_potential_delimiters(): void
    {
        $this->counts = array_fill_keys(self::POTENTIAL_DELIMITERS, []);
        $delimiter_keys = array_flip(self::POTENTIAL_DELIMITERS);
        // Count how many times each of the potential delimiters appears in each line
        $this->number_lines = 0;
        while (($line = $this->get_next_line()) !== false && ++$this->number_lines < 1000) {
            $this->count_delimiter_values($line, $delimiter_keys);
        }
    }
    /** @param array<string, int> $delimiterKeys */
    protected function count_delimiter_values(string $line, array $delimiter_keys): void
    {
        $split_string = mb_str_split($line, 1, 'UTF-8');
        $distribution = array_count_values($split_string);
        $count_line = array_intersect_key($distribution, $delimiter_keys);
        foreach (self::POTENTIAL_DELIMITERS as $delimiter) {
            $this->counts[$delimiter][] = $count_line[$delimiter] ?? 0;
        }
    }
    public function infer(): ?string
    {
        // Calculate the mean square deviations for each delimiter
        //     (ignoring delimiters that haven't been found consistently)
        $mean_square_deviations = [];
        $middle_idx = (int) floor(($this->number_lines - 1) / 2);
        foreach (self::POTENTIAL_DELIMITERS as $delimiter) {
            $series = $this->counts[$delimiter];
            sort($series);
            $median = $this->number_lines % 2 ? $series[$middle_idx] : ($series[$middle_idx] + $series[$middle_idx + 1]) / 2;
            if ($median === 0) {
                continue;
            }
            $mean_square_deviations[$delimiter] = array_reduce($series, fn($sum, $value): int|float => $sum + ($value - $median) ** 2) / count($series);
        }
        // ... and pick the delimiter with the smallest mean square deviation
        //         (in case of ties, the order in potentialDelimiters is respected)
        $min = INF;
        foreach (self::POTENTIAL_DELIMITERS as $delimiter) {
            if (!isset($mean_square_deviations[$delimiter])) {
                continue;
            }
            if ($mean_square_deviations[$delimiter] < $min) {
                $min = $mean_square_deviations[$delimiter];
                $this->delimiter = $delimiter;
            }
        }
        return $this->delimiter;
    }
    /**
     * Get the next full line from the file.
     *
     * @return false|string
     */
    public function get_next_line()
    {
        $line = '';
        $enclosure = ($this->escape_character === '' ? '' : '(?<!' . preg_quote($this->escape_character, '/') . ')') . preg_quote($this->enclosure, '/');
        do {
            // Get the next line in the file
            $new_line = fgets($this->file_handle);
            // Return false if there is no next line
            if ($new_line === false) {
                return false;
            }
            // Add the new line to the line passed in
            $line = $line . $new_line;
            // Drop everything that is enclosed to avoid counting false positives in enclosures
            $line = (string) preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/Us', '', $line);
            // See if we have any enclosures left in the line
            // if we still have an enclosure then we need to read the next line as well
        } while (preg_match('/(' . $enclosure . ')/', $line) > 0);
        return $line !== '' ? $line : false;
    }
}