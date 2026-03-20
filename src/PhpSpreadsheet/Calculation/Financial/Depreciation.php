<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial;

use Php_Office\Php_Spreadsheet\Calculation\Exception;
use Php_Office\Php_Spreadsheet\Calculation\Functions;
use Php_Office\Php_Spreadsheet\Calculation\Information\Excel_Error;
class Depreciation
{
    private static float $zero_point_zero = 0.0;
    /**
     * DB.
     *
     * Returns the depreciation of an asset for a specified period using the
     * fixed-declining balance method.
     * This form of depreciation is used if you want to get a higher depreciation value
     * at the beginning of the depreciation (as opposed to linear depreciation). The
     * depreciation value is reduced with every depreciation period by the depreciation
     * already deducted from the initial cost.
     *
     * Excel Function:
     *        DB(cost,salvage,life,period[,month])
     *
     * @param mixed $cost Initial cost of the asset
     * @param mixed $salvage Value at the end of the depreciation.
     *                             (Sometimes called the salvage value of the asset)
     * @param mixed $life Number of periods over which the asset is depreciated.
     *                           (Sometimes called the useful life of the asset)
     * @param mixed $period The period for which you want to calculate the
     *                          depreciation. Period must use the same units as life.
     * @param mixed $month Number of months in the first year. If month is omitted,
     *                         it defaults to 12.
     */
    public static function DB(mixed $cost, mixed $salvage, mixed $life, mixed $period, mixed $month = 12): string|float|int
    {
        $cost = Functions::flatten_single_value($cost);
        $salvage = Functions::flatten_single_value($salvage);
        $life = Functions::flatten_single_value($life);
        $period = Functions::flatten_single_value($period);
        $month = Functions::flatten_single_value($month);
        try {
            $cost = self::validate_cost($cost);
            $salvage = self::validate_salvage($salvage);
            $life = self::validate_life($life);
            $period = self::validate_period($period);
            $month = self::validate_month($month);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($cost === self::$zero_point_zero) {
            return 0.0;
        }
        //    Set Fixed Depreciation Rate
        $fixed_depreciation_rate = 1 - ($salvage / $cost) ** (1 / $life);
        $fixed_depreciation_rate = round($fixed_depreciation_rate, 3);
        //    Loop through each period calculating the depreciation
        // TODO Handle period value between 0 and 1 (e.g. 0.5)
        $previous_depreciation = 0;
        $depreciation = 0;
        for ($per = 1; $per <= $period; ++$per) {
            if ($per == 1) {
                $depreciation = $cost * $fixed_depreciation_rate * $month / 12;
            } elseif ($per == $life + 1) {
                $depreciation = ($cost - $previous_depreciation) * $fixed_depreciation_rate * (12 - $month) / 12;
            } else {
                $depreciation = ($cost - $previous_depreciation) * $fixed_depreciation_rate;
            }
            $previous_depreciation += $depreciation;
        }
        return $depreciation;
    }
    /**
     * DDB.
     *
     * Returns the depreciation of an asset for a specified period using the
     * double-declining balance method or some other method you specify.
     *
     * Excel Function:
     *        DDB(cost,salvage,life,period[,factor])
     *
     * @param mixed $cost Initial cost of the asset
     * @param mixed $salvage Value at the end of the depreciation.
     *                                (Sometimes called the salvage value of the asset)
     * @param mixed $life Number of periods over which the asset is depreciated.
     *                                (Sometimes called the useful life of the asset)
     * @param mixed $period The period for which you want to calculate the
     *                                depreciation. Period must use the same units as life.
     * @param mixed $factor The rate at which the balance declines.
     *                                If factor is omitted, it is assumed to be 2 (the
     *                                double-declining balance method).
     */
    public static function DDB(mixed $cost, mixed $salvage, mixed $life, mixed $period, mixed $factor = 2.0): float|string
    {
        $cost = Functions::flatten_single_value($cost);
        $salvage = Functions::flatten_single_value($salvage);
        $life = Functions::flatten_single_value($life);
        $period = Functions::flatten_single_value($period);
        $factor = Functions::flatten_single_value($factor);
        try {
            $cost = self::validate_cost($cost);
            $salvage = self::validate_salvage($salvage);
            $life = self::validate_life($life);
            $period = self::validate_period($period);
            $factor = self::validate_factor($factor);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($period > $life) {
            return Excel_Error::NAN();
        }
        // Loop through each period calculating the depreciation
        // TODO Handling for fractional $period values
        $previous_depreciation = 0;
        $depreciation = 0;
        for ($per = 1; $per <= $period; ++$per) {
            $depreciation = min(($cost - $previous_depreciation) * ($factor / $life), $cost - $salvage - $previous_depreciation);
            $previous_depreciation += $depreciation;
        }
        return $depreciation;
    }
    /**
     * SLN.
     *
     * Returns the straight-line depreciation of an asset for one period
     *
     * @param mixed $cost Initial cost of the asset
     * @param mixed $salvage Value at the end of the depreciation
     * @param mixed $life Number of periods over which the asset is depreciated
     *
     * @return float|string Result, or a string containing an error
     */
    public static function SLN(mixed $cost, mixed $salvage, mixed $life): string|float
    {
        $cost = Functions::flatten_single_value($cost);
        $salvage = Functions::flatten_single_value($salvage);
        $life = Functions::flatten_single_value($life);
        try {
            $cost = self::validate_cost($cost, true);
            $salvage = self::validate_salvage($salvage, true);
            $life = self::validate_life($life, true);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($life === self::$zero_point_zero) {
            return Excel_Error::DIV0();
        }
        return ($cost - $salvage) / $life;
    }
    /**
     * SYD.
     *
     * Returns the sum-of-years' digits depreciation of an asset for a specified period.
     *
     * @param mixed $cost Initial cost of the asset
     * @param mixed $salvage Value at the end of the depreciation
     * @param mixed $life Number of periods over which the asset is depreciated
     * @param mixed $period Period
     *
     * @return float|string Result, or a string containing an error
     */
    public static function SYD(mixed $cost, mixed $salvage, mixed $life, mixed $period): string|float
    {
        $cost = Functions::flatten_single_value($cost);
        $salvage = Functions::flatten_single_value($salvage);
        $life = Functions::flatten_single_value($life);
        $period = Functions::flatten_single_value($period);
        try {
            $cost = self::validate_cost($cost, true);
            $salvage = self::validate_salvage($salvage);
            $life = self::validate_life($life);
            $period = self::validate_period($period);
        } catch (Exception $e) {
            return $e->get_message();
        }
        if ($period > $life) {
            return Excel_Error::NAN();
        }
        return ($cost - $salvage) * ($life - $period + 1) * 2 / ($life * ($life + 1));
    }
    private static function validate_cost(mixed $cost, bool $negative_value_allowed = false): float
    {
        $cost = Financial_Validations::validate_float($cost);
        if ($cost < 0.0 && $negative_value_allowed === false) {
            throw new Exception(Excel_Error::NAN());
        }
        return $cost;
    }
    private static function validate_salvage(mixed $salvage, bool $negative_value_allowed = false): float
    {
        $salvage = Financial_Validations::validate_float($salvage);
        if ($salvage < 0.0 && $negative_value_allowed === false) {
            throw new Exception(Excel_Error::NAN());
        }
        return $salvage;
    }
    private static function validate_life(mixed $life, bool $negative_value_allowed = false): float
    {
        $life = Financial_Validations::validate_float($life);
        if ($life < 0.0 && $negative_value_allowed === false) {
            throw new Exception(Excel_Error::NAN());
        }
        return $life;
    }
    private static function validate_period(mixed $period, bool $negative_value_allowed = false): float
    {
        $period = Financial_Validations::validate_float($period);
        if ($period <= 0.0 && $negative_value_allowed === false) {
            throw new Exception(Excel_Error::NAN());
        }
        return $period;
    }
    private static function validate_month(mixed $month): int
    {
        $month = Financial_Validations::validate_int($month);
        if ($month < 1) {
            throw new Exception(Excel_Error::NAN());
        }
        return $month;
    }
    private static function validate_factor(mixed $factor): float
    {
        $factor = Financial_Validations::validate_float($factor);
        if ($factor <= 0.0) {
            throw new Exception(Excel_Error::NAN());
        }
        return $factor;
    }
}