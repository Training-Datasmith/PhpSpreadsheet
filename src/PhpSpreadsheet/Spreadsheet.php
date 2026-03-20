<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

use Composer\Pcre\Preg;
use JsonSerializable;
use Php_Office\Php_Spreadsheet\Calculation\Calculation;
use Php_Office\Php_Spreadsheet\Cell\I_Value_Binder;
use Php_Office\Php_Spreadsheet\Document\Properties;
use Php_Office\Php_Spreadsheet\Document\Security;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Font as SharedFont;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Iterator;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
/**
 * Represents a PhpSpreadsheet workbook.
 *
 * A Spreadsheet contains one or more Worksheet objects, document Properties,
 * optional macro code (VBA), and a Calculation engine. Use IOFactory to load
 * existing files or write a Spreadsheet to disk.
 *
 * Example:
 *   $spreadsheet = new Spreadsheet();
 *   $sheet = $spreadsheet->getActiveSheet();
 *   $sheet->setCellValue('A1', 'Hello World');
 *   $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
 *   $writer->save('output.xlsx');
 *
 * @see \PhpOffice\PhpSpreadsheet\IOFactory For loading and saving
 * @see Worksheet For cell operations
 */
class Spreadsheet implements JsonSerializable
{
    // Allowable values for workbook window visibility
    public const VISIBILITY_VISIBLE = 'visible';
    public const VISIBILITY_HIDDEN = 'hidden';
    public const VISIBILITY_VERY_HIDDEN = 'veryHidden';
    private const DEFINED_NAME_IS_RANGE = false;
    private const DEFINED_NAME_IS_FORMULA = true;
    private const WORKBOOK_VIEW_VISIBILITY_VALUES = [self::VISIBILITY_VISIBLE, self::VISIBILITY_HIDDEN, self::VISIBILITY_VERY_HIDDEN];
    protected int $excel_calendar = Date::CALENDAR_WINDOWS_1900;
    /**
     * Unique ID.
     */
    private string $unique_id;
    /**
     * Document properties.
     */
    private Properties $properties;
    /**
     * Document security.
     */
    private Security $security;
    /**
     * Collection of Worksheet objects.
     *
     * @var Worksheet[]
     */
    private array $work_sheet_collection;
    /**
     * Calculation Engine.
     */
    private Calculation $calculation_engine;
    /**
     * Active sheet index.
     */
    private int $active_sheet_index;
    /**
     * Named ranges.
     *
     * @var DefinedName[]
     */
    private array $defined_names;
    /**
     * CellXf supervisor.
     */
    private Style $cell_xf_supervisor;
    /**
     * CellXf collection.
     *
     * @var Style[]
     */
    private array $cell_xf_collection = [];
    /**
     * CellStyleXf collection.
     *
     * @var Style[]
     */
    private array $cell_style_xf_collection = [];
    /**
     * hasMacros : this workbook have macros ?
     */
    private bool $has_macros = false;
    /**
     * macrosCode : all macros code as binary data (the vbaProject.bin file, this include form, code,  etc.), null if no macro.
     */
    private ?string $macros_code = null;
    /**
     * macrosCertificate : if macros are signed, contains binary data vbaProjectSignature.bin file, null if not signed.
     */
    private ?string $macros_certificate = null;
    /**
     * ribbonXMLData : null if workbook isn't Excel 2007 or not contain a customized UI.
     *
     * @var null|array{target: string, data: string}
     */
    private ?array $ribbon_xml_data = null;
    /**
     * ribbonBinObjects : null if workbook isn't Excel 2007 or not contain embedded objects (picture(s)) for Ribbon Elements
     * ignored if $ribbonXMLData is null.
     *
     * @var null|mixed[]
     */
    private ?array $ribbon_bin_objects = null;
    /**
     * List of unparsed loaded data for export to same format with better compatibility.
     * It has to be minimized when the library start to support currently unparsed data.
     *
     * @var array<array<array<array<string>|string>>>
     */
    private array $unparsed_loaded_data = [];
    /**
     * Controls visibility of the horizonal scroll bar in the application.
     */
    private bool $show_horizontal_scroll = true;
    /**
     * Controls visibility of the horizonal scroll bar in the application.
     */
    private bool $show_vertical_scroll = true;
    /**
     * Controls visibility of the sheet tabs in the application.
     */
    private bool $show_sheet_tabs = true;
    /**
     * Specifies a boolean value that indicates whether the workbook window
     * is minimized.
     */
    private bool $minimized = false;
    /**
     * Specifies a boolean value that indicates whether to group dates
     * when presenting the user with filtering options in the user
     * interface.
     */
    private bool $auto_filter_date_grouping = true;
    /**
     * Specifies the index to the first sheet in the book view.
     */
    private int $first_sheet_index = 0;
    /**
     * Specifies the visible status of the workbook.
     */
    private string $visibility = self::VISIBILITY_VISIBLE;
    /**
     * Specifies the ratio between the workbook tabs bar and the horizontal
     * scroll bar.  TabRatio is assumed to be out of 1000 of the horizontal
     * window width.
     */
    private int $tab_ratio = 600;
    private readonly Theme $theme;
    private ?I_Value_Binder $value_binder = null;
    /** @var array<string, int> */
    private array $font_charsets = ['B Nazanin' => Shared_Font::CHARSET_ANSI_ARABIC];
    /**
     * @param int $charset uses any value from Shared\Font,
     *    but defaults to ARABIC because that is the only known
     *    charset for which this declaration might be needed
     */
    public function add_font_charset(string $font_name, int $charset = Shared_Font::CHARSET_ANSI_ARABIC): void
    {
        $this->font_charsets[$font_name] = $charset;
    }
    public function get_font_charset(string $font_name): int
    {
        return $this->font_charsets[$font_name] ?? -1;
    }
    /**
     * Return all fontCharsets.
     *
     * @return array<string, int>
     */
    public function get_font_charsets(): array
    {
        return $this->font_charsets;
    }
    /**
     * Returns the workbook theme used for default styles and colour schemes.
     *
     * @return Theme The active theme applied to this workbook
     */
    public function get_theme(): Theme
    {
        return $this->theme;
    }
    /**
     * The workbook has macros ?
     */
    public function has_macros(): bool
    {
        return $this->has_macros;
    }
    /**
     * Define if a workbook has macros.
     *
     * @param bool $hasMacros true|false
     */
    public function set_has_macros(bool $has_macros): void
    {
        $this->has_macros = $has_macros;
    }
    /**
     * Set the macros code.
     */
    public function set_macros_code(?string $macro_code): void
    {
        $this->macros_code = $macro_code;
        $this->set_has_macros($macro_code !== null);
    }
    /**
     * Return the macros code.
     */
    public function get_macros_code(): ?string
    {
        return $this->macros_code;
    }
    /**
     * Set the macros certificate.
     */
    public function set_macros_certificate(?string $certificate): void
    {
        $this->macros_certificate = $certificate;
    }
    /**
     * Is the project signed ?
     *
     * @return bool true|false
     */
    public function has_macros_certificate(): bool
    {
        return $this->macros_certificate !== null;
    }
    /**
     * Return the macros certificate.
     */
    public function get_macros_certificate(): ?string
    {
        return $this->macros_certificate;
    }
    /**
     * Remove all macros, certificate from spreadsheet.
     */
    public function discard_macros(): void
    {
        $this->has_macros = false;
        $this->macros_code = null;
        $this->macros_certificate = null;
    }
    /**
     * set ribbon XML data.
     */
    public function set_ribbon_xml_data(mixed $target, mixed $xml_data): void
    {
        if (is_string($target) && is_string($xml_data)) {
            $this->ribbon_xml_data = ['target' => $target, 'data' => $xml_data];
        } else {
            $this->ribbon_xml_data = null;
        }
    }
    /**
     * retrieve ribbon XML Data.
     *
     * @return mixed[]
     */
    public function get_ribbon_xml_data(string $what = 'all'): null|array|string
    {
        $return_data = null;
        $what = strtolower($what);
        switch ($what) {
            case 'all':
                $return_data = $this->ribbon_xml_data;
                break;
            case 'target':
            case 'data':
                if (is_array($this->ribbon_xml_data)) {
                    $return_data = $this->ribbon_xml_data[$what];
                }
                break;
        }
        return $return_data;
    }
    /**
     * store binaries ribbon objects (pictures).
     */
    public function set_ribbon_bin_objects(mixed $bin_objects_names, mixed $bin_objects_data): void
    {
        if ($bin_objects_names !== null && $bin_objects_data !== null) {
            $this->ribbon_bin_objects = ['names' => $bin_objects_names, 'data' => $bin_objects_data];
        } else {
            $this->ribbon_bin_objects = null;
        }
    }
    /**
     * List of unparsed loaded data for export to same format with better compatibility.
     * It has to be minimized when the library start to support currently unparsed data.
     *
     * @internal
     *
     * @return mixed[]
     */
    public function get_unparsed_loaded_data(): array
    {
        return $this->unparsed_loaded_data;
    }
    /**
     * List of unparsed loaded data for export to same format with better compatibility.
     * It has to be minimized when the library start to support currently unparsed data.
     *
     * @internal
     *
     * @param array<array<array<array<string>|string>>> $unparsedLoadedData
     */
    public function set_unparsed_loaded_data(array $unparsed_loaded_data): void
    {
        $this->unparsed_loaded_data = $unparsed_loaded_data;
    }
    /**
     * retrieve Binaries Ribbon Objects.
     *
     * @return mixed[]
     */
    public function get_ribbon_bin_objects(string $what = 'all'): ?array
    {
        $return_data = null;
        $what = strtolower($what);
        switch ($what) {
            case 'all':
                return $this->ribbon_bin_objects;
            case 'names':
            case 'data':
                if (is_array($this->ribbon_bin_objects) && is_array($this->ribbon_bin_objects[$what] ?? null)) {
                    $return_data = $this->ribbon_bin_objects[$what];
                }
                break;
            case 'types':
                if (is_array($this->ribbon_bin_objects) && isset($this->ribbon_bin_objects['data']) && is_array($this->ribbon_bin_objects['data'])) {
                    $tmp_types = array_keys($this->ribbon_bin_objects['data']);
                    $return_data = array_unique(array_map(fn(string $path): string => pathinfo($path, PATHINFO_EXTENSION), $tmp_types));
                } else {
                    $return_data = [];
                    // the caller want an array... not null if empty
                }
                break;
        }
        return $return_data;
    }
    /**
     * This workbook have a custom UI ?
     */
    public function has_ribbon(): bool
    {
        return $this->ribbon_xml_data !== null;
    }
    /**
     * This workbook have additional object for the ribbon ?
     */
    public function has_ribbon_bin_objects(): bool
    {
        return $this->ribbon_bin_objects !== null;
    }
    /**
     * This workbook has in cell images.
     */
    public function has_in_cell_drawings(): bool
    {
        $sheet_count = $this->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            if ($this->get_sheet($i)->get_in_cell_drawing_collection()->count() > 0) {
                return true;
            }
        }
        return false;
    }
    /**
     * Check if a sheet with a specified code name already exists.
     *
     * @param string $codeName Name of the worksheet to check
     */
    public function sheet_code_name_exists(string $code_name): bool
    {
        return $this->get_sheet_by_code_name($code_name) !== null;
    }
    /**
     * Get sheet by code name. Warning : sheet don't have always a code name !
     *
     * @param string $codeName Sheet name
     */
    public function get_sheet_by_code_name(string $code_name): ?Worksheet
    {
        $worksheet_count = count($this->work_sheet_collection);
        for ($i = 0; $i < $worksheet_count; ++$i) {
            if ($this->work_sheet_collection[$i]->get_code_name() == $code_name) {
                return $this->work_sheet_collection[$i];
            }
        }
        return null;
    }
    /**
     * Create a new PhpSpreadsheet with one Worksheet.
     */
    public function __construct()
    {
        $this->unique_id = uniqid('', true);
        $this->calculation_engine = new Calculation($this);
        $this->theme = new Theme();
        // Initialise worksheet collection and add one worksheet
        $this->work_sheet_collection = [];
        $this->work_sheet_collection[] = new Worksheet($this);
        $this->active_sheet_index = 0;
        // Create document properties
        $this->properties = new Properties();
        // Create document security
        $this->security = new Security();
        // Set defined names
        $this->defined_names = [];
        // Create the cellXf supervisor
        $this->cell_xf_supervisor = new Style(true);
        $this->cell_xf_supervisor->bind_parent($this);
        // Create the default style
        $this->add_cell_xf(new Style());
        $this->add_cell_style_xf(new Style());
    }
    /**
     * Code to execute when this worksheet is unset().
     */
    public function __destruct()
    {
        $this->disconnect_worksheets();
        unset($this->calculation_engine);
        $this->cell_xf_collection = [];
        $this->cell_style_xf_collection = [];
        $this->defined_names = [];
    }
    /**
     * Disconnect all worksheets from this PhpSpreadsheet workbook object,
     * typically so that the PhpSpreadsheet object can be unset.
     */
    public function disconnect_worksheets(): void
    {
        foreach ($this->work_sheet_collection as $worksheet) {
            $worksheet->disconnect_cells();
            unset($worksheet);
        }
        $this->work_sheet_collection = [];
    }
    /**
     * Return the calculation engine for this worksheet.
     */
    public function get_calculation_engine(): Calculation
    {
        return $this->calculation_engine;
    }
    /**
     * Intended for use only via a destructor.
     *
     * @internal
     */
    public function get_calculation_engine_or_null(): ?Calculation
    {
        if (!isset($this->calculation_engine)) {
            //* @phpstan-ignore-line
            return null;
        }
        return $this->calculation_engine;
    }
    /**
     * Get properties.
     */
    public function get_properties(): Properties
    {
        return $this->properties;
    }
    /**
     * Set properties.
     */
    public function set_properties(Properties $document_properties): void
    {
        $this->properties = $document_properties;
    }
    /**
     * Get security.
     */
    public function get_security(): Security
    {
        return $this->security;
    }
    /**
     * Set security.
     */
    public function set_security(Security $document_security): void
    {
        $this->security = $document_security;
    }
    /**
     * Get active sheet.
     */
    public function get_active_sheet(): Worksheet
    {
        return $this->get_sheet($this->active_sheet_index);
    }
    /**
     * Create sheet and add it to this workbook.
     *
     * @param null|int $sheetIndex Index where sheet should go (0,1,..., or null for last)
     */
    public function create_sheet(?int $sheet_index = null): Worksheet
    {
        $new_sheet = new Worksheet($this);
        $this->add_sheet($new_sheet, $sheet_index, true);
        return $new_sheet;
    }
    /**
     * Check if a sheet with a specified name already exists.
     *
     * @param string $worksheetName Name of the worksheet to check
     */
    public function sheet_name_exists(string $worksheet_name): bool
    {
        return $this->get_sheet_by_name($worksheet_name) !== null;
    }
    public function duplicate_worksheet_by_title(string $title): Worksheet
    {
        $original = $this->get_sheet_by_name_or_throw($title);
        $index = $this->get_index($original) + 1;
        $clone = clone $original;
        return $this->add_sheet($clone, $index, true);
    }
    /**
     * Add sheet.
     *
     * @param Worksheet $worksheet The worksheet to add
     * @param null|int $sheetIndex Index where sheet should go (0,1,..., or null for last)
     */
    public function add_sheet(Worksheet $worksheet, ?int $sheet_index = null, bool $retitle_if_needed = false): Worksheet
    {
        if ($retitle_if_needed) {
            $title = $worksheet->get_title();
            if ($this->sheet_name_exists($title)) {
                $i = 1;
                $new_title = "{$title} {$i}";
                while ($this->sheet_name_exists($new_title)) {
                    ++$i;
                    $new_title = "{$title} {$i}";
                }
                $worksheet->set_title($new_title);
            }
        }
        if ($this->sheet_name_exists($worksheet->get_title())) {
            throw new Exception("Workbook already contains a worksheet named '{$worksheet->get_title()}'. Rename this worksheet first.");
        }
        if ($sheet_index === null) {
            if ($this->active_sheet_index < 0) {
                $this->active_sheet_index = 0;
            }
            $this->work_sheet_collection[] = $worksheet;
        } else {
            // Insert the sheet at the requested index
            array_splice($this->work_sheet_collection, $sheet_index, 0, [$worksheet]);
            // Adjust active sheet index if necessary
            if ($this->active_sheet_index >= $sheet_index) {
                ++$this->active_sheet_index;
            }
            if ($this->active_sheet_index < 0) {
                $this->active_sheet_index = 0;
            }
        }
        if ($worksheet->get_parent() === null) {
            $worksheet->rebind_parent($this);
        }
        return $worksheet;
    }
    /**
     * Remove sheet by index.
     *
     * @param int $sheetIndex Index position of the worksheet to remove
     */
    public function remove_sheet_by_index(int $sheet_index): void
    {
        $num_sheets = count($this->work_sheet_collection);
        if ($sheet_index > $num_sheets - 1) {
            throw new Exception("You tried to remove a sheet by the out of bounds index: {$sheet_index}. The actual number of sheets is {$num_sheets}.");
        }
        array_splice($this->work_sheet_collection, $sheet_index, 1);
        // Adjust active sheet index if necessary
        if ($this->active_sheet_index >= $sheet_index && ($this->active_sheet_index > 0 || $num_sheets <= 1)) {
            --$this->active_sheet_index;
        }
    }
    /**
     * Get sheet by index.
     *
     * @param int $sheetIndex Sheet index
     */
    public function get_sheet(int $sheet_index): Worksheet
    {
        if (!isset($this->work_sheet_collection[$sheet_index])) {
            $num_sheets = $this->get_sheet_count();
            throw new Exception("Your requested sheet index: {$sheet_index} is out of bounds. The actual number of sheets is {$num_sheets}.");
        }
        return $this->work_sheet_collection[$sheet_index];
    }
    /**
     * Get all sheets.
     *
     * @return Worksheet[]
     */
    public function get_all_sheets(): array
    {
        return $this->work_sheet_collection;
    }
    /**
     * Get sheet by name.
     *
     * @param string $worksheetName Sheet name
     */
    public function get_sheet_by_name(string $worksheet_name): ?Worksheet
    {
        $trim_worksheet_name = String_Helper::str_to_upper(trim($worksheet_name, "'"));
        foreach ($this->work_sheet_collection as $worksheet) {
            if (String_Helper::str_to_upper($worksheet->get_title()) === $trim_worksheet_name) {
                return $worksheet;
            }
        }
        return null;
    }
    /**
     * Get sheet by name, throwing exception if not found.
     */
    public function get_sheet_by_name_or_throw(string $worksheet_name): Worksheet
    {
        $worksheet = $this->get_sheet_by_name($worksheet_name);
        if ($worksheet === null) {
            throw new Exception("Sheet {$worksheet_name} does not exist.");
        }
        return $worksheet;
    }
    /**
     * Get index for sheet.
     *
     * @return int index
     */
    public function get_index(Worksheet $worksheet, bool $no_throw = false): int
    {
        foreach ($this->work_sheet_collection as $key => $value) {
            if ($value === $worksheet) {
                return $key;
            }
        }
        if ($no_throw) {
            return -1;
        }
        throw new Exception('Sheet does not exist.');
    }
    /**
     * Set index for sheet by sheet name.
     *
     * @param string $worksheetName Sheet name to modify index for
     * @param int $newIndexPosition New index for the sheet
     *
     * @return int New sheet index
     */
    public function set_index_by_name(string $worksheet_name, int $new_index_position): int
    {
        $old_index = $this->get_index($this->get_sheet_by_name_or_throw($worksheet_name));
        $worksheet = array_splice($this->work_sheet_collection, $old_index, 1);
        array_splice($this->work_sheet_collection, $new_index_position, 0, $worksheet);
        return $new_index_position;
    }
    /**
     * Get sheet count.
     */
    public function get_sheet_count(): int
    {
        return count($this->work_sheet_collection);
    }
    /**
     * Get active sheet index.
     *
     * @return int Active sheet index
     */
    public function get_active_sheet_index(): int
    {
        return $this->active_sheet_index;
    }
    /**
     * Set active sheet index.
     *
     * @param int $worksheetIndex Active sheet index
     */
    public function set_active_sheet_index(int $worksheet_index): Worksheet
    {
        $num_sheets = count($this->work_sheet_collection);
        if ($worksheet_index > $num_sheets - 1) {
            throw new Exception("You tried to set a sheet active by the out of bounds index: {$worksheet_index}. The actual number of sheets is {$num_sheets}.");
        }
        $this->active_sheet_index = $worksheet_index;
        return $this->get_active_sheet();
    }
    /**
     * Set active sheet index by name.
     *
     * @param string $worksheetName Sheet title
     */
    public function set_active_sheet_index_by_name(string $worksheet_name): Worksheet
    {
        if (($worksheet = $this->get_sheet_by_name($worksheet_name)) instanceof Worksheet) {
            $this->set_active_sheet_index($this->get_index($worksheet));
            return $worksheet;
        }
        throw new Exception('Workbook does not contain sheet:' . $worksheet_name);
    }
    /**
     * Get sheet names.
     *
     * @return string[]
     */
    public function get_sheet_names(): array
    {
        $return_value = [];
        $worksheet_count = $this->get_sheet_count();
        for ($i = 0; $i < $worksheet_count; ++$i) {
            $return_value[] = $this->get_sheet($i)->get_title();
        }
        return $return_value;
    }
    /**
     * Add external sheet.
     *
     * @param Worksheet $worksheet External sheet to add
     * @param null|int $sheetIndex Index where sheet should go (0,1,..., or null for last)
     */
    public function add_external_sheet(Worksheet $worksheet, ?int $sheet_index = null): Worksheet
    {
        if ($this->sheet_name_exists($worksheet->get_title())) {
            throw new Exception("Workbook already contains a worksheet named '{$worksheet->get_title()}'. Rename the external sheet first.");
        }
        // count how many cellXfs there are in this workbook currently, we will need this below
        $count_cell_xfs = count($this->cell_xf_collection);
        // copy all the shared cellXfs from the external workbook and append them to the current
        foreach ($worksheet->get_parent_or_throw()->get_cell_xf_collection() as $cell_xf) {
            $this->add_cell_xf(clone $cell_xf);
        }
        // move sheet to this workbook
        $worksheet->rebind_parent($this);
        // update the cellXfs
        foreach ($worksheet->get_coordinates(false) as $coordinate) {
            $cell = $worksheet->get_cell($coordinate);
            $cell->set_xf_index($cell->get_xf_index() + $count_cell_xfs);
        }
        // update the column dimensions Xfs
        foreach ($worksheet->get_column_dimensions() as $column_dimension) {
            $column_dimension->set_xf_index($column_dimension->get_xf_index() + $count_cell_xfs);
        }
        // update the row dimensions Xfs
        foreach ($worksheet->get_row_dimensions() as $row_dimension) {
            $xf_index = $row_dimension->get_xf_index();
            if ($xf_index !== null) {
                $row_dimension->set_xf_index($xf_index + $count_cell_xfs);
            }
        }
        return $this->add_sheet($worksheet, $sheet_index);
    }
    /**
     * Get an array of all Named Ranges.
     *
     * @return DefinedName[]
     */
    public function get_named_ranges(): array
    {
        return array_filter($this->defined_names, fn(Defined_Name $defined_name): bool => $defined_name->is_formula() === self::DEFINED_NAME_IS_RANGE);
    }
    /**
     * Get an array of all Named Formulae.
     *
     * @return DefinedName[]
     */
    public function get_named_formulae(): array
    {
        return array_filter($this->defined_names, fn(Defined_Name $defined_name): bool => $defined_name->is_formula() === self::DEFINED_NAME_IS_FORMULA);
    }
    /**
     * Get an array of all Defined Names (both named ranges and named formulae).
     *
     * @return DefinedName[]
     */
    public function get_defined_names(): array
    {
        return $this->defined_names;
    }
    /**
     * Add a named range.
     * If a named range with this name already exists, then this will replace the existing value.
     */
    public function add_named_range(Named_Range $named_range): void
    {
        $this->add_defined_name($named_range);
    }
    /**
     * Add a named formula.
     * If a named formula with this name already exists, then this will replace the existing value.
     */
    public function add_named_formula(Named_Formula $named_formula): void
    {
        $this->add_defined_name($named_formula);
    }
    /**
     * Add a defined name (either a named range or a named formula).
     * If a defined named with this name already exists, then this will replace the existing value.
     */
    public function add_defined_name(Defined_Name $defined_name): void
    {
        $upper_case_name = String_Helper::str_to_upper($defined_name->get_name());
        if ($defined_name->get_scope() == null) {
            // global scope
            $this->defined_names[$upper_case_name] = $defined_name;
        } else {
            // local scope
            $this->defined_names[$defined_name->get_scope()->get_title() . '!' . $upper_case_name] = $defined_name;
        }
    }
    /**
     * Get named range.
     *
     * @param null|Worksheet $worksheet Scope. Use null for global scope
     */
    public function get_named_range(string $named_range, ?Worksheet $worksheet = null): ?Named_Range
    {
        $return_value = null;
        if ($named_range !== '') {
            $named_range = String_Helper::str_to_upper($named_range);
            // first look for global named range
            $return_value = $this->get_global_defined_name_by_type($named_range, self::DEFINED_NAME_IS_RANGE);
            // then look for local named range (has priority over global named range if both names exist)
            $return_value = $this->get_local_defined_name_by_type($named_range, self::DEFINED_NAME_IS_RANGE, $worksheet) ?: $return_value;
        }
        return $return_value instanceof Named_Range ? $return_value : null;
    }
    /**
     * Get named formula.
     *
     * @param null|Worksheet $worksheet Scope. Use null for global scope
     */
    public function get_named_formula(string $named_formula, ?Worksheet $worksheet = null): ?Named_Formula
    {
        $return_value = null;
        if ($named_formula !== '') {
            $named_formula = String_Helper::str_to_upper($named_formula);
            // first look for global named formula
            $return_value = $this->get_global_defined_name_by_type($named_formula, self::DEFINED_NAME_IS_FORMULA);
            // then look for local named formula (has priority over global named formula if both names exist)
            $return_value = $this->get_local_defined_name_by_type($named_formula, self::DEFINED_NAME_IS_FORMULA, $worksheet) ?: $return_value;
        }
        return $return_value instanceof Named_Formula ? $return_value : null;
    }
    private function get_global_defined_name_by_type(string $name, bool $type): ?Defined_Name
    {
        if (isset($this->defined_names[$name]) && $this->defined_names[$name]->is_formula() === $type) {
            return $this->defined_names[$name];
        }
        return null;
    }
    private function get_local_defined_name_by_type(string $name, bool $type, ?Worksheet $worksheet = null): ?Defined_Name
    {
        if ($worksheet !== null && isset($this->defined_names[$worksheet->get_title() . '!' . $name]) && $this->defined_names[$worksheet->get_title() . '!' . $name]->is_formula() === $type) {
            return $this->defined_names[$worksheet->get_title() . '!' . $name];
        }
        return null;
    }
    /**
     * Get named range.
     *
     * @param null|Worksheet $worksheet Scope. Use null for global scope
     */
    public function get_defined_name(string $defined_name, ?Worksheet $worksheet = null): ?Defined_Name
    {
        $return_value = null;
        if ($defined_name !== '') {
            $defined_name = String_Helper::str_to_upper($defined_name);
            // first look for global defined name
            foreach ($this->defined_names as $dn) {
                $upper = String_Helper::str_to_upper($dn->get_name());
                if (!$dn->get_local_only() && $defined_name === $upper) {
                    $return_value = $dn;
                    break;
                }
            }
            // then look for local defined name (has priority over global defined name if both names exist)
            if ($worksheet !== null) {
                $ws_title = String_Helper::str_to_upper($worksheet->get_title());
                $defined_name = Preg::replace('/^.*!/', '', $defined_name);
                foreach ($this->defined_names as $dn) {
                    $sheet = $dn->get_scope() ?? $dn->get_worksheet();
                    $upper = String_Helper::str_to_upper($dn->get_name());
                    $upper_title = String_Helper::str_to_upper((string) $sheet?->get_title());
                    if ($dn->get_local_only() && $upper === $defined_name && $upper_title === $ws_title) {
                        return $dn;
                    }
                }
            }
        }
        return $return_value;
    }
    /**
     * Remove named range.
     *
     * @param null|Worksheet $worksheet scope: use null for global scope
     *
     * @return $this
     */
    public function remove_named_range(string $named_range, ?Worksheet $worksheet = null): self
    {
        if ($this->get_named_range($named_range, $worksheet) === null) {
            return $this;
        }
        return $this->remove_defined_name($named_range, $worksheet);
    }
    /**
     * Remove named formula.
     *
     * @param null|Worksheet $worksheet scope: use null for global scope
     *
     * @return $this
     */
    public function remove_named_formula(string $named_formula, ?Worksheet $worksheet = null): self
    {
        if ($this->get_named_formula($named_formula, $worksheet) === null) {
            return $this;
        }
        return $this->remove_defined_name($named_formula, $worksheet);
    }
    /**
     * Remove defined name.
     *
     * @param null|Worksheet $worksheet scope: use null for global scope
     *
     * @return $this
     */
    public function remove_defined_name(string $defined_name, ?Worksheet $worksheet = null): self
    {
        $defined_name = String_Helper::str_to_upper($defined_name);
        if ($worksheet === null) {
            if (isset($this->defined_names[$defined_name])) {
                unset($this->defined_names[$defined_name]);
            }
        } else if (isset($this->defined_names[$worksheet->get_title() . '!' . $defined_name])) {
            unset($this->defined_names[$worksheet->get_title() . '!' . $defined_name]);
        } elseif (isset($this->defined_names[$defined_name])) {
            unset($this->defined_names[$defined_name]);
        }
        return $this;
    }
    /**
     * Get worksheet iterator.
     */
    public function get_worksheet_iterator(): Iterator
    {
        return new Iterator($this);
    }
    /**
     * Copy workbook (!= clone!).
     */
    public function copy(): self
    {
        return unserialize(serialize($this));
        //* @phpstan-ignore-line
    }
    /**
     * Implement PHP __clone to create a deep clone, not just a shallow copy.
     */
    public function __clone()
    {
        $this->unique_id = uniqid('', true);
        $used_keys = [];
        // I don't know why new Style rather than clone.
        $this->cell_xf_supervisor = new Style(true);
        //$this->cellXfSupervisor = clone $this->cellXfSupervisor;
        $this->cell_xf_supervisor->bind_parent($this);
        $used_keys['cellXfSupervisor'] = true;
        $old_calc = $this->calculation_engine;
        $this->calculation_engine = new Calculation($this);
        $this->calculation_engine->set_suppress_formula_errors($old_calc->get_suppress_formula_errors())->set_calculation_cache_enabled($old_calc->get_calculation_cache_enabled())->set_branch_pruning_enabled($old_calc->get_branch_pruning_enabled())->set_instance_array_return_type($old_calc->get_instance_array_return_type());
        $used_keys['calculationEngine'] = true;
        $current_collection = $this->cell_style_xf_collection;
        $this->cell_style_xf_collection = [];
        foreach ($current_collection as $item) {
            $clone = $item->export_array();
            $style = (new Style())->apply_from_array($clone);
            $this->add_cell_style_xf($style);
        }
        $used_keys['cellStyleXfCollection'] = true;
        $current_collection = $this->cell_xf_collection;
        $this->cell_xf_collection = [];
        foreach ($current_collection as $item) {
            $clone = $item->export_array();
            $style = (new Style())->apply_from_array($clone);
            $this->add_cell_xf($style);
        }
        $used_keys['cellXfCollection'] = true;
        $current_collection = $this->work_sheet_collection;
        $this->work_sheet_collection = [];
        foreach ($current_collection as $item) {
            $clone = clone $item;
            $clone->set_parent($this);
            $this->work_sheet_collection[] = $clone;
        }
        $used_keys['workSheetCollection'] = true;
        foreach (get_object_vars($this) as $key => $val) {
            if (isset($used_keys[$key])) {
                continue;
            }
            switch ($key) {
                // arrays of objects not covered above
                case 'definedNames':
                    /** @var DefinedName[] */
                    $current_collection = $val;
                    $this->{$key} = [];
                    foreach ($current_collection as $item) {
                        $clone = clone $item;
                        $title = $clone->get_worksheet()?->get_title();
                        if ($title !== null) {
                            $ws = $this->get_sheet_by_name($title);
                            $clone->set_worksheet($ws);
                        }
                        $title = $clone->get_scope()?->get_title();
                        if ($title !== null) {
                            $ws = $this->get_sheet_by_name($title);
                            $clone->set_scope($ws);
                        }
                        $this->{$key}[] = $clone;
                    }
                    break;
                default:
                    if (is_object($val)) {
                        $this->{$key} = clone $val;
                    }
            }
        }
    }
    /**
     * Get the workbook collection of cellXfs.
     *
     * @return Style[]
     */
    public function get_cell_xf_collection(): array
    {
        return $this->cell_xf_collection;
    }
    /**
     * Get cellXf by index.
     */
    public function get_cell_xf_by_index(int $cell_style_index): Style
    {
        return $this->cell_xf_collection[$cell_style_index];
    }
    public function get_cell_xf_by_index_or_null(?int $cell_style_index): ?Style
    {
        return $cell_style_index === null ? null : $this->cell_xf_collection[$cell_style_index] ?? null;
    }
    /**
     * Get cellXf by hash code.
     *
     * @return false|Style
     */
    public function get_cell_xf_by_hash_code(string $hashcode): bool|Style
    {
        foreach ($this->cell_xf_collection as $cell_xf) {
            if ($cell_xf->get_hash_code() === $hashcode) {
                return $cell_xf;
            }
        }
        return false;
    }
    /**
     * Check if style exists in style collection.
     */
    public function cell_xf_exists(Style $cell_style_index): bool
    {
        return in_array($cell_style_index, $this->cell_xf_collection, true);
    }
    /**
     * Get default style.
     */
    public function get_default_style(): Style
    {
        if (isset($this->cell_xf_collection[0])) {
            return $this->cell_xf_collection[0];
        }
        throw new Exception('No default style found for this workbook');
    }
    /**
     * Add a cellXf to the workbook.
     */
    public function add_cell_xf(Style $style): void
    {
        $this->cell_xf_collection[] = $style;
        $style->set_index(count($this->cell_xf_collection) - 1);
    }
    /**
     * Remove cellXf by index. It is ensured that all cells get their xf index updated.
     *
     * @param int $cellStyleIndex Index to cellXf
     */
    public function remove_cell_xf_by_index(int $cell_style_index): void
    {
        if ($cell_style_index > count($this->cell_xf_collection) - 1) {
            throw new Exception('CellXf index is out of bounds.');
        }
        // first remove the cellXf
        array_splice($this->cell_xf_collection, $cell_style_index, 1);
        // then update cellXf indexes for cells
        foreach ($this->work_sheet_collection as $worksheet) {
            foreach ($worksheet->get_coordinates(false) as $coordinate) {
                $cell = $worksheet->get_cell($coordinate);
                $xf_index = $cell->get_xf_index();
                if ($xf_index > $cell_style_index) {
                    // decrease xf index by 1
                    $cell->set_xf_index($xf_index - 1);
                } elseif ($xf_index == $cell_style_index) {
                    // set to default xf index 0
                    $cell->set_xf_index(0);
                }
            }
        }
    }
    /**
     * Get the cellXf supervisor.
     */
    public function get_cell_xf_supervisor(): Style
    {
        return $this->cell_xf_supervisor;
    }
    /**
     * Get the workbook collection of cellStyleXfs.
     *
     * @return Style[]
     */
    public function get_cell_style_xf_collection(): array
    {
        return $this->cell_style_xf_collection;
    }
    /**
     * Get cellStyleXf by index.
     *
     * @param int $cellStyleIndex Index to cellXf
     */
    public function get_cell_style_xf_by_index(int $cell_style_index): Style
    {
        return $this->cell_style_xf_collection[$cell_style_index];
    }
    /**
     * Get cellStyleXf by hash code.
     *
     * @return false|Style
     */
    public function get_cell_style_xf_by_hash_code(string $hashcode): bool|Style
    {
        foreach ($this->cell_style_xf_collection as $cell_style_xf) {
            if ($cell_style_xf->get_hash_code() === $hashcode) {
                return $cell_style_xf;
            }
        }
        return false;
    }
    /**
     * Add a cellStyleXf to the workbook.
     */
    public function add_cell_style_xf(Style $style): void
    {
        $this->cell_style_xf_collection[] = $style;
        $style->set_index(count($this->cell_style_xf_collection) - 1);
    }
    /**
     * Remove cellStyleXf by index.
     *
     * @param int $cellStyleIndex Index to cellXf
     */
    public function remove_cell_style_xf_by_index(int $cell_style_index): void
    {
        if ($cell_style_index > count($this->cell_style_xf_collection) - 1) {
            throw new Exception('CellStyleXf index is out of bounds.');
        }
        array_splice($this->cell_style_xf_collection, $cell_style_index, 1);
    }
    /**
     * Eliminate all unneeded cellXf and afterwards update the xfIndex for all cells
     * and columns in the workbook.
     */
    public function garbage_collect(): void
    {
        // how many references are there to each cellXf ?
        $count_references_cell_xf = [];
        foreach ($this->cell_xf_collection as $index => $cell_xf) {
            $count_references_cell_xf[$index] = 0;
        }
        foreach ($this->get_worksheet_iterator() as $sheet) {
            // from cells
            foreach ($sheet->get_coordinates(false) as $coordinate) {
                $cell = $sheet->get_cell($coordinate);
                ++$count_references_cell_xf[$cell->get_xf_index()];
            }
            // from row dimensions
            foreach ($sheet->get_row_dimensions() as $row_dimension) {
                if ($row_dimension->get_xf_index() !== null) {
                    ++$count_references_cell_xf[$row_dimension->get_xf_index()];
                }
            }
            // from column dimensions
            foreach ($sheet->get_column_dimensions() as $column_dimension) {
                ++$count_references_cell_xf[$column_dimension->get_xf_index()];
            }
        }
        // remove cellXfs without references and create mapping so we can update xfIndex
        // for all cells and columns
        $count_needed_cell_xfs = 0;
        $map = [];
        foreach ($this->cell_xf_collection as $index => $cell_xf) {
            if ($count_references_cell_xf[$index] > 0 || $index == 0) {
                // we must never remove the first cellXf
                ++$count_needed_cell_xfs;
            } else {
                unset($this->cell_xf_collection[$index]);
            }
            $map[$index] = $count_needed_cell_xfs - 1;
        }
        $this->cell_xf_collection = array_values($this->cell_xf_collection);
        // update the index for all cellXfs
        foreach ($this->cell_xf_collection as $i => $cell_xf) {
            $cell_xf->set_index($i);
        }
        // make sure there is always at least one cellXf (there should be)
        if (empty($this->cell_xf_collection)) {
            $this->cell_xf_collection[] = new Style();
        }
        // update the xfIndex for all cells, row dimensions, column dimensions
        foreach ($this->get_worksheet_iterator() as $sheet) {
            // for all cells
            foreach ($sheet->get_coordinates(false) as $coordinate) {
                $cell = $sheet->get_cell($coordinate);
                $cell->set_xf_index($map[$cell->get_xf_index()]);
            }
            // for all row dimensions
            foreach ($sheet->get_row_dimensions() as $row_dimension) {
                if ($row_dimension->get_xf_index() !== null) {
                    $row_dimension->set_xf_index($map[$row_dimension->get_xf_index()]);
                }
            }
            // for all column dimensions
            foreach ($sheet->get_column_dimensions() as $column_dimension) {
                $column_dimension->set_xf_index($map[$column_dimension->get_xf_index()]);
            }
            // also do garbage collection for all the sheets
            $sheet->garbage_collect();
        }
    }
    /**
     * Return the unique ID value assigned to this spreadsheet workbook.
     *
     * @deprecated 5.2.0 Serves no useful purpose. No replacement.
     *
     * @codeCoverageIgnore
     */
    public function get_id(): string
    {
        return $this->unique_id;
    }
    /**
     * Get the visibility of the horizonal scroll bar in the application.
     *
     * @return bool True if horizonal scroll bar is visible
     */
    public function get_show_horizontal_scroll(): bool
    {
        return $this->show_horizontal_scroll;
    }
    /**
     * Set the visibility of the horizonal scroll bar in the application.
     *
     * @param bool $showHorizontalScroll True if horizonal scroll bar is visible
     */
    public function set_show_horizontal_scroll(bool $show_horizontal_scroll): void
    {
        $this->show_horizontal_scroll = $show_horizontal_scroll;
    }
    /**
     * Get the visibility of the vertical scroll bar in the application.
     *
     * @return bool True if vertical scroll bar is visible
     */
    public function get_show_vertical_scroll(): bool
    {
        return $this->show_vertical_scroll;
    }
    /**
     * Set the visibility of the vertical scroll bar in the application.
     *
     * @param bool $showVerticalScroll True if vertical scroll bar is visible
     */
    public function set_show_vertical_scroll(bool $show_vertical_scroll): void
    {
        $this->show_vertical_scroll = $show_vertical_scroll;
    }
    /**
     * Get the visibility of the sheet tabs in the application.
     *
     * @return bool True if the sheet tabs are visible
     */
    public function get_show_sheet_tabs(): bool
    {
        return $this->show_sheet_tabs;
    }
    /**
     * Set the visibility of the sheet tabs  in the application.
     *
     * @param bool $showSheetTabs True if sheet tabs are visible
     */
    public function set_show_sheet_tabs(bool $show_sheet_tabs): void
    {
        $this->show_sheet_tabs = $show_sheet_tabs;
    }
    /**
     * Return whether the workbook window is minimized.
     *
     * @return bool true if workbook window is minimized
     */
    public function get_minimized(): bool
    {
        return $this->minimized;
    }
    /**
     * Set whether the workbook window is minimized.
     *
     * @param bool $minimized true if workbook window is minimized
     */
    public function set_minimized(bool $minimized): void
    {
        $this->minimized = $minimized;
    }
    /**
     * Return whether to group dates when presenting the user with
     * filtering options in the user interface.
     *
     * @return bool true if workbook window is minimized
     */
    public function get_auto_filter_date_grouping(): bool
    {
        return $this->auto_filter_date_grouping;
    }
    /**
     * Set whether to group dates when presenting the user with
     * filtering options in the user interface.
     *
     * @param bool $autoFilterDateGrouping true if workbook window is minimized
     */
    public function set_auto_filter_date_grouping(bool $auto_filter_date_grouping): void
    {
        $this->auto_filter_date_grouping = $auto_filter_date_grouping;
    }
    /**
     * Return the first sheet in the book view.
     *
     * @return int First sheet in book view
     */
    public function get_first_sheet_index(): int
    {
        return $this->first_sheet_index;
    }
    /**
     * Set the first sheet in the book view.
     *
     * @param int $firstSheetIndex First sheet in book view
     */
    public function set_first_sheet_index(int $first_sheet_index): void
    {
        if ($first_sheet_index >= 0) {
            $this->first_sheet_index = $first_sheet_index;
        } else {
            throw new Exception('First sheet index must be a positive integer.');
        }
    }
    /**
     * Return the visibility status of the workbook.
     *
     * This may be one of the following three values:
     * - visibile
     *
     * @return string Visible status
     */
    public function get_visibility(): string
    {
        return $this->visibility;
    }
    /**
     * Set the visibility status of the workbook.
     *
     * Valid values are:
     *  - 'visible' (self::VISIBILITY_VISIBLE):
     *       Workbook window is visible
     *  - 'hidden' (self::VISIBILITY_HIDDEN):
     *       Workbook window is hidden, but can be shown by the user
     *       via the user interface
     *  - 'veryHidden' (self::VISIBILITY_VERY_HIDDEN):
     *       Workbook window is hidden and cannot be shown in the
     *       user interface.
     *
     * @param null|string $visibility visibility status of the workbook
     */
    public function set_visibility(?string $visibility): void
    {
        if ($visibility === null) {
            $visibility = self::VISIBILITY_VISIBLE;
        }
        if (in_array($visibility, self::WORKBOOK_VIEW_VISIBILITY_VALUES)) {
            $this->visibility = $visibility;
        } else {
            throw new Exception('Invalid visibility value.');
        }
    }
    /**
     * Get the ratio between the workbook tabs bar and the horizontal scroll bar.
     * TabRatio is assumed to be out of 1000 of the horizontal window width.
     *
     * @return int Ratio between the workbook tabs bar and the horizontal scroll bar
     */
    public function get_tab_ratio(): int
    {
        return $this->tab_ratio;
    }
    /**
     * Set the ratio between the workbook tabs bar and the horizontal scroll bar
     * TabRatio is assumed to be out of 1000 of the horizontal window width.
     *
     * @param int $tabRatio Ratio between the tabs bar and the horizontal scroll bar
     */
    public function set_tab_ratio(int $tab_ratio): void
    {
        if ($tab_ratio >= 0 && $tab_ratio <= 1000) {
            $this->tab_ratio = $tab_ratio;
        } else {
            throw new Exception('Tab ratio must be between 0 and 1000.');
        }
    }
    public function reevaluate_auto_filters(bool $reset_to_max): void
    {
        foreach ($this->work_sheet_collection as $sheet) {
            $filter = $sheet->get_auto_filter();
            if (!empty($filter->get_range())) {
                if ($reset_to_max) {
                    $filter->set_range_to_max_row();
                }
                $filter->show_hide_rows();
            }
        }
    }
    /**
     * @throws Exception
     */
    public function jsonSerialize(): mixed
    {
        throw new Exception('Spreadsheet objects cannot be json encoded');
    }
    public function reset_theme_fonts(): void
    {
        $major_font_latin = $this->theme->get_major_font_latin();
        $minor_font_latin = $this->theme->get_minor_font_latin();
        foreach ($this->cell_xf_collection as $cell_style_xf) {
            $scheme = $cell_style_xf->get_font()->get_scheme();
            if ($scheme === 'major') {
                $cell_style_xf->get_font()->set_name($major_font_latin)->set_scheme($scheme);
            } elseif ($scheme === 'minor') {
                $cell_style_xf->get_font()->set_name($minor_font_latin)->set_scheme($scheme);
            }
        }
        foreach ($this->cell_style_xf_collection as $cell_style_xf) {
            $scheme = $cell_style_xf->get_font()->get_scheme();
            if ($scheme === 'major') {
                $cell_style_xf->get_font()->set_name($major_font_latin)->set_scheme($scheme);
            } elseif ($scheme === 'minor') {
                $cell_style_xf->get_font()->set_name($minor_font_latin)->set_scheme($scheme);
            }
        }
    }
    public function get_table_by_name(string $table_name): ?Table
    {
        $table = null;
        foreach ($this->work_sheet_collection as $sheet) {
            $table = $sheet->get_table_by_name($table_name);
            if ($table !== null) {
                break;
            }
        }
        return $table;
    }
    /**
     * @return bool Success or failure
     */
    public function set_excel_calendar(int $base_year): bool
    {
        if ($base_year === Date::CALENDAR_WINDOWS_1900 || $base_year === Date::CALENDAR_MAC_1904) {
            $this->excel_calendar = $base_year;
            return true;
        }
        return false;
    }
    /**
     * @return int Excel base date (1900 or 1904)
     */
    public function get_excel_calendar(): int
    {
        return $this->excel_calendar;
    }
    public function delete_legacy_drawing(Worksheet $worksheet): void
    {
        unset($this->unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['legacyDrawing']);
    }
    public function get_legacy_drawing(Worksheet $worksheet): ?string
    {
        /** @var ?string */
        $temp = $this->unparsed_loaded_data['sheets'][$worksheet->get_code_name()]['legacyDrawing'] ?? null;
        return $temp;
    }
    public function get_value_binder(): ?I_Value_Binder
    {
        return $this->value_binder;
    }
    public function set_value_binder(?I_Value_Binder $value_binder): self
    {
        $this->value_binder = $value_binder;
        return $this;
    }
    /**
     * All the PDF writers treat charts as if they occupy a single cell.
     * This will be better most of the time.
     * It is not needed for any other output type.
     * It changes the contents of the spreadsheet, so you might
     * be better off cloning the spreadsheet and then using
     * this method on, and then writing, the clone.
     */
    public function merge_chart_cells_for_pdf(): void
    {
        foreach ($this->work_sheet_collection as $worksheet) {
            foreach ($worksheet->get_chart_collection() as $chart) {
                $br = $chart->get_bottom_right_cell();
                $tl = $chart->get_top_left_cell();
                if ($br !== '' && $br !== $tl) {
                    if (!$worksheet->cell_exists($br)) {
                        $worksheet->get_cell($br)->set_value(' ');
                    }
                    $worksheet->merge_cells("{$tl}:{$br}");
                }
            }
        }
    }
    /**
     * All the PDF writers do better with drawings than charts.
     * This will be better some of the time.
     * It is not needed for any other output type.
     * It changes the contents of the spreadsheet, so you might
     * be better off cloning the spreadsheet and then using
     * this method on, and then writing, the clone.
     */
    public function merge_drawing_cells_for_pdf(): void
    {
        foreach ($this->work_sheet_collection as $worksheet) {
            foreach ($worksheet->get_drawing_collection() as $drawing) {
                $br = $drawing->get_coordinates2();
                $tl = $drawing->get_coordinates();
                if ($br !== '' && $br !== $tl) {
                    if (!$worksheet->cell_exists($br)) {
                        $worksheet->get_cell($br)->set_value(' ');
                    }
                    $worksheet->merge_cells("{$tl}:{$br}");
                }
            }
        }
    }
    /**
     * Excel will sometimes replace user's formatting choice
     * with a built-in choice that it thinks is equivalent.
     * Its choice is often not equivalent after all.
     * Such treatment is astonishingly user-hostile.
     * This function will undo such changes.
     */
    public function replace_builtin_number_format(int $builtin_format_index, string $format_code): void
    {
        foreach ($this->cell_xf_collection as $style) {
            $number_format = $style->get_number_format();
            if ($number_format->get_built_in_format_code() === $builtin_format_index) {
                $number_format->set_format_code($format_code);
            }
        }
    }
    /**
     * Change all 2-digit-year date styles to use 4-digit year;
     * change all dd-mm-yyyy and mm-dd-yyyy styles to yyyy-mm-dd;
     * dd-mmm-yyyy is unambiguous and left unchanged.
     */
    public function disambiguate_date_styles(): void
    {
        foreach ($this->cell_xf_collection as $style) {
            $number_format = $style->get_number_format();
            $old_format = (string) $number_format->get_format_code();
            $new_format = Preg::replace('/\byy\b/i', 'yyyy', $old_format);
            $new_format = Preg::replace('~\bdd?(-|/|"-"|"/")' . 'mm?(-|/|"-"|"/")' . 'yyyy~', 'yyyy-mm-dd', $new_format);
            $new_format = Preg::replace('~\bmm?(-|/|"-"|"/")' . 'dd?(-|/|"-"|"/")' . 'yyyy~', 'yyyy-mm-dd', $new_format);
            if ($new_format !== $old_format) {
                $number_format->set_format_code($new_format);
            }
        }
    }
    public function return_array_as_array(): void
    {
        $this->calculation_engine->set_instance_array_return_type(Calculation::RETURN_ARRAY_AS_ARRAY);
    }
    public function return_array_as_value(): void
    {
        $this->calculation_engine->set_instance_array_return_type(Calculation::RETURN_ARRAY_AS_VALUE);
    }
    /** @var string[] */
    private $domain_white_list = [];
    /**
     * Currently used only by WEBSERVICE function.
     *
     * @param string[] $domainWhiteList
     */
    public function set_domain_white_list(array $domain_white_list): self
    {
        $this->domain_white_list = $domain_white_list;
        return $this;
    }
    /** @return string[] */
    public function get_domain_white_list(): array
    {
        return $this->domain_white_list;
    }
    private bool $uses_check_box_style = false;
    public function get_uses_check_box_style(): bool
    {
        return $this->uses_check_box_style;
    }
    public function set_uses_check_box_style(): bool
    {
        $this->uses_check_box_style = false;
        foreach ($this->get_cell_xf_collection() as $cell_xf) {
            if ($cell_xf->get_check_box()) {
                $this->uses_check_box_style = true;
                break;
            }
        }
        return $this->uses_check_box_style;
    }
}