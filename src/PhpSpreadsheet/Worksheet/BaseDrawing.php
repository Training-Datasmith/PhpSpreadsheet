<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Cell\Hyperlink;
use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Php_Office\Php_Spreadsheet\I_Comparable;
use Php_Office\Php_Spreadsheet\Worksheet\Drawing\Shadow;
use Simple_Xml_Element;
class Base_Drawing implements I_Comparable
{
    public const EDIT_AS_ABSOLUTE = 'absolute';
    public const EDIT_AS_ONECELL = 'oneCell';
    public const EDIT_AS_TWOCELL = 'twoCell';
    private const VALID_EDIT_AS = [self::EDIT_AS_ABSOLUTE, self::EDIT_AS_ONECELL, self::EDIT_AS_TWOCELL];
    /**
     * The editAs attribute, used only with two cell anchor.
     */
    protected string $edit_as = '';
    /**
     * Image counter.
     */
    private static int $image_counter = 0;
    /**
     * Image index.
     */
    private readonly int $image_index;
    /**
     * Name.
     */
    protected string $name = '';
    /**
     * Description.
     */
    protected string $description = '';
    /**
     * Worksheet.
     */
    protected ?Worksheet $worksheet = null;
    /**
     * Coordinates.
     */
    protected string $coordinates = 'A1';
    /**
     * Offset X.
     */
    protected int $offset_x = 0;
    /**
     * Offset Y.
     */
    protected int $offset_y = 0;
    /**
     * Coordinates2.
     */
    protected string $coordinates2 = '';
    /**
     * Offset X2.
     */
    protected int $offset_x2 = 0;
    /**
     * Offset Y2.
     */
    protected int $offset_y2 = 0;
    /**
     * Width.
     */
    protected int $width = 0;
    /**
     * Height.
     */
    protected int $height = 0;
    /**
     * Pixel width of image. See $width for the size the Drawing will be in the sheet.
     */
    protected int $image_width = 0;
    /**
     * Pixel width of image. See $height for the size the Drawing will be in the sheet.
     */
    protected int $image_height = 0;
    /**
     * Proportional resize.
     */
    protected bool $resize_proportional = true;
    /**
     * Rotation.
     */
    protected int $rotation = 0;
    protected bool $flip_vertical = false;
    protected bool $flip_horizontal = false;
    /**
     * Shadow.
     */
    protected Shadow $shadow;
    /**
     * Image hyperlink.
     */
    private ?Hyperlink $hyperlink = null;
    /**
     * Image type.
     */
    protected int $type = IMAGETYPE_UNKNOWN;
    /** @var null|SimpleXMLElement|string[] */
    protected $src_rect = [];
    /**
     * Percentage multiplied by 100,000, e.g. 40% = 40,000.
     * Opacity=x is the same as transparency=100000-x.
     */
    protected ?int $opacity = null;
    protected bool $in_cell = false;
    protected int $index = 0;
    /**
     * Create a new BaseDrawing.
     */
    public function __construct()
    {
        // Initialise values
        $this->set_shadow();
        // Set image index
        ++self::$image_counter;
        $this->image_index = self::$image_counter;
    }
    public function __destruct()
    {
        $this->worksheet = null;
    }
    public function get_image_index(): int
    {
        return $this->image_index;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function set_name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    public function get_description(): string
    {
        return $this->description;
    }
    public function set_description(string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function get_worksheet(): ?Worksheet
    {
        return $this->worksheet;
    }
    /**
     * Set Worksheet.
     *
     * @param bool $overrideOld If a Worksheet has already been assigned, overwrite it and remove image from old Worksheet?
     */
    public function set_worksheet(?Worksheet $worksheet = null, bool $override_old = false): self
    {
        if ($this->worksheet === null) {
            // Add drawing to Worksheet
            if ($worksheet !== null) {
                $this->worksheet = $worksheet;
                if (!($this instanceof Drawing && $this->get_path() === '')) {
                    $this->worksheet->get_cell($this->coordinates);
                }
                if ($this->in_cell) {
                    $this->worksheet->get_in_cell_drawing_collection()->append($this);
                } else {
                    $this->worksheet->get_drawing_collection()->append($this);
                }
            }
        } else if ($override_old) {
            // Remove drawing from old Worksheet
            $collections = [$this->worksheet->get_drawing_collection(), $this->worksheet->get_in_cell_drawing_collection()];
            foreach ($collections as $collection) {
                foreach ($collection as $key => $drawing) {
                    if ($drawing->get_hash_code() === $this->get_hash_code()) {
                        $collection->offsetUnset($key);
                        $this->worksheet = null;
                        break 2;
                        // break both loops
                    }
                }
            }
            // Set new Worksheet
            $this->set_worksheet($worksheet);
        } else {
            throw new Php_Spreadsheet_Exception('A Worksheet has already been assigned. Drawings can only exist on one Worksheet.');
        }
        return $this;
    }
    public function get_coordinates(): string
    {
        return $this->coordinates;
    }
    public function set_coordinates(string $coordinates): self
    {
        $this->coordinates = $coordinates;
        if ($this->worksheet !== null) {
            if (!($this instanceof Drawing && $this->get_path() === '')) {
                $this->worksheet->get_cell($this->coordinates);
            }
        }
        return $this;
    }
    public function get_offset_x(): int
    {
        return $this->offset_x;
    }
    public function set_offset_x(int $offset_x): self
    {
        $this->offset_x = $offset_x;
        return $this;
    }
    public function get_offset_y(): int
    {
        return $this->offset_y;
    }
    public function set_offset_y(int $offset_y): self
    {
        $this->offset_y = $offset_y;
        return $this;
    }
    public function get_coordinates2(): string
    {
        return $this->coordinates2;
    }
    public function set_coordinates2(string $coordinates2): self
    {
        $this->coordinates2 = $coordinates2;
        return $this;
    }
    public function get_offset_x2(): int
    {
        return $this->offset_x2;
    }
    public function set_offset_x2(int $offset_x2): self
    {
        $this->offset_x2 = $offset_x2;
        return $this;
    }
    public function get_offset_y2(): int
    {
        return $this->offset_y2;
    }
    public function set_offset_y2(int $offset_y2): self
    {
        $this->offset_y2 = $offset_y2;
        return $this;
    }
    public function get_width(): int
    {
        return $this->width;
    }
    public function set_width(int $width): self
    {
        // Resize proportional?
        if ($this->resize_proportional && $width != 0) {
            $ratio = $this->height / ($this->width != 0 ? $this->width : 1);
            $this->height = (int) round($ratio * $width);
        }
        // Set width
        $this->width = $width;
        return $this;
    }
    public function get_height(): int
    {
        return $this->height;
    }
    public function set_height(int $height): self
    {
        // Resize proportional?
        if ($this->resize_proportional && $height != 0) {
            $ratio = $this->width / ($this->height != 0 ? $this->height : 1);
            $this->width = (int) round($ratio * $height);
        }
        // Set height
        $this->height = $height;
        return $this;
    }
    /**
     * Set width and height with proportional resize.
     *
     * Example:
     * <code>
     * $objDrawing->setResizeProportional(true);
     * $objDrawing->setWidthAndHeight(160,120);
     * </code>
     *
     * @author Vincent@luo MSN:kele_100@hotmail.com
     */
    public function set_width_and_height(int $width, int $height): self
    {
        if ($this->width === 0 || $this->height === 0 || $width === 0 || $height === 0 || !$this->resize_proportional) {
            $this->width = $width;
            $this->height = $height;
        } else {
            $xratio = $width / $this->width;
            $yratio = $height / $this->height;
            if ($xratio * $this->height < $height) {
                $this->height = (int) ceil($xratio * $this->height);
                $this->width = $width;
            } else {
                $this->width = (int) ceil($yratio * $this->width);
                $this->height = $height;
            }
        }
        return $this;
    }
    public function get_resize_proportional(): bool
    {
        return $this->resize_proportional;
    }
    public function set_resize_proportional(bool $resize_proportional): self
    {
        $this->resize_proportional = $resize_proportional;
        return $this;
    }
    public function get_rotation(): int
    {
        return $this->rotation;
    }
    public function set_rotation(int $rotation): self
    {
        $this->rotation = $rotation;
        return $this;
    }
    public function get_shadow(): Shadow
    {
        return $this->shadow;
    }
    public function set_shadow(?Shadow $shadow = null): self
    {
        $this->shadow = $shadow ?? new Shadow();
        return $this;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->name . $this->description . ($this->worksheet === null ? '' : (string) spl_object_id($this->worksheet)) . $this->coordinates . $this->offset_x . $this->offset_y . $this->coordinates2 . $this->offset_x2 . $this->offset_y2 . $this->width . $this->height . $this->rotation . $this->shadow->get_hash_code() . self::class);
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            if ($key == 'worksheet') {
                $this->worksheet = null;
            } elseif (is_object($value)) {
                $this->{$key} = clone $value;
            } else {
                $this->{$key} = $value;
            }
        }
    }
    public function set_hyperlink(?Hyperlink $hyperlink = null): void
    {
        $this->hyperlink = $hyperlink;
    }
    public function get_hyperlink(): ?Hyperlink
    {
        return $this->hyperlink;
    }
    /**
     * Set Fact Sizes and Type of Image.
     */
    protected function set_sizes_and_type(string $path): void
    {
        if ($this->image_width === 0 && $this->image_height === 0 && $this->type === IMAGETYPE_UNKNOWN) {
            $image_data = getimagesize($path);
            if (!empty($image_data)) {
                $this->image_width = $image_data[0];
                $this->image_height = $image_data[1];
                $this->type = $image_data[2];
            }
        }
        if ($this->width === 0 && $this->height === 0) {
            $this->width = $this->image_width;
            $this->height = $this->image_height;
        }
    }
    /**
     * Get Image Type.
     */
    public function get_type(): int
    {
        return $this->type;
    }
    public function get_image_width(): int
    {
        return $this->image_width;
    }
    public function get_image_height(): int
    {
        return $this->image_height;
    }
    public function get_edit_as(): string
    {
        return $this->edit_as;
    }
    public function set_edit_as(string $edit_as): self
    {
        $this->edit_as = $edit_as;
        return $this;
    }
    public function valid_edit_as(): bool
    {
        return in_array($this->edit_as, self::VALID_EDIT_AS, true);
    }
    /**
     * @return null|SimpleXMLElement|string[]
     */
    public function get_src_rect()
    {
        return $this->src_rect;
    }
    /**
     * @param null|SimpleXMLElement|string[] $srcRect
     */
    public function set_src_rect($src_rect): self
    {
        $this->src_rect = $src_rect;
        return $this;
    }
    public function set_flip_horizontal(bool $flip_horizontal): self
    {
        $this->flip_horizontal = $flip_horizontal;
        return $this;
    }
    public function get_flip_horizontal(): bool
    {
        return $this->flip_horizontal;
    }
    public function set_flip_vertical(bool $flip_vertical): self
    {
        $this->flip_vertical = $flip_vertical;
        return $this;
    }
    public function get_flip_vertical(): bool
    {
        return $this->flip_vertical;
    }
    public function set_opacity(?int $opacity): self
    {
        $this->opacity = $opacity;
        return $this;
    }
    public function get_opacity(): ?int
    {
        return $this->opacity;
    }
    public function set_in_cell(bool $in_cell): self
    {
        $this->in_cell = $in_cell;
        return $this;
    }
    public function is_in_cell(): ?bool
    {
        return $this->in_cell;
    }
    public function set_index(int $index): self
    {
        $this->index = $index;
        return $this;
    }
    public function get_index(): int
    {
        return $this->index;
    }
}