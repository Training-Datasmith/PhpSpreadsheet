<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xlsx;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xlsx;
use Php_Office\Php_Spreadsheet\Worksheet\Pane;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use Simple_Xml_Element;
class Sheet_Views extends Base_Parser_Class
{
    private readonly Simple_Xml_Element $sheet_view_attributes;
    private string $active_pane = '';
    public function __construct(private readonly Simple_Xml_Element $sheet_view_xml, private readonly Worksheet $worksheet)
    {
        $this->sheet_view_attributes = Xlsx::test_simple_xml($this->sheet_view_xml->attributes());
    }
    public function load(): void
    {
        $this->top_left();
        $this->zoom_scale();
        $this->view();
        $this->grid_lines();
        $this->headers();
        $this->direction();
        $this->show_zeros();
        $uses_panes = false;
        if (isset($this->sheet_view_xml->pane)) {
            $this->pane();
            $uses_panes = true;
        }
        if (isset($this->sheet_view_xml->selection)) {
            foreach ($this->sheet_view_xml->selection as $selection) {
                $this->selection($selection, $uses_panes);
            }
        }
    }
    private function zoom_scale(): void
    {
        if (isset($this->sheet_view_attributes->zoom_scale)) {
            $zoom_scale = (int) $this->sheet_view_attributes->zoom_scale;
            if ($zoom_scale <= 0) {
                // setZoomScale will throw an Exception if the scale is less than or equals 0
                // that is OK when manually creating documents, but we should be able to read all documents
                $zoom_scale = 100;
            }
            $this->worksheet->get_sheet_view()->set_zoom_scale($zoom_scale);
        }
        if (isset($this->sheet_view_attributes->zoom_scale_normal)) {
            $zoom_scale_normal = (int) $this->sheet_view_attributes->zoom_scale_normal;
            if ($zoom_scale_normal <= 0) {
                // setZoomScaleNormal will throw an Exception if the scale is less than or equals 0
                // that is OK when manually creating documents, but we should be able to read all documents
                $zoom_scale_normal = 100;
            }
            $this->worksheet->get_sheet_view()->set_zoom_scale_normal($zoom_scale_normal);
        }
        if (isset($this->sheet_view_attributes->zoom_scale_page_layout_view)) {
            $zoom_scale_normal = (int) $this->sheet_view_attributes->zoom_scale_page_layout_view;
            if ($zoom_scale_normal > 0) {
                $this->worksheet->get_sheet_view()->set_zoom_scale_page_layout_view($zoom_scale_normal);
            }
        }
        if (isset($this->sheet_view_attributes->zoom_scale_sheet_layout_view)) {
            $zoom_scale_normal = (int) $this->sheet_view_attributes->zoom_scale_sheet_layout_view;
            if ($zoom_scale_normal > 0) {
                $this->worksheet->get_sheet_view()->set_zoom_scale_sheet_layout_view($zoom_scale_normal);
            }
        }
    }
    private function view(): void
    {
        if (isset($this->sheet_view_attributes->view)) {
            $this->worksheet->get_sheet_view()->set_view((string) $this->sheet_view_attributes->view);
        }
    }
    private function top_left(): void
    {
        if (isset($this->sheet_view_attributes->top_left_cell)) {
            $this->worksheet->set_top_left_cell($this->sheet_view_attributes->top_left_cell);
        }
    }
    private function grid_lines(): void
    {
        if (isset($this->sheet_view_attributes->show_grid_lines)) {
            $this->worksheet->set_show_grid_lines(self::boolean((string) $this->sheet_view_attributes->show_grid_lines));
        }
    }
    private function headers(): void
    {
        if (isset($this->sheet_view_attributes->show_row_col_headers)) {
            $this->worksheet->set_show_row_col_headers(self::boolean((string) $this->sheet_view_attributes->show_row_col_headers));
        }
    }
    private function direction(): void
    {
        if (isset($this->sheet_view_attributes->right_to_left)) {
            $this->worksheet->set_right_to_left(self::boolean((string) $this->sheet_view_attributes->right_to_left));
        }
    }
    private function show_zeros(): void
    {
        if (isset($this->sheet_view_attributes->show_zeros)) {
            $this->worksheet->get_sheet_view()->set_show_zeros(self::boolean((string) $this->sheet_view_attributes->show_zeros));
        }
    }
    private function pane(): void
    {
        $x_split = 0;
        $y_split = 0;
        $top_left_cell = null;
        $pane_attributes = $this->sheet_view_xml->pane->attributes();
        if (isset($pane_attributes->x_split)) {
            $x_split = (int) $pane_attributes->x_split;
            $this->worksheet->set_x_split($x_split);
        }
        if (isset($pane_attributes->y_split)) {
            $y_split = (int) $pane_attributes->y_split;
            $this->worksheet->set_y_split($y_split);
        }
        $pane_state = isset($pane_attributes->state) ? (string) $pane_attributes->state : '';
        $this->worksheet->set_pane_state($pane_state);
        if (isset($pane_attributes->top_left_cell)) {
            $top_left_cell = (string) $pane_attributes->top_left_cell;
            $this->worksheet->set_pane_top_left_cell($top_left_cell);
            if ($pane_state === Worksheet::PANE_FROZEN) {
                $this->worksheet->set_top_left_cell($top_left_cell);
            }
        }
        $active_pane = isset($pane_attributes->active_pane) ? (string) $pane_attributes->active_pane : 'topLeft';
        $this->worksheet->set_active_pane($active_pane);
        $this->active_pane = $active_pane;
        if ($pane_state === Worksheet::PANE_FROZEN || $pane_state === Worksheet::PANE_FROZENSPLIT) {
            $this->worksheet->freeze_pane(Coordinate::string_from_column_index($x_split + 1) . ($y_split + 1), $top_left_cell, $pane_state === Worksheet::PANE_FROZENSPLIT);
        }
    }
    private function selection(?Simple_Xml_Element $selection, bool $uses_panes): void
    {
        $attributes = $selection === null ? null : $selection->attributes();
        if ($attributes !== null) {
            $position = (string) $attributes->pane;
            if ($uses_panes && $position === '') {
                $position = 'topLeft';
            }
            $active_cell = (string) $attributes->active_cell;
            $sqref = (string) $attributes->sqref;
            $sqref = explode(' ', $sqref);
            $sqref = $sqref[0];
            if ($position === '') {
                $this->worksheet->set_selected_cells($sqref);
            } else {
                $pane = new Pane($position, $sqref, $active_cell);
                $this->worksheet->set_pane($position, $pane);
                if ($position === $this->active_pane && $sqref !== '') {
                    $this->worksheet->set_selected_cells($sqref);
                }
            }
        }
    }
}