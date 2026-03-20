<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Size
{
    /**
     * LARGE.
     *
     * Returns the nth largest value in a data set. You can use this function to
     *        select a value based on its relative standing.
     *
     * Excel Function:
     *        LARGE(value1[,value2[, ...]],entry)
     *
     * @param mixed $args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function large(mixed ...$args): string|float
    {
        $a_args = Functions::flatten_array($args);
        $entry = array_pop($a_args);
        if (is_numeric($entry) && !is_string($entry)) {
            $entry = (int) floor($entry);
            $m_args = self::filter($a_args);
            $count = Counts::COUNT($m_args);
            --$entry;
            if ($count === 0 || $entry < 0 || $entry >= $count) {
                return Excel_Error::NAN();
            }
            rsort($m_args);
            /** @var float[] $mArgs */
            return $m_args[$entry];
        }
        return Excel_Error::VALUE();
    }
    /**
     * SMALL.
     *
     * Returns the nth smallest value in a data set. You can use this function to
     *        select a value based on its relative standing.
     *
     * Excel Function:
     *        SMALL(value1[,value2[, ...]],entry)
     *
     * @param mixed $args Data values
     *
     * @return float|string The result, or a string containing an error
     */
    public static function small(mixed ...$args): string|float
    {
        $a_args = Functions::flatten_array($args);
        $entry = array_pop($a_args);
        if (is_numeric($entry) && !is_string($entry)) {
            $entry = (int) floor($entry);
            $m_args = self::filter($a_args);
            $count = Counts::COUNT($m_args);
            --$entry;
            if ($count === 0 || $entry < 0 || $entry >= $count) {
                return Excel_Error::NAN();
            }
            sort($m_args);
            /** @var float[] $mArgs */
            return $m_args[$entry];
        }
        return Excel_Error::VALUE();
    }
    /**
     * @param mixed[] $args Data values
     *
     * @return mixed[]
     */
    protected static function filter(array $args): array
    {
        $m_args = [];
        foreach ($args as $arg) {
            // Is it a numeric value?
            if (is_numeric($arg) && !is_string($arg)) {
                $m_args[] = $arg;
            }
        }
        return $m_args;
    }
}