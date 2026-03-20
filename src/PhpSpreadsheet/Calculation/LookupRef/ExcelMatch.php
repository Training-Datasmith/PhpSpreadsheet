<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Lookup_Ref;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Internal\Wildcard_Match;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
class Excel_Match
{
    use Array_Enabled;
    public const MATCHTYPE_SMALLEST_VALUE = -1;
    public const MATCHTYPE_FIRST_VALUE = 0;
    public const MATCHTYPE_LARGEST_VALUE = 1;
    /**
     * MATCH.
     *
     * The MATCH function searches for a specified item in a range of cells
     *
     * Excel Function:
     *        =MATCH(lookup_value, lookup_array, [match_type])
     *
     * @param mixed $lookupValue The value that you want to match in lookup_array
     * @param mixed $lookupArray The range of cells being searched
     * @param mixed $matchType The number -1, 0, or 1. -1 means above, 0 means exact match, 1 means below.
     *                         If match_type is 1 or -1, the list has to be ordered.
     *
     * @return array<mixed>|float|int|string The relative position of the found item
     */
    public static function MATCH(mixed $lookup_value, mixed $lookup_array, mixed $match_type = self::MATCHTYPE_LARGEST_VALUE): array|string|int|float
    {
        if (is_array($lookup_value)) {
            return self::evaluate_array_arguments_ignore([self::class, __FUNCTION__], 1, $lookup_value, $lookup_array, $match_type);
        }
        $lookup_array = Functions::flatten_array($lookup_array);
        try {
            // Input validation
            self::validate_lookup_value($lookup_value);
            $match_type = self::validate_match_type($match_type);
            self::validate_lookup_array($lookup_array);
            $key_set = array_keys($lookup_array);
            if ($match_type == self::MATCHTYPE_LARGEST_VALUE) {
                // If match_type is 1 the list has to be processed from last to first
                $lookup_array = array_reverse($lookup_array);
                $key_set = array_reverse($key_set);
            }
            $lookup_array = self::prepare_lookup_array($lookup_array, $match_type);
        } catch (Exception $e) {
            return $e->get_message();
        }
        // MATCH() is not case-sensitive, so we convert lookup value to be lower cased if it's a string type.
        if (is_string($lookup_value)) {
            $lookup_value = String_Helper::str_to_lower($lookup_value);
        }
        $value_key = match ($match_type) {
            self::MATCHTYPE_LARGEST_VALUE => self::match_largest_value($lookup_array, $lookup_value, $key_set),
            self::MATCHTYPE_FIRST_VALUE => self::match_first_value($lookup_array, $lookup_value),
            default => self::match_smallest_value($lookup_array, $lookup_value),
        };
        if ($value_key !== null) {
            return ++$value_key;
            //* @phpstan-ignore-line
        }
        // Unsuccessful in finding a match, return #N/A error value
        return Excel_Error::NA();
    }
    /** @param mixed[] $lookupArray */
    private static function match_first_value(array $lookup_array, mixed $lookup_value): int|string|null
    {
        if (is_string($lookup_value)) {
            $value_is_string = true;
            $wildcard = Wildcard_Match::wildcard($lookup_value);
        } else {
            $value_is_string = false;
            $wildcard = '';
        }
        $value_is_numeric = is_int($lookup_value) || is_float($lookup_value);
        foreach ($lookup_array as $i => $lookup_array_value) {
            if ($value_is_string && is_string($lookup_array_value)) {
                if (Wildcard_Match::compare($lookup_array_value, $wildcard)) {
                    return $i;
                    // wildcard match
                }
            } else {
                if ($lookup_array_value === $lookup_value) {
                    return $i;
                    // exact match
                }
                if ($value_is_numeric && (is_float($lookup_array_value) || is_int($lookup_array_value)) && $lookup_array_value == $lookup_value) {
                    return $i;
                    // exact match
                }
            }
        }
        return null;
    }
    /**
     * @param mixed[] $lookupArray
     * @param mixed[] $keySet
     */
    private static function match_largest_value(array $lookup_array, mixed $lookup_value, array $key_set): mixed
    {
        if (is_string($lookup_value)) {
            if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
                $wildcard = Wildcard_Match::wildcard($lookup_value);
                foreach (array_reverse($lookup_array) as $i => $lookup_array_value) {
                    if (is_string($lookup_array_value) && Wildcard_Match::compare($lookup_array_value, $wildcard)) {
                        return $i;
                    }
                }
            } else {
                foreach ($lookup_array as $i => $lookup_array_value) {
                    if ($lookup_array_value === $lookup_value) {
                        return $key_set[$i];
                    }
                }
            }
        }
        $value_is_numeric = is_int($lookup_value) || is_float($lookup_value);
        foreach ($lookup_array as $i => $lookup_array_value) {
            if ($value_is_numeric && (is_int($lookup_array_value) || is_float($lookup_array_value))) {
                if ($lookup_array_value <= $lookup_value) {
                    return array_search($i, $key_set);
                }
            }
            $type_match = gettype($lookup_value) === gettype($lookup_array_value);
            if ($type_match && $lookup_array_value <= $lookup_value) {
                return array_search($i, $key_set);
            }
        }
        return null;
    }
    /** @param mixed[] $lookupArray */
    private static function match_smallest_value(array $lookup_array, mixed $lookup_value): int|string|null
    {
        $value_key = null;
        if (is_string($lookup_value)) {
            if (Functions::get_compatibility_mode() === Functions::COMPATIBILITY_OPENOFFICE) {
                $wildcard = Wildcard_Match::wildcard($lookup_value);
                foreach ($lookup_array as $i => $lookup_array_value) {
                    if (is_string($lookup_array_value) && Wildcard_Match::compare($lookup_array_value, $wildcard)) {
                        return $i;
                    }
                }
            }
        }
        $value_is_numeric = is_int($lookup_value) || is_float($lookup_value);
        // The basic algorithm is:
        // Iterate and keep the highest match until the next element is smaller than the searched value.
        // Return immediately if perfect match is found
        foreach ($lookup_array as $i => $lookup_array_value) {
            $type_match = gettype($lookup_value) === gettype($lookup_array_value);
            $both_numeric = $value_is_numeric && (is_int($lookup_array_value) || is_float($lookup_array_value));
            if ($lookup_array_value === $lookup_value) {
                // Another "special" case. If a perfect match is found,
                // the algorithm gives up immediately
                return $i;
            }
            if ($both_numeric && $lookup_value == $lookup_array_value) {
                return $i;
                // exact match, as above
            }
            if (($type_match || $both_numeric) && $lookup_array_value >= $lookup_value) {
                $value_key = $i;
            } elseif ($type_match && $lookup_array_value < $lookup_value) {
                //Excel algorithm gives up immediately if the first element is smaller than the searched value
                break;
            }
        }
        return $value_key;
    }
    private static function validate_lookup_value(mixed $lookup_value): void
    {
        // Lookup_value type has to be number, text, or logical values
        if (!is_numeric($lookup_value) && !is_string($lookup_value) && !is_bool($lookup_value)) {
            throw new Exception(Excel_Error::NA());
        }
    }
    private static function validate_match_type(mixed $match_type): int
    {
        // Match_type is 0, 1 or -1
        // However Excel accepts other numeric values,
        //  including numeric strings and floats.
        //  It seems to just be interested in the sign.
        if (!is_numeric($match_type)) {
            throw new Exception(Excel_Error::Value());
        }
        if ($match_type > 0) {
            return self::MATCHTYPE_LARGEST_VALUE;
        }
        if ($match_type < 0) {
            return self::MATCHTYPE_SMALLEST_VALUE;
        }
        return self::MATCHTYPE_FIRST_VALUE;
    }
    /** @param mixed[] $lookupArray */
    private static function validate_lookup_array(array $lookup_array): void
    {
        // Lookup_array should not be empty
        $lookup_array_size = count($lookup_array);
        if ($lookup_array_size <= 0) {
            throw new Exception(Excel_Error::NA());
        }
    }
    /**
     * @param mixed[] $lookupArray
     *
     * @return mixed[]
     */
    private static function prepare_lookup_array(array $lookup_array, mixed $match_type): array
    {
        // Lookup_array should contain only number, text, or logical values, or empty (null) cells
        foreach ($lookup_array as $i => $value) {
            //    check the type of the value
            if (!is_numeric($value) && !is_string($value) && !is_bool($value) && $value !== null) {
                throw new Exception(Excel_Error::NA());
            }
            // Convert strings to lowercase for case-insensitive testing
            if (is_string($value)) {
                $lookup_array[$i] = String_Helper::str_to_lower($value);
            }
            if ($value === null && ($match_type == self::MATCHTYPE_LARGEST_VALUE || $match_type == self::MATCHTYPE_SMALLEST_VALUE)) {
                unset($lookup_array[$i]);
            }
        }
        return $lookup_array;
    }
}