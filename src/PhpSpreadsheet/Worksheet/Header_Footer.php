<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

/**
 * <code>
 * Header/Footer Formatting Syntax taken from Office Open XML Part 4 - Markup Language Reference, page 1970:.
 *
 * There are a number of formatting codes that can be written inline with the actual header / footer text, which
 * affect the formatting in the header or footer.
 *
 * Example: This example shows the text "Center Bold Header" on the first line (center section), and the date on
 * the second line (center section).
 *         &CCenter &"-,Bold"Bold&"-,Regular"Header_x000A_&D
 *
 * General Rules:
 * There is no required order in which these codes must appear.
 *
 * The first occurrence of the following codes turns the formatting ON, the second occurrence turns it OFF again:
 * - strikethrough
 * - superscript
 * - subscript
 * Superscript and subscript cannot both be ON at same time. Whichever comes first wins and the other is ignored,
 * while the first is ON.
 * &L - code for "left section" (there are three header / footer locations, "left", "center", and "right"). When
 * two or more occurrences of this section marker exist, the contents from all markers are concatenated, in the
 * order of appearance, and placed into the left section.
 * &P - code for "current page #"
 * &N - code for "total pages"
 * &font size - code for "text font size", where font size is a font size in points.
 * &K - code for "text font color"
 * RGB Color is specified as RRGGBB
 * Theme Color is specified as TTSNN where TT is the theme color Id, S is either "+" or "-" of the tint/shade
 * value, NN is the tint/shade value.
 * &S - code for "text strikethrough" on / off
 * &X - code for "text super script" on / off
 * &Y - code for "text subscript" on / off
 * &C - code for "center section". When two or more occurrences of this section marker exist, the contents
 * from all markers are concatenated, in the order of appearance, and placed into the center section.
 *
 * &D - code for "date"
 * &T - code for "time"
 * &G - code for "picture as background"
 * &U - code for "text single underline"
 * &E - code for "double underline"
 * &R - code for "right section". When two or more occurrences of this section marker exist, the contents
 * from all markers are concatenated, in the order of appearance, and placed into the right section.
 * &Z - code for "this workbook's file path"
 * &F - code for "this workbook's file name"
 * &A - code for "sheet tab name"
 * &+ - code for add to page #.
 * &- - code for subtract from page #.
 * &"font name,font type" - code for "text font name" and "text font type", where font name and font type
 * are strings specifying the name and type of the font, separated by a comma. When a hyphen appears in font
 * name, it means "none specified". Both of font name and font type can be localized values.
 * &"-,Bold" - code for "bold font style"
 * &B - also means "bold font style".
 * &"-,Regular" - code for "regular font style"
 * &"-,Italic" - code for "italic font style"
 * &I - also means "italic font style"
 * &"-,Bold Italic" code for "bold italic font style"
 * &O - code for "outline style"
 * &H - code for "shadow style"
 * </code>
 */
class Header_Footer
{
    // Header/footer image location
    public const IMAGE_HEADER_LEFT = 'LH';
    public const IMAGE_HEADER_LEFT_ODD = 'LH';
    public const IMAGE_HEADER_LEFT_FIRST = 'LHFIRST';
    public const IMAGE_HEADER_LEFT_EVEN = 'LHEVEN';
    public const IMAGE_HEADER_CENTER = 'CH';
    public const IMAGE_HEADER_CENTER_ODD = 'CH';
    public const IMAGE_HEADER_CENTER_FIRST = 'CHFIRST';
    public const IMAGE_HEADER_CENTER_EVEN = 'CHEVEN';
    public const IMAGE_HEADER_RIGHT = 'RH';
    public const IMAGE_HEADER_RIGHT_ODD = 'RH';
    public const IMAGE_HEADER_RIGHT_FIRST = 'RHFIRST';
    public const IMAGE_HEADER_RIGHT_EVEN = 'RHEVEN';
    public const IMAGE_FOOTER_LEFT = 'LF';
    public const IMAGE_FOOTER_LEFT_ODD = 'LF';
    public const IMAGE_FOOTER_LEFT_FIRST = 'LFFIRST';
    public const IMAGE_FOOTER_LEFT_EVEN = 'LFEVEN';
    public const IMAGE_FOOTER_CENTER = 'CF';
    public const IMAGE_FOOTER_CENTER_ODD = 'CF';
    public const IMAGE_FOOTER_CENTER_FIRST = 'CFFIRST';
    public const IMAGE_FOOTER_CENTER_EVEN = 'CFEVEN';
    public const IMAGE_FOOTER_RIGHT = 'RF';
    public const IMAGE_FOOTER_RIGHT_ODD = 'RF';
    public const IMAGE_FOOTER_RIGHT_FIRST = 'RFFIRST';
    public const IMAGE_FOOTER_RIGHT_EVEN = 'RFEVEN';
    /**
     * OddHeader.
     */
    private string $odd_header = '';
    /**
     * OddFooter.
     */
    private string $odd_footer = '';
    /**
     * EvenHeader.
     */
    private string $even_header = '';
    /**
     * EvenFooter.
     */
    private string $even_footer = '';
    /**
     * FirstHeader.
     */
    private string $first_header = '';
    /**
     * FirstFooter.
     */
    private string $first_footer = '';
    /**
     * Different header for Odd/Even, defaults to false.
     */
    private bool $different_odd_even = false;
    /**
     * Different header for first page, defaults to false.
     */
    private bool $different_first = false;
    /**
     * Scale with document, defaults to true.
     */
    private bool $scale_with_document = true;
    /**
     * Align with margins, defaults to true.
     */
    private bool $align_with_margins = true;
    /**
     * Header/footer images.
     *
     * @var HeaderFooterDrawing[]
     */
    private array $header_footer_images = [];
    /**
     * Get OddHeader.
     */
    public function get_odd_header(): string
    {
        return $this->odd_header;
    }
    /**
     * Set OddHeader.
     *
     * @return $this
     */
    public function set_odd_header(string $odd_header): static
    {
        $this->odd_header = $odd_header;
        return $this;
    }
    /**
     * Get OddFooter.
     */
    public function get_odd_footer(): string
    {
        return $this->odd_footer;
    }
    /**
     * Set OddFooter.
     *
     * @return $this
     */
    public function set_odd_footer(string $odd_footer): static
    {
        $this->odd_footer = $odd_footer;
        return $this;
    }
    /**
     * Get EvenHeader.
     */
    public function get_even_header(): string
    {
        return $this->even_header;
    }
    /**
     * Set EvenHeader.
     *
     * @return $this
     */
    public function set_even_header(string $event_header): static
    {
        $this->even_header = $event_header;
        return $this;
    }
    /**
     * Get EvenFooter.
     */
    public function get_even_footer(): string
    {
        return $this->even_footer;
    }
    /**
     * Set EvenFooter.
     *
     * @return $this
     */
    public function set_even_footer(string $even_footer): static
    {
        $this->even_footer = $even_footer;
        return $this;
    }
    /**
     * Get FirstHeader.
     */
    public function get_first_header(): string
    {
        return $this->first_header;
    }
    /**
     * Set FirstHeader.
     *
     * @return $this
     */
    public function set_first_header(string $first_header): static
    {
        $this->first_header = $first_header;
        return $this;
    }
    /**
     * Get FirstFooter.
     */
    public function get_first_footer(): string
    {
        return $this->first_footer;
    }
    /**
     * Set FirstFooter.
     *
     * @return $this
     */
    public function set_first_footer(string $first_footer): static
    {
        $this->first_footer = $first_footer;
        return $this;
    }
    /**
     * Get DifferentOddEven.
     */
    public function get_different_odd_even(): bool
    {
        return $this->different_odd_even;
    }
    /**
     * Set DifferentOddEven.
     *
     * @return $this
     */
    public function set_different_odd_even(bool $different_odd_event): static
    {
        $this->different_odd_even = $different_odd_event;
        return $this;
    }
    /**
     * Get DifferentFirst.
     */
    public function get_different_first(): bool
    {
        return $this->different_first;
    }
    /**
     * Set DifferentFirst.
     *
     * @return $this
     */
    public function set_different_first(bool $different_first): static
    {
        $this->different_first = $different_first;
        return $this;
    }
    /**
     * Get ScaleWithDocument.
     */
    public function get_scale_with_document(): bool
    {
        return $this->scale_with_document;
    }
    /**
     * Set ScaleWithDocument.
     *
     * @return $this
     */
    public function set_scale_with_document(bool $scale_with_document): static
    {
        $this->scale_with_document = $scale_with_document;
        return $this;
    }
    /**
     * Get AlignWithMargins.
     */
    public function get_align_with_margins(): bool
    {
        return $this->align_with_margins;
    }
    /**
     * Set AlignWithMargins.
     *
     * @return $this
     */
    public function set_align_with_margins(bool $align_with_margins): static
    {
        $this->align_with_margins = $align_with_margins;
        return $this;
    }
    /**
     * Add header/footer image.
     *
     * @return $this
     */
    public function add_image(Header_Footer_Drawing $image, string $location = self::IMAGE_HEADER_LEFT): static
    {
        $this->header_footer_images[$location] = $image;
        return $this;
    }
    /**
     * Remove header/footer image.
     *
     * @return $this
     */
    public function remove_image(string $location = self::IMAGE_HEADER_LEFT): static
    {
        if (isset($this->header_footer_images[$location])) {
            unset($this->header_footer_images[$location]);
        }
        return $this;
    }
    /**
     * Set header/footer images.
     *
     * @param HeaderFooterDrawing[] $images
     *
     * @return $this
     */
    public function set_images(array $images): static
    {
        $this->header_footer_images = $images;
        return $this;
    }
    private const IMAGE_SORT_ORDER = [self::IMAGE_HEADER_LEFT, self::IMAGE_HEADER_LEFT_FIRST, self::IMAGE_HEADER_LEFT_EVEN, self::IMAGE_HEADER_CENTER, self::IMAGE_HEADER_CENTER_FIRST, self::IMAGE_HEADER_CENTER_EVEN, self::IMAGE_HEADER_RIGHT, self::IMAGE_HEADER_RIGHT_FIRST, self::IMAGE_HEADER_RIGHT_EVEN, self::IMAGE_FOOTER_LEFT, self::IMAGE_FOOTER_LEFT_FIRST, self::IMAGE_FOOTER_LEFT_EVEN, self::IMAGE_FOOTER_CENTER, self::IMAGE_FOOTER_CENTER_FIRST, self::IMAGE_FOOTER_CENTER_EVEN, self::IMAGE_FOOTER_RIGHT, self::IMAGE_FOOTER_RIGHT_FIRST, self::IMAGE_FOOTER_RIGHT_EVEN];
    /**
     * Get header/footer images.
     *
     * @return HeaderFooterDrawing[]
     */
    public function get_images(): array
    {
        // Sort array - not sure why needed
        $images = [];
        foreach (self::IMAGE_SORT_ORDER as $key) {
            if (isset($this->header_footer_images[$key])) {
                $images[$key] = $this->header_footer_images[$key];
            }
        }
        $this->header_footer_images = $images;
        return $this->header_footer_images;
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