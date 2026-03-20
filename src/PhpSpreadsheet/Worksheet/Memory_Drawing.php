<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Gd_Image;
use Php_Office\Php_Spreadsheet\Exception;
use Php_Office\Php_Spreadsheet\Shared\File;
class Memory_Drawing extends Base_Drawing
{
    // Rendering functions
    public const RENDERING_DEFAULT = 'imagepng';
    public const RENDERING_PNG = 'imagepng';
    public const RENDERING_GIF = 'imagegif';
    public const RENDERING_JPEG = 'imagejpeg';
    // MIME types
    public const MIMETYPE_DEFAULT = 'image/png';
    public const MIMETYPE_PNG = 'image/png';
    public const MIMETYPE_GIF = 'image/gif';
    public const MIMETYPE_JPEG = 'image/jpeg';
    public const SUPPORTED_MIME_TYPES = [self::MIMETYPE_GIF, self::MIMETYPE_JPEG, self::MIMETYPE_PNG];
    /**
     * Image resource.
     */
    private null|Gd_Image $image_resource = null;
    /**
     * Rendering function.
     *
     * @var callable-string
     */
    private string $rendering_function;
    /**
     * Mime type.
     */
    private string $mime_type;
    /**
     * Unique name.
     */
    private readonly string $unique_name;
    /**
     * Create a new MemoryDrawing.
     */
    public function __construct()
    {
        // Initialise values
        $this->rendering_function = self::RENDERING_DEFAULT;
        $this->mime_type = self::MIMETYPE_DEFAULT;
        $this->unique_name = md5(mt_rand(0, 9999) . time() . mt_rand(0, 9999));
        // Initialize parent
        parent::__construct();
    }
    public function __destruct()
    {
        $this->image_resource = null;
        $this->worksheet = null;
    }
    public function __clone()
    {
        parent::__clone();
        $this->clone_resource();
    }
    private function clone_resource(): void
    {
        if (!$this->image_resource) {
            return;
        }
        $width = imagesx($this->image_resource);
        $height = imagesy($this->image_resource);
        if (imageistruecolor($this->image_resource)) {
            $clone = imagecreatetruecolor($width, $height);
            if (!$clone) {
                throw new Exception('Could not clone image resource');
            }
            imagealphablending($clone, false);
            imagesavealpha($clone, true);
        } else {
            $clone = imagecreate($width, $height);
            if (!$clone) {
                throw new Exception('Could not clone image resource');
            }
            // If the image has transparency...
            $transparent = imagecolortransparent($this->image_resource);
            if ($transparent >= 0) {
                // Starting with Php8.0, next function throws rather than return false
                $rgb = imagecolorsforindex($this->image_resource, $transparent);
                imagesavealpha($clone, true);
                $color = imagecolorallocatealpha($clone, $rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']);
                if ($color === false) {
                    throw new Exception('Could not get image alpha color');
                }
                imagefill($clone, 0, 0, $color);
            }
        }
        //Create the Clone!!
        imagecopy($clone, $this->image_resource, 0, 0, 0, 0, $width, $height);
        $this->image_resource = $clone;
    }
    /**
     * @param resource $imageStream Stream data to be converted to a Memory Drawing
     *
     * @throws Exception
     */
    public static function from_stream($image_stream): self
    {
        $stream_value = stream_get_contents($image_stream);
        return self::from_string($stream_value);
    }
    /**
     * @param string $imageString String data to be converted to a Memory Drawing
     *
     * @throws Exception
     */
    public static function from_string(string $image_string): self
    {
        $gd_image = @imagecreatefromstring($image_string);
        if ($gd_image === false) {
            throw new Exception('Value cannot be converted to an image');
        }
        $mime_type = self::identify_mime_type($image_string);
        if (imageistruecolor($gd_image) || imagecolortransparent($gd_image) >= 0) {
            imagesavealpha($gd_image, true);
        }
        $rendering_function = self::identify_rendering_function($mime_type);
        $drawing = new self();
        $drawing->set_image_resource($gd_image);
        $drawing->set_rendering_function($rendering_function);
        $drawing->set_mime_type($mime_type);
        return $drawing;
    }
    /** @return callable-string */
    private static function identify_rendering_function(string $mime_type): string
    {
        return match ($mime_type) {
            self::MIMETYPE_PNG => self::RENDERING_PNG,
            self::MIMETYPE_JPEG => self::RENDERING_JPEG,
            self::MIMETYPE_GIF => self::RENDERING_GIF,
            default => self::RENDERING_DEFAULT,
        };
    }
    /**
     * @throws Exception
     */
    private static function identify_mime_type(string $image_string): string
    {
        $temporary_file_name = File::temporary_filename();
        file_put_contents($temporary_file_name, $image_string);
        $mime_type = self::identify_mime_type_using_gd($temporary_file_name);
        if ($mime_type !== null) {
            unlink($temporary_file_name);
            return $mime_type;
        }
        unlink($temporary_file_name);
        return self::MIMETYPE_DEFAULT;
    }
    /** @internal */
    protected static string $get_image_size = 'getImageSize';
    private static function identify_mime_type_using_gd(string $temporary_file_name): ?string
    {
        if (function_exists(static::$get_image_size)) {
            $image_size = @getimagesize($temporary_file_name);
            if (is_array($image_size)) {
                $mime_type = $image_size['mime'];
                return self::supported_mime_types($mime_type);
            }
        }
        return null;
    }
    private static function supported_mime_types(?string $mime_type = null): ?string
    {
        if (in_array($mime_type, self::SUPPORTED_MIME_TYPES, true)) {
            return $mime_type;
        }
        return null;
    }
    /**
     * Get image resource.
     */
    public function get_image_resource(): ?Gd_Image
    {
        return $this->image_resource;
    }
    /**
     * Set image resource.
     *
     * @return $this
     */
    public function set_image_resource(?Gd_Image $value): static
    {
        $this->image_resource = $value;
        if ($this->image_resource !== null) {
            // Get width/height
            $this->width = imagesx($this->image_resource);
            $this->height = imagesy($this->image_resource);
        }
        return $this;
    }
    /**
     * Get rendering function.
     *
     * @return callable-string
     */
    public function get_rendering_function(): string
    {
        return $this->rendering_function;
    }
    /**
     * Set rendering function.
     *
     * @param callable-string $value see self::RENDERING_*
     *
     * @return $this
     */
    public function set_rendering_function(string $value): static
    {
        $this->rendering_function = $value;
        return $this;
    }
    /**
     * Get mime type.
     */
    public function get_mime_type(): string
    {
        return $this->mime_type;
    }
    /**
     * Set mime type.
     *
     * @param string $value see self::MIMETYPE_*
     *
     * @return $this
     */
    public function set_mime_type(string $value): static
    {
        $this->mime_type = $value;
        return $this;
    }
    /**
     * Get indexed filename (using image index).
     */
    public function get_indexed_filename(): string
    {
        $extension = strtolower($this->get_mime_type());
        $extension = explode('/', $extension);
        $extension = $extension[1];
        return $this->unique_name . $this->get_image_index() . '.' . $extension;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->rendering_function . $this->mime_type . $this->unique_name . parent::get_hash_code() . self::class);
    }
}