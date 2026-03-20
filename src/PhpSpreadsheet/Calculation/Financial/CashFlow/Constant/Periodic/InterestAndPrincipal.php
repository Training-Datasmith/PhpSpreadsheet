<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation\Financial\Cash_Flow\Constant\Periodic;

use Php_Office\Php_Spreadsheet\Calculation\Financial\Constants as FinancialConstants;
class Interest_And_Principal
{
    protected float $interest;
    protected float $principal;
    public function __construct(float $rate = 0.0, int $period = 0, int $number_of_periods = 0, float $present_value = 0, float $future_value = 0, int $type = Financial_Constants::PAYMENT_END_OF_PERIOD)
    {
        $payment = Payments::annuity($rate, $number_of_periods, $present_value, $future_value, $type);
        $capital = $present_value;
        $interest = 0.0;
        $principal = 0.0;
        for ($i = 1; $i <= $period; ++$i) {
            $interest = $type === Financial_Constants::PAYMENT_BEGINNING_OF_PERIOD && $i == 1 ? 0 : -$capital * $rate;
            $principal = (float) $payment - $interest;
            $capital += $principal;
        }
        $this->interest = $interest;
        $this->principal = $principal;
    }
    public function interest(): float
    {
        return $this->interest;
    }
    public function principal(): float
    {
        return $this->principal;
    }
}