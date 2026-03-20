<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Chart;

use Php_Office\Php_Spreadsheet\Style\Font;
class Layout
{
    /**
     * layoutTarget.
     */
    private ?string $layout_target = null;
    /**
     * X Mode.
     */
    private ?string $x_mode = null;
    /**
     * Y Mode.
     */
    private ?string $y_mode = null;
    /**
     * X-Position.
     */
    private ?float $x_pos = null;
    /**
     * Y-Position.
     */
    private ?float $y_pos = null;
    /**
     * width.
     */
    private ?float $width = null;
    /**
     * height.
     */
    private ?float $height = null;
    /**
     * Position - t=top.
     */
    private string $d_lbl_pos = '';
    private string $num_fmt_code = '';
    private bool $num_fmt_linked = false;
    /**
     * show legend key
     * Specifies that legend keys should be shown in data labels.
     */
    private ?bool $show_legend_key = null;
    /**
     * show value
     * Specifies that the value should be shown in a data label.
     */
    private ?bool $show_val = null;
    /**
     * show category name
     * Specifies that the category name should be shown in the data label.
     */
    private ?bool $show_cat_name = null;
    /**
     * show data series name
     * Specifies that the series name should be shown in the data label.
     */
    private ?bool $show_ser_name = null;
    /**
     * show percentage
     * Specifies that the percentage should be shown in the data label.
     */
    private ?bool $show_percent = null;
    /**
     * show bubble size.
     */
    private ?bool $show_bubble_size = null;
    /**
     * show leader lines
     * Specifies that leader lines should be shown for the data label.
     */
    private ?bool $show_leader_lines = null;
    private ?Chart_Color $label_fill_color = null;
    private ?Chart_Color $label_border_color = null;
    private ?Font $label_font = null;
    private ?Properties $label_effects = null;
    /**
     * Create a new Layout.
     *
     * @param array<mixed> $layout
     */
    public function __construct(array $layout = [])
    {
        /** @var array{layoutTarget?: string, xMode?: string, yMode?: string, x?: float, y?: float, w?:float, h?:float, dLblPos?: string, labelFont?: ?mixed, labelFontColor?: ?mixed, labelEffects?: ?mixed, numFmtCode?: string} $layout */
        if (isset($layout['layoutTarget'])) {
            $this->layout_target = $layout['layoutTarget'];
        }
        if (isset($layout['xMode'])) {
            $this->x_mode = $layout['xMode'];
        }
        if (isset($layout['yMode'])) {
            $this->y_mode = $layout['yMode'];
        }
        if (isset($layout['x'])) {
            $this->x_pos = $layout['x'];
        }
        if (isset($layout['y'])) {
            $this->y_pos = $layout['y'];
        }
        if (isset($layout['w'])) {
            $this->width = $layout['w'];
        }
        if (isset($layout['h'])) {
            $this->height = $layout['h'];
        }
        if (isset($layout['dLblPos'])) {
            $this->d_lbl_pos = $layout['dLblPos'];
        }
        if (isset($layout['numFmtCode'])) {
            $this->num_fmt_code = $layout['numFmtCode'];
        }
        $this->init_boolean($layout, 'showLegendKey');
        $this->init_boolean($layout, 'showVal');
        $this->init_boolean($layout, 'showCatName');
        $this->init_boolean($layout, 'showSerName');
        $this->init_boolean($layout, 'showPercent');
        $this->init_boolean($layout, 'showBubbleSize');
        $this->init_boolean($layout, 'showLeaderLines');
        $this->init_boolean($layout, 'numFmtLinked');
        $this->init_color($layout, 'labelFillColor');
        $this->init_color($layout, 'labelBorderColor');
        $label_font = $layout['labelFont'] ?? null;
        if ($label_font instanceof Font) {
            $this->label_font = $label_font;
        }
        $label_font_color = $layout['labelFontColor'] ?? null;
        if ($label_font_color instanceof Chart_Color) {
            $this->set_label_font_color($label_font_color);
        }
        $label_effects = $layout['labelEffects'] ?? null;
        if ($label_effects instanceof Properties) {
            $this->label_effects = $label_effects;
        }
    }
    /** @param mixed[] $layout */
    private function init_boolean(array $layout, string $name): void
    {
        if (isset($layout[$name])) {
            $this->{$name} = (bool) $layout[$name];
        }
    }
    /** @param mixed[] $layout */
    private function init_color(array $layout, string $name): void
    {
        if (isset($layout[$name]) && $layout[$name] instanceof Chart_Color) {
            $this->{$name} = $layout[$name];
        }
    }
    /**
     * Get Layout Target.
     */
    public function get_layout_target(): ?string
    {
        return $this->layout_target;
    }
    /**
     * Set Layout Target.
     *
     * @return $this
     */
    public function set_layout_target(?string $target): static
    {
        $this->layout_target = $target;
        return $this;
    }
    /**
     * Get X-Mode.
     */
    public function get_x_mode(): ?string
    {
        return $this->x_mode;
    }
    /**
     * Set X-Mode.
     *
     * @return $this
     */
    public function set_x_mode(?string $mode): static
    {
        $this->x_mode = (string) $mode;
        return $this;
    }
    /**
     * Get Y-Mode.
     */
    public function get_y_mode(): ?string
    {
        return $this->y_mode;
    }
    /**
     * Set Y-Mode.
     *
     * @return $this
     */
    public function set_y_mode(?string $mode): static
    {
        $this->y_mode = (string) $mode;
        return $this;
    }
    /**
     * Get X-Position.
     */
    public function get_x_position(): null|float|int
    {
        return $this->x_pos;
    }
    /**
     * Set X-Position.
     *
     * @return $this
     */
    public function set_x_position(float $position): static
    {
        $this->x_pos = $position;
        return $this;
    }
    /**
     * Get Y-Position.
     */
    public function get_y_position(): ?float
    {
        return $this->y_pos;
    }
    /**
     * Set Y-Position.
     *
     * @return $this
     */
    public function set_y_position(float $position): static
    {
        $this->y_pos = $position;
        return $this;
    }
    /**
     * Get Width.
     */
    public function get_width(): ?float
    {
        return $this->width;
    }
    /**
     * Set Width.
     *
     * @return $this
     */
    public function set_width(?float $width): static
    {
        $this->width = $width;
        return $this;
    }
    /**
     * Get Height.
     */
    public function get_height(): ?float
    {
        return $this->height;
    }
    /**
     * Set Height.
     *
     * @return $this
     */
    public function set_height(?float $height): static
    {
        $this->height = $height;
        return $this;
    }
    public function get_show_legend_key(): ?bool
    {
        return $this->show_legend_key;
    }
    /**
     * Set show legend key
     * Specifies that legend keys should be shown in data labels.
     */
    public function set_show_legend_key(?bool $show_legend_key): self
    {
        $this->show_legend_key = $show_legend_key;
        return $this;
    }
    public function get_show_val(): ?bool
    {
        return $this->show_val;
    }
    /**
     * Set show val
     * Specifies that the value should be shown in data labels.
     */
    public function set_show_val(?bool $show_data_label_values): self
    {
        $this->show_val = $show_data_label_values;
        return $this;
    }
    public function get_show_cat_name(): ?bool
    {
        return $this->show_cat_name;
    }
    /**
     * Set show cat name
     * Specifies that the category name should be shown in data labels.
     */
    public function set_show_cat_name(?bool $show_category_name): self
    {
        $this->show_cat_name = $show_category_name;
        return $this;
    }
    public function get_show_ser_name(): ?bool
    {
        return $this->show_ser_name;
    }
    /**
     * Set show data series name.
     * Specifies that the series name should be shown in data labels.
     */
    public function set_show_ser_name(?bool $show_series_name): self
    {
        $this->show_ser_name = $show_series_name;
        return $this;
    }
    public function get_show_percent(): ?bool
    {
        return $this->show_percent;
    }
    /**
     * Set show percentage.
     * Specifies that the percentage should be shown in data labels.
     */
    public function set_show_percent(?bool $show_percentage): self
    {
        $this->show_percent = $show_percentage;
        return $this;
    }
    public function get_show_bubble_size(): ?bool
    {
        return $this->show_bubble_size;
    }
    /**
     * Set show bubble size.
     * Specifies that the bubble size should be shown in data labels.
     */
    public function set_show_bubble_size(?bool $show_bubble_size): self
    {
        $this->show_bubble_size = $show_bubble_size;
        return $this;
    }
    public function get_show_leader_lines(): ?bool
    {
        return $this->show_leader_lines;
    }
    /**
     * Set show leader lines.
     * Specifies that leader lines should be shown in data labels.
     */
    public function set_show_leader_lines(?bool $show_leader_lines): self
    {
        $this->show_leader_lines = $show_leader_lines;
        return $this;
    }
    public function get_label_fill_color(): ?Chart_Color
    {
        return $this->label_fill_color;
    }
    public function set_label_fill_color(?Chart_Color $chart_color): self
    {
        $this->label_fill_color = $chart_color;
        return $this;
    }
    public function get_label_border_color(): ?Chart_Color
    {
        return $this->label_border_color;
    }
    public function set_label_border_color(?Chart_Color $chart_color): self
    {
        $this->label_border_color = $chart_color;
        return $this;
    }
    public function get_label_font(): ?Font
    {
        return $this->label_font;
    }
    public function set_label_font(?Font $label_font): self
    {
        $this->label_font = $label_font;
        return $this;
    }
    public function get_label_effects(): ?Properties
    {
        return $this->label_effects;
    }
    public function get_label_font_color(): ?Chart_Color
    {
        if ($this->label_font === null) {
            return null;
        }
        return $this->label_font->get_chart_color();
    }
    public function set_label_font_color(?Chart_Color $chart_color): self
    {
        if ($this->label_font === null) {
            $this->label_font = new Font();
            $this->label_font->set_size(null, true);
        }
        $this->label_font->set_chart_color_from_object($chart_color);
        return $this;
    }
    public function get_d_lbl_pos(): string
    {
        return $this->d_lbl_pos;
    }
    public function set_d_lbl_pos(string $d_lbl_pos): self
    {
        $this->d_lbl_pos = $d_lbl_pos;
        return $this;
    }
    public function get_num_fmt_code(): string
    {
        return $this->num_fmt_code;
    }
    public function set_num_fmt_code(string $num_fmt_code): self
    {
        $this->num_fmt_code = $num_fmt_code;
        return $this;
    }
    public function get_num_fmt_linked(): bool
    {
        return $this->num_fmt_linked;
    }
    public function set_num_fmt_linked(bool $num_fmt_linked): self
    {
        $this->num_fmt_linked = $num_fmt_linked;
        return $this;
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->label_fill_color = $this->label_fill_color === null ? null : clone $this->label_fill_color;
        $this->label_border_color = $this->label_border_color === null ? null : clone $this->label_border_color;
        $this->label_font = $this->label_font === null ? null : clone $this->label_font;
        $this->label_effects = $this->label_effects === null ? null : clone $this->label_effects;
    }
}