<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet;

class Theme
{
    private string $theme_color_name = 'Office';
    private string $theme_font_name = 'Office';
    public const HYPERLINK_THEME = 10;
    public const COLOR_SCHEME_2013_2022_NAME = 'Office 2013-2022';
    public const COLOR_SCHEME_2013_2022 = ['dk1' => '000000', 'lt1' => 'FFFFFF', 'dk2' => '44546A', 'lt2' => 'E7E6E6', 'accent1' => '4472C4', 'accent2' => 'ED7D31', 'accent3' => 'A5A5A5', 'accent4' => 'FFC000', 'accent5' => '5B9BD5', 'accent6' => '70AD47', 'hlink' => '0563C1', 'folHlink' => '954F72'];
    private const COLOR_SCHEME_2013_PLUS_NAME = 'Office 2013+';
    public const COLOR_SCHEME_2007_2010_NAME = 'Office 2007-2010';
    public const COLOR_SCHEME_2007_2010 = ['dk1' => '000000', 'lt1' => 'FFFFFF', 'dk2' => '1F497D', 'lt2' => 'EEECE1', 'accent1' => '4F81BD', 'accent2' => 'C0504D', 'accent3' => '9BBB59', 'accent4' => '8064A2', 'accent5' => '4BACC6', 'accent6' => 'F79646', 'hlink' => '0000FF', 'folHlink' => '800080'];
    public const COLOR_SCHEME_2023_PLUS_NAME = 'Office 2023+';
    public const COLOR_SCHEME_2023_PLUS = ['dk1' => '000000', 'lt1' => 'FFFFFF', 'dk2' => '0E2841', 'lt2' => 'E8E8E8', 'accent1' => '156082', 'accent2' => 'E97132', 'accent3' => '196B24', 'accent4' => '0F9ED5', 'accent5' => 'A02B93', 'accent6' => '4EA72E', 'hlink' => '467886', 'folHlink' => '96607D'];
    /** @var string[] */
    private array $theme_colors = self::COLOR_SCHEME_2007_2010;
    private string $major_font_latin = 'Cambria';
    private string $major_font_east_asian = '';
    private string $major_font_complex_script = '';
    private string $minor_font_latin = 'Calibri';
    private string $minor_font_east_asian = '';
    private string $minor_font_complex_script = '';
    /**
     * Map of Major (header) fonts to write.
     *
     * @var string[]
     */
    private array $major_font_substitutions = self::FONTS_TIMES_SUBSTITUTIONS;
    /**
     * Map of Minor (body) fonts to write.
     *
     * @var string[]
     */
    private array $minor_font_substitutions = self::FONTS_ARIAL_SUBSTITUTIONS;
    public const FONTS_TIMES_SUBSTITUTIONS = ['Jpan' => 'ＭＳ Ｐゴシック', 'Hang' => '맑은 고딕', 'Hans' => '宋体', 'Hant' => '新細明體', 'Arab' => 'Times New Roman', 'Hebr' => 'Times New Roman', 'Thai' => 'Tahoma', 'Ethi' => 'Nyala', 'Beng' => 'Vrinda', 'Gujr' => 'Shruti', 'Khmr' => 'MoolBoran', 'Knda' => 'Tunga', 'Guru' => 'Raavi', 'Cans' => 'Euphemia', 'Cher' => 'Plantagenet Cherokee', 'Yiii' => 'Microsoft Yi Baiti', 'Tibt' => 'Microsoft Himalaya', 'Thaa' => 'MV Boli', 'Deva' => 'Mangal', 'Telu' => 'Gautami', 'Taml' => 'Latha', 'Syrc' => 'Estrangelo Edessa', 'Orya' => 'Kalinga', 'Mlym' => 'Kartika', 'Laoo' => 'DokChampa', 'Sinh' => 'Iskoola Pota', 'Mong' => 'Mongolian Baiti', 'Viet' => 'Times New Roman', 'Uigh' => 'Microsoft Uighur', 'Geor' => 'Sylfaen'];
    public const FONTS_ARIAL_SUBSTITUTIONS = ['Jpan' => 'ＭＳ Ｐゴシック', 'Hang' => '맑은 고딕', 'Hans' => '宋体', 'Hant' => '新細明體', 'Arab' => 'Arial', 'Hebr' => 'Arial', 'Thai' => 'Tahoma', 'Ethi' => 'Nyala', 'Beng' => 'Vrinda', 'Gujr' => 'Shruti', 'Khmr' => 'DaunPenh', 'Knda' => 'Tunga', 'Guru' => 'Raavi', 'Cans' => 'Euphemia', 'Cher' => 'Plantagenet Cherokee', 'Yiii' => 'Microsoft Yi Baiti', 'Tibt' => 'Microsoft Himalaya', 'Thaa' => 'MV Boli', 'Deva' => 'Mangal', 'Telu' => 'Gautami', 'Taml' => 'Latha', 'Syrc' => 'Estrangelo Edessa', 'Orya' => 'Kalinga', 'Mlym' => 'Kartika', 'Laoo' => 'DokChampa', 'Sinh' => 'Iskoola Pota', 'Mong' => 'Mongolian Baiti', 'Viet' => 'Arial', 'Uigh' => 'Microsoft Uighur', 'Geor' => 'Sylfaen'];
    /** @return string[] */
    public function get_theme_colors(): array
    {
        return $this->theme_colors;
    }
    public function set_theme_color(string $key, string $value): self
    {
        $this->theme_colors[$key] = $value;
        return $this;
    }
    public function get_theme_color_name(): string
    {
        return $this->theme_color_name;
    }
    /** @param null|string[] $themeColors */
    public function set_theme_color_name(string $name, ?array $theme_colors = null, ?Spreadsheet $spreadsheet = null): self
    {
        if ($name === self::COLOR_SCHEME_2013_PLUS_NAME) {
            // Ensure against this value being found in
            // spreadsheets created while constant was public.
            $name = self::COLOR_SCHEME_2013_2022_NAME;
        }
        $this->theme_color_name = $name;
        if ($name === self::COLOR_SCHEME_2007_2010_NAME) {
            $theme_colors ??= self::COLOR_SCHEME_2007_2010;
            $this->major_font_latin = 'Cambria';
            $this->minor_font_latin = 'Calibri';
        } elseif ($name === self::COLOR_SCHEME_2013_2022_NAME) {
            $theme_colors ??= self::COLOR_SCHEME_2013_2022;
            $this->major_font_latin = 'Calibri Light';
            $this->minor_font_latin = 'Calibri';
        } elseif ($name === self::COLOR_SCHEME_2023_PLUS_NAME) {
            $theme_colors ??= self::COLOR_SCHEME_2023_PLUS;
            $this->major_font_latin = 'Aptos Display';
            $this->minor_font_latin = 'Aptos Narrow';
        }
        if ($theme_colors !== null) {
            $this->theme_colors = $theme_colors;
        }
        if ($spreadsheet !== null) {
            $spreadsheet->get_default_style()->get_font()->apply_theme_fonts($this);
        }
        return $this;
    }
    public function get_major_font_latin(): string
    {
        return $this->major_font_latin;
    }
    public function get_major_font_east_asian(): string
    {
        return $this->major_font_east_asian;
    }
    public function get_major_font_complex_script(): string
    {
        return $this->major_font_complex_script;
    }
    /** @return string[] */
    public function get_major_font_substitutions(): array
    {
        return $this->major_font_substitutions;
    }
    /** @param null|string[] $substitutions */
    public function set_major_font_values(?string $latin, ?string $east_asian, ?string $complex_script, ?array $substitutions): self
    {
        if (!empty($latin)) {
            $this->major_font_latin = $latin;
        }
        if ($east_asian !== null) {
            $this->major_font_east_asian = $east_asian;
        }
        if ($complex_script !== null) {
            $this->major_font_complex_script = $complex_script;
        }
        if ($substitutions !== null) {
            $this->major_font_substitutions = $substitutions;
        }
        return $this;
    }
    public function get_minor_font_latin(): string
    {
        return $this->minor_font_latin;
    }
    public function get_minor_font_east_asian(): string
    {
        return $this->minor_font_east_asian;
    }
    public function get_minor_font_complex_script(): string
    {
        return $this->minor_font_complex_script;
    }
    /** @return string[] */
    public function get_minor_font_substitutions(): array
    {
        return $this->minor_font_substitutions;
    }
    /** @param null|string[] $substitutions */
    public function set_minor_font_values(?string $latin, ?string $east_asian, ?string $complex_script, ?array $substitutions): self
    {
        if (!empty($latin)) {
            $this->minor_font_latin = $latin;
        }
        if ($east_asian !== null) {
            $this->minor_font_east_asian = $east_asian;
        }
        if ($complex_script !== null) {
            $this->minor_font_complex_script = $complex_script;
        }
        if ($substitutions !== null) {
            $this->minor_font_substitutions = $substitutions;
        }
        return $this;
    }
    public function get_theme_font_name(): string
    {
        return $this->theme_font_name;
    }
    public function set_theme_font_name(?string $name): self
    {
        if (!empty($name)) {
            $this->theme_font_name = $name;
        }
        return $this;
    }
}