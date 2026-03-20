<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

class Calculation_Base
{
    /**
     * Get a list of all implemented functions as an array of function objects.
     *
     * @return array<string, array{category: string, functionCall: string|string[], argumentCount: string, passCellReference?: bool, passByReference?: bool[], custom?: bool}>
     */
    public static function get_functions(): array
    {
        return Function_Array::$php_spreadsheet_functions;
    }
    /**
     * Get address of list of all implemented functions as an array of function objects.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function &get_functions_address(): array
    {
        return Function_Array::$php_spreadsheet_functions;
    }
    /**
     * @param array{category: string, functionCall: string|string[], argumentCount: string, passCellReference?: bool, passByReference?: bool[], custom?: bool} $value
     */
    public static function add_function(string $key, array $value): bool
    {
        $key = strtoupper($key);
        if (array_key_exists($key, Function_Array::$php_spreadsheet_functions) && !self::is_dummy($key)) {
            return false;
        }
        $value['custom'] = true;
        Function_Array::$php_spreadsheet_functions[$key] = $value;
        return true;
    }
    private static function is_dummy(string $key): bool
    {
        // key is already known to exist
        $function_call = Function_Array::$php_spreadsheet_functions[$key]['functionCall'] ?? null;
        if (!is_array($function_call)) {
            return false;
        }
        if (($function_call[1] ?? '') !== 'DUMMY') {
            return false;
        }
        return true;
    }
    public static function remove_function(string $key): bool
    {
        $key = strtoupper($key);
        if (array_key_exists($key, Function_Array::$php_spreadsheet_functions)) {
            if (Function_Array::$php_spreadsheet_functions[$key]['custom'] ?? false) {
                unset(Function_Array::$php_spreadsheet_functions[$key]);
                return true;
            }
        }
        return false;
    }
}