<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xls;

use Php_Office\Php_Spreadsheet\Cell\Data_Validation;
class Cell_Data_Validation
{
    /**
     * @var array<string, int>
     */
    protected static array $validation_type_map = [Data_Validation::TYPE_NONE => 0x0, Data_Validation::TYPE_WHOLE => 0x1, Data_Validation::TYPE_DECIMAL => 0x2, Data_Validation::TYPE_LIST => 0x3, Data_Validation::TYPE_DATE => 0x4, Data_Validation::TYPE_TIME => 0x5, Data_Validation::TYPE_TEXTLENGTH => 0x6, Data_Validation::TYPE_CUSTOM => 0x7];
    /**
     * @var array<string, int>
     */
    protected static array $error_style_map = [Data_Validation::STYLE_STOP => 0x0, Data_Validation::STYLE_WARNING => 0x1, Data_Validation::STYLE_INFORMATION => 0x2];
    /**
     * @var array<string, int>
     */
    protected static array $operator_map = [Data_Validation::OPERATOR_BETWEEN => 0x0, Data_Validation::OPERATOR_NOTBETWEEN => 0x1, Data_Validation::OPERATOR_EQUAL => 0x2, Data_Validation::OPERATOR_NOTEQUAL => 0x3, Data_Validation::OPERATOR_GREATERTHAN => 0x4, Data_Validation::OPERATOR_LESSTHAN => 0x5, Data_Validation::OPERATOR_GREATERTHANOREQUAL => 0x6, Data_Validation::OPERATOR_LESSTHANOREQUAL => 0x7];
    public static function type(Data_Validation $data_validation): int
    {
        $validation_type = $data_validation->get_type();
        return self::$validation_type_map[$validation_type] ?? self::$validation_type_map[Data_Validation::TYPE_NONE];
    }
    public static function error_style(Data_Validation $data_validation): int
    {
        $error_style = $data_validation->get_error_style();
        return self::$error_style_map[$error_style] ?? self::$error_style_map[Data_Validation::STYLE_STOP];
    }
    public static function operator(Data_Validation $data_validation): int
    {
        $operator = $data_validation->get_operator();
        return self::$operator_map[$operator] ?? self::$operator_map[Data_Validation::OPERATOR_BETWEEN];
    }
}