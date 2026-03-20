<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting\Wizard;

use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Style\Conditional;
/**
 * @method DateValue yesterday()
 * @method DateValue today()
 * @method DateValue tomorrow()
 * @method DateValue lastSevenDays()
 * @method DateValue lastWeek()
 * @method DateValue thisWeek()
 * @method DateValue nextWeek()
 * @method DateValue lastMonth()
 * @method DateValue thisMonth()
 * @method DateValue nextMonth()
 */
class Date_Value extends Wizard_Abstract implements Wizard_Interface
{
    protected const MAGIC_OPERATIONS = ['yesterday' => Conditional::TIMEPERIOD_YESTERDAY, 'today' => Conditional::TIMEPERIOD_TODAY, 'tomorrow' => Conditional::TIMEPERIOD_TOMORROW, 'lastSevenDays' => Conditional::TIMEPERIOD_LAST_7_DAYS, 'last7Days' => Conditional::TIMEPERIOD_LAST_7_DAYS, 'lastWeek' => Conditional::TIMEPERIOD_LAST_WEEK, 'thisWeek' => Conditional::TIMEPERIOD_THIS_WEEK, 'nextWeek' => Conditional::TIMEPERIOD_NEXT_WEEK, 'lastMonth' => Conditional::TIMEPERIOD_LAST_MONTH, 'thisMonth' => Conditional::TIMEPERIOD_THIS_MONTH, 'nextMonth' => Conditional::TIMEPERIOD_NEXT_MONTH];
    protected const EXPRESSIONS = [Conditional::TIMEPERIOD_YESTERDAY => 'FLOOR(%s,1)=TODAY()-1', Conditional::TIMEPERIOD_TODAY => 'FLOOR(%s,1)=TODAY()', Conditional::TIMEPERIOD_TOMORROW => 'FLOOR(%s,1)=TODAY()+1', Conditional::TIMEPERIOD_LAST_7_DAYS => 'AND(TODAY()-FLOOR(%s,1)<=6,FLOOR(%s,1)<=TODAY())', Conditional::TIMEPERIOD_LAST_WEEK => 'AND(TODAY()-ROUNDDOWN(%s,0)>=(WEEKDAY(TODAY())),TODAY()-ROUNDDOWN(%s,0)<(WEEKDAY(TODAY())+7))', Conditional::TIMEPERIOD_THIS_WEEK => 'AND(TODAY()-ROUNDDOWN(%s,0)<=WEEKDAY(TODAY())-1,ROUNDDOWN(%s,0)-TODAY()<=7-WEEKDAY(TODAY()))', Conditional::TIMEPERIOD_NEXT_WEEK => 'AND(ROUNDDOWN(%s,0)-TODAY()>(7-WEEKDAY(TODAY())),ROUNDDOWN(%s,0)-TODAY()<(15-WEEKDAY(TODAY())))', Conditional::TIMEPERIOD_LAST_MONTH => 'AND(MONTH(%s)=MONTH(EDATE(TODAY(),0-1)),YEAR(%s)=YEAR(EDATE(TODAY(),0-1)))', Conditional::TIMEPERIOD_THIS_MONTH => 'AND(MONTH(%s)=MONTH(TODAY()),YEAR(%s)=YEAR(TODAY()))', Conditional::TIMEPERIOD_NEXT_MONTH => 'AND(MONTH(%s)=MONTH(EDATE(TODAY(),0+1)),YEAR(%s)=YEAR(EDATE(TODAY(),0+1)))'];
    protected string $operator;
    protected function operator(string $operator): void
    {
        $this->operator = $operator;
    }
    protected function set_expression(): void
    {
        $reference_count = substr_count(self::EXPRESSIONS[$this->operator], '%s');
        $references = array_fill(0, $reference_count, $this->reference_cell);
        $this->expression = sprintf(self::EXPRESSIONS[$this->operator], ...$references);
    }
    public function get_conditional(): Conditional
    {
        $this->set_expression();
        $conditional = new Conditional();
        $conditional->set_condition_type(Conditional::CONDITION_TIMEPERIOD);
        $conditional->set_text($this->operator);
        $conditional->set_conditions([$this->expression]);
        $conditional->set_style($this->get_style());
        $conditional->set_stop_if_true($this->get_stop_if_true());
        return $conditional;
    }
    public static function from_conditional(Conditional $conditional, string $cell_range = 'A1'): Wizard_Interface
    {
        if ($conditional->get_condition_type() !== Conditional::CONDITION_TIMEPERIOD) {
            throw new Exception('Conditional is not a Date Value CF Rule conditional');
        }
        $wizard = new self($cell_range);
        $wizard->style = $conditional->get_style();
        $wizard->stop_if_true = $conditional->get_stop_if_true();
        $wizard->operator = $conditional->get_text();
        return $wizard;
    }
    /**
     * @param mixed[] $arguments
     */
    public function __call(string $method_name, array $arguments): self
    {
        if (!isset(self::MAGIC_OPERATIONS[$method_name])) {
            throw new Exception('Invalid Operation for Date Value CF Rule Wizard');
        }
        $this->operator(self::MAGIC_OPERATIONS[$method_name]);
        return $this;
    }
}