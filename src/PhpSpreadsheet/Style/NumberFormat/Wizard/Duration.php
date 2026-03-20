<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Number_Format\Wizard;

class Duration extends Date_Time_Wizard
{
    public const DAYS_DURATION = 'd';
    /**
     * Hours as a duration (can exceed 24), e.g. 29.
     */
    public const HOURS_DURATION = '[h]';
    /**
     * Hours without a leading zero, e.g. 9.
     */
    public const HOURS_SHORT = 'h';
    /**
     * Hours with a leading zero, e.g. 09.
     */
    public const HOURS_LONG = 'hh';
    /**
     * Minutes as a duration (can exceed 60), e.g. 109.
     */
    public const MINUTES_DURATION = '[m]';
    /**
     * Minutes without a leading zero, e.g. 5.
     */
    public const MINUTES_SHORT = 'm';
    /**
     * Minutes with a leading zero, e.g. 05.
     */
    public const MINUTES_LONG = 'mm';
    /**
     * Seconds as a duration (can exceed 60), e.g. 129.
     */
    public const SECONDS_DURATION = '[s]';
    /**
     * Seconds without a leading zero, e.g. 2.
     */
    public const SECONDS_SHORT = 's';
    /**
     * Seconds with a leading zero, e.g. 02.
     */
    public const SECONDS_LONG = 'ss';
    protected const DURATION_BLOCKS = [self::DAYS_DURATION, self::HOURS_DURATION, self::HOURS_LONG, self::HOURS_SHORT, self::MINUTES_DURATION, self::MINUTES_LONG, self::MINUTES_SHORT, self::SECONDS_DURATION, self::SECONDS_LONG, self::SECONDS_SHORT];
    protected const DURATION_MASKS = [self::DAYS_DURATION => self::DAYS_DURATION, self::HOURS_DURATION => self::HOURS_SHORT, self::MINUTES_DURATION => self::MINUTES_LONG, self::SECONDS_DURATION => self::SECONDS_LONG];
    protected const DURATION_DEFAULTS = [self::HOURS_LONG => self::HOURS_DURATION, self::HOURS_SHORT => self::HOURS_DURATION, self::MINUTES_LONG => self::MINUTES_DURATION, self::MINUTES_SHORT => self::MINUTES_DURATION, self::SECONDS_LONG => self::SECONDS_DURATION, self::SECONDS_SHORT => self::SECONDS_DURATION];
    public const SEPARATOR_COLON = ':';
    public const SEPARATOR_SPACE_NONBREAKING = " ";
    public const SEPARATOR_SPACE = ' ';
    public const DURATION_DEFAULT = [self::HOURS_DURATION, self::MINUTES_LONG, self::SECONDS_LONG];
    /**
     * @var array<?string>
     */
    protected array $separators;
    /**
     * @var string[]
     */
    protected array $format_blocks;
    protected bool $duration_is_set = false;
    /**
     * @param null|string|string[] $separators
     *        If you want to use the same separator for all format blocks, then it can be passed as a string literal;
     *           if you wish to use different separators, then they should be passed as an array.
     *        If you want to use only a single format block, then pass a null as the separator argument
     */
    public function __construct($separators = self::SEPARATOR_COLON, string ...$format_blocks)
    {
        $separators ??= self::SEPARATOR_COLON;
        $format_blocks = count($format_blocks) === 0 ? self::DURATION_DEFAULT : $format_blocks;
        $this->separators = $this->pad_separator_array(is_array($separators) ? $separators : [$separators], count($format_blocks) - 1);
        $this->format_blocks = array_map($this->map_format_blocks(...), $format_blocks);
        if ($this->duration_is_set === false) {
            // We need at least one duration mask, so if none has been set we change the first mask element
            //    to a duration.
            $this->format_blocks[0] = self::DURATION_DEFAULTS[mb_strtolower($this->format_blocks[0])];
        }
    }
    private function map_format_blocks(string $value): string
    {
        // Any duration masking codes are returned as lower case values
        if (in_array(mb_strtolower($value), self::DURATION_BLOCKS, true)) {
            if (array_key_exists(mb_strtolower($value), self::DURATION_MASKS)) {
                if ($this->duration_is_set) {
                    // We should only have a single duration mask, the first defined in the mask set,
                    //    so convert any additional duration masks to standard time masks.
                    $value = self::DURATION_MASKS[mb_strtolower($value)];
                }
                $this->duration_is_set = true;
            }
            return mb_strtolower($value);
        }
        // Wrap any string literals in quotes, so that they're clearly defined as string literals
        return $this->wrap_literal($value);
    }
    public function format(): string
    {
        return implode('', array_map($this->intersperse(...), $this->format_blocks, $this->separators));
    }
}