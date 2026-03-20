<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Address_Range;
use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Cell\Data_Type;
use Php_Office\Php_Spreadsheet\Named_Range;
use Php_Office\Php_Spreadsheet\Reader\Xls;
use Php_Office\Php_Spreadsheet\Shared\Code_Page;
use Php_Office\Php_Spreadsheet\Shared\Escher as SharedEscher;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dg_Container\Spgr_Container\Sp_Container;
use Php_Office\Php_Spreadsheet\Shared\Escher\Dgg_Container\Bstore_Container\BSE;
use Php_Office\Php_Spreadsheet\Shared\Xls as SharedXls;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Worksheet\Memory_Drawing;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class Load_Spreadsheet extends Xls
{
    /**
     * Loads PhpSpreadsheet from file.
     */
    protected function load_spreadsheet_from_file2(string $filename, Xls $xls): Spreadsheet
    {
        // Read the OLE file
        $xls->load_ole($filename);
        // Initialisations
        $xls->spreadsheet = $this->new_spreadsheet();
        $xls->spreadsheet->set_value_binder($xls->value_binder);
        $xls->spreadsheet->remove_sheet_by_index(0);
        // remove 1st sheet
        if (!$xls->read_data_only) {
            $xls->spreadsheet->remove_cell_style_xf_by_index(0);
            // remove the default style
            $xls->spreadsheet->remove_cell_xf_by_index(0);
            // remove the default style
        }
        // Read the summary information stream (containing metadata)
        $xls->read_summary_information();
        // Read the Additional document summary information stream (containing application-specific metadata)
        $xls->read_document_summary_information();
        // total byte size of Excel data (workbook global substream + sheet substreams)
        $xls->data_size = strlen($xls->data);
        // initialize
        $xls->pos = 0;
        $xls->codepage = $xls->codepage ?: Code_Page::DEFAULT_CODE_PAGE;
        $xls->formats = [];
        $xls->obj_fonts = [];
        $xls->palette = [];
        $xls->sheets = [];
        $xls->external_books = [];
        $xls->ref = [];
        $xls->definedname = [];
        //* @phpstan-ignore-line
        $xls->sst = [];
        $xls->drawing_group_data = '';
        $xls->xf_index = 0;
        $xls->map_cell_xf_index = [];
        $xls->map_cell_style_xf_index = [];
        // Parse Workbook Global Substream
        while ($xls->pos < $xls->data_size) {
            $code = self::get_u_int2d($xls->data, $xls->pos);
            match ($code) {
                self::XLS_TYPE_BOF => $xls->read_bof(),
                self::XLS_TYPE_FILEPASS => $xls->read_filepass(),
                self::XLS_TYPE_CODEPAGE => $xls->read_codepage(),
                self::XLS_TYPE_DATEMODE => $xls->read_date_mode(),
                self::XLS_TYPE_FONT => $xls->read_font(),
                self::XLS_TYPE_FORMAT => $xls->read_format(),
                self::XLS_TYPE_XF => $xls->read_xf(),
                self::XLS_TYPE_XFEXT => $xls->read_xf_ext(),
                self::XLS_TYPE_STYLE => $xls->read_style(),
                self::XLS_TYPE_PALETTE => $xls->read_palette(),
                self::XLS_TYPE_SHEET => $xls->read_sheet(),
                self::XLS_TYPE_EXTERNALBOOK => $xls->read_external_book(),
                self::XLS_TYPE_EXTERNNAME => $xls->read_extern_name(),
                self::XLS_TYPE_EXTERNSHEET => $xls->read_extern_sheet(),
                self::XLS_TYPE_DEFINEDNAME => $xls->read_defined_name(),
                self::XLS_TYPE_MSODRAWINGGROUP => $xls->read_mso_drawing_group(),
                self::XLS_TYPE_SST => $xls->read_sst(),
                self::XLS_TYPE_EOF => $xls->read_default(),
                default => $xls->read_default(),
            };
            if ($code === self::XLS_TYPE_EOF) {
                break;
            }
        }
        // Resolve indexed colors for font, fill, and border colors
        // Cannot be resolved already in XF record, because PALETTE record comes afterwards
        if (!$xls->read_data_only) {
            foreach ($xls->obj_fonts as $obj_font) {
                if (isset($obj_font->color_index)) {
                    $color = Color::map($obj_font->color_index, $xls->palette, $xls->version);
                    $obj_font->get_color()->set_rgb($color['rgb']);
                }
            }
            foreach ($xls->spreadsheet->get_cell_xf_collection() as $obj_style) {
                // fill start and end color
                $fill = $obj_style->get_fill();
                if (isset($fill->startcolor_index)) {
                    $start_color = Color::map($fill->startcolor_index, $xls->palette, $xls->version);
                    $fill->get_start_color()->set_rgb($start_color['rgb']);
                }
                if (isset($fill->endcolor_index)) {
                    $end_color = Color::map($fill->endcolor_index, $xls->palette, $xls->version);
                    $fill->get_end_color()->set_rgb($end_color['rgb']);
                }
                // border colors
                $top = $obj_style->get_borders()->get_top();
                $right = $obj_style->get_borders()->get_right();
                $bottom = $obj_style->get_borders()->get_bottom();
                $left = $obj_style->get_borders()->get_left();
                $diagonal = $obj_style->get_borders()->get_diagonal();
                if (isset($top->color_index)) {
                    $border_top_color = Color::map($top->color_index, $xls->palette, $xls->version);
                    $top->get_color()->set_rgb($border_top_color['rgb']);
                }
                if (isset($right->color_index)) {
                    $border_right_color = Color::map($right->color_index, $xls->palette, $xls->version);
                    $right->get_color()->set_rgb($border_right_color['rgb']);
                }
                if (isset($bottom->color_index)) {
                    $border_bottom_color = Color::map($bottom->color_index, $xls->palette, $xls->version);
                    $bottom->get_color()->set_rgb($border_bottom_color['rgb']);
                }
                if (isset($left->color_index)) {
                    $border_left_color = Color::map($left->color_index, $xls->palette, $xls->version);
                    $left->get_color()->set_rgb($border_left_color['rgb']);
                }
                if (isset($diagonal->color_index)) {
                    $border_diagonal_color = Color::map($diagonal->color_index, $xls->palette, $xls->version);
                    $diagonal->get_color()->set_rgb($border_diagonal_color['rgb']);
                }
            }
        }
        // treat MSODRAWINGGROUP records, workbook-level Escher
        $escher_workbook = null;
        if (!$xls->read_data_only && $xls->drawing_group_data) {
            $escher = new Shared_Escher();
            $reader = new Escher($escher);
            $escher_workbook = $reader->load($xls->drawing_group_data);
        }
        // Parse the individual sheets
        $xls->active_sheet_set = false;
        $sheet_created = false;
        foreach ($xls->sheets as $sheet) {
            $selected_cells = '';
            if ($sheet['sheetType'] != 0x0) {
                // 0x00: Worksheet, 0x02: Chart, 0x06: Visual Basic module
                continue;
            }
            // check if sheet should be skipped
            if (isset($xls->load_sheets_only) && !in_array($sheet['name'], $xls->load_sheets_only)) {
                continue;
            }
            // add sheet to PhpSpreadsheet object
            $xls->php_sheet = $xls->spreadsheet->create_sheet();
            $sheet_created = true;
            //    Use false for $updateFormulaCellReferences to prevent adjustment of worksheet references in formula
            //        cells... during the load, all formulae should be correct, and we're simply bringing the worksheet
            //        name in line with the formula, not the reverse
            $xls->php_sheet->set_title($sheet['name'], false, false);
            $xls->php_sheet->set_sheet_state($sheet['sheetState']);
            $xls->pos = $sheet['offset'];
            // Initialize isFitToPages. May change after reading SHEETPR record.
            $xls->is_fit_to_pages = false;
            // Initialize drawingData
            $xls->drawing_data = '';
            // Initialize objs
            $xls->objs = [];
            // Initialize shared formula parts
            $xls->shared_formula_parts = [];
            // Initialize shared formulas
            $xls->shared_formulas = [];
            // Initialize text objs
            $xls->text_objects = [];
            // Initialize cell annotations
            $xls->cell_notes = [];
            $xls->text_obj_ref = -1;
            while ($xls->pos <= $xls->data_size - 4) {
                $code = self::get_u_int2d($xls->data, $xls->pos);
                switch ($code) {
                    case self::XLS_TYPE_BOF:
                        $xls->read_bof();
                        break;
                    case self::XLS_TYPE_PRINTGRIDLINES:
                        $xls->read_print_gridlines();
                        break;
                    case self::XLS_TYPE_DEFAULTROWHEIGHT:
                        $xls->read_default_row_height();
                        break;
                    case self::XLS_TYPE_SHEETPR:
                        $xls->read_sheet_pr();
                        break;
                    case self::XLS_TYPE_HORIZONTALPAGEBREAKS:
                        $xls->read_horizontal_page_breaks();
                        break;
                    case self::XLS_TYPE_VERTICALPAGEBREAKS:
                        $xls->read_vertical_page_breaks();
                        break;
                    case self::XLS_TYPE_HEADER:
                        $xls->read_header();
                        break;
                    case self::XLS_TYPE_FOOTER:
                        $xls->read_footer();
                        break;
                    case self::XLS_TYPE_HCENTER:
                        $xls->read_hcenter();
                        break;
                    case self::XLS_TYPE_VCENTER:
                        $xls->read_vcenter();
                        break;
                    case self::XLS_TYPE_LEFTMARGIN:
                        $xls->read_left_margin();
                        break;
                    case self::XLS_TYPE_RIGHTMARGIN:
                        $xls->read_right_margin();
                        break;
                    case self::XLS_TYPE_TOPMARGIN:
                        $xls->read_top_margin();
                        break;
                    case self::XLS_TYPE_BOTTOMMARGIN:
                        $xls->read_bottom_margin();
                        break;
                    case self::XLS_TYPE_PAGESETUP:
                        $xls->read_page_setup();
                        break;
                    case self::XLS_TYPE_PROTECT:
                        $xls->read_protect();
                        break;
                    case self::XLS_TYPE_SCENPROTECT:
                        $xls->read_scen_protect();
                        break;
                    case self::XLS_TYPE_OBJECTPROTECT:
                        $xls->read_object_protect();
                        break;
                    case self::XLS_TYPE_PASSWORD:
                        $xls->read_password();
                        break;
                    case self::XLS_TYPE_DEFCOLWIDTH:
                        $xls->read_def_col_width();
                        break;
                    case self::XLS_TYPE_COLINFO:
                        $xls->read_col_info();
                        break;
                    case self::XLS_TYPE_DIMENSION:
                    case self::XLS_TYPE_DBCELL:
                    default:
                        $xls->read_default();
                        break;
                    case self::XLS_TYPE_ROW:
                        $xls->read_row();
                        break;
                    case self::XLS_TYPE_RK:
                        $xls->read_rk();
                        break;
                    case self::XLS_TYPE_LABELSST:
                        $xls->read_label_sst();
                        break;
                    case self::XLS_TYPE_MULRK:
                        $xls->read_mul_rk();
                        break;
                    case self::XLS_TYPE_NUMBER:
                        $xls->read_number();
                        break;
                    case self::XLS_TYPE_FORMULA:
                        $xls->read_formula();
                        break;
                    case self::XLS_TYPE_SHAREDFMLA:
                        $xls->read_shared_fmla();
                        break;
                    case self::XLS_TYPE_BOOLERR:
                        $xls->read_bool_err();
                        break;
                    case self::XLS_TYPE_MULBLANK:
                        $xls->read_mul_blank();
                        break;
                    case self::XLS_TYPE_LABEL:
                        $xls->read_label();
                        break;
                    case self::XLS_TYPE_BLANK:
                        $xls->read_blank();
                        break;
                    case self::XLS_TYPE_MSODRAWING:
                        $xls->read_mso_drawing();
                        break;
                    case self::XLS_TYPE_OBJ:
                        $xls->read_obj();
                        break;
                    case self::XLS_TYPE_WINDOW2:
                        $xls->read_window2();
                        break;
                    case self::XLS_TYPE_PAGELAYOUTVIEW:
                        $xls->read_page_layout_view();
                        break;
                    case self::XLS_TYPE_SCL:
                        $xls->read_scl();
                        break;
                    case self::XLS_TYPE_PANE:
                        $xls->read_pane();
                        break;
                    case self::XLS_TYPE_SELECTION:
                        $selected_cells = $xls->read_selection();
                        break;
                    case self::XLS_TYPE_MERGEDCELLS:
                        $xls->read_merged_cells();
                        break;
                    case self::XLS_TYPE_HYPERLINK:
                        $xls->read_hyper_link();
                        break;
                    case self::XLS_TYPE_DATAVALIDATIONS:
                        $xls->read_data_validations();
                        break;
                    case self::XLS_TYPE_DATAVALIDATION:
                        $xls->read_data_validation();
                        break;
                    case self::XLS_TYPE_CFHEADER:
                        /** @var string[] */
                        $cell_range_addresses = $xls->read_cf_header();
                        break;
                    case self::XLS_TYPE_CFRULE:
                        $xls->read_cf_rule($cell_range_addresses ?? []);
                        break;
                    case self::XLS_TYPE_SHEETLAYOUT:
                        $xls->read_sheet_layout();
                        break;
                    case self::XLS_TYPE_SHEETPROTECTION:
                        $xls->read_sheet_protection();
                        break;
                    case self::XLS_TYPE_RANGEPROTECTION:
                        $xls->read_range_protection();
                        break;
                    case self::XLS_TYPE_NOTE:
                        $xls->read_note();
                        break;
                    case self::XLS_TYPE_TXO:
                        $xls->read_text_object();
                        break;
                    case self::XLS_TYPE_CONTINUE:
                        $xls->read_continue();
                        break;
                    case self::XLS_TYPE_EOF:
                        $xls->read_default();
                        break 2;
                }
            }
            // treat MSODRAWING records, sheet-level Escher
            if (!$xls->read_data_only && $xls->drawing_data) {
                $escher_worksheet = new Shared_Escher();
                $reader = new Escher($escher_worksheet);
                $escher_worksheet = $reader->load($xls->drawing_data);
                // get all spContainers in one long array, so they can be mapped to OBJ records
                /** @var SpContainer[] $allSpContainers */
                $all_sp_containers = $escher_worksheet->get_dg_container_or_throw()->get_spgr_container_or_throw()->get_all_sp_containers();
            }
            // treat OBJ records
            foreach ($xls->objs as $n => $obj) {
                // the first shape container never has a corresponding OBJ record, hence $n + 1
                if (isset($all_sp_containers[$n + 1])) {
                    $sp_container = $all_sp_containers[$n + 1];
                    // we skip all spContainers that are a part of a group shape since we cannot yet handle those
                    if ($sp_container->get_nesting_level() > 1) {
                        continue;
                    }
                    // calculate the width and height of the shape
                    /** @var int $startRow */
                    [$start_column, $start_row] = Coordinate::coordinate_from_string($sp_container->get_start_coordinates());
                    /** @var int $endRow */
                    [$end_column, $end_row] = Coordinate::coordinate_from_string($sp_container->get_end_coordinates());
                    $start_offset_x = $sp_container->get_start_offset_x();
                    $start_offset_y = $sp_container->get_start_offset_y();
                    $end_offset_x = $sp_container->get_end_offset_x();
                    $end_offset_y = $sp_container->get_end_offset_y();
                    $width = Shared_Xls::get_distance_x($xls->php_sheet, $start_column, $start_offset_x, $end_column, $end_offset_x);
                    $height = Shared_Xls::get_distance_y($xls->php_sheet, $start_row, $start_offset_y, $end_row, $end_offset_y);
                    // calculate offsetX and offsetY of the shape
                    $offset_x = (int) ($start_offset_x * Shared_Xls::size_col($xls->php_sheet, $start_column) / 1024);
                    $offset_y = (int) ($start_offset_y * Shared_Xls::size_row($xls->php_sheet, $start_row) / 256);
                    /** @var int[] $obj */
                    switch ($obj['otObjType']) {
                        case 0x19:
                            // Note
                            if (isset($xls->cell_notes[$obj['idObjID']])) {
                                //$cellNote = $xls->cellNotes[$obj['idObjID']];
                                if (isset($xls->text_objects[$obj['idObjID']])) {
                                    $text_object = $xls->text_objects[$obj['idObjID']];
                                    $xls->cell_notes[$obj['idObjID']]['objTextData'] = $text_object;
                                    //* @phpstan-ignore-line
                                }
                            }
                            break;
                        case 0x8:
                            // picture
                            // get index to BSE entry (1-based)
                            /** @var int */
                            $bs_eindex = $sp_container->get_opt(0x104);
                            // If there is no BSE Index, we will fail here and other fields are not read.
                            // Fix by checking here.
                            // TODO: Why is there no BSE Index? Is this a new Office Version? Password protected field?
                            // More likely: an incompatible picture
                            if (!$bs_eindex) {
                                continue 2;
                            }
                            if ($escher_workbook) {
                                /** @var BSE[] */
                                $bse_collection = $escher_workbook->get_dgg_container_or_throw()->get_bstore_container_or_throw()->get_bse_collection();
                                $BSE = $bse_collection[$bs_eindex - 1];
                                $blip_type = $BSE->get_blip_type();
                                // need check because some blip types are not supported by Escher reader such as EMF
                                if ($blip = $BSE->get_blip()) {
                                    $ih = imagecreatefromstring($blip->get_data());
                                    if ($ih !== false) {
                                        $drawing = new Memory_Drawing();
                                        $drawing->set_image_resource($ih);
                                        // width, height, offsetX, offsetY
                                        $drawing->set_resize_proportional(false);
                                        $drawing->set_width($width);
                                        $drawing->set_height($height);
                                        $drawing->set_offset_x($offset_x);
                                        $drawing->set_offset_y($offset_y);
                                        switch ($blip_type) {
                                            case BSE::BLIPTYPE_JPEG:
                                                $drawing->set_rendering_function(Memory_Drawing::RENDERING_JPEG);
                                                $drawing->set_mime_type(Memory_Drawing::MIMETYPE_JPEG);
                                                break;
                                            case BSE::BLIPTYPE_PNG:
                                                imagealphablending($ih, false);
                                                imagesavealpha($ih, true);
                                                $drawing->set_rendering_function(Memory_Drawing::RENDERING_PNG);
                                                $drawing->set_mime_type(Memory_Drawing::MIMETYPE_PNG);
                                                break;
                                        }
                                        $drawing->set_worksheet($xls->php_sheet);
                                        $drawing->set_coordinates($sp_container->get_start_coordinates());
                                    }
                                }
                            }
                            break;
                        default:
                            // other object type
                            break;
                    }
                }
            }
            // treat SHAREDFMLA records
            if ($xls->version == self::XLS_BIFF8) {
                foreach ($xls->shared_formula_parts as $cell => $base_cell) {
                    /** @var int $row */
                    [$column, $row] = Coordinate::coordinate_from_string($cell);
                    /** @var string $baseCell */
                    if ($xls->get_read_filter()->read_cell($column, $row, $xls->php_sheet->get_title())) {
                        /** @var string */
                        $temp = $xls->shared_formulas[$base_cell];
                        $formula = $xls->get_formula_from_structure($temp, $cell);
                        $xls->php_sheet->get_cell($cell)->set_value_explicit('=' . $formula, Data_Type::TYPE_FORMULA);
                    }
                }
            }
            foreach ($xls->cell_notes as $note => $note_details) {
                /** @var array{author: string, cellRef: string, objTextData?: mixed[]} $noteDetails */
                if (!isset($note_details['objTextData'])) {
                    if (isset($xls->text_objects[$note])) {
                        $text_object = $xls->text_objects[$note];
                        $note_details['objTextData'] = $text_object;
                    } else {
                        $note_details['objTextData']['text'] = '';
                    }
                }
                $cell_address = str_replace('$', '', $note_details['cellRef']);
                /** @var string */
                $temp_details = $note_details['objTextData']['text'];
                $xls->php_sheet->get_comment($cell_address)->set_author($note_details['author'])->set_text($xls->parse_rich_text($temp_details));
            }
            if ($selected_cells !== '') {
                $xls->php_sheet->set_selected_cells($selected_cells);
            }
        }
        if ($xls->create_blank_sheet_if_none_read && !$sheet_created) {
            $xls->spreadsheet->create_sheet();
        }
        if ($xls->active_sheet_set === false) {
            $xls->spreadsheet->set_active_sheet_index(0);
        }
        // add the named ranges (defined names)
        foreach ($xls->definedname as $defined_name) {
            /** @var array{isBuiltInName: int, name: string, formula: string, scope: int} $definedName */
            if ($defined_name['isBuiltInName']) {
                switch ($defined_name['name']) {
                    case pack('C', 0x6):
                        // print area
                        //    in general, formula looks like this: Foo!$C$7:$J$66,Bar!$A$1:$IV$2
                        $ranges = explode(',', $defined_name['formula']);
                        // FIXME: what if sheetname contains comma?
                        $extracted_ranges = [];
                        $sheet_name = '';
                        /** @var non-empty-string $range */
                        foreach ($ranges as $range) {
                            // $range should look like one of these
                            //        Foo!$C$7:$J$66
                            //        Bar!$A$1:$IV$2
                            $explodes = Worksheet::extract_sheet_title($range, true, true);
                            $sheet_name = $explodes[0];
                            if (!str_contains($explodes[1], ':')) {
                                $explodes[1] = $explodes[1] . ':' . $explodes[1];
                            }
                            $extracted_ranges[] = str_replace('$', '', $explodes[1]);
                            // C7:J66
                        }
                        if ($doc_sheet = $xls->spreadsheet->get_sheet_by_name($sheet_name)) {
                            $doc_sheet->get_page_setup()->set_print_area(implode(',', $extracted_ranges));
                            // C7:J66,A1:IV2
                        }
                        break;
                    case pack('C', 0x7):
                        // print titles (repeating rows)
                        // Assuming BIFF8, there are 3 cases
                        // 1. repeating rows
                        //        formula looks like this: Sheet!$A$1:$IV$2
                        //        rows 1-2 repeat
                        // 2. repeating columns
                        //        formula looks like this: Sheet!$A$1:$B$65536
                        //        columns A-B repeat
                        // 3. both repeating rows and repeating columns
                        //        formula looks like this: Sheet!$A$1:$B$65536,Sheet!$A$1:$IV$2
                        $ranges = explode(',', $defined_name['formula']);
                        // FIXME: what if sheetname contains comma?
                        foreach ($ranges as $range) {
                            // $range should look like this one of these
                            //        Sheet!$A$1:$B$65536
                            //        Sheet!$A$1:$IV$2
                            if (str_contains($range, '!')) {
                                $explodes = Worksheet::extract_sheet_title($range, true, true);
                                $doc_sheet = $xls->spreadsheet->get_sheet_by_name($explodes[0]);
                                if ($doc_sheet) {
                                    $extracted_range = $explodes[1];
                                    $extracted_range = str_replace('$', '', $extracted_range);
                                    $coordinate_strings = explode(':', $extracted_range);
                                    if (count($coordinate_strings) == 2) {
                                        [$first_column, $first_row] = Coordinate::coordinate_from_string($coordinate_strings[0]);
                                        [$last_column, $last_row] = Coordinate::coordinate_from_string($coordinate_strings[1]);
                                        $first_row = (int) $first_row;
                                        $last_row = (int) $last_row;
                                        if ($first_column == 'A' && $last_column == 'IV') {
                                            // then we have repeating rows
                                            $doc_sheet->get_page_setup()->set_rows_to_repeat_at_top([$first_row, $last_row]);
                                        } elseif ($first_row === 1 && $last_row === Address_Range::MAX_ROW_XLS) {
                                            // then we have repeating columns
                                            $doc_sheet->get_page_setup()->set_columns_to_repeat_at_left([$first_column, $last_column]);
                                        }
                                    }
                                }
                            }
                        }
                        break;
                }
            } else {
                // Extract range
                $formula = $defined_name['formula'];
                if (str_contains($formula, '!')) {
                    $explodes = Worksheet::extract_sheet_title($formula, true, true);
                    $doc_sheet = $xls->spreadsheet->get_sheet_by_name($explodes[0]);
                    if ($doc_sheet) {
                        $extracted_range = $explodes[1];
                        $local_only = $defined_name['scope'] === 0 ? false : true;
                        $scope = $defined_name['scope'] === 0 ? null : $xls->spreadsheet->get_sheet_by_name($xls->sheets[$defined_name['scope'] - 1]['name']);
                        $xls->spreadsheet->add_named_range(new Named_Range((string) $defined_name['name'], $doc_sheet, $extracted_range, $local_only, $scope));
                    }
                }
                //    Named Value
                //    TODO Provide support for named values
            }
        }
        $xls->data = '';
        return $xls->spreadsheet;
    }
}