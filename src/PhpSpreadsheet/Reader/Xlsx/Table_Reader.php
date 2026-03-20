<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
use Php_Office\Php_Spreadsheet\Worksheet\Table\Table_Dxfs_Style;
use Php_Office\Php_Spreadsheet\Worksheet\Table\Table_Style;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Table_Reader
{
    /** @var mixed[]|SimpleXMLElement */
    private $table_attributes;
    public function __construct(private readonly Worksheet $worksheet, private readonly Simple_Xml_Element $table_xml)
    {
    }
    /**
     * Loads Table into the Worksheet.
     *
     * @param TableDxfsStyle[] $tableStyles
     * @param Style[] $dxfs
     */
    public function load(array $table_styles, array $dxfs): void
    {
        $this->table_attributes = $this->table_xml->attributes() ?? [];
        // Remove all "$" in the table range
        $table_range = (string) preg_replace('/\$/', '', $this->table_attributes['ref'] ?? '');
        if (str_contains($table_range, ':')) {
            $this->read_table($table_range, $table_styles, $dxfs);
        }
    }
    /**
     * Read Table from xml.
     *
     * @param TableDxfsStyle[] $tableStyles
     * @param Style[] $dxfs
     */
    private function read_table(string $table_range, array $table_styles, array $dxfs): void
    {
        $table = new Table($table_range);
        /** @var string[] */
        $attributes = $this->table_attributes;
        $table->set_name((string) ($attributes['displayName'] ?? ''));
        $table->set_show_header_row((string) ($attributes['headerRowCount'] ?? '') !== '0');
        $table->set_show_totals_row((string) ($attributes['totalsRowCount'] ?? '') === '1');
        $this->read_table_auto_filter($table, $this->table_xml->auto_filter);
        $this->read_table_columns($table, $this->table_xml->table_columns);
        $this->read_table_style($table, $this->table_xml->table_style_info, $table_styles, $dxfs);
        (new Auto_Filter($table, $this->table_xml))->load();
        $this->worksheet->add_table($table);
    }
    /**
     * Reads TableAutoFilter from xml.
     */
    private function read_table_auto_filter(Table $table, Simple_Xml_Element $auto_filter_xml): void
    {
        if ($auto_filter_xml->filter_column === null) {
            $table->set_allow_filter(false);
            return;
        }
        foreach ($auto_filter_xml->filter_column as $filter_column) {
            /** @var SimpleXMLElement */
            $attributes = $filter_column->attributes() ?? ['colId' => 0, 'hiddenButton' => 0];
            $column = $table->get_column_by_offset((int) $attributes['colId']);
            $column->set_show_filter_button((string) $attributes['hiddenButton'] !== '1');
        }
    }
    /**
     * Reads TableColumns from xml.
     */
    private function read_table_columns(Table $table, Simple_Xml_Element $table_columns_xml): void
    {
        $offset = 0;
        foreach ($table_columns_xml->table_column as $table_column) {
            /** @var SimpleXMLElement */
            $attributes = $table_column->attributes() ?? ['totalsRowLabel' => 0, 'totalsRowFunction' => 0];
            $column = $table->get_column_by_offset($offset++);
            if ($table->get_show_totals_row()) {
                if ($attributes['totalsRowLabel']) {
                    $column->set_totals_row_label((string) $attributes['totalsRowLabel']);
                }
                if ($attributes['totalsRowFunction']) {
                    $column->set_totals_row_function((string) $attributes['totalsRowFunction']);
                }
            }
            if ($table_column->calculated_column_formula) {
                $column->set_column_formula((string) $table_column->calculated_column_formula);
            }
        }
    }
    /**
     * Reads TableStyle from xml.
     *
     * @param TableDxfsStyle[] $tableStyles
     * @param Style[] $dxfs
     */
    private function read_table_style(Table $table, Simple_Xml_Element $table_style_info_xml, array $table_styles, array $dxfs): void
    {
        $table_style = new Table_Style();
        $attributes = $table_style_info_xml->attributes();
        if ($attributes !== null) {
            $table_style->set_theme((string) $attributes['name']);
            $table_style->set_show_row_stripes((string) $attributes['showRowStripes'] === '1');
            $table_style->set_show_column_stripes((string) $attributes['showColumnStripes'] === '1');
            $table_style->set_show_first_column((string) $attributes['showFirstColumn'] === '1');
            $table_style->set_show_last_column((string) $attributes['showLastColumn'] === '1');
            foreach ($table_styles as $style) {
                if ($style->get_name() === (string) $attributes['name']) {
                    $table_style->set_table_dxfs_style($style, $dxfs);
                }
            }
        }
        $table->set_style($table_style);
    }
}