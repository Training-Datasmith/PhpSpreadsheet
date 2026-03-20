<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Document;

use DateTime;
use Php_Office\Php_Spreadsheet\Shared\Int_Or_Float;
class Properties
{
    /** constants */
    public const PROPERTY_TYPE_BOOLEAN = 'b';
    public const PROPERTY_TYPE_INTEGER = 'i';
    public const PROPERTY_TYPE_FLOAT = 'f';
    public const PROPERTY_TYPE_DATE = 'd';
    public const PROPERTY_TYPE_STRING = 's';
    public const PROPERTY_TYPE_UNKNOWN = 'u';
    private const VALID_PROPERTY_TYPE_LIST = [self::PROPERTY_TYPE_BOOLEAN, self::PROPERTY_TYPE_INTEGER, self::PROPERTY_TYPE_FLOAT, self::PROPERTY_TYPE_DATE, self::PROPERTY_TYPE_STRING];
    /**
     * Creator.
     */
    private string $creator = 'Unknown Creator';
    /**
     * LastModifiedBy.
     */
    private string $last_modified_by;
    /**
     * Created.
     */
    private float|int $created;
    /**
     * Modified.
     */
    private float|int $modified;
    /**
     * Title.
     */
    private string $title = 'Untitled Spreadsheet';
    /**
     * Description.
     */
    private string $description = '';
    /**
     * Subject.
     */
    private string $subject = '';
    /**
     * Keywords.
     */
    private string $keywords = '';
    /**
     * Category.
     */
    private string $category = '';
    /**
     * Manager.
     */
    private string $manager = '';
    /**
     * Company.
     */
    private string $company = '';
    /**
     * Custom Properties.
     *
     * @var array{value: null|bool|float|int|string, type: string}[]
     */
    private array $custom_properties = [];
    private string $hyperlink_base = '';
    private string $viewport = '';
    /**
     * Create a new Document Properties instance.
     */
    public function __construct()
    {
        // Initialise values
        $this->last_modified_by = $this->creator;
        $this->created = self::int_or_float_timestamp(null);
        $this->modified = $this->created;
    }
    /**
     * Get Creator.
     */
    public function get_creator(): string
    {
        return $this->creator;
    }
    /**
     * Set Creator.
     *
     * @return $this
     */
    public function set_creator(string $creator): self
    {
        $this->creator = $creator;
        return $this;
    }
    /**
     * Get Last Modified By.
     */
    public function get_last_modified_by(): string
    {
        return $this->last_modified_by;
    }
    /**
     * Set Last Modified By.
     *
     * @return $this
     */
    public function set_last_modified_by(string $modified_by): self
    {
        $this->last_modified_by = $modified_by;
        return $this;
    }
    private static function int_or_float_timestamp(null|bool|float|int|string $timestamp): float|int
    {
        if ($timestamp === null || is_bool($timestamp)) {
            $timestamp = (float) (new DateTime())->format('U');
        } elseif (is_string($timestamp)) {
            if (is_numeric($timestamp)) {
                $timestamp = (float) $timestamp;
            } else {
                $timestamp = (string) preg_replace('/[.][0-9]*$/', '', $timestamp);
                $timestamp = (string) preg_replace('/^(\d{4})- (\d)/', '$1-0$2', $timestamp);
                $timestamp = (string) preg_replace('/^(\d{4}-\d{2})- (\d)/', '$1-0$2', $timestamp);
                $timestamp = (float) (new DateTime($timestamp))->format('U');
            }
        }
        return Int_Or_Float::evaluate($timestamp);
    }
    /**
     * Get Created.
     */
    public function get_created(): float|int
    {
        return $this->created;
    }
    /**
     * Set Created.
     *
     * @return $this
     */
    public function set_created(null|float|int|string $timestamp): self
    {
        $this->created = self::int_or_float_timestamp($timestamp);
        return $this;
    }
    /**
     * Get Modified.
     */
    public function get_modified(): float|int
    {
        return $this->modified;
    }
    /**
     * Set Modified.
     *
     * @return $this
     */
    public function set_modified(null|float|int|string $timestamp): self
    {
        $this->modified = self::int_or_float_timestamp($timestamp);
        return $this;
    }
    /**
     * Get Title.
     */
    public function get_title(): string
    {
        return $this->title;
    }
    /**
     * Set Title.
     *
     * @return $this
     */
    public function set_title(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    /**
     * Get Description.
     */
    public function get_description(): string
    {
        return $this->description;
    }
    /**
     * Set Description.
     *
     * @return $this
     */
    public function set_description(string $description): self
    {
        $this->description = $description;
        return $this;
    }
    /**
     * Get Subject.
     */
    public function get_subject(): string
    {
        return $this->subject;
    }
    /**
     * Set Subject.
     *
     * @return $this
     */
    public function set_subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }
    /**
     * Get Keywords.
     */
    public function get_keywords(): string
    {
        return $this->keywords;
    }
    /**
     * Set Keywords.
     *
     * @return $this
     */
    public function set_keywords(string $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }
    /**
     * Get Category.
     */
    public function get_category(): string
    {
        return $this->category;
    }
    /**
     * Set Category.
     *
     * @return $this
     */
    public function set_category(string $category): self
    {
        $this->category = $category;
        return $this;
    }
    /**
     * Get Company.
     */
    public function get_company(): string
    {
        return $this->company;
    }
    /**
     * Set Company.
     *
     * @return $this
     */
    public function set_company(string $company): self
    {
        $this->company = $company;
        return $this;
    }
    /**
     * Get Manager.
     */
    public function get_manager(): string
    {
        return $this->manager;
    }
    /**
     * Set Manager.
     *
     * @return $this
     */
    public function set_manager(string $manager): self
    {
        $this->manager = $manager;
        return $this;
    }
    /**
     * Get a List of Custom Property Names.
     *
     * @return string[]
     */
    public function get_custom_properties(): array
    {
        return array_keys($this->custom_properties);
    }
    /**
     * Check if a Custom Property is defined.
     */
    public function is_custom_property_set(string $property_name): bool
    {
        return array_key_exists($property_name, $this->custom_properties);
    }
    /**
     * Get a Custom Property Value.
     */
    public function get_custom_property_value(string $property_name): bool|int|float|string|null
    {
        if (isset($this->custom_properties[$property_name])) {
            return $this->custom_properties[$property_name]['value'];
        }
        return null;
    }
    /**
     * Get a Custom Property Type.
     */
    public function get_custom_property_type(string $property_name): ?string
    {
        return $this->custom_properties[$property_name]['type'] ?? null;
    }
    private function identify_property_type(bool|int|float|string|null $property_value): string
    {
        if (is_float($property_value)) {
            return self::PROPERTY_TYPE_FLOAT;
        }
        if (is_int($property_value)) {
            return self::PROPERTY_TYPE_INTEGER;
        }
        if (is_bool($property_value)) {
            return self::PROPERTY_TYPE_BOOLEAN;
        }
        return self::PROPERTY_TYPE_STRING;
    }
    /**
     * Set a Custom Property.
     *
     * @param ?string $propertyType see `self::VALID_PROPERTY_TYPE_LIST`
     *
     * @return $this
     */
    public function set_custom_property(string $property_name, bool|int|float|string|null $property_value = '', ?string $property_type = null): self
    {
        if ($property_type === null || !in_array($property_type, self::VALID_PROPERTY_TYPE_LIST)) {
            $property_type = $this->identify_property_type($property_value);
        }
        $this->custom_properties[$property_name] = ['value' => self::convert_property($property_value, $property_type), 'type' => $property_type];
        return $this;
    }
    private const PROPERTY_TYPE_ARRAY = [
        'i' => self::PROPERTY_TYPE_INTEGER,
        //    Integer
        'i1' => self::PROPERTY_TYPE_INTEGER,
        //    1-Byte Signed Integer
        'i2' => self::PROPERTY_TYPE_INTEGER,
        //    2-Byte Signed Integer
        'i4' => self::PROPERTY_TYPE_INTEGER,
        //    4-Byte Signed Integer
        'i8' => self::PROPERTY_TYPE_INTEGER,
        //    8-Byte Signed Integer
        'int' => self::PROPERTY_TYPE_INTEGER,
        //    Integer
        'ui1' => self::PROPERTY_TYPE_INTEGER,
        //    1-Byte Unsigned Integer
        'ui2' => self::PROPERTY_TYPE_INTEGER,
        //    2-Byte Unsigned Integer
        'ui4' => self::PROPERTY_TYPE_INTEGER,
        //    4-Byte Unsigned Integer
        'ui8' => self::PROPERTY_TYPE_INTEGER,
        //    8-Byte Unsigned Integer
        'uint' => self::PROPERTY_TYPE_INTEGER,
        //    Unsigned Integer
        'f' => self::PROPERTY_TYPE_FLOAT,
        //    Real Number
        'r4' => self::PROPERTY_TYPE_FLOAT,
        //    4-Byte Real Number
        'r8' => self::PROPERTY_TYPE_FLOAT,
        //    8-Byte Real Number
        'decimal' => self::PROPERTY_TYPE_FLOAT,
        //    Decimal
        's' => self::PROPERTY_TYPE_STRING,
        //    String
        'empty' => self::PROPERTY_TYPE_STRING,
        //    Empty
        'null' => self::PROPERTY_TYPE_STRING,
        //    Null
        'lpstr' => self::PROPERTY_TYPE_STRING,
        //    LPSTR
        'lpwstr' => self::PROPERTY_TYPE_STRING,
        //    LPWSTR
        'bstr' => self::PROPERTY_TYPE_STRING,
        //    Basic String
        'd' => self::PROPERTY_TYPE_DATE,
        //    Date and Time
        'date' => self::PROPERTY_TYPE_DATE,
        //    Date and Time
        'filetime' => self::PROPERTY_TYPE_DATE,
        //    File Time
        'b' => self::PROPERTY_TYPE_BOOLEAN,
        //    Boolean
        'bool' => self::PROPERTY_TYPE_BOOLEAN,
    ];
    private const SPECIAL_TYPES = ['empty' => '', 'null' => null];
    /**
     * Convert property to form desired by Excel.
     */
    public static function convert_property(bool|int|float|string|null $property_value, string $property_type): bool|int|float|string|null
    {
        return self::SPECIAL_TYPES[$property_type] ?? self::convert_property2($property_value, $property_type);
    }
    /**
     * Convert property to form desired by Excel.
     */
    private static function convert_property2(bool|int|float|string|null $property_value, string $type): bool|int|float|string|null
    {
        $property_type = self::convert_property_type($type);
        switch ($property_type) {
            case self::PROPERTY_TYPE_INTEGER:
                $int_value = (int) $property_value;
                return $type[0] === 'u' ? abs($int_value) : $int_value;
            case self::PROPERTY_TYPE_FLOAT:
                return (float) $property_value;
            case self::PROPERTY_TYPE_DATE:
                return self::int_or_float_timestamp($property_value);
            case self::PROPERTY_TYPE_BOOLEAN:
                return is_bool($property_value) ? $property_value : $property_value === 'true';
            default:
                // includes string
                return $property_value;
        }
    }
    public static function convert_property_type(string $property_type): string
    {
        return self::PROPERTY_TYPE_ARRAY[$property_type] ?? self::PROPERTY_TYPE_UNKNOWN;
    }
    public function get_hyperlink_base(): string
    {
        return $this->hyperlink_base;
    }
    public function set_hyperlink_base(string $hyperlink_base): self
    {
        $this->hyperlink_base = $hyperlink_base;
        return $this;
    }
    public function get_viewport(): string
    {
        return $this->viewport;
    }
    public const SUGGESTED_VIEWPORT = 'width=device-width, initial-scale=1';
    public function set_viewport(string $viewport): self
    {
        $this->viewport = $viewport;
        return $this;
    }
}