<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Calculation;

class Calculation_Locale extends Calculation_Base
{
    public const FORMULA_OPEN_FUNCTION_BRACE = '(';
    public const FORMULA_CLOSE_FUNCTION_BRACE = ')';
    public const FORMULA_OPEN_MATRIX_BRACE = '{';
    public const FORMULA_CLOSE_MATRIX_BRACE = '}';
    public const FORMULA_STRING_QUOTE = '"';
    //    Strip xlfn and xlws prefixes from function name
    public const CALCULATION_REGEXP_STRIP_XLFN_XLWS = '/(_xlfn[.])?(_xlws[.])?(?=[\p{L}][\p{L}\p{N}\.]*[\s]*[(])/';
    /**
     * The current locale setting.
     */
    protected static string $locale_language = 'en_us';
    //    US English    (default locale)
    /**
     * List of available locale settings
     * Note that this is read for the locale subdirectory only when requested.
     *
     * @var string[]
     */
    protected static array $valid_locale_languages = ['en'];
    /**
     * Locale-specific argument separator for function arguments.
     */
    protected static string $locale_argument_separator = ',';
    /** @var string[] */
    protected static array $locale_functions = [];
    /**
     * Locale-specific translations for Excel constants (True, False and Null).
     *
     * @var array<string, string>
     */
    protected static array $locale_boolean = ['TRUE' => 'TRUE', 'FALSE' => 'FALSE', 'NULL' => 'NULL'];
    /** @var array<int, array<int, string>> */
    protected static array $false_true_array = [];
    public static function get_locale_boolean(string $index): string
    {
        return self::$locale_boolean[$index];
    }
    protected static function load_locales(): void
    {
        $locale_file_directory = __DIR__ . '/locale/';
        $locale_file_names = glob($locale_file_directory . '*', GLOB_ONLYDIR) ?: [];
        foreach ($locale_file_names as $filename) {
            $filename = substr($filename, strlen($locale_file_directory));
            if ($filename != 'en') {
                self::$valid_locale_languages[] = $filename;
                $subdirs = glob("{$locale_file_directory}{$filename}/*", GLOB_ONLYDIR) ?: [];
                foreach ($subdirs as $subdir) {
                    $subdirx = basename($subdir);
                    self::$valid_locale_languages[] = "{$filename}_{$subdirx}";
                }
            }
        }
    }
    /**
     * Return the locale-specific translation of TRUE.
     *
     * @return string locale-specific translation of TRUE
     */
    public static function get_true(): string
    {
        return self::$locale_boolean['TRUE'];
    }
    /**
     * Return the locale-specific translation of FALSE.
     *
     * @return string locale-specific translation of FALSE
     */
    public static function get_false(): string
    {
        return self::$locale_boolean['FALSE'];
    }
    /**
     * Get the currently defined locale code.
     */
    public function get_locale(): string
    {
        return self::$locale_language;
    }
    protected function get_locale_file(string $locale_dir, string $locale, string $language, string $file): string
    {
        $locale_file_name = $locale_dir . str_replace('_', DIRECTORY_SEPARATOR, $locale) . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($locale_file_name)) {
            //    If there isn't a locale specific file, look for a language specific file
            $locale_file_name = $locale_dir . $language . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($locale_file_name)) {
                throw new Exception('Locale file not found');
            }
        }
        return $locale_file_name;
    }
    /** @return array<int, array<int, string>> */
    public function get_false_true_array(): array
    {
        if (!empty(self::$false_true_array)) {
            return self::$false_true_array;
        }
        if (count(self::$valid_locale_languages) == 1) {
            self::load_locales();
        }
        $false_true_array = [['FALSE'], ['TRUE']];
        foreach (self::$valid_locale_languages as $language) {
            if (str_starts_with($language, 'en')) {
                continue;
            }
            $locale = $language;
            if (str_contains($locale, '_')) {
                [$language] = explode('_', $locale);
            }
            $locale_dir = implode(DIRECTORY_SEPARATOR, [__DIR__, 'locale', null]);
            try {
                $function_names_file = $this->get_locale_file($locale_dir, $locale, $language, 'functions');
            } catch (Exception) {
                continue;
            }
            //    Retrieve the list of locale or language specific function names
            $locale_functions = file($function_names_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($locale_functions as $locale_function) {
                [$locale_function] = explode('##', $locale_function);
                //    Strip out comments
                if (str_contains($locale_function, '=')) {
                    [$f_name, $lf_name] = array_map(trim(...), explode('=', $locale_function));
                    if ($f_name === 'FALSE') {
                        $false_true_array[0][] = $lf_name;
                    } elseif ($f_name === 'TRUE') {
                        $false_true_array[1][] = $lf_name;
                    }
                }
            }
        }
        self::$false_true_array = $false_true_array;
        return $false_true_array;
    }
    /**
     * Set the locale code.
     *
     * @param string $locale The locale to use for formula translation, eg: 'en_us'
     */
    public function set_locale(string $locale): bool
    {
        //    Identify our locale and language
        $language = $locale = strtolower($locale);
        if (str_contains($locale, '_')) {
            [$language] = explode('_', $locale);
        }
        if (count(self::$valid_locale_languages) == 1) {
            self::load_locales();
        }
        //    Test whether we have any language data for this language (any locale)
        if (in_array($language, self::$valid_locale_languages, true)) {
            //    initialise language/locale settings
            self::$locale_functions = [];
            self::$locale_argument_separator = ',';
            self::$locale_boolean = ['TRUE' => 'TRUE', 'FALSE' => 'FALSE', 'NULL' => 'NULL'];
            //    Default is US English, if user isn't requesting US english, then read the necessary data from the locale files
            if ($locale !== 'en_us') {
                $locale_dir = implode(DIRECTORY_SEPARATOR, [__DIR__, 'locale', null]);
                //    Search for a file with a list of function names for locale
                try {
                    $function_names_file = $this->get_locale_file($locale_dir, $locale, $language, 'functions');
                } catch (Exception) {
                    return false;
                }
                //    Retrieve the list of locale or language specific function names
                $locale_functions = file($function_names_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $php_spreadsheet_functions =& self::get_functions_address();
                foreach ($locale_functions as $locale_function) {
                    [$locale_function] = explode('##', $locale_function);
                    //    Strip out comments
                    if (str_contains($locale_function, '=')) {
                        [$f_name, $lf_name] = array_map(trim(...), explode('=', $locale_function));
                        if ((str_starts_with($f_name, '*') || isset($php_spreadsheet_functions[$f_name])) && $lf_name != '' && $f_name != $lf_name) {
                            self::$locale_functions[$f_name] = $lf_name;
                        }
                    }
                }
                //    Default the TRUE and FALSE constants to the locale names of the TRUE() and FALSE() functions
                if (isset(self::$locale_functions['TRUE'])) {
                    self::$locale_boolean['TRUE'] = self::$locale_functions['TRUE'];
                }
                if (isset(self::$locale_functions['FALSE'])) {
                    self::$locale_boolean['FALSE'] = self::$locale_functions['FALSE'];
                }
                try {
                    $config_file = $this->get_locale_file($locale_dir, $locale, $language, 'config');
                } catch (Exception) {
                    return false;
                }
                $locale_settings = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($locale_settings as $locale_setting) {
                    [$locale_setting] = explode('##', $locale_setting);
                    //    Strip out comments
                    if (str_contains($locale_setting, '=')) {
                        [$setting_name, $setting_value] = array_map(trim(...), explode('=', $locale_setting));
                        $setting_name = strtoupper($setting_name);
                        if ($setting_value !== '') {
                            switch ($setting_name) {
                                case 'ARGUMENTSEPARATOR':
                                    self::$locale_argument_separator = $setting_value;
                                    break;
                            }
                        }
                    }
                }
            }
            self::$function_replace_from_excel = self::$function_replace_to_excel = self::$function_replace_from_locale = self::$function_replace_to_locale = null;
            self::$locale_language = $locale;
            return true;
        }
        return false;
    }
    public static function translate_separator(string $from_separator, string $to_separator, string $formula, int &$in_braces_level, string $open_brace = self::FORMULA_OPEN_FUNCTION_BRACE, string $close_brace = self::FORMULA_CLOSE_FUNCTION_BRACE): string
    {
        $strlen = mb_strlen($formula);
        for ($i = 0; $i < $strlen; ++$i) {
            $chr = mb_substr($formula, $i, 1);
            switch ($chr) {
                case $open_brace:
                    ++$in_braces_level;
                    break;
                case $close_brace:
                    --$in_braces_level;
                    break;
                case $from_separator:
                    if ($in_braces_level > 0) {
                        $formula = mb_substr($formula, 0, $i) . $to_separator . mb_substr($formula, $i + 1);
                    }
            }
        }
        return $formula;
    }
    /**
     * @param string[] $from
     * @param string[] $to
     */
    protected static function translate_formula_block(array $from, array $to, string $formula, int &$in_function_braces_level, int &$in_matrix_braces_level, string $from_separator, string $to_separator): string
    {
        // Function Names
        $formula = (string) preg_replace($from, $to, $formula);
        // Temporarily adjust matrix separators so that they won't be confused with function arguments
        $formula = self::translate_separator(';', '|', $formula, $in_matrix_braces_level, self::FORMULA_OPEN_MATRIX_BRACE, self::FORMULA_CLOSE_MATRIX_BRACE);
        $formula = self::translate_separator(',', '!', $formula, $in_matrix_braces_level, self::FORMULA_OPEN_MATRIX_BRACE, self::FORMULA_CLOSE_MATRIX_BRACE);
        // Function Argument Separators
        $formula = self::translate_separator($from_separator, $to_separator, $formula, $in_function_braces_level);
        // Restore matrix separators
        $formula = self::translate_separator('|', ';', $formula, $in_matrix_braces_level, self::FORMULA_OPEN_MATRIX_BRACE, self::FORMULA_CLOSE_MATRIX_BRACE);
        return self::translate_separator('!', ',', $formula, $in_matrix_braces_level, self::FORMULA_OPEN_MATRIX_BRACE, self::FORMULA_CLOSE_MATRIX_BRACE);
    }
    /**
     * @param string[] $from
     * @param string[] $to
     */
    protected static function translate_formula(array $from, array $to, string $formula, string $from_separator, string $to_separator): string
    {
        // Convert any Excel function names and constant names to the required language;
        //     and adjust function argument separators
        if (self::$locale_language !== 'en_us') {
            $in_function_braces_level = 0;
            $in_matrix_braces_level = 0;
            //    If there is the possibility of separators within a quoted string, then we treat them as literals
            if (str_contains($formula, self::FORMULA_STRING_QUOTE)) {
                //    So instead we skip replacing in any quoted strings by only replacing in every other array element
                //       after we've exploded the formula
                $temp = explode(self::FORMULA_STRING_QUOTE, $formula);
                $not_within_quotes = false;
                foreach ($temp as &$value) {
                    //    Only adjust in alternating array entries
                    $not_within_quotes = $not_within_quotes === false;
                    if ($not_within_quotes === true) {
                        $value = self::translate_formula_block($from, $to, $value, $in_function_braces_level, $in_matrix_braces_level, $from_separator, $to_separator);
                    }
                }
                unset($value);
                //    Then rebuild the formula string
                $formula = implode(self::FORMULA_STRING_QUOTE, $temp);
            } else {
                //    If there's no quoted strings, then we do a simple count/replace
                $formula = self::translate_formula_block($from, $to, $formula, $in_function_braces_level, $in_matrix_braces_level, $from_separator, $to_separator);
            }
        }
        return $formula;
    }
    /** @var null|string[] */
    private static ?array $function_replace_from_excel;
    /** @var null|string[] */
    private static ?array $function_replace_to_locale;
    public function translate_formula_to_locale(string $formula): string
    {
        $formula = preg_replace(self::CALCULATION_REGEXP_STRIP_XLFN_XLWS, '', $formula) ?? '';
        // Build list of function names and constants for translation
        if (self::$function_replace_from_excel === null) {
            self::$function_replace_from_excel = [];
            foreach (array_keys(self::$locale_functions) as $excel_function_name) {
                self::$function_replace_from_excel[] = '/(@?[^\w\.])' . preg_quote((string) $excel_function_name, '/') . '([\s]*\()/ui';
            }
            foreach (array_keys(self::$locale_boolean) as $excel_boolean) {
                self::$function_replace_from_excel[] = '/(@?[^\w\.])' . preg_quote($excel_boolean, '/') . '([^\w\.])/ui';
            }
        }
        if (self::$function_replace_to_locale === null) {
            self::$function_replace_to_locale = [];
            foreach (self::$locale_functions as $locale_function_name) {
                self::$function_replace_to_locale[] = '$1' . trim($locale_function_name) . '$2';
            }
            foreach (self::$locale_boolean as $locale_boolean) {
                self::$function_replace_to_locale[] = '$1' . trim($locale_boolean) . '$2';
            }
        }
        return self::translate_formula(self::$function_replace_from_excel, self::$function_replace_to_locale, $formula, ',', self::$locale_argument_separator);
    }
    /** @var null|string[] */
    protected static ?array $function_replace_from_locale;
    /** @var null|string[] */
    protected static ?array $function_replace_to_excel;
    public function translate_formula_to_english(string $formula): string
    {
        if (self::$function_replace_from_locale === null) {
            self::$function_replace_from_locale = [];
            foreach (self::$locale_functions as $locale_function_name) {
                self::$function_replace_from_locale[] = '/(@?[^\w\.])' . preg_quote($locale_function_name, '/') . '([\s]*\()/ui';
            }
            foreach (self::$locale_boolean as $excel_boolean) {
                self::$function_replace_from_locale[] = '/(@?[^\w\.])' . preg_quote($excel_boolean, '/') . '([^\w\.])/ui';
            }
        }
        if (self::$function_replace_to_excel === null) {
            self::$function_replace_to_excel = [];
            foreach (array_keys(self::$locale_functions) as $excel_function_name) {
                self::$function_replace_to_excel[] = '$1' . trim((string) $excel_function_name) . '$2';
            }
            foreach (array_keys(self::$locale_boolean) as $excel_boolean) {
                self::$function_replace_to_excel[] = '$1' . trim($excel_boolean) . '$2';
            }
        }
        return self::translate_formula(self::$function_replace_from_locale, self::$function_replace_to_excel, $formula, self::$locale_argument_separator, ',');
    }
    public static function locale_func(string $function): string
    {
        if (self::$locale_language !== 'en_us') {
            $function_name = trim($function, '(');
            if (isset(self::$locale_functions[$function_name])) {
                $brace = $function_name != $function;
                $function = self::$locale_functions[$function_name];
                if ($brace) {
                    $function .= '(';
                }
            }
        }
        return $function;
    }
}