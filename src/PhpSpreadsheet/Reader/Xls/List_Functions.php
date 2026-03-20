<?php

declare (strict_types=1);
namespace Php_Office\Php_Spreadsheet\Reader\Xls;

use Php_Office\Php_Spreadsheet\Cell\Coordinate;
use Php_Office\Php_Spreadsheet\Reader\Xls;
use Php_Office\Php_Spreadsheet\Shared\File;
use Php_Office\Php_Spreadsheet\Shared\String_Helper;
use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
class List_Functions extends Xls
{
    /**
     * Reads names of the worksheets from a file, without parsing the whole file to a PhpSpreadsheet object.
     *
     * @return string[]
     */
    protected function list_worksheet_names2(string $filename, Xls $xls): array
    {
        File::assert_file($filename);
        $worksheet_names = [];
        // Read the OLE file
        $xls->load_ole($filename);
        // total byte size of Excel data (workbook global substream + sheet substreams)
        $xls->data_size = strlen($xls->data);
        $xls->pos = 0;
        $xls->sheets = [];
        // Parse Workbook Global Substream
        while ($xls->pos < $xls->data_size) {
            $code = self::get_u_int2d($xls->data, $xls->pos);
            match ($code) {
                self::XLS_TYPE_BOF => $xls->read_bof(),
                self::XLS_TYPE_SHEET => $xls->read_sheet(),
                self::XLS_TYPE_EOF => $xls->read_default(),
                self::XLS_TYPE_CODEPAGE => $xls->read_codepage(),
                default => $xls->read_default(),
            };
            if ($code === self::XLS_TYPE_EOF) {
                break;
            }
        }
        foreach ($xls->sheets as $sheet) {
            if ($sheet['sheetType'] === 0x0) {
                // 0x00: Worksheet, 0x02: Chart, 0x06: Visual Basic module
                $worksheet_names[] = $sheet['name'];
            }
        }
        return $worksheet_names;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    protected function list_worksheet_info2(string $filename, Xls $xls): array
    {
        File::assert_file($filename);
        $worksheet_info = [];
        // Read the OLE file
        $xls->load_ole($filename);
        // total byte size of Excel data (workbook global substream + sheet substreams)
        $xls->data_size = strlen($xls->data);
        // initialize
        $xls->pos = 0;
        $xls->sheets = [];
        // Parse Workbook Global Substream
        while ($xls->pos < $xls->data_size) {
            $code = self::get_u_int2d($xls->data, $xls->pos);
            match ($code) {
                self::XLS_TYPE_BOF => $xls->read_bof(),
                self::XLS_TYPE_SHEET => $xls->read_sheet(),
                self::XLS_TYPE_EOF => $xls->read_default(),
                self::XLS_TYPE_CODEPAGE => $xls->read_codepage(),
                default => $xls->read_default(),
            };
            if ($code === self::XLS_TYPE_EOF) {
                break;
            }
        }
        // Parse the individual sheets
        foreach ($xls->sheets as $sheet) {
            if ($sheet['sheetType'] !== 0x0) {
                // 0x00: Worksheet
                // 0x02: Chart
                // 0x06: Visual Basic module
                continue;
            }
            $tmp_info = [];
            $tmp_info['worksheetName'] = String_Helper::convert_to_string($sheet['name']);
            $tmp_info['lastColumnLetter'] = 'A';
            $tmp_info['lastColumnIndex'] = 0;
            $tmp_info['totalRows'] = 0;
            $tmp_info['totalColumns'] = 0;
            $tmp_info['sheetState'] = String_Helper::convert_to_string($sheet['sheetState']);
            $xls->pos = $sheet['offset'];
            while ($xls->pos <= $xls->data_size - 4) {
                $code = self::get_u_int2d($xls->data, $xls->pos);
                switch ($code) {
                    case self::XLS_TYPE_RK:
                    case self::XLS_TYPE_LABELSST:
                    case self::XLS_TYPE_NUMBER:
                    case self::XLS_TYPE_FORMULA:
                    case self::XLS_TYPE_BOOLERR:
                    case self::XLS_TYPE_LABEL:
                    case self::XLS_TYPE_MULRK:
                        $length = self::get_u_int2d($xls->data, $xls->pos + 2);
                        $record_data = $xls->read_record_data($xls->data, $xls->pos + 4, $length);
                        // move stream pointer to next record
                        $xls->pos += 4 + $length;
                        $row_index = self::get_u_int2d($record_data, 0) + 1;
                        if ($code === self::XLS_TYPE_MULRK) {
                            $column_index = self::get_u_int2d($record_data, $length - 2);
                        } else {
                            $column_index = self::get_u_int2d($record_data, 2);
                        }
                        $tmp_info['totalRows'] = max($tmp_info['totalRows'], $row_index);
                        $tmp_info['lastColumnIndex'] = max($tmp_info['lastColumnIndex'], $column_index);
                        break;
                    case self::XLS_TYPE_BOF:
                        $xls->read_bof();
                        break;
                    case self::XLS_TYPE_EOF:
                        $xls->read_default();
                        break 2;
                    default:
                        $xls->read_default();
                        break;
                }
            }
            $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['lastColumnIndex'] + 1, true);
            $tmp_info['totalColumns'] = $tmp_info['lastColumnIndex'] + 1;
            $worksheet_info[] = $tmp_info;
        }
        return $worksheet_info;
    }
    /**
     * Return worksheet info (Name, Last Column Letter, Last Column Index, Total Rows, Total Columns).
     *
     * @return array<int, array{worksheetName: string, dimensionsMinR: int, dimensionsMinC: int, dimensionsMaxR: int, dimensionsMaxC: int, lastColumnLetter: string}>
     */
    protected function list_worksheet_dimensions2(string $filename, Xls $xls): array
    {
        File::assert_file($filename);
        $worksheet_info = [];
        // Read the OLE file
        $xls->load_ole($filename);
        // total byte size of Excel data (workbook global substream + sheet substreams)
        $xls->data_size = strlen($xls->data);
        // initialize
        $xls->pos = 0;
        $xls->sheets = [];
        // Parse Workbook Global Substream
        while ($xls->pos < $xls->data_size) {
            $code = self::get_u_int2d($xls->data, $xls->pos);
            match ($code) {
                self::XLS_TYPE_BOF => $xls->read_bof(),
                self::XLS_TYPE_SHEET => $xls->read_sheet(),
                self::XLS_TYPE_EOF => $xls->read_default(),
                self::XLS_TYPE_CODEPAGE => $xls->read_codepage(),
                default => $xls->read_default(),
            };
            if ($code === self::XLS_TYPE_EOF) {
                break;
            }
        }
        // Parse the individual sheets
        foreach ($xls->sheets as $sheet) {
            if ($sheet['sheetType'] !== 0x0) {
                // 0x00: Worksheet
                // 0x02: Chart
                // 0x06: Visual Basic module
                continue;
            }
            $tmp_info = [];
            $tmp_info['worksheetName'] = String_Helper::convert_to_string($sheet['name']);
            $tmp_info['dimensionsMinR'] = -1;
            $tmp_info['dimensionsMaxR'] = -1;
            $tmp_info['dimensionsMinC'] = -1;
            $tmp_info['dimensionsMaxC'] = -1;
            $tmp_info['lastColumnLetter'] = '';
            $xls->pos = $sheet['offset'];
            while ($xls->pos <= $xls->data_size - 4) {
                $code = self::get_u_int2d($xls->data, $xls->pos);
                switch ($code) {
                    case self::XLS_TYPE_BOF:
                        $xls->read_bof();
                        break;
                    case self::XLS_TYPE_EOF:
                        $xls->read_default();
                        break 2;
                    case self::XLS_TYPE_DIMENSION:
                        $length = self::get_u_int2d($xls->data, $xls->pos + 2);
                        if ($length === 14) {
                            $dimensions_data = substr($xls->data, $xls->pos + 4, $length);
                            $data = unpack('VrwMic/VrwMac/vcolMic/vcolMac/vreserved', $dimensions_data);
                            if (is_array($data)) {
                                /** @var int[] $data */
                                $tmp_info['dimensionsMinR'] = $data['rwMic'];
                                $tmp_info['dimensionsMaxR'] = $data['rwMac'];
                                $tmp_info['dimensionsMinC'] = $data['colMic'];
                                $tmp_info['dimensionsMaxC'] = $data['colMac'];
                                $tmp_info['lastColumnLetter'] = Coordinate::string_from_column_index($tmp_info['dimensionsMaxC'], true);
                            }
                        }
                        $xls->read_default();
                        break;
                    default:
                        $xls->read_default();
                        break;
                }
            }
            $worksheet_info[] = $tmp_info;
        }
        return $worksheet_info;
    }
}