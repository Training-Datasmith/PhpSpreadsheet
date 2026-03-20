<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Worksheet\Table;

use Php_Office\Php_Spreadsheet\Style\Style;
use Php_Office\Php_Spreadsheet\Worksheet\Table;
class Table_Style
{
    public const TABLE_STYLE_NONE = '';
    public const TABLE_STYLE_LIGHT1 = 'TableStyleLight1';
    public const TABLE_STYLE_LIGHT2 = 'TableStyleLight2';
    public const TABLE_STYLE_LIGHT3 = 'TableStyleLight3';
    public const TABLE_STYLE_LIGHT4 = 'TableStyleLight4';
    public const TABLE_STYLE_LIGHT5 = 'TableStyleLight5';
    public const TABLE_STYLE_LIGHT6 = 'TableStyleLight6';
    public const TABLE_STYLE_LIGHT7 = 'TableStyleLight7';
    public const TABLE_STYLE_LIGHT8 = 'TableStyleLight8';
    public const TABLE_STYLE_LIGHT9 = 'TableStyleLight9';
    public const TABLE_STYLE_LIGHT10 = 'TableStyleLight10';
    public const TABLE_STYLE_LIGHT11 = 'TableStyleLight11';
    public const TABLE_STYLE_LIGHT12 = 'TableStyleLight12';
    public const TABLE_STYLE_LIGHT13 = 'TableStyleLight13';
    public const TABLE_STYLE_LIGHT14 = 'TableStyleLight14';
    public const TABLE_STYLE_LIGHT15 = 'TableStyleLight15';
    public const TABLE_STYLE_LIGHT16 = 'TableStyleLight16';
    public const TABLE_STYLE_LIGHT17 = 'TableStyleLight17';
    public const TABLE_STYLE_LIGHT18 = 'TableStyleLight18';
    public const TABLE_STYLE_LIGHT19 = 'TableStyleLight19';
    public const TABLE_STYLE_LIGHT20 = 'TableStyleLight20';
    public const TABLE_STYLE_LIGHT21 = 'TableStyleLight21';
    public const TABLE_STYLE_MEDIUM1 = 'TableStyleMedium1';
    public const TABLE_STYLE_MEDIUM2 = 'TableStyleMedium2';
    public const TABLE_STYLE_MEDIUM3 = 'TableStyleMedium3';
    public const TABLE_STYLE_MEDIUM4 = 'TableStyleMedium4';
    public const TABLE_STYLE_MEDIUM5 = 'TableStyleMedium5';
    public const TABLE_STYLE_MEDIUM6 = 'TableStyleMedium6';
    public const TABLE_STYLE_MEDIUM7 = 'TableStyleMedium7';
    public const TABLE_STYLE_MEDIUM8 = 'TableStyleMedium8';
    public const TABLE_STYLE_MEDIUM9 = 'TableStyleMedium9';
    public const TABLE_STYLE_MEDIUM10 = 'TableStyleMedium10';
    public const TABLE_STYLE_MEDIUM11 = 'TableStyleMedium11';
    public const TABLE_STYLE_MEDIUM12 = 'TableStyleMedium12';
    public const TABLE_STYLE_MEDIUM13 = 'TableStyleMedium13';
    public const TABLE_STYLE_MEDIUM14 = 'TableStyleMedium14';
    public const TABLE_STYLE_MEDIUM15 = 'TableStyleMedium15';
    public const TABLE_STYLE_MEDIUM16 = 'TableStyleMedium16';
    public const TABLE_STYLE_MEDIUM17 = 'TableStyleMedium17';
    public const TABLE_STYLE_MEDIUM18 = 'TableStyleMedium18';
    public const TABLE_STYLE_MEDIUM19 = 'TableStyleMedium19';
    public const TABLE_STYLE_MEDIUM20 = 'TableStyleMedium20';
    public const TABLE_STYLE_MEDIUM21 = 'TableStyleMedium21';
    public const TABLE_STYLE_MEDIUM22 = 'TableStyleMedium22';
    public const TABLE_STYLE_MEDIUM23 = 'TableStyleMedium23';
    public const TABLE_STYLE_MEDIUM24 = 'TableStyleMedium24';
    public const TABLE_STYLE_MEDIUM25 = 'TableStyleMedium25';
    public const TABLE_STYLE_MEDIUM26 = 'TableStyleMedium26';
    public const TABLE_STYLE_MEDIUM27 = 'TableStyleMedium27';
    public const TABLE_STYLE_MEDIUM28 = 'TableStyleMedium28';
    public const TABLE_STYLE_DARK1 = 'TableStyleDark1';
    public const TABLE_STYLE_DARK2 = 'TableStyleDark2';
    public const TABLE_STYLE_DARK3 = 'TableStyleDark3';
    public const TABLE_STYLE_DARK4 = 'TableStyleDark4';
    public const TABLE_STYLE_DARK5 = 'TableStyleDark5';
    public const TABLE_STYLE_DARK6 = 'TableStyleDark6';
    public const TABLE_STYLE_DARK7 = 'TableStyleDark7';
    public const TABLE_STYLE_DARK8 = 'TableStyleDark8';
    public const TABLE_STYLE_DARK9 = 'TableStyleDark9';
    public const TABLE_STYLE_DARK10 = 'TableStyleDark10';
    public const TABLE_STYLE_DARK11 = 'TableStyleDark11';
    /**
     * Show First Column.
     */
    private bool $show_first_column = false;
    /**
     * Show Last Column.
     */
    private bool $show_last_column = false;
    /**
     * Show Row Stripes.
     */
    private bool $show_row_stripes = false;
    /**
     * Show Column Stripes.
     */
    private bool $show_column_stripes = false;
    /**
     * TableDxfsStyle.
     */
    private ?Table_Dxfs_Style $table_style = null;
    /**
     * Table.
     */
    private ?Table $table = null;
    /**
     * Create a new Table Style.
     *
     * @param string $theme (e.g. TableStyle::TABLE_STYLE_MEDIUM2)
     */
    public function __construct(private string $theme = self::TABLE_STYLE_MEDIUM2)
    {
    }
    /**
     * Get theme.
     */
    public function get_theme(): string
    {
        return $this->theme;
    }
    /**
     * Set theme.
     */
    public function set_theme(string $theme): self
    {
        $this->theme = $theme;
        return $this;
    }
    /**
     * Get show First Column.
     */
    public function get_show_first_column(): bool
    {
        return $this->show_first_column;
    }
    /**
     * Set show First Column.
     */
    public function set_show_first_column(bool $show_first_column): self
    {
        $this->show_first_column = $show_first_column;
        return $this;
    }
    /**
     * Get show Last Column.
     */
    public function get_show_last_column(): bool
    {
        return $this->show_last_column;
    }
    /**
     * Set show Last Column.
     */
    public function set_show_last_column(bool $show_last_column): self
    {
        $this->show_last_column = $show_last_column;
        return $this;
    }
    /**
     * Get show Row Stripes.
     */
    public function get_show_row_stripes(): bool
    {
        return $this->show_row_stripes;
    }
    /**
     * Set show Row Stripes.
     */
    public function set_show_row_stripes(bool $show_row_stripes): self
    {
        $this->show_row_stripes = $show_row_stripes;
        return $this;
    }
    /**
     * Get show Column Stripes.
     */
    public function get_show_column_stripes(): bool
    {
        return $this->show_column_stripes;
    }
    /**
     * Set show Column Stripes.
     */
    public function set_show_column_stripes(bool $show_column_stripes): self
    {
        $this->show_column_stripes = $show_column_stripes;
        return $this;
    }
    /**
     * Get this Style's Dxfs TableStyle.
     */
    public function get_table_dxfs_style(): ?Table_Dxfs_Style
    {
        return $this->table_style;
    }
    /**
     * Set this Style's Dxfs TableStyle.
     *
     * @param Style[] $dxfs
     */
    public function set_table_dxfs_style(Table_Dxfs_Style $table_style, array $dxfs): self
    {
        $this->table_style = $table_style;
        if ($this->table_style->get_header_row() !== null && isset($dxfs[$this->table_style->get_header_row()])) {
            $this->table_style->set_header_row_style($dxfs[$this->table_style->get_header_row()]);
        }
        if ($this->table_style->get_first_row_stripe() !== null && isset($dxfs[$this->table_style->get_first_row_stripe()])) {
            $this->table_style->set_first_row_stripe_style($dxfs[$this->table_style->get_first_row_stripe()]);
        }
        if ($this->table_style->get_second_row_stripe() !== null && isset($dxfs[$this->table_style->get_second_row_stripe()])) {
            $this->table_style->set_second_row_stripe_style($dxfs[$this->table_style->get_second_row_stripe()]);
        }
        return $this;
    }
    /**
     * Get this Style's Table.
     */
    public function get_table(): ?Table
    {
        return $this->table;
    }
    /**
     * Set this Style's Table.
     */
    public function set_table(?Table $table = null): self
    {
        $this->table = $table;
        return $this;
    }
}