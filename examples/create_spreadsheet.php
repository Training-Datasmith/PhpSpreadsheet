<?php

declare(strict_types=1);

/**
 * PhpSpreadsheet — create and write an XLSX file example.
 *
 * Run:
 *   php examples/create_spreadsheet.php
 *   # Produces: output.xlsx in the current directory
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();

// Document properties
$spreadsheet->getProperties()
    ->setCreator('PhpSpreadsheet')
    ->setTitle('Sales Report 2024')
    ->setDescription('Monthly sales data');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sales');

// Bold header row
$headers = ['Month', 'Revenue', 'Units Sold', 'Avg Price'];
foreach ($headers as $col => $header) {
    $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
    $cell->setValue($header);
    $sheet->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
}

// Set column widths
$sheet->getColumnDimension('A')->setWidth(12);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(12);

// Data rows
$data = [
    ['January',  45000.50, 300, 150.00],
    ['February', 38000.00, 250, 152.00],
    ['March',    52000.75, 340, 152.94],
];

foreach ($data as $rowIndex => $row) {
    $phpRow = $rowIndex + 2;
    $sheet->setCellValue('A' . $phpRow, $row[0]);
    $sheet->setCellValue('B' . $phpRow, $row[1]);
    $sheet->setCellValue('C' . $phpRow, $row[2]);
    $sheet->setCellValue('D' . $phpRow, $row[3]);

    // Format currency columns
    $sheet->getStyle('B' . $phpRow)->getNumberFormat()->setFormatCode('$#,##0.00');
    $sheet->getStyle('D' . $phpRow)->getNumberFormat()->setFormatCode('$#,##0.00');
}

// Auto-filter on the header row
$sheet->setAutoFilter('A1:D1');

// Write to file
$writer = new Xlsx($spreadsheet);
$outputPath = __DIR__ . '/output.xlsx';
$writer->save($outputPath);

echo 'Spreadsheet written to: ' . $outputPath . PHP_EOL;
