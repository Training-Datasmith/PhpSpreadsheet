<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Statistical\Distributions;

use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Newton_Raphson
{
    private const MAX_ITERATIONS = 256;
    /** @var callable(float): mixed */
    protected $callback;
    /** @param callable(float): mixed $callback */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    public function execute(float $probability): string|int|float
    {
        $x_lo = 100;
        $x_hi = 0;
        $dx = 1;
        $x = $x_new = 1;
        $i = 0;
        while (abs($dx) > Functions::PRECISION && $i++ < self::MAX_ITERATIONS) {
            // Apply Newton-Raphson step
            $result = call_user_func($this->callback, $x);
            if (!is_float($result)) {
                return Excel_Error::VALUE();
            }
            $error = $result - $probability;
            if ($error == 0.0) {
                $dx = 0;
            } elseif ($error < 0.0) {
                $x_lo = $x;
            } else {
                $x_hi = $x;
            }
            // Avoid division by zero
            if ($result != 0.0) {
                $dx = $error / $result;
                $x_new = $x - $dx;
            }
            // If the NR fails to converge (which for example may be the
            // case if the initial guess is too rough) we apply a bisection
            // step to determine a more narrow interval around the root.
            if ($x_new < $x_lo || $x_new > $x_hi || $result == 0.0) {
                $x_new = ($x_lo + $x_hi) / 2;
                $dx = $x_new - $x;
            }
            $x = $x_new;
        }
        if ($i == self::MAX_ITERATIONS) {
            return Excel_Error::NA();
        }
        return $x;
    }
}