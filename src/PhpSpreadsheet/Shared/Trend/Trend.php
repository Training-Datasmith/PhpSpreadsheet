<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Trend;

use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
class Trend
{
    public const TREND_LINEAR = 'Linear';
    public const TREND_LOGARITHMIC = 'Logarithmic';
    public const TREND_EXPONENTIAL = 'Exponential';
    public const TREND_POWER = 'Power';
    public const TREND_POLYNOMIAL_2 = 'Polynomial_2';
    public const TREND_POLYNOMIAL_3 = 'Polynomial_3';
    public const TREND_POLYNOMIAL_4 = 'Polynomial_4';
    public const TREND_POLYNOMIAL_5 = 'Polynomial_5';
    public const TREND_POLYNOMIAL_6 = 'Polynomial_6';
    public const TREND_BEST_FIT = 'Bestfit';
    public const TREND_BEST_FIT_NO_POLY = 'Bestfit_no_Polynomials';
    /**
     * Names of the best-fit Trend analysis methods.
     */
    private const TREND_TYPES = [self::TREND_LINEAR, self::TREND_LOGARITHMIC, self::TREND_EXPONENTIAL, self::TREND_POWER];
    /**
     * Names of the best-fit Trend polynomial orders.
     *
     * @var string[]
     */
    private static array $trend_type_polynomial_orders = [self::TREND_POLYNOMIAL_2, self::TREND_POLYNOMIAL_3, self::TREND_POLYNOMIAL_4, self::TREND_POLYNOMIAL_5, self::TREND_POLYNOMIAL_6];
    /**
     * Cached results for each method when trying to identify which provides the best fit.
     *
     * @var BestFit[]
     */
    private static array $trend_cache = [];
    /**
     * @param mixed[] $yValues
     * @param mixed[] $xValues
     */
    public static function calculate(string $trend_type = self::TREND_BEST_FIT, array $y_values = [], array $x_values = [], bool $const = true): Best_Fit
    {
        //    Calculate number of points in each dataset
        /** @var float[] $xValues */
        $n_y = count($y_values);
        /** @var float[] $xValues */
        $n_x = count($x_values);
        //    Define X Values if necessary
        if ($n_x === 0) {
            $x_values = range(1, $n_y);
        } elseif ($n_y !== $n_x) {
            //    Ensure both arrays of points are the same size
            throw new Spreadsheet_Exception('Trend(): Number of elements in coordinate arrays do not match.');
        }
        $key = md5($trend_type . $const . serialize($y_values) . serialize($x_values));
        //    Determine which Trend method has been requested
        switch ($trend_type) {
            //    Instantiate and return the class for the requested Trend method
            case self::TREND_LINEAR:
            case self::TREND_LOGARITHMIC:
            case self::TREND_EXPONENTIAL:
            case self::TREND_POWER:
                if (!isset(self::$trend_cache[$key])) {
                    /** @var float[] $yValues */
                    $class_name = '\PhpOffice\PhpSpreadsheet\Shared\Trend\\' . $trend_type . 'BestFit';
                    /** @var float[] $xValues */
                    self::$trend_cache[$key] = new $class_name($y_values, $x_values, $const);
                }
                return self::$trend_cache[$key];
            case self::TREND_POLYNOMIAL_2:
            case self::TREND_POLYNOMIAL_3:
            case self::TREND_POLYNOMIAL_4:
            case self::TREND_POLYNOMIAL_5:
            case self::TREND_POLYNOMIAL_6:
                if (!isset(self::$trend_cache[$key])) {
                    $order = (int) substr($trend_type, -1);
                    /** @var float[] $yValues */
                    self::$trend_cache[$key] = new Polynomial_Best_Fit($order, $y_values, $x_values);
                }
                return self::$trend_cache[$key];
            case self::TREND_BEST_FIT:
            case self::TREND_BEST_FIT_NO_POLY:
                //    If the request is to determine the best fit regression, then we test each Trend line in turn
                //    Start by generating an instance of each available Trend method
                /** @var float[] $yValues */
                $best_fit = [];
                /** @var float[] $xValues */
                $best_fit_value = [];
                foreach (self::TREND_TYPES as $trend_method) {
                    $class_name = '\PhpOffice\PhpSpreadsheet\Shared\Trend\\' . $trend_method . 'BestFit';
                    $best_fit[$trend_method] = new $class_name($y_values, $x_values, $const);
                    $best_fit_value[$trend_method] = $best_fit[$trend_method]->get_goodness_of_fit();
                }
                if ($trend_type !== self::TREND_BEST_FIT_NO_POLY) {
                    foreach (self::$trend_type_polynomial_orders as $trend_method) {
                        $order = (int) substr($trend_method, -1);
                        $best_fit[$trend_method] = new Polynomial_Best_Fit($order, $y_values, $x_values);
                        if ($best_fit[$trend_method]->get_error()) {
                            unset($best_fit[$trend_method]);
                        } else {
                            $best_fit_value[$trend_method] = $best_fit[$trend_method]->get_goodness_of_fit();
                        }
                    }
                }
                //    Determine which of our Trend lines is the best fit, and then we return the instance of that Trend class
                arsort($best_fit_value);
                $best_fit_type = key($best_fit_value);
                return $best_fit[$best_fit_type];
            default:
                throw new Spreadsheet_Exception("Unknown trend type {$trend_type}");
        }
    }
}