<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Drawing;

use Php_Office\Php_Spreadsheet\I_Comparable;
use Php_Office\Php_Spreadsheet\Style\Color;
class Shadow implements I_Comparable
{
    // Shadow alignment
    public const SHADOW_BOTTOM = 'b';
    public const SHADOW_BOTTOM_LEFT = 'bl';
    public const SHADOW_BOTTOM_RIGHT = 'br';
    public const SHADOW_CENTER = 'ctr';
    public const SHADOW_LEFT = 'l';
    public const SHADOW_TOP = 't';
    public const SHADOW_TOP_LEFT = 'tl';
    public const SHADOW_TOP_RIGHT = 'tr';
    /**
     * Visible.
     */
    private bool $visible;
    /**
     * Blur radius.
     *
     * Defaults to 6
     */
    private int $blur_radius;
    /**
     * Shadow distance.
     *
     * Defaults to 2
     */
    private int $distance;
    /**
     * Shadow direction (in degrees).
     */
    private int $direction;
    /**
     * Shadow alignment.
     */
    private string $alignment;
    /**
     * Color.
     */
    private Color $color;
    /**
     * Alpha.
     */
    private int $alpha;
    /**
     * Create a new Shadow.
     */
    public function __construct()
    {
        // Initialise values
        $this->visible = false;
        $this->blur_radius = 6;
        $this->distance = 2;
        $this->direction = 0;
        $this->alignment = self::SHADOW_BOTTOM_RIGHT;
        $this->color = new Color(Color::COLOR_BLACK);
        $this->alpha = 50;
    }
    /**
     * Get Visible.
     */
    public function get_visible(): bool
    {
        return $this->visible;
    }
    /**
     * Set Visible.
     *
     * @return $this
     */
    public function set_visible(bool $visible): static
    {
        $this->visible = $visible;
        return $this;
    }
    /**
     * Get Blur radius.
     */
    public function get_blur_radius(): int
    {
        return $this->blur_radius;
    }
    /**
     * Set Blur radius.
     *
     * @return $this
     */
    public function set_blur_radius(int $blur_radius): static
    {
        $this->blur_radius = $blur_radius;
        return $this;
    }
    /**
     * Get Shadow distance.
     */
    public function get_distance(): int
    {
        return $this->distance;
    }
    /**
     * Set Shadow distance.
     *
     * @return $this
     */
    public function set_distance(int $distance): static
    {
        $this->distance = $distance;
        return $this;
    }
    /**
     * Get Shadow direction (in degrees).
     */
    public function get_direction(): int
    {
        return $this->direction;
    }
    /**
     * Set Shadow direction (in degrees).
     *
     * @return $this
     */
    public function set_direction(int $direction): static
    {
        $this->direction = $direction;
        return $this;
    }
    /**
     * Get Shadow alignment.
     */
    public function get_alignment(): string
    {
        return $this->alignment;
    }
    /**
     * Set Shadow alignment.
     *
     * @return $this
     */
    public function set_alignment(string $alignment): static
    {
        $this->alignment = $alignment;
        return $this;
    }
    /**
     * Get Color.
     */
    public function get_color(): Color
    {
        return $this->color;
    }
    /**
     * Set Color.
     *
     * @return $this
     */
    public function set_color(Color $color): static
    {
        $this->color = $color;
        return $this;
    }
    /**
     * Get Alpha.
     */
    public function get_alpha(): int
    {
        return $this->alpha;
    }
    /**
     * Set Alpha.
     *
     * @return $this
     */
    public function set_alpha(int $alpha): static
    {
        $this->alpha = $alpha;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5(($this->visible ? 't' : 'f') . $this->blur_radius . $this->distance . $this->direction . $this->alignment . $this->color->get_hash_code() . $this->alpha . self::class);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if (is_object($value)) {
                $this->{$key} = clone $value;
            } else {
                $this->{$key} = $value;
            }
        }
    }
}