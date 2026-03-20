<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical;

use Php_Office\Php_Spreadsheet\Calculation\Array_Enabled;
use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
use Php_Office\Php_Spreadsheet\Shared\Trend\Trend;
class Trends
{
    use Array_Enabled;
    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     */
    private static function filter_trend_values(array &$array1, array &$array2): void
    {
        foreach ($array1 as $key => $value) {
            if (is_bool($value) || is_string($value) || $value === null) {
                unset($array1[$key], $array2[$key]);
            }
        }
    }
    /**
     * @param mixed $array1 should be array, but scalar is made into one
     * @param mixed $array2 should be array, but scalar is made into one
     *
     * @param-out array<mixed> $array1
     * @param-out array<mixed> $array2
     */
    private static function check_trend_arrays(mixed &$array1, mixed &$array2): void
    {
        if (!is_array($array1)) {
            $array1 = [$array1];
        }
        if (!is_array($array2)) {
            $array2 = [$array2];
        }
        $array1 = Functions::flatten_array($array1);
        $array2 = Functions::flatten_array($array2);
        self::filter_trend_values($array1, $array2);
        self::filter_trend_values($array2, $array1);
        // Reset the array indexes
        $array1 = array_merge($array1);
        $array2 = array_merge($array2);
    }
    /**
     * @param mixed[] $yValues
     * @param mixed[] $xValues
     */
    protected static function validate_trend_arrays(array $y_values, array $x_values): void
    {
        $y_value_count = count($y_values);
        $x_value_count = count($x_values);
        if ($y_value_count === 0 || $y_value_count !== $x_value_count) {
            throw new Exception(Excel_Error::NA());
        }
        if ($y_value_count === 1) {
            throw new Exception(Excel_Error::DIV0());
        }
    }
    /**
     * CORREL.
     *
     * Returns covariance, the average of the products of deviations for each data point pair.
     *
     * @param mixed $yValues array of mixed Data Series Y
     * @param null|mixed $xValues array of mixed Data Series X
     */
    public static function CORREL(mixed $y_values, $x_values = null): float|string
    {
        if ($x_values === null || !is_array($y_values) || !is_array($x_values)) {
            return Excel_Error::VALUE();
        }
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_correlation();
    }
    /**
     * COVAR.
     *
     * Returns covariance, the average of the products of deviations for each data point pair.
     *
     * @param mixed[] $yValues array of mixed Data Series Y
     * @param mixed[] $xValues array of mixed Data Series X
     */
    public static function COVAR(array $y_values, array $x_values): float|string
    {
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_covariance();
    }
    /**
     * FORECAST.
     *
     * Calculates, or predicts, a future value by using existing values.
     * The predicted value is a y-value for a given x-value.
     *
     * @param mixed $xValue Float value of X for which we want to find Y
     *                      Or can be an array of values
     * @param mixed[] $yValues array of mixed Data Series Y
     * @param mixed[] $xValues array of mixed Data Series X
     *
     * @return array<mixed>|bool|float|string If an array of numbers is passed as an argument, then the returned result will also be an array
     *            with the same dimensions
     */
    public static function FORECAST(mixed $x_value, array $y_values, array $x_values): array|string|float
    {
        if (is_array($x_value)) {
            return self::evaluate_array_arguments_subset([self::class, __FUNCTION__], 1, $x_value, $y_values, $x_values);
        }
        try {
            $x_value = Statistical_Validations::validate_float($x_value);
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_value_of_y_for_x($x_value);
    }
    /**
     * GROWTH.
     *
     * Returns values along a predicted exponential Trend
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     * @param mixed[] $newValues Values of X for which we want to find Y
     * @param mixed $const A logical (boolean) value specifying whether to force the intersect to equal 0 or not
     *
     * @return array<int, array<int, array<int, float>>>
     */
    public static function GROWTH(array $y_values, array $x_values = [], array $new_values = [], mixed $const = true): array
    {
        $y_values = Functions::flatten_array($y_values);
        $x_values = Functions::flatten_array($x_values);
        $new_values = Functions::flatten_array($new_values);
        $const = $const === null ? true : (bool) Functions::flatten_single_value($const);
        $best_fit_exponential = Trend::calculate(Trend::TREND_EXPONENTIAL, $y_values, $x_values, $const);
        if (empty($new_values)) {
            $new_values = $best_fit_exponential->get_x_values();
        }
        $return_array = [];
        foreach ($new_values as $x_value) {
            /** @var float $xValue */
            $return_array[0][] = [$best_fit_exponential->get_value_of_y_for_x($x_value)];
        }
        return $return_array;
    }
    /**
     * INTERCEPT.
     *
     * Calculates the point at which a line will intersect the y-axis by using existing x-values and y-values.
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     */
    public static function INTERCEPT(array $y_values, array $x_values): float|string
    {
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_intersect();
    }
    /**
     * LINEST.
     *
     * Calculates the statistics for a line by using the "least squares" method to calculate a straight line
     *     that best fits your data, and then returns an array that describes the line.
     *
     * @param mixed[] $yValues Data Series Y
     * @param null|mixed[] $xValues Data Series X
     * @param mixed $const A logical (boolean) value specifying whether to force the intersect to equal 0 or not
     * @param mixed $stats A logical (boolean) value specifying whether to return additional regression statistics
     *
     * @return array<mixed>|string The result, or a string containing an error
     */
    public static function LINEST(array $y_values, ?array $x_values = null, mixed $const = true, mixed $stats = false): string|array
    {
        $const = $const === null ? true : (bool) Functions::flatten_single_value($const);
        $stats = $stats === null ? false : (bool) Functions::flatten_single_value($stats);
        if ($x_values === null) {
            $x_values = $y_values;
        }
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values, $const);
        if ($stats === true) {
            return [[$best_fit_linear->get_slope(), $best_fit_linear->get_intersect()], [$best_fit_linear->get_slope_se(), $const === false ? Excel_Error::NA() : $best_fit_linear->get_intersect_se()], [$best_fit_linear->get_goodness_of_fit(), $best_fit_linear->get_stdev_of_residuals()], [$best_fit_linear->get_f(), $best_fit_linear->get_df_residuals()], [$best_fit_linear->get_ss_regression(), $best_fit_linear->get_ss_residuals()]];
        }
        return [$best_fit_linear->get_slope(), $best_fit_linear->get_intersect()];
    }
    /**
     * LOGEST.
     *
     * Calculates an exponential curve that best fits the X and Y data series,
     *        and then returns an array that describes the line.
     *
     * @param mixed[] $yValues Data Series Y
     * @param null|mixed[] $xValues Data Series X
     * @param mixed $const A logical (boolean) value specifying whether to force the intersect to equal 0 or not
     * @param mixed $stats A logical (boolean) value specifying whether to return additional regression statistics
     *
     * @return array<mixed>|string The result, or a string containing an error
     */
    public static function LOGEST(array $y_values, ?array $x_values = null, mixed $const = true, mixed $stats = false): string|array
    {
        $const = $const === null ? true : (bool) Functions::flatten_single_value($const);
        $stats = $stats === null ? false : (bool) Functions::flatten_single_value($stats);
        if ($x_values === null) {
            $x_values = $y_values;
        }
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        foreach ($y_values as $value) {
            if ($value < 0.0) {
                return Excel_Error::NAN();
            }
        }
        $best_fit_exponential = Trend::calculate(Trend::TREND_EXPONENTIAL, $y_values, $x_values, $const);
        if ($stats === true) {
            return [[$best_fit_exponential->get_slope(), $best_fit_exponential->get_intersect()], [$best_fit_exponential->get_slope_se(), $const === false ? Excel_Error::NA() : $best_fit_exponential->get_intersect_se()], [$best_fit_exponential->get_goodness_of_fit(), $best_fit_exponential->get_stdev_of_residuals()], [$best_fit_exponential->get_f(), $best_fit_exponential->get_df_residuals()], [$best_fit_exponential->get_ss_regression(), $best_fit_exponential->get_ss_residuals()]];
        }
        return [$best_fit_exponential->get_slope(), $best_fit_exponential->get_intersect()];
    }
    /**
     * RSQ.
     *
     * Returns the square of the Pearson product moment correlation coefficient through data points
     *     in known_y's and known_x's.
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     *
     * @return float|string The result, or a string containing an error
     */
    public static function RSQ(array $y_values, array $x_values): string|float
    {
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_goodness_of_fit();
    }
    /**
     * SLOPE.
     *
     * Returns the slope of the linear regression line through data points in known_y's and known_x's.
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     *
     * @return float|string The result, or a string containing an error
     */
    public static function SLOPE(array $y_values, array $x_values): string|float
    {
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_slope();
    }
    /**
     * STEYX.
     *
     * Returns the standard error of the predicted y-value for each x in the regression.
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     */
    public static function STEYX(array $y_values, array $x_values): float|string
    {
        try {
            self::check_trend_arrays($y_values, $x_values);
            self::validate_trend_arrays($y_values, $x_values);
        } catch (Exception $e) {
            return $e->get_message();
        }
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values);
        return $best_fit_linear->get_stdev_of_residuals();
    }
    /**
     * TREND.
     *
     * Returns values along a linear Trend
     *
     * @param mixed[] $yValues Data Series Y
     * @param mixed[] $xValues Data Series X
     * @param mixed[] $newValues Values of X for which we want to find Y
     * @param mixed $const A logical (boolean) value specifying whether to force the intersect to equal 0 or not
     *
     * @return array<int, array<int, array<int, float>>>
     */
    public static function TREND(array $y_values, array $x_values = [], array $new_values = [], mixed $const = true): array
    {
        $y_values = Functions::flatten_array($y_values);
        $x_values = Functions::flatten_array($x_values);
        $new_values = Functions::flatten_array($new_values);
        $const = $const === null ? true : (bool) Functions::flatten_single_value($const);
        $best_fit_linear = Trend::calculate(Trend::TREND_LINEAR, $y_values, $x_values, $const);
        if (empty($new_values)) {
            $new_values = $best_fit_linear->get_x_values();
        }
        $return_array = [];
        foreach ($new_values as $x_value) {
            /** @var float $xValue */
            $return_array[0][] = [$best_fit_linear->get_value_of_y_for_x($x_value)];
        }
        return $return_array;
    }
}