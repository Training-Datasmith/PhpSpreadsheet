<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Trend;

class Logarithmic_Best_Fit extends Best_Fit
{
    /**
     * Algorithm type to use for best-fit
     * (Name of this Trend class).
     */
    protected string $best_fit_type = 'logarithmic';
    /**
     * Return the Y-Value for a specified value of X.
     *
     * @param float $xValue X-Value
     *
     * @return float Y-Value
     */
    public function get_value_of_y_for_x(float $x_value): float
    {
        return $this->get_intersect() + $this->get_slope() * log($x_value - $this->x_offset);
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
        return exp(($y_value - $this->get_intersect()) / $this->get_slope());
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
        return 'Y = ' . $slope . ' * log(' . $intersect . ' * X)';
    }
    /**
     * Execute the regression and calculate the goodness of fit for a set of X and Y data values.
     *
     * @param float[] $yValues The set of Y-values for this regression
     * @param float[] $xValues The set of X-values for this regression
     */
    private function logarithmic_regression(array $y_values, array $x_values, bool $const): void
    {
        $adjusted_y_values = array_map(fn(float $value): float => $value < 0.0 ? -log(abs($value)) : log($value), $y_values);
        $this->least_square_fit($adjusted_y_values, $x_values, $const);
    }
    /**
     * Define the regression and calculate the goodness of fit for a set of X and Y data values.
     *
     * @param float[] $yValues The set of Y-values for this regression
     * @param float[] $xValues The set of X-values for this regression
     */
    public function __construct(array $y_values, array $x_values = [], bool $const = true)
    {
        parent::__construct($y_values, $x_values);
        if (!$this->error) {
            $this->logarithmic_regression($y_values, $x_values, $const);
        }
    }
}