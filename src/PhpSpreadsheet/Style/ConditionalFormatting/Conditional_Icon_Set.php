<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Style\Conditional_Formatting;

class Conditional_Icon_Set
{
    /** The icon set to display. */
    private ?Icon_Set_Values $icon_set_type = null;
    /**  If true, reverses the default order of the icons in this icon set. */
    private ?bool $reverse = null;
    /** Indicates whether to show the values of the cells on which this icon set is applied. */
    private ?bool $show_value = null;
    /**
     * If true, indicates that the icon set is a custom icon set.
     * If this value is "true", there MUST be the same number of cfIcon elements
     * as cfvo elements.
     * If this value is "false", there MUST be 0 cfIcon elements.
     */
    private ?bool $custom = null;
    /** @var ConditionalFormatValueObject[] */
    private array $cfvos = [];
    public function get_icon_set_type(): ?Icon_Set_Values
    {
        return $this->icon_set_type;
    }
    public function set_icon_set_type(Icon_Set_Values $type): self
    {
        $this->icon_set_type = $type;
        return $this;
    }
    public function get_reverse(): ?bool
    {
        return $this->reverse;
    }
    public function set_reverse(bool $reverse): self
    {
        $this->reverse = $reverse;
        return $this;
    }
    public function get_show_value(): ?bool
    {
        return $this->show_value;
    }
    public function set_show_value(bool $show_value): self
    {
        $this->show_value = $show_value;
        return $this;
    }
    public function get_custom(): ?bool
    {
        return $this->custom;
    }
    public function set_custom(bool $custom): self
    {
        $this->custom = $custom;
        return $this;
    }
    /**
     * Get the conditional format value objects.
     *
     * @return ConditionalFormatValueObject[]
     */
    public function get_cfvos(): array
    {
        return $this->cfvos;
    }
    /**
     * Set the conditional format value objects.
     *
     * @param ConditionalFormatValueObject[] $cfvos
     */
    public function set_cfvos(array $cfvos): self
    {
        $this->cfvos = $cfvos;
        return $this;
    }
}