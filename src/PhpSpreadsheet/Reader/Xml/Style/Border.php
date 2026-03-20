<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xml\Style;

use Php_Office\Php_Spreadsheet\Style\Border as BorderStyle;
use Php_Office\Php_Spreadsheet\Style\Borders;
use Simple_Xml_Element;
class Border extends Style_Base
{
    protected const BORDER_POSITIONS = ['top', 'left', 'bottom', 'right'];
    public const BORDER_MAPPINGS = ['borderStyle' => ['continuous' => Border_Style::BORDER_HAIR, 'dash' => Border_Style::BORDER_DASHED, 'dashdot' => Border_Style::BORDER_DASHDOT, 'dashdotdot' => Border_Style::BORDER_DASHDOTDOT, 'dot' => Border_Style::BORDER_DOTTED, 'double' => Border_Style::BORDER_DOUBLE, '0continuous' => Border_Style::BORDER_HAIR, '0dash' => Border_Style::BORDER_DASHED, '0dashdot' => Border_Style::BORDER_DASHDOT, '0dashdotdot' => Border_Style::BORDER_DASHDOTDOT, '0dot' => Border_Style::BORDER_DOTTED, '0double' => Border_Style::BORDER_DOUBLE, '1continuous' => Border_Style::BORDER_THIN, '1dash' => Border_Style::BORDER_DASHED, '1dashdot' => Border_Style::BORDER_DASHDOT, '1dashdotdot' => Border_Style::BORDER_DASHDOTDOT, '1dot' => Border_Style::BORDER_DOTTED, '1double' => Border_Style::BORDER_DOUBLE, '2continuous' => Border_Style::BORDER_MEDIUM, '2dash' => Border_Style::BORDER_MEDIUMDASHED, '2dashdot' => Border_Style::BORDER_MEDIUMDASHDOT, '2dashdotdot' => Border_Style::BORDER_MEDIUMDASHDOTDOT, '2dot' => Border_Style::BORDER_DOTTED, '2double' => Border_Style::BORDER_DOUBLE, '3continuous' => Border_Style::BORDER_THICK, '3dash' => Border_Style::BORDER_MEDIUMDASHED, '3dashdot' => Border_Style::BORDER_MEDIUMDASHDOT, '3dashdotdot' => Border_Style::BORDER_MEDIUMDASHDOTDOT, '3dot' => Border_Style::BORDER_DOTTED, '3double' => Border_Style::BORDER_DOUBLE]];
    /**
     * @param string[] $namespaces
     *
     * @return mixed[]
     */
    public function parse_style(Simple_Xml_Element $style_data, array $namespaces): array
    {
        $style = [];
        $diagonal_direction = Borders::DIAGONAL_NONE;
        foreach ($style_data->Border as $border_style) {
            $border_attributes = self::get_attributes($border_style, $namespaces['ss']);
            /** @var array{color?: array{rgb: string}, borderStyle: string} */
            $this_border = [];
            $style_type = (string) $border_attributes->Weight;
            $style_type .= strtolower((string) $border_attributes->line_style);
            $this_border['borderStyle'] = self::BORDER_MAPPINGS['borderStyle'][$style_type] ?? Border_Style::BORDER_NONE;
            $color = (string) ($border_attributes['Color'] ?? '');
            if ($color !== '') {
                $this_border['color']['rgb'] = substr($color, 1);
            }
            $position = (string) ($border_attributes['Position'] ?? '');
            if ($position !== '') {
                [$border_position, $diagonal_direction] = $this->parse_position($position, $diagonal_direction);
                if ($border_position) {
                    $style['borders'][$border_position] = $this_border;
                } elseif ($diagonal_direction !== Borders::DIAGONAL_NONE) {
                    $style['borders']['diagonalDirection'] = $diagonal_direction;
                    $style['borders']['diagonal'] = $this_border;
                }
            }
        }
        return $style;
    }
    /** @return array{0: string, 1: int} */
    protected function parse_position(string $border_style_value, int $diagonal_direction): array
    {
        $border_style_value = strtolower($border_style_value);
        $border_position = '';
        if (in_array($border_style_value, self::BORDER_POSITIONS)) {
            $border_position = $border_style_value;
        } elseif ($border_style_value === 'diagonalleft') {
            $diagonal_direction = $diagonal_direction !== Borders::DIAGONAL_NONE ? Borders::DIAGONAL_BOTH : Borders::DIAGONAL_DOWN;
        } elseif ($border_style_value === 'diagonalright') {
            $diagonal_direction = $diagonal_direction !== Borders::DIAGONAL_NONE ? Borders::DIAGONAL_BOTH : Borders::DIAGONAL_UP;
        }
        return [$border_position, $diagonal_direction];
    }
}