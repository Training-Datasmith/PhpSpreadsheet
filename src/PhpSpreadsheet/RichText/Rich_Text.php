<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Rich_Text;

use Php_Office\Php_Spreadsheet\Cell\Cell;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\I_Comparable;
use Stringable;
class Rich_Text implements I_Comparable, Stringable
{
    /**
     * Rich text elements.
     *
     * @var ITextElement[]
     */
    private array $rich_text_elements;
    /**
     * Create a new RichText instance.
     */
    public function __construct(?Cell $cell = null)
    {
        // Initialise variables
        $this->rich_text_elements = [];
        // Rich-Text string attached to cell?
        if ($cell !== null) {
            // Add cell text and style
            if ($cell->get_value_string() !== '') {
                $obj_run = new Run($cell->get_value_string());
                $obj_run->set_font(clone $cell->get_worksheet()->get_style($cell->get_coordinate())->get_font());
                $this->add_text($obj_run);
            }
            // Set parent value
            $cell->set_value_explicit($this, Data_Type::TYPE_STRING);
        }
    }
    /**
     * Add text.
     *
     * @param ITextElement $text Rich text element
     *
     * @return $this
     */
    public function add_text(I_Text_Element $text): static
    {
        $this->rich_text_elements[] = $text;
        return $this;
    }
    /**
     * Create text.
     *
     * @param string $text Text
     */
    public function create_text(string $text): Text_Element
    {
        $obj_text = new Text_Element($text);
        $this->add_text($obj_text);
        return $obj_text;
    }
    /**
     * Create text run.
     *
     * @param string $text Text
     */
    public function create_text_run(string $text): Run
    {
        $obj_text = new Run($text);
        $this->add_text($obj_text);
        return $obj_text;
    }
    /**
     * Get plain text.
     */
    public function get_plain_text(): string
    {
        // Return value
        $return_value = '';
        // Loop through all ITextElements
        foreach ($this->rich_text_elements as $text) {
            $return_value .= $text->get_text();
        }
        return $return_value;
    }
    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->get_plain_text();
    }
    /**
     * Get Rich Text elements.
     *
     * @return ITextElement[]
     */
    public function get_rich_text_elements(): array
    {
        return $this->rich_text_elements;
    }
    /**
     * Set Rich Text elements.
     *
     * @param ITextElement[] $textElements Array of elements
     *
     * @return $this
     */
    public function set_rich_text_elements(array $text_elements): static
    {
        $this->rich_text_elements = $text_elements;
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        $hash_elements = '';
        foreach ($this->rich_text_elements as $element) {
            $hash_elements .= $element->get_hash_code();
        }
        return md5($hash_elements . self::class);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            $new_value = is_object($value) ? clone $value : $value;
            if (is_array($value)) {
                $new_value = [];
                foreach ($value as $key2 => $value2) {
                    $new_value[$key2] = is_object($value2) ? clone $value2 : $value2;
                }
            }
            $this->{$key} = $new_value;
        }
    }
}