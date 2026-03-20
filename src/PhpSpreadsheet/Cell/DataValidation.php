<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Cell;

class Data_Validation
{
    // Data validation types
    public const TYPE_NONE = 'none';
    public const TYPE_CUSTOM = 'custom';
    public const TYPE_DATE = 'date';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_LIST = 'list';
    public const TYPE_TEXTLENGTH = 'textLength';
    public const TYPE_TIME = 'time';
    public const TYPE_WHOLE = 'whole';
    // Data validation error styles
    public const STYLE_STOP = 'stop';
    public const STYLE_WARNING = 'warning';
    public const STYLE_INFORMATION = 'information';
    // Data validation operators
    public const OPERATOR_BETWEEN = 'between';
    public const OPERATOR_EQUAL = 'equal';
    public const OPERATOR_GREATERTHAN = 'greaterThan';
    public const OPERATOR_GREATERTHANOREQUAL = 'greaterThanOrEqual';
    public const OPERATOR_LESSTHAN = 'lessThan';
    public const OPERATOR_LESSTHANOREQUAL = 'lessThanOrEqual';
    public const OPERATOR_NOTBETWEEN = 'notBetween';
    public const OPERATOR_NOTEQUAL = 'notEqual';
    private const DEFAULT_OPERATOR = self::OPERATOR_BETWEEN;
    /**
     * Formula 1.
     */
    private string $formula1 = '';
    /**
     * Formula 2.
     */
    private string $formula2 = '';
    /**
     * Type.
     */
    private string $type = self::TYPE_NONE;
    /**
     * Error style.
     */
    private string $error_style = self::STYLE_STOP;
    /**
     * Operator.
     */
    private string $operator = self::DEFAULT_OPERATOR;
    /**
     * Allow Blank.
     */
    private bool $allow_blank = false;
    /**
     * Show DropDown.
     */
    private bool $show_drop_down = false;
    /**
     * Show InputMessage.
     */
    private bool $show_input_message = false;
    /**
     * Show ErrorMessage.
     */
    private bool $show_error_message = false;
    /**
     * Error title.
     */
    private string $error_title = '';
    /**
     * Error.
     */
    private string $error = '';
    /**
     * Prompt title.
     */
    private string $prompt_title = '';
    /**
     * Prompt.
     */
    private string $prompt = '';
    /**
     * Get Formula 1.
     */
    public function get_formula1(): string
    {
        return $this->formula1;
    }
    /**
     * Set Formula 1.
     *
     * @return $this
     */
    public function set_formula1(string $formula): static
    {
        $this->formula1 = $formula;
        return $this;
    }
    /**
     * Get Formula 2.
     */
    public function get_formula2(): string
    {
        return $this->formula2;
    }
    /**
     * Set Formula 2.
     *
     * @return $this
     */
    public function set_formula2(string $formula): static
    {
        $this->formula2 = $formula;
        return $this;
    }
    /**
     * Get Type.
     */
    public function get_type(): string
    {
        return $this->type;
    }
    /**
     * Set Type.
     *
     * @return $this
     */
    public function set_type(string $type): static
    {
        $this->type = $type;
        return $this;
    }
    /**
     * Get Error style.
     */
    public function get_error_style(): string
    {
        return $this->error_style;
    }
    /**
     * Set Error style.
     *
     * @param string $errorStyle see self::STYLE_*
     *
     * @return $this
     */
    public function set_error_style(string $error_style): static
    {
        $this->error_style = $error_style;
        return $this;
    }
    /**
     * Get Operator.
     */
    public function get_operator(): string
    {
        return $this->operator;
    }
    /**
     * Set Operator.
     *
     * @return $this
     */
    public function set_operator(string $operator): static
    {
        $this->operator = $operator === '' ? self::DEFAULT_OPERATOR : $operator;
        return $this;
    }
    /**
     * Get Allow Blank.
     */
    public function get_allow_blank(): bool
    {
        return $this->allow_blank;
    }
    /**
     * Set Allow Blank.
     *
     * @return $this
     */
    public function set_allow_blank(bool $allow_blank): static
    {
        $this->allow_blank = $allow_blank;
        return $this;
    }
    /**
     * Get Show DropDown.
     */
    public function get_show_drop_down(): bool
    {
        return $this->show_drop_down;
    }
    /**
     * Set Show DropDown.
     *
     * @return $this
     */
    public function set_show_drop_down(bool $show_drop_down): static
    {
        $this->show_drop_down = $show_drop_down;
        return $this;
    }
    /**
     * Get Show InputMessage.
     */
    public function get_show_input_message(): bool
    {
        return $this->show_input_message;
    }
    /**
     * Set Show InputMessage.
     *
     * @return $this
     */
    public function set_show_input_message(bool $show_input_message): static
    {
        $this->show_input_message = $show_input_message;
        return $this;
    }
    /**
     * Get Show ErrorMessage.
     */
    public function get_show_error_message(): bool
    {
        return $this->show_error_message;
    }
    /**
     * Set Show ErrorMessage.
     *
     * @return $this
     */
    public function set_show_error_message(bool $show_error_message): static
    {
        $this->show_error_message = $show_error_message;
        return $this;
    }
    /**
     * Get Error title.
     */
    public function get_error_title(): string
    {
        return $this->error_title;
    }
    /**
     * Set Error title.
     *
     * @return $this
     */
    public function set_error_title(string $error_title): static
    {
        $this->error_title = $error_title;
        return $this;
    }
    /**
     * Get Error.
     */
    public function get_error(): string
    {
        return $this->error;
    }
    /**
     * Set Error.
     *
     * @return $this
     */
    public function set_error(string $error): static
    {
        $this->error = $error;
        return $this;
    }
    /**
     * Get Prompt title.
     */
    public function get_prompt_title(): string
    {
        return $this->prompt_title;
    }
    /**
     * Set Prompt title.
     *
     * @return $this
     */
    public function set_prompt_title(string $prompt_title): static
    {
        $this->prompt_title = $prompt_title;
        return $this;
    }
    /**
     * Get Prompt.
     */
    public function get_prompt(): string
    {
        return $this->prompt;
    }
    /**
     * Set Prompt.
     *
     * @return $this
     */
    public function set_prompt(string $prompt): static
    {
        $this->prompt = $prompt;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->formula1 . $this->formula2 . $this->type . $this->error_style . $this->operator . ($this->allow_blank ? 't' : 'f') . ($this->show_drop_down ? 't' : 'f') . ($this->show_input_message ? 't' : 'f') . ($this->show_error_message ? 't' : 'f') . $this->error_title . $this->error . $this->prompt_title . $this->prompt . $this->sqref . self::class);
    }
    private ?string $sqref = null;
    public function get_sqref(): ?string
    {
        return $this->sqref;
    }
    public function set_sqref(?string $str): self
    {
        $this->sqref = $str;
        return $this;
    }
}