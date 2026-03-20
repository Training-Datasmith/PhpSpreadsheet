<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

use Php_Office\Php_Spreadsheet\Calculation\Engine\Array_Argument_Helper;
use Php_Office\Php_Spreadsheet\Calculation\Engine\Array_Argument_Processor;
trait Array_Enabled
{
    private static bool $initialization_needed = true;
    private static Array_Argument_Helper $array_argument_helper;
    /**
     * @param mixed[] $arguments
     */
    private static function initialise_helper(array $arguments): void
    {
        if (self::$initialization_needed === true) {
            self::$array_argument_helper = new Array_Argument_Helper();
            self::$initialization_needed = false;
        }
        self::$array_argument_helper->initialise($arguments);
    }
    /**
     * Handles array argument processing when the function accepts a single argument that can be an array argument.
     * Example use for:
     *         DAYOFMONTH() or FACT().
     *
     * @param mixed[] $values
     *
     * @return mixed[]
     */
    protected static function evaluate_single_argument_array(callable $method, array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = $method($value);
        }
        return $result;
    }
    /**
     * Handles array argument processing when the function accepts multiple arguments,
     *     and any of them can be an array argument.
     * Example use for:
     *         ROUND() or DATE().
     *
     * @return mixed[]
     */
    protected static function evaluate_array_arguments(callable $method, mixed ...$arguments): array
    {
        self::initialise_helper($arguments);
        $arguments = self::$array_argument_helper->arguments();
        return Array_Argument_Processor::process_arguments(self::$array_argument_helper, $method, ...$arguments);
    }
    /**
     * Handles array argument processing when the function accepts multiple arguments,
     *     but only the first few (up to limit) can be an array arguments.
     * Example use for:
     *         NETWORKDAYS() or CONCATENATE(), where the last argument is a matrix (or a series of values) that need
     *                                         to be treated as a such rather than as an array arguments.
     *
     * @return mixed[]
     */
    protected static function evaluate_array_arguments_subset(callable $method, int $limit, mixed ...$arguments): array
    {
        self::initialise_helper(array_slice($arguments, 0, $limit));
        $trailing_arguments = array_slice($arguments, $limit);
        $arguments = self::$array_argument_helper->arguments();
        $arguments = array_merge($arguments, $trailing_arguments);
        return Array_Argument_Processor::process_arguments(self::$array_argument_helper, $method, ...$arguments);
    }
    private static function test_false(mixed $value): bool
    {
        return $value === false;
    }
    /**
     * Handles array argument processing when the function accepts multiple arguments,
     *     but only the last few (from start) can be an array arguments.
     * Example use for:
     *         Z.TEST() or INDEX(), where the first argument 1 is a matrix that needs to be treated as a dataset
     *                   rather than as an array argument.
     *
     * @return mixed[]
     */
    protected static function evaluate_array_arguments_subset_from(callable $method, int $start, mixed ...$arguments): array
    {
        $array_arguments_subset = array_combine(range($start, count($arguments) - $start), array_slice($arguments, $start));
        if (self::test_false($array_arguments_subset)) {
            return ['#VALUE!'];
        }
        self::initialise_helper($array_arguments_subset);
        $leading_arguments = array_slice($arguments, 0, $start);
        $arguments = self::$array_argument_helper->arguments();
        $arguments = array_merge($leading_arguments, $arguments);
        return Array_Argument_Processor::process_arguments(self::$array_argument_helper, $method, ...$arguments);
    }
    /**
     * Handles array argument processing when the function accepts multiple arguments,
     *     and any of them can be an array argument except for the one specified by ignore.
     * Example use for:
     *         HLOOKUP() and VLOOKUP(), where argument 1 is a matrix that needs to be treated as a database
     *                                  rather than as an array argument.
     *
     * @return mixed[]
     */
    protected static function evaluate_array_arguments_ignore(callable $method, int $ignore, mixed ...$arguments): array
    {
        $leading_arguments = array_slice($arguments, 0, $ignore);
        $ignore_argument = array_slice($arguments, $ignore, 1);
        $trailing_arguments = array_slice($arguments, $ignore + 1);
        self::initialise_helper(array_merge($leading_arguments, [[null]], $trailing_arguments));
        $arguments = self::$array_argument_helper->arguments();
        array_splice($arguments, $ignore, 1, $ignore_argument);
        return Array_Argument_Processor::process_arguments(self::$array_argument_helper, $method, ...$arguments);
    }
}