<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

class Chart_Color
{
    public const EXCEL_COLOR_TYPE_STANDARD = 'prstClr';
    public const EXCEL_COLOR_TYPE_SCHEME = 'schemeClr';
    public const EXCEL_COLOR_TYPE_RGB = 'srgbClr';
    public const EXCEL_COLOR_TYPES = [self::EXCEL_COLOR_TYPE_RGB, self::EXCEL_COLOR_TYPE_SCHEME, self::EXCEL_COLOR_TYPE_STANDARD];
    private string $value = '';
    private string $type = '';
    private ?int $alpha = null;
    private ?int $brightness = null;
    /**
     * @param array{value: ?string, alpha: null|int|string, brightness?: null|int|string, type: ?string}|string  $value
     */
    public function __construct($value = '', ?int $alpha = null, ?string $type = null, ?int $brightness = null)
    {
        if (is_array($value)) {
            $this->set_color_properties_array($value);
        } else {
            $this->set_color_properties($value, $alpha, $type, $brightness);
        }
    }
    public function get_value(): string
    {
        return $this->value;
    }
    public function set_value(string $value): self
    {
        $this->value = $value;
        return $this;
    }
    public function get_type(): string
    {
        return $this->type;
    }
    public function set_type(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    public function get_alpha(): ?int
    {
        return $this->alpha;
    }
    public function set_alpha(?int $alpha): self
    {
        $this->alpha = $alpha;
        return $this;
    }
    public function get_brightness(): ?int
    {
        return $this->brightness;
    }
    public function set_brightness(?int $brightness): self
    {
        $this->brightness = $brightness;
        return $this;
    }
    public function set_color_properties(?string $color, null|float|int|string $alpha = null, ?string $type = null, null|float|int|string $brightness = null): self
    {
        if (empty($type) && !empty($color)) {
            if (str_starts_with($color, '*')) {
                $type = 'schemeClr';
                $color = substr($color, 1);
            } elseif (str_starts_with($color, '/')) {
                $type = 'prstClr';
                $color = substr($color, 1);
            } elseif (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1) {
                $type = 'srgbClr';
            }
        }
        if ($color !== null) {
            $this->set_value("{$color}");
        }
        if ($type !== null) {
            $this->set_type($type);
        }
        if ($alpha === null) {
            $this->set_alpha(null);
        } elseif (is_numeric($alpha)) {
            $this->set_alpha((int) $alpha);
        }
        if ($brightness === null) {
            $this->set_brightness(null);
        } elseif (is_numeric($brightness)) {
            $this->set_brightness((int) $brightness);
        }
        return $this;
    }
    /** @param array{value: ?string, alpha: null|int|string, brightness?: null|int|string, type: ?string}  $color */
    public function set_color_properties_array(array $color): self
    {
        return $this->set_color_properties($color['value'] ?? '', $color['alpha'] ?? null, $color['type'] ?? null, $color['brightness'] ?? null);
    }
    public function is_usable(): bool
    {
        return $this->type !== '' && $this->value !== '';
    }
    /**
     * Get Color Property.
     */
    public function get_color_property(string $property_name): null|int|string
    {
        $ret_val = null;
        if ($property_name === 'value') {
            $ret_val = $this->value;
        } elseif ($property_name === 'type') {
            $ret_val = $this->type;
        } elseif ($property_name === 'alpha') {
            $ret_val = $this->alpha;
        } elseif ($property_name === 'brightness') {
            $ret_val = $this->brightness;
        }
        return $ret_val;
    }
    public static function alpha_to_xml(int $alpha): string
    {
        return 100 - $alpha . '000';
    }
    public static function alpha_from_xml(float|int|string $alpha): int
    {
        return 100 - (int) $alpha / 1000;
    }
}