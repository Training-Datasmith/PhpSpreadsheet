<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Writer\Xlsx;

use Php_Office\Php_Spreadsheet\Reader\Xlsx\Namespaces;
use Php_Office\Php_Spreadsheet\Shared\Date;
use Php_Office\Php_Spreadsheet\Shared\Xml_Writer;
use Php_Office\Php_Spreadsheet\Spreadsheet;
use Php_Office\Php_Spreadsheet\Writer\Exception as WriterException;
use Php_Office\Php_Spreadsheet\Writer\Xlsx\Defined_Names as DefinedNamesWriter;
class Workbook extends Writer_Part
{
    /**
     * Write workbook to XML format.
     *
     * @param bool $preCalculateFormulas If true, formulas will be calculated before writing
     * @param ?bool $forceFullCalc If null, !$preCalculateFormulas
     *
     * @return string XML Output
     */
    public function write_workbook(Spreadsheet $spreadsheet, bool $pre_calculate_formulas = false, ?bool $force_full_calc = null): string
    {
        // Create XML writer
        if ($this->get_parent_writer()->get_use_disk_caching()) {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_DISK, $this->get_parent_writer()->get_disk_caching_directory());
        } else {
            $obj_writer = new Xml_Writer(Xml_Writer::STORAGE_MEMORY);
        }
        // XML header
        $obj_writer->start_document('1.0', 'UTF-8', 'yes');
        // workbook
        $obj_writer->start_element('workbook');
        $obj_writer->write_attribute('xmlns', Namespaces::MAIN);
        $obj_writer->write_attribute('xmlns:r', Namespaces::SCHEMA_OFFICE_DOCUMENT);
        // fileVersion
        $this->write_file_version($obj_writer);
        // workbookPr
        $this->write_workbook_pr($obj_writer, $spreadsheet);
        // workbookProtection
        $this->write_workbook_protection($obj_writer, $spreadsheet);
        // bookViews
        if ($this->get_parent_writer()->get_office2003compatibility() === false) {
            $this->write_book_views($obj_writer, $spreadsheet);
        }
        // sheets
        $this->write_sheets($obj_writer, $spreadsheet);
        // definedNames
        (new Defined_Names_Writer($obj_writer, $spreadsheet))->write();
        // calcPr
        $this->write_calc_pr($obj_writer, $pre_calculate_formulas, $force_full_calc);
        $obj_writer->end_element();
        // Return
        return $obj_writer->get_data();
    }
    /**
     * Write file version.
     */
    private function write_file_version(Xml_Writer $obj_writer): void
    {
        $obj_writer->start_element('fileVersion');
        $obj_writer->write_attribute('appName', 'xl');
        $obj_writer->write_attribute('lastEdited', '4');
        $obj_writer->write_attribute('lowestEdited', '4');
        $obj_writer->write_attribute('rupBuild', '4505');
        $obj_writer->end_element();
    }
    /**
     * Write WorkbookPr.
     */
    private function write_workbook_pr(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        $obj_writer->start_element('workbookPr');
        if ($spreadsheet->get_excel_calendar() === Date::CALENDAR_MAC_1904) {
            $obj_writer->write_attribute('date1904', '1');
        }
        $obj_writer->write_attribute('codeName', 'ThisWorkbook');
        $obj_writer->end_element();
    }
    /**
     * Write BookViews.
     */
    private function write_book_views(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        // bookViews
        $obj_writer->start_element('bookViews');
        // workbookView
        $obj_writer->start_element('workbookView');
        $obj_writer->write_attribute('activeTab', (string) $spreadsheet->get_active_sheet_index());
        $obj_writer->write_attribute('autoFilterDateGrouping', $spreadsheet->get_auto_filter_date_grouping() ? 'true' : 'false');
        $obj_writer->write_attribute('firstSheet', (string) $spreadsheet->get_first_sheet_index());
        $obj_writer->write_attribute('minimized', $spreadsheet->get_minimized() ? 'true' : 'false');
        $obj_writer->write_attribute('showHorizontalScroll', $spreadsheet->get_show_horizontal_scroll() ? 'true' : 'false');
        $obj_writer->write_attribute('showSheetTabs', $spreadsheet->get_show_sheet_tabs() ? 'true' : 'false');
        $obj_writer->write_attribute('showVerticalScroll', $spreadsheet->get_show_vertical_scroll() ? 'true' : 'false');
        $obj_writer->write_attribute('tabRatio', (string) $spreadsheet->get_tab_ratio());
        $obj_writer->write_attribute('visibility', $spreadsheet->get_visibility());
        $obj_writer->end_element();
        $obj_writer->end_element();
    }
    /**
     * Write WorkbookProtection.
     */
    private function write_workbook_protection(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        $security = $spreadsheet->get_security();
        if ($security->is_security_enabled()) {
            $obj_writer->start_element('workbookProtection');
            $obj_writer->write_attribute('lockRevision', $security->get_lock_revision() ? 'true' : 'false');
            $obj_writer->write_attribute('lockStructure', $security->get_lock_structure() ? 'true' : 'false');
            $obj_writer->write_attribute('lockWindows', $security->get_lock_windows() ? 'true' : 'false');
            if ($security->get_revisions_password() !== '') {
                $obj_writer->write_attribute('revisionsPassword', $security->get_revisions_password());
            } else {
                $hash_value = $security->get_revisions_hash_value();
                if ($hash_value !== '') {
                    $obj_writer->write_attribute('revisionsAlgorithmName', $security->get_revisions_algorithm_name());
                    $obj_writer->write_attribute('revisionsHashValue', $hash_value);
                    $obj_writer->write_attribute('revisionsSaltValue', $security->get_revisions_salt_value());
                    $obj_writer->write_attribute('revisionsSpinCount', (string) $security->get_revisions_spin_count());
                }
            }
            if ($security->get_workbook_password() !== '') {
                $obj_writer->write_attribute('workbookPassword', $security->get_workbook_password());
            } else {
                $hash_value = $security->get_workbook_hash_value();
                if ($hash_value !== '') {
                    $obj_writer->write_attribute('workbookAlgorithmName', $security->get_workbook_algorithm_name());
                    $obj_writer->write_attribute('workbookHashValue', $hash_value);
                    $obj_writer->write_attribute('workbookSaltValue', $security->get_workbook_salt_value());
                    $obj_writer->write_attribute('workbookSpinCount', (string) $security->get_workbook_spin_count());
                }
            }
            $obj_writer->end_element();
        }
    }
    /**
     * Write calcPr.
     *
     * @param bool $preCalculateFormulas If true, formulas will be calculated before writing
     */
    private function write_calc_pr(Xml_Writer $obj_writer, bool $pre_calculate_formulas, ?bool $force_full_calc): void
    {
        $obj_writer->start_element('calcPr');
        //    Set the calcid to a higher value than Excel itself will use, otherwise Excel will always recalc
        //  If MS Excel does do a recalc, then users opening a file in MS Excel will be prompted to save on exit
        //     because the file has changed
        $obj_writer->write_attribute('calcId', '999999');
        $obj_writer->write_attribute('calcMode', 'auto');
        //    fullCalcOnLoad isn't needed if we will calculate before writing
        $obj_writer->write_attribute('calcCompleted', $pre_calculate_formulas ? '1' : '0');
        $obj_writer->write_attribute('fullCalcOnLoad', $pre_calculate_formulas ? '0' : '1');
        if ($force_full_calc === null) {
            $obj_writer->write_attribute('forceFullCalc', $pre_calculate_formulas ? '0' : '1');
        } else {
            $obj_writer->write_attribute('forceFullCalc', $force_full_calc ? '1' : '0');
        }
        $obj_writer->end_element();
    }
    /**
     * Write sheets.
     */
    private function write_sheets(Xml_Writer $obj_writer, Spreadsheet $spreadsheet): void
    {
        // Write sheets
        $obj_writer->start_element('sheets');
        $sheet_count = $spreadsheet->get_sheet_count();
        for ($i = 0; $i < $sheet_count; ++$i) {
            // sheet
            $this->write_sheet($obj_writer, $spreadsheet->get_sheet($i)->get_title(), $i + 1, $i + 1 + 3, $spreadsheet->get_sheet($i)->get_sheet_state());
        }
        $obj_writer->end_element();
    }
    /**
     * Write sheet.
     *
     * @param string $worksheetName Sheet name
     * @param int $worksheetId Sheet id
     * @param int $relId Relationship ID
     * @param string $sheetState Sheet state (visible, hidden, veryHidden)
     */
    private function write_sheet(Xml_Writer $obj_writer, string $worksheet_name, int $worksheet_id = 1, int $rel_id = 1, string $sheet_state = 'visible'): void
    {
        if ($worksheet_name != '') {
            // Write sheet
            $obj_writer->start_element('sheet');
            $obj_writer->write_attribute('name', $worksheet_name);
            $obj_writer->write_attribute('sheetId', (string) $worksheet_id);
            if ($sheet_state !== 'visible' && $sheet_state != '') {
                $obj_writer->write_attribute('state', $sheet_state);
            }
            $obj_writer->write_attribute('r:id', 'rId' . $rel_id);
            $obj_writer->end_element();
        } else {
            throw new Writer_Exception('Invalid parameters passed.');
        }
    }
}