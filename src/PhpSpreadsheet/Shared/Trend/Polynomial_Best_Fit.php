<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Trend;

use Matrix\Matrix;
use Php_Office\Php_Spreadsheet\Exception as SpreadsheetException;
// Phpstan and Scrutinizer seem to have legitimate complaints.
// $this->slope is specified where an array is expected in several places.
// But it seems that it should always be float.
// This code is probably not exercised at all in unit tests.
// Private bool property $implemented is set to indicate
//     whether this implementation is correct.
class Polynomial_Best_Fit extends Best_Fit
{
    /**
     * Algorithm type to use for best-fit
     * (Name of this Trend class).
     */
    protected string $best_fit_type = 'polynomial';
    /**
     * Polynomial order.
     */
    protected int $order = 0;
    private bool $implemented = false;
    /**
     * Return the order of this polynomial.
     */
    public function get_order(): int
    {
        return $this->order;
    }
    /**
     * Return the Y-Value for a specified value of X.
     *
     * @param float $xValue X-Value
     *
     * @return float Y-Value
     */
    public function get_value_of_y_for_x(float $x_value): float
    {
        $ret_val = $this->get_intersect();
        $slope = $this->get_slope();
        // Phpstan and Scrutinizer are both correct - getSlope returns float, not array.
        // @phpstan-ignore-next-line
        foreach ($slope as $key => $value) {
            /** @var float $value */
            if ($value != 0.0) {
                /** @var int $key */
                $ret_val += $value * $x_value ** ($key + 1);
            }
        }
        return $ret_val;
    }
    /**
     * Return the X-Value for a specified value of Y.
     *
     * @param float $yValue Y-Value
     *
     * @return float X-Value
     */
    public function get_value_of_x_for_y(float $y_value): float
    {
        return ($y_value - $this->get_intersect()) / $this->get_slope();
    }
    /**
     * Return the Equation of the best-fit line.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_equation(int $dp = 0): string
    {
        $slope = $this->get_slope($dp);
        $intersect = $this->get_intersect($dp);
        $equation = 'Y = ' . $intersect;
        // Phpstan and Scrutinizer are both correct - getSlope returns float, not array.
        // @phpstan-ignore-next-line
        foreach ($slope as $key => $value) {
            /** @var float|int $value */
            if ($value != 0.0) {
                $equation .= ' + ' . $value . ' * X';
                /** @var int $key */
                if ($key > 0) {
                    $equation .= '^' . ($key + 1);
                }
            }
        }
        return $equation;
    }
    /**
     * Return the Slope of the line.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_slope(int $dp = 0): float
    {
        if ($dp != 0) {
            $coefficients = [];
            //* @phpstan-ignore-next-line
            foreach ($this->slope as $coefficient) {
                /** @var float|int $coefficient */
                $coefficients[] = round($coefficient, $dp);
            }
            // @phpstan-ignore-next-line
            return $coefficients;
        }
        return $this->slope;
    }
    /** @return array<float|int> */
    public function get_coefficients(int $dp = 0): array
    {
        // Phpstan and Scrutinizer are both correct - getSlope returns float, not array.
        // @phpstan-ignore-next-line
        return array_merge([$this->get_intersect($dp)], $this->get_slope($dp));
    }
    /**
     * Execute the regression and calculate the goodness of fit for a set of X and Y data values.
     *
     * @param int $order Order of Polynomial for this regression
     * @param float[] $yValues The set of Y-values for this regression
     * @param float[] $xValues The set of X-values for this regression
     */
    private function polynomial_regression(int $order, array $y_values, array $x_values): void
    {
        // calculate sums
        $x_sum = array_sum($x_values);
        $y_sum = array_sum($y_values);
        $xx_sum = $xy_sum = $yy_sum = 0;
        for ($i = 0; $i < $this->value_count; ++$i) {
            $xy_sum += $x_values[$i] * $y_values[$i];
            $xx_sum += $x_values[$i] * $x_values[$i];
            $yy_sum += $y_values[$i] * $y_values[$i];
        }
        /*
         *    This routine uses logic from the PHP port of polyfit version 0.1
         *    written by Michael Bommarito and Paul Meagher
         *
         *    The function fits a polynomial function of order $order through
         *    a series of x-y data points using least squares.
         *
         */
        $A = [];
        $B = [];
        for ($i = 0; $i < $this->value_count; ++$i) {
            for ($j = 0; $j <= $order; ++$j) {
                $A[$i][$j] = $x_values[$i] ** $j;
            }
        }
        for ($i = 0; $i < $this->value_count; ++$i) {
            $B[$i] = [$y_values[$i]];
        }
        $matrix_a = new Matrix($A);
        $matrix_b = new Matrix($B);
        $C = $matrix_a->solve($matrix_b);
        $coefficients = [];
        for ($i = 0; $i < $C->rows; ++$i) {
            $r = $C->get_value($i + 1, 1);
            // row and column are origin-1
            if (!is_numeric($r) || abs($r + 0) <= 10 ** -9) {
                $r = 0;
            } else {
                $r += 0;
            }
            $coefficients[] = $r;
        }
        $this->intersect = (float) array_shift($coefficients);
        // Phpstan is correct
        //* @phpstan-ignore-next-line
        $this->slope = $coefficients;
        $this->calculate_goodness_of_fit($x_sum, $y_sum, $xx_sum, $yy_sum, $xy_sum, 0, 0, 0);
        foreach ($this->x_values as $x_key => $x_value) {
            $this->y_best_fit_values[$x_key] = $this->get_value_of_y_for_x($x_value);
        }
    }
    /**
     * Define the regression and calculate the goodness of fit for a set of X and Y data values.
     *
     * @param int $order Order of Polynomial for this regression
     * @param float[] $yValues The set of Y-values for this regression
     * @param float[] $xValues The set of X-values for this regression
     */
    public function __construct(int $order, array $y_values, array $x_values = [])
    {
        if (!$this->implemented) {
            throw new Spreadsheet_Exception('Polynomial Best Fit not yet implemented');
        }
        parent::__construct($y_values, $x_values);
        if (!$this->error) {
            if ($order < $this->value_count) {
                $this->best_fit_type .= '_' . $order;
                $this->order = $order;
                $this->polynomial_regression($order, $y_values, $x_values);
                if ($this->get_goodness_of_fit() < 0.0 || $this->get_goodness_of_fit() > 1.0) {
                    $this->error = true;
                }
            } else {
                $this->error = true;
            }
        }
    }
}