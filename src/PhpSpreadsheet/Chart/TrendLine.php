<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

class Trend_Line extends Properties
{
    public const TRENDLINE_EXPONENTIAL = 'exp';
    public const TRENDLINE_LINEAR = 'linear';
    public const TRENDLINE_LOGARITHMIC = 'log';
    public const TRENDLINE_POLYNOMIAL = 'poly';
    // + 'order'
    public const TRENDLINE_POWER = 'power';
    public const TRENDLINE_MOVING_AVG = 'movingAvg';
    // + 'period'
    public const TRENDLINE_TYPES = [self::TRENDLINE_EXPONENTIAL, self::TRENDLINE_LINEAR, self::TRENDLINE_LOGARITHMIC, self::TRENDLINE_POLYNOMIAL, self::TRENDLINE_POWER, self::TRENDLINE_MOVING_AVG];
    private string $trend_line_type = 'linear';
    // TRENDLINE_LINEAR
    private int $order = 2;
    private int $period = 3;
    private bool $disp_r_sqr = false;
    private bool $disp_eq = false;
    private string $name = '';
    private float $backward = 0.0;
    private float $forward = 0.0;
    private float $intercept = 0.0;
    /**
     * Create a new TrendLine object.
     */
    public function __construct(string $trend_line_type = '', ?int $order = null, ?int $period = null, bool $disp_r_sqr = false, bool $disp_eq = false, ?float $backward = null, ?float $forward = null, ?float $intercept = null, ?string $name = null)
    {
        parent::__construct();
        $this->set_trend_line_properties($trend_line_type, $order, $period, $disp_r_sqr, $disp_eq, $backward, $forward, $intercept, $name);
    }
    public function get_trend_line_type(): string
    {
        return $this->trend_line_type;
    }
    public function set_trend_line_type(string $trend_line_type): self
    {
        $this->trend_line_type = $trend_line_type;
        return $this;
    }
    public function get_order(): int
    {
        return $this->order;
    }
    public function set_order(int $order): self
    {
        $this->order = $order;
        return $this;
    }
    public function get_period(): int
    {
        return $this->period;
    }
    public function set_period(int $period): self
    {
        $this->period = $period;
        return $this;
    }
    public function get_disp_r_sqr(): bool
    {
        return $this->disp_r_sqr;
    }
    public function set_disp_r_sqr(bool $disp_r_sqr): self
    {
        $this->disp_r_sqr = $disp_r_sqr;
        return $this;
    }
    public function get_disp_eq(): bool
    {
        return $this->disp_eq;
    }
    public function set_disp_eq(bool $disp_eq): self
    {
        $this->disp_eq = $disp_eq;
        return $this;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function set_name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function get_backward(): float
    {
        return $this->backward;
    }
    public function set_backward(float $backward): self
    {
        $this->backward = $backward;
        return $this;
    }
    public function get_forward(): float
    {
        return $this->forward;
    }
    public function set_forward(float $forward): self
    {
        $this->forward = $forward;
        return $this;
    }
    public function get_intercept(): float
    {
        return $this->intercept;
    }
    public function set_intercept(float $intercept): self
    {
        $this->intercept = $intercept;
        return $this;
    }
    public function set_trend_line_properties(?string $trend_line_type = null, ?int $order = 0, ?int $period = 0, ?bool $disp_r_sqr = false, ?bool $disp_eq = false, ?float $backward = null, ?float $forward = null, ?float $intercept = null, ?string $name = null): self
    {
        if (!empty($trend_line_type)) {
            $this->set_trend_line_type($trend_line_type);
        }
        if ($order !== null) {
            $this->set_order($order);
        }
        if ($period !== null) {
            $this->set_period($period);
        }
        if ($disp_r_sqr !== null) {
            $this->set_disp_r_sqr($disp_r_sqr);
        }
        if ($disp_eq !== null) {
            $this->set_disp_eq($disp_eq);
        }
        if ($backward !== null) {
            $this->set_backward($backward);
        }
        if ($forward !== null) {
            $this->set_forward($forward);
        }
        if ($intercept !== null) {
            $this->set_intercept($intercept);
        }
        if ($name !== null) {
            $this->set_name($name);
        }
        return $this;
    }
}