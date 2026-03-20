<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Filter
{
    public static function filter(mixed $lookup_array, mixed $match_array, mixed $if_empty = null): mixed
    {
        if (!is_array($lookup_array)) {
            return Excel_Error::VALUE();
        }
        /** @var mixed[] $lookupArray */
        if (!is_array($match_array)) {
            return Excel_Error::VALUE();
        }
        $match_array = self::enumerate_array_keys($match_array);
        $result = Matrix::is_column_vector($match_array) ? self::filter_by_row($lookup_array, $match_array) : self::filter_by_column($lookup_array, $match_array);
        if (empty($result)) {
            return $if_empty ?? Excel_Error::CALC();
        }
        /** @var callable(mixed): mixed */
        $func = 'array_values';
        return array_values(array_map($func, $result));
    }
    /**
     * @param mixed[] $sortArray
     *
     * @return mixed[]
     */
    private static function enumerate_array_keys(array $sort_array): array
    {
        array_walk($sort_array, function (&$columns): void {
            if (is_array($columns)) {
                $columns = array_values($columns);
            }
        });
        return array_values($sort_array);
    }
    /**
     * @param mixed[] $lookupArray
     * @param mixed[] $matchArray
     *
     * @return mixed[]
     */
    private static function filter_by_row(array $lookup_array, array $match_array): array
    {
        $match_array = array_values(array_column($match_array, 0));
        // @phpstan-ignore-line
        return array_filter(array_values($lookup_array), fn($index): bool => (bool) ($match_array[$index] ?? null), ARRAY_FILTER_USE_KEY);
    }
    /**
     * @param mixed[] $lookupArray
     * @param mixed[] $matchArray
     *
     * @return mixed[]
     */
    private static function filter_by_column(array $lookup_array, array $match_array): array
    {
        $lookup_array = Matrix::transpose($lookup_array);
        if (count($match_array) === 1) {
            $match_array = array_pop($match_array);
        }
        /** @var mixed[] $matchArray */
        array_walk($match_array, function (&$value): void {
            $value = [$value];
        });
        $result = self::filter_by_row($lookup_array, $match_array);
        return Matrix::transpose($result);
    }
}