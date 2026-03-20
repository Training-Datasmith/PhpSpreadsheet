<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Shared\Trend;

abstract class Best_Fit
{
    /**
     * Indicator flag for a calculation error.
     */
    protected bool $error = false;
    /**
     * Algorithm type to use for best-fit.
     */
    protected string $best_fit_type = 'undetermined';
    /**
     * Number of entries in the sets of x- and y-value arrays.
     */
    protected int $value_count;
    /**
     * X-value dataseries of values.
     *
     * @var float[]
     */
    protected array $x_values = [];
    /**
     * Y-value dataseries of values.
     *
     * @var float[]
     */
    protected array $y_values = [];
    /**
     * Flag indicating whether values should be adjusted to Y=0.
     */
    protected bool $adjust_to_zero = false;
    /**
     * Y-value series of best-fit values.
     *
     * @var float[]
     */
    protected array $y_best_fit_values = [];
    protected float $goodness_of_fit = 1;
    protected float $stdev_of_residuals = 0;
    protected float $covariance = 0;
    protected float $correlation = 0;
    protected float $ss_regression = 0;
    protected float $ss_residuals = 0;
    protected float $df_residuals = 0;
    protected float $f = 0;
    protected float $slope = 0;
    protected float $slope_se = 0;
    protected float $intersect = 0;
    protected float $intersect_se = 0;
    protected float $x_offset = 0;
    protected float $y_offset = 0;
    public function get_error(): bool
    {
        return $this->error;
    }
    public function get_best_fit_type(): string
    {
        return $this->best_fit_type;
    }
    /**
     * Return the Y-Value for a specified value of X.
     *
     * @param float $xValue X-Value
     *
     * @return float Y-Value
     */
    abstract public function get_value_of_y_for_x(float $x_value): float;
    /**
     * Return the X-Value for a specified value of Y.
     *
     * @param float $yValue Y-Value
     *
     * @return float X-Value
     */
    abstract public function get_value_of_x_for_y(float $y_value): float;
    /**
     * Return the original set of X-Values.
     *
     * @return float[] X-Values
     */
    public function get_x_values(): array
    {
        return $this->x_values;
    }
    /**
     * Return the original set of Y-Values.
     *
     * @return float[] Y-Values
     */
    public function get_y_values(): array
    {
        return $this->y_values;
    }
    /**
     * Return the Equation of the best-fit line.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    abstract public function get_equation(int $dp = 0): string;
    /**
     * Return the Slope of the line.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_slope(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->slope, $dp);
        }
        return $this->slope;
    }
    /**
     * Return the standard error of the Slope.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_slope_se(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->slope_se, $dp);
        }
        return $this->slope_se;
    }
    /**
     * Return the Value of X where it intersects Y = 0.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_intersect(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->intersect, $dp);
        }
        return $this->intersect;
    }
    /**
     * Return the standard error of the Intersect.
     *
     * @param int $dp Number of places of decimal precision to display
     */
    public function get_intersect_se(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->intersect_se, $dp);
        }
        return $this->intersect_se;
    }
    /**
     * Return the goodness of fit for this regression.
     *
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_goodness_of_fit(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->goodness_of_fit, $dp);
        }
        return $this->goodness_of_fit;
    }
    /**
     * Return the goodness of fit for this regression.
     *
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_goodness_of_fit_percent(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->goodness_of_fit * 100, $dp);
        }
        return $this->goodness_of_fit * 100;
    }
    /**
     * Return the standard deviation of the residuals for this regression.
     *
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_stdev_of_residuals(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->stdev_of_residuals, $dp);
        }
        return $this->stdev_of_residuals;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_ss_regression(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->ss_regression, $dp);
        }
        return $this->ss_regression;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_ss_residuals(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->ss_residuals, $dp);
        }
        return $this->ss_residuals;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_df_residuals(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->df_residuals, $dp);
        }
        return $this->df_residuals;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_f(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->f, $dp);
        }
        return $this->f;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_covariance(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->covariance, $dp);
        }
        return $this->covariance;
    }
    /**
     * @param int $dp Number of places of decimal precision to return
     */
    public function get_correlation(int $dp = 0): float
    {
        if ($dp != 0) {
            return round($this->correlation, $dp);
        }
        return $this->correlation;
    }
    /**
     * @return float[]
     */
    public function get_y_best_fit_values(): array
    {
        return $this->y_best_fit_values;
    }
    protected function calculate_goodness_of_fit(float $sum_x, float $sum_y, float $sum_x2, float $sum_y2, float $sum_xy, float $mean_x, float $mean_y, bool|int $const): void
    {
        $s_sres = $s_scov = $s_stot = $s_ssex = 0.0;
        foreach ($this->x_values as $x_key => $x_value) {
            $best_fit_y = $this->y_best_fit_values[$x_key] = $this->get_value_of_y_for_x($x_value);
            $s_sres += ($this->y_values[$x_key] - $best_fit_y) * ($this->y_values[$x_key] - $best_fit_y);
            if ($const === true) {
                $s_stot += ($this->y_values[$x_key] - $mean_y) * ($this->y_values[$x_key] - $mean_y);
            } else {
                $s_stot += $this->y_values[$x_key] * $this->y_values[$x_key];
            }
            $s_scov += ($this->x_values[$x_key] - $mean_x) * ($this->y_values[$x_key] - $mean_y);
            if ($const === true) {
                $s_ssex += ($this->x_values[$x_key] - $mean_x) * ($this->x_values[$x_key] - $mean_x);
            } else {
                $s_ssex += $this->x_values[$x_key] * $this->x_values[$x_key];
            }
        }
        $this->ss_residuals = $s_sres;
        $this->df_residuals = $this->value_count - 1 - ($const === true ? 1 : 0);
        if ($this->df_residuals == 0.0) {
            $this->stdev_of_residuals = 0.0;
        } else {
            $this->stdev_of_residuals = sqrt($s_sres / $this->df_residuals);
        }
        if ($s_stot == 0.0 || $s_sres == $s_stot) {
            $this->goodness_of_fit = 1;
        } else {
            $this->goodness_of_fit = 1 - $s_sres / $s_stot;
        }
        $this->ss_regression = $this->goodness_of_fit * $s_stot;
        $this->covariance = $s_scov / $this->value_count;
        $this->correlation = ($this->value_count * $sum_xy - $sum_x * $sum_y) / sqrt(($this->value_count * $sum_x2 - $sum_x ** 2) * ($this->value_count * $sum_y2 - $sum_y ** 2));
        $this->slope_se = $this->stdev_of_residuals / sqrt($s_ssex);
        $this->intersect_se = $this->stdev_of_residuals * sqrt(1 / ($this->value_count - $sum_x * $sum_x / $sum_x2));
        if ($this->ss_residuals != 0.0) {
            if ($this->df_residuals == 0.0) {
                $this->f = 0.0;
            } else {
                $this->f = $this->ss_regression / ($this->ss_residuals / $this->df_residuals);
            }
        } else if ($this->df_residuals == 0.0) {
            $this->f = 0.0;
        } else {
            $this->f = $this->ss_regression / $this->df_residuals;
        }
    }
    /**
     * @param array<float|int> $values
     */
    private function sum_squares(array $values): float|int
    {
        return array_sum(array_map(fn(float|int $value): float|int => $value ** 2, $values));
    }
    /**
     * @param float[] $yValues
     * @param float[] $xValues
     */
    protected function least_square_fit(array $y_values, array $x_values, bool $const): void
    {
        // calculate sums
        $sum_values_x = array_sum($x_values);
        $sum_values_y = array_sum($y_values);
        $mean_value_x = $sum_values_x / $this->value_count;
        $mean_value_y = $sum_values_y / $this->value_count;
        $sum_squares_x = $this->sum_squares($x_values);
        $sum_squares_y = $this->sum_squares($y_values);
        $m_base = $m_divisor = 0.0;
        $xy_sum = 0.0;
        for ($i = 0; $i < $this->value_count; ++$i) {
            $xy_sum += $x_values[$i] * $y_values[$i];
            if ($const === true) {
                $m_base += ($x_values[$i] - $mean_value_x) * ($y_values[$i] - $mean_value_y);
                $m_divisor += ($x_values[$i] - $mean_value_x) * ($x_values[$i] - $mean_value_x);
            } else {
                $m_base += $x_values[$i] * $y_values[$i];
                $m_divisor += $x_values[$i] * $x_values[$i];
            }
        }
        // calculate slope
        $this->slope = $m_base / $m_divisor;
        // calculate intersect
        $this->intersect = $const === true ? $mean_value_y - $this->slope * $mean_value_x : 0.0;
        $this->calculate_goodness_of_fit($sum_values_x, $sum_values_y, $sum_squares_x, $sum_squares_y, $xy_sum, $mean_value_x, $mean_value_y, $const);
    }
    /**
     * Define the regression.
     *
     * @param float[] $yValues The set of Y-values for this regression
     * @param float[] $xValues The set of X-values for this regression
     */
    public function __construct(array $y_values, array $x_values = [])
    {
        //    Calculate number of points
        $y_value_count = count($y_values);
        $x_value_count = count($x_values);
        //    Define X Values if necessary
        if ($x_value_count === 0) {
            $x_values = range(1.0, $y_value_count);
        } elseif ($y_value_count !== $x_value_count) {
            //    Ensure both arrays of points are the same size
            $this->error = true;
        }
        $this->value_count = $y_value_count;
        $this->x_values = $x_values;
        $this->y_values = $y_values;
    }
}