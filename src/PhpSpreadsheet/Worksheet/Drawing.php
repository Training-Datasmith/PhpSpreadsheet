<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet;

use Php_Office\Php_Spreadsheet\Exception as PhpSpreadsheetException;
use Zip_Archive;
class Drawing extends Base_Drawing
{
    public const IMAGE_TYPES_CONVERTION_MAP = [IMAGETYPE_GIF => IMAGETYPE_PNG, IMAGETYPE_JPEG => IMAGETYPE_JPEG, IMAGETYPE_PNG => IMAGETYPE_PNG, IMAGETYPE_BMP => IMAGETYPE_PNG];
    /**
     * Path.
     */
    private string $path;
    /**
     * Whether or not we are dealing with a URL.
     */
    private bool $is_url;
    /**
     * Create a new Drawing.
     */
    public function __construct()
    {
        // Initialise values
        $this->path = '';
        $this->is_url = false;
        // Initialize parent
        parent::__construct();
    }
    /**
     * Get Filename.
     */
    public function get_filename(): string
    {
        return basename($this->path);
    }
    /**
     * Get indexed filename (using image index).
     */
    public function get_indexed_filename(): string
    {
        return md5($this->path) . '.' . $this->get_extension();
    }
    /**
     * Get Extension.
     */
    public function get_extension(): string
    {
        $exploded = explode('.', basename($this->path));
        return $exploded[count($exploded) - 1];
    }
    /**
     * Get full filepath to store drawing in zip archive.
     */
    public function get_media_filename(): string
    {
        if (!array_key_exists($this->type, self::IMAGE_TYPES_CONVERTION_MAP)) {
            throw new Php_Spreadsheet_Exception('Unsupported image type in comment background. Supported types: PNG, JPEG, BMP, GIF.');
        }
        return sprintf('image%d%s', $this->get_image_index(), $this->get_image_file_extension_for_save());
    }
    /**
     * Get Path.
     */
    public function get_path(): string
    {
        return $this->path;
    }
    /**
     * Set Path.
     *
     * @param string $path File path
     * @param bool $verifyFile Verify file
     * @param ?ZipArchive $zip Zip archive instance
     * @param null|callable(string):bool $isWhitelisted
     *
     * @return $this
     */
    public function set_path(string $path, bool $verify_file = true, ?Zip_Archive $zip = null, bool $allow_external = true, ?callable $is_whitelisted = null): static
    {
        $this->is_url = false;
        if (preg_match('~^data:image/[a-z]+;base64,~', $path) === 1) {
            $this->path = $path;
            return $this;
        }
        $this->path = '';
        if ($zip instanceof Zip_Archive) {
            $zip_path = explode('#', $path)[1];
            $locate = @$zip->locate_name($zip_path);
            if ($locate !== false) {
                if ($this->is_image($path)) {
                    $this->path = $path;
                    $this->set_sizes_and_type($path);
                }
            }
            // Check if a URL has been passed. https://stackoverflow.com/a/2058596/1252979
        } elseif (filter_var($path, FILTER_VALIDATE_URL) || preg_match('/^([\w\s\x00-\x1f]+):/u', $path) && !preg_match('/^([\w]+):/u', $path)) {
            if (!preg_match('/^(http|https|file|ftp|s3):/', $path)) {
                throw new Php_Spreadsheet_Exception('Invalid protocol for linked drawing');
            }
            if (!$allow_external) {
                return $this;
            }
            if ($is_whitelisted !== null && !$is_whitelisted($path)) {
                return $this;
            }
            // Implicit that it is a URL, rather store info than running check above on value in other places.
            $this->is_url = true;
            $ctx = null;
            // https://github.com/php/php-src/issues/16023
            // https://github.com/php/php-src/issues/17121
            if (str_starts_with($path, 'https:') || str_starts_with($path, 'http:')) {
                $ctx_array = ['http' => ['user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'header' => [
                    //'Connection: keep-alive', // unacceptable performance
                    'Accept: image/*;q=0.9,*/*;q=0.8',
                ]]];
                if (str_starts_with($path, 'https:')) {
                    $ctx_array['ssl'] = ['crypto_method' => Stream_crypto_method_tl_Sv1_3_client];
                }
                $ctx = stream_context_create($ctx_array);
            }
            $image_contents = @file_get_contents($path, false, $ctx);
            if ($image_contents !== false) {
                $file_path = tempnam(sys_get_temp_dir(), 'Drawing');
                if ($file_path) {
                    $put = @file_put_contents($file_path, $image_contents);
                    if ($put !== false) {
                        if ($this->is_image($file_path)) {
                            $this->path = $path;
                            $this->set_sizes_and_type($file_path);
                        }
                        unlink($file_path);
                    }
                }
            }
        } else {
            $exists = @file_exists($path);
            if ($exists !== false && $this->is_image($path)) {
                $this->path = $path;
                $this->set_sizes_and_type($path);
            }
        }
        if ($this->path === '' && $verify_file) {
            throw new Php_Spreadsheet_Exception("File {$path} not found!");
        }
        if ($this->worksheet !== null) {
            if ($this->path !== '') {
                $this->worksheet->get_cell($this->coordinates);
            }
        }
        return $this;
    }
    private function is_image(string $path): bool
    {
        $mime = (string) @mime_content_type($path);
        $ret_val = false;
        if (str_starts_with($mime, 'image/')) {
            $ret_val = true;
        } elseif ($mime === 'application/octet-stream') {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $ret_val = in_array($extension, ['bin', 'emf'], true);
        }
        return $ret_val;
    }
    /**
     * Get isURL.
     */
    public function get_is_url(): bool
    {
        return $this->is_url;
    }
    /**
     * Get hash code.
     *
     * @return string Hash code
     */
    public function get_hash_code(): string
    {
        return md5($this->path . parent::get_hash_code() . self::class);
    }
    /**
     * Get Image Type for Save.
     */
    public function get_image_type_for_save(): int
    {
        if (!array_key_exists($this->type, self::IMAGE_TYPES_CONVERTION_MAP)) {
            throw new Php_Spreadsheet_Exception('Unsupported image type in comment background. Supported types: PNG, JPEG, BMP, GIF.');
        }
        return self::IMAGE_TYPES_CONVERTION_MAP[$this->type];
    }
    /**
     * Get Image file extension for Save.
     */
    public function get_image_file_extension_for_save(bool $include_dot = true): string
    {
        if (!array_key_exists($this->type, self::IMAGE_TYPES_CONVERTION_MAP)) {
            throw new Php_Spreadsheet_Exception('Unsupported image type in comment background. Supported types: PNG, JPEG, BMP, GIF.');
        }
        $result = image_type_to_extension(self::IMAGE_TYPES_CONVERTION_MAP[$this->type], $include_dot);
        return "{$result}";
    }
    /**
     * Get Image mime type.
     */
    public function get_image_mime_type(): string
    {
        if (!array_key_exists($this->type, self::IMAGE_TYPES_CONVERTION_MAP)) {
            throw new Php_Spreadsheet_Exception('Unsupported image type in comment background. Supported types: PNG, JPEG, BMP, GIF.');
        }
        return image_type_to_mime_type(self::IMAGE_TYPES_CONVERTION_MAP[$this->type]);
    }
}