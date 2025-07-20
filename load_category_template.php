<?php
require 'db.php'; // or config file that loads Excel reader
require 'vendor/autoload.php'; // if using PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load("Sample DB Approved Budget AY24-25.xlsx");
$sheet = $spreadsheet->getSheetByName("Code Template");
$data = $sheet->toArray();

$category_gl_data = [];

for ($i = 5; $i < count($data); $i++) {
    $row = $data[$i];
    $category = trim($row[6] ?? '');
    $gl = trim($row[8] ?? '');
    $desc = trim($row[9] ?? '');
    if ($category && $gl && $desc) {
        $category_gl_data[$category][] = ['gl_code' => $gl, 'gl_description' => $desc];
    }
}
