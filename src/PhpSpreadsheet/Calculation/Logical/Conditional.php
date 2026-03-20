<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Logical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Error_Value;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Calculation\Information\Value;
class Conditional
{
    use Array_Enabled;
    /**
     * STATEMENT_IF.
     *
     * Returns one value if a condition you specify evaluates to TRUE and another value if it evaluates to FALSE.
     *
     * Excel Function:
     *        =IF(condition[,returnIfTrue[,returnIfFalse]])
     *
     *        Condition is any value or expression that can be evaluated to TRUE or FALSE.
     *            For example, A10=100 is a logical expression; if the value in cell A10 is equal to 100,
     *            the expression evaluates to TRUE. Otherwise, the expression evaluates to FALSE.
     *            This argument can use any comparison calculation operator.
     *        ReturnIfTrue is the value that is returned if condition evaluates to TRUE.
     *            For example, if this argument is the text string "Within budget" and
     *                the condition argument evaluates to TRUE, then the IF function returns the text "Within budget"
     *            If condition is TRUE and ReturnIfTrue is blank, this argument returns 0 (zero).
     *            To display the word TRUE, use the logical value TRUE for this argument.
     *            ReturnIfTrue can be another formula.
     *        ReturnIfFalse is the value that is returned if condition evaluates to FALSE.
     *            For example, if this argument is the text string "Over budget" and the condition argument evaluates
     *                to FALSE, then the IF function returns the text "Over budget".
     *            If condition is FALSE and ReturnIfFalse is omitted, then the logical value FALSE is returned.
     *            If condition is FALSE and ReturnIfFalse is blank, then the value 0 (zero) is returned.
     *            ReturnIfFalse can be another formula.
     *
     * @param mixed $condition Condition to evaluate
     * @param mixed $returnIfTrue Value to return when condition is true
     *              Note that this can be an array value
     * @param mixed $returnIfFalse Optional value to return when condition is false
     *              Note that this can be an array value
     *
     * @return mixed The value of returnIfTrue or returnIfFalse determined by condition
     */
    public static function statement_if(mixed $condition = true, mixed $return_if_true = 0, mixed $return_if_false = false): mixed
    {
        $condition = $condition === null ? true : Functions::flatten_single_value($condition);
        if (Error_Value::is_error($condition, true)) {
            return $condition;
        }
        $return_if_true ??= 0;
        $return_if_false ??= false;
        return (bool) $condition ? $return_if_true : $return_if_false;
    }
    /**
     * STATEMENT_SWITCH.
     *
     * Returns corresponding with first match (any data type such as a string, numeric, date, etc).
     *
     * Excel Function:
     *        =SWITCH (expression, value1, result1, value2, result2, ... value_n, result_n [, default])
     *
     *        Expression
     *              The expression to compare to a list of values.
     *        value1, value2, ... value_n
     *              A list of values that are compared to expression.
     *              The SWITCH function is looking for the first value that matches the expression.
     *        result1, result2, ... result_n
     *              A list of results. The SWITCH function returns the corresponding result when a value
     *              matches expression.
     *              Note that these can be array values to be returned
     *         default
     *              Optional. It is the default to return if expression does not match any of the values
     *              (value1, value2, ... value_n).
     *              Note that this can be an array value to be returned
     *
     * @param mixed $arguments Statement arguments
     *
     * @return mixed The value of matched expression
     */
    public static function statement_switch(mixed ...$arguments): mixed
    {
        $result = Excel_Error::VALUE();
        if (count($arguments) > 0) {
            $target_value = Functions::flatten_single_value($arguments[0]);
            $argc = count($arguments) - 1;
            $switch_count = floor($argc / 2);
            $has_default_clause = $argc % 2 !== 0;
            $default_clause = $argc % 2 === 0 ? null : $arguments[$argc];
            $switch_satisfied = false;
            if ($switch_count > 0) {
                for ($index = 0; $index < $switch_count; ++$index) {
                    if ($target_value == Functions::flatten_single_value($arguments[$index * 2 + 1])) {
                        $result = $arguments[$index * 2 + 2];
                        $switch_satisfied = true;
                        break;
                    }
                }
            }
            if ($switch_satisfied !== true) {
                $result = $has_default_clause ? $default_clause : Excel_Error::NA();
            }
        }
        return $result;
    }
    /**
     * IFERROR.
     *
     * Excel Function:
     *        =IFERROR(testValue,errorpart)
     *
     * @param mixed $testValue Value to check, is also the value returned when no error
     *                      Or can be an array of values
     * @param mixed $errorpart Value to return when testValue is an error condition
     *              Note that this can be an array value to be returned
     *
     * @return mixed The value of errorpart or testValue determined by error condition
     *         If an array of values is passed as the $testValue argument, then the returned result will also be
     *            an array with the same dimensions
     */
    public static function IFERROR(mixed $test_value = '', mixed $errorpart = ''): mixed
    {
        if (is_array($test_value)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $test_value, $errorpart);
        }
        $errorpart ??= '';
        $test_value ??= 0;
        // this is how Excel handles empty cell
        return self::statement_if(Error_Value::is_error($test_value), $errorpart, $test_value);
    }
    /**
     * IFNA.
     *
     * Excel Function:
     *        =IFNA(testValue,napart)
     *
     * @param mixed $testValue Value to check, is also the value returned when not an NA
     *                      Or can be an array of values
     * @param mixed $napart Value to return when testValue is an NA condition
     *              Note that this can be an array value to be returned
     *
     * @return mixed The value of errorpart or testValue determined by error condition
     *         If an array of values is passed as the $testValue argument, then the returned result will also be
     *            an array with the same dimensions
     */
    public static function IFNA(mixed $test_value = '', mixed $napart = ''): mixed
    {
        if (is_array($test_value)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $test_value, $napart);
        }
        $napart ??= '';
        $test_value ??= 0;
        // this is how Excel handles empty cell
        return self::statement_if(Error_Value::is_na($test_value), $napart, $test_value);
    }
    /**
     * IFS.
     *
     * Excel Function:
     *         =IFS(testValue1;returnIfTrue1;testValue2;returnIfTrue2;...;testValue_n;returnIfTrue_n)
     *
     *         testValue1 ... testValue_n
     *             Conditions to Evaluate
     *         returnIfTrue1 ... returnIfTrue_n
     *             Value returned if corresponding testValue (nth) was true
     *
     * @param mixed ...$arguments Statement arguments
     *              Note that this can be an array value to be returned
     *
     * @return mixed|string The value of returnIfTrue_n, if testValue_n was true. #N/A if none of testValues was true
     */
    public static function IFS(mixed ...$arguments)
    {
        $argument_count = count($arguments);
        if ($argument_count % 2 != 0) {
            return Excel_Error::NA();
        }
        // We use instance of Exception as a falseValue in order to prevent string collision with value in cell
        $false_value_exception = new Exception();
        for ($i = 0; $i < $argument_count; $i += 2) {
            $test_value = $arguments[$i] === null ? '' : Functions::flatten_single_value($arguments[$i]);
            $return_if_true = $arguments[$i + 1] ?? '';
            $result = self::statement_if($test_value, $return_if_true, $false_value_exception);
            if ($result !== $false_value_exception) {
                return $result;
            }
        }
        return Excel_Error::NA();
    }
}