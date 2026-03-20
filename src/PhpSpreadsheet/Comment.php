<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\Helper\Size;
use Php_Office\Php_Spreadsheet\Rich_Text\Rich_Text;
use Php_Office\Php_Spreadsheet\Shared\Drawing as SharedDrawing;
use Php_Office\Php_Spreadsheet\Style\Alignment;
use Php_Office\Php_Spreadsheet\Style\Color;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing;
use Stringable;
class Comment implements I_Comparable, Stringable
{
    /**
     * Author.
     */
    private string $author;
    /**
     * Rich text comment.
     */
    private Rich_Text $text;
    /**
     * Comment width (CSS style, i.e. XXpx or YYpt).
     */
    private string $width = '96pt';
    /**
     * Left margin (CSS style, i.e. XXpx or YYpt).
     */
    private string $margin_left = '59.25pt';
    /**
     * Top margin (CSS style, i.e. XXpx or YYpt).
     */
    private string $margin_top = '1.5pt';
    /**
     * Visible.
     */
    private bool $visible = false;
    /**
     * Comment height (CSS style, i.e. XXpx or YYpt).
     */
    private string $height = '55.5pt';
    /**
     * Comment fill color.
     */
    private Color $fill_color;
    /**
     * Alignment.
     */
    private string $alignment;
    /**
     * Background image in comment.
     */
    private Drawing $background_image;
    public const TEXTBOX_DIRECTION_RTL = 'rtl';
    public const TEXTBOX_DIRECTION_LTR = 'ltr';
    // MS uses 'auto' in xml but 'context' in UI
    public const TEXTBOX_DIRECTION_AUTO = 'auto';
    public const TEXTBOX_DIRECTION_CONTEXT = 'auto';
    private string $textbox_direction = '';
    /**
     * Create a new Comment.
     */
    public function __construct()
    {
        // Initialise variables
        $this->author = 'Author';
        $this->text = new Rich_Text();
        $this->fill_color = new Color('FFFFFFE1');
        $this->alignment = Alignment::HORIZONTAL_GENERAL;
        $this->background_image = new Drawing();
    }
    /**
     * Get Author.
     */
    public function get_author(): string
    {
        return $this->author;
    }
    /**
     * Set Author.
     */
    public function set_author(string $author): self
    {
        $this->author = $author;
        return $this;
    }
    /**
     * Get Rich text comment.
     */
    public function get_text(): Rich_Text
    {
        return $this->text;
    }
    /**
     * Set Rich text comment.
     */
    public function set_text(Rich_Text $text): self
    {
        $this->text = $text;
        return $this;
    }
    /**
     * Get comment width (CSS style, i.e. XXpx or YYpt).
     */
    public function get_width(): string
    {
        return $this->width;
    }
    /**
     * Set comment width (CSS style, i.e. XXpx or YYpt). Default unit is pt.
     */
    public function set_width(string $width): self
    {
        $width = new Size($width);
        if ($width->valid()) {
            $this->width = (string) $width;
        }
        return $this;
    }
    /**
     * Get comment height (CSS style, i.e. XXpx or YYpt).
     */
    public function get_height(): string
    {
        return $this->height;
    }
    /**
     * Set comment height (CSS style, i.e. XXpx or YYpt). Default unit is pt.
     */
    public function set_height(string $height): self
    {
        $height = new Size($height);
        if ($height->valid()) {
            $this->height = (string) $height;
        }
        return $this;
    }
    /**
     * Get left margin (CSS style, i.e. XXpx or YYpt).
     */
    public function get_margin_left(): string
    {
        return $this->margin_left;
    }
    /**
     * Set left margin (CSS style, i.e. XXpx or YYpt). Default unit is pt.
     */
    public function set_margin_left(string $margin): self
    {
        $margin = new Size($margin);
        if ($margin->valid()) {
            $this->margin_left = (string) $margin;
        }
        return $this;
    }
    /**
     * Get top margin (CSS style, i.e. XXpx or YYpt).
     */
    public function get_margin_top(): string
    {
        return $this->margin_top;
    }
    /**
     * Set top margin (CSS style, i.e. XXpx or YYpt). Default unit is pt.
     */
    public function set_margin_top(string $margin): self
    {
        $margin = new Size($margin);
        if ($margin->valid()) {
            $this->margin_top = (string) $margin;
        }
        return $this;
    }
    /**
     * Is the comment visible by default?
     */
    public function get_visible(): bool
    {
        return $this->visible;
    }
    /**
     * Set comment default visibility.
     */
    public function set_visible(bool $visibility): self
    {
        $this->visible = $visibility;
        return $this;
    }
    /**
     * Set fill color.
     */
    public function set_fill_color(Color $color): self
    {
        $this->fill_color = $color;
        return $this;
    }
    /**
     * Get fill color.
     */
    public function get_fill_color(): Color
    {
        return $this->fill_color;
    }
    public function set_alignment(string $alignment): self
    {
        $this->alignment = $alignment;
        return $this;
    }
    public function get_alignment(): string
    {
        return $this->alignment;
    }
    public function set_textbox_direction(string $textbox_direction): self
    {
        $this->textbox_direction = $textbox_direction;
        return $this;
    }
    public function get_textbox_direction(): string
    {
        return $this->textbox_direction;
    }
    /**
     * Get hash code.
     */
    public function get_hash_code(): string
    {
        return md5($this->author . $this->text->get_hash_code() . $this->width . $this->height . $this->margin_left . $this->margin_top . ($this->visible ? 1 : 0) . $this->fill_color->get_hash_code() . $this->alignment . $this->textbox_direction . ($this->has_background_image() ? $this->background_image->get_hash_code() : '') . self::class);
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
    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->text->get_plain_text();
    }
    /**
     * Check is background image exists.
     */
    public function has_background_image(): bool
    {
        $path = $this->background_image->get_path();
        if (empty($path)) {
            return false;
        }
        return getimagesize($path) !== false;
    }
    /**
     * Returns background image.
     */
    public function get_background_image(): Drawing
    {
        return $this->background_image;
    }
    /**
     * Sets background image.
     */
    public function set_background_image(Drawing $obj_drawing): self
    {
        if (!array_key_exists($obj_drawing->get_type(), Drawing::IMAGE_TYPES_CONVERTION_MAP)) {
            throw new Php_Spreadsheet_Exception('Unsupported image type in comment background. Supported types: PNG, JPEG, BMP, GIF.');
        }
        $this->background_image = $obj_drawing;
        return $this;
    }
    /**
     * Sets size of comment as size of background image.
     */
    public function set_size_as_background_image(): self
    {
        if ($this->has_background_image()) {
            $this->set_width(Shared_Drawing::pixels_to_points($this->background_image->get_width()) . 'pt');
            $this->set_height(Shared_Drawing::pixels_to_points($this->background_image->get_height()) . 'pt');
        }
        return $this;
    }
}