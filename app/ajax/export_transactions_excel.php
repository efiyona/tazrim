<?php
require('../../path.php');
include(ROOT_PATH . '/app/database/db.php');
require_once ROOT_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$home_id = (int)($_SESSION['home_id'] ?? 0);
if ($home_id <= 0) {
    http_response_code(403);
    exit('גישה נדחתה.');
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$scope = $_GET['scope'] ?? 'all';
$user_id = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$min_amount = isset($_GET['min_amount']) && $_GET['min_amount'] !== '' ? (float)$_GET['min_amount'] : null;
$max_amount = isset($_GET['max_amount']) && $_GET['max_amount'] !== '' ? (float)$_GET['max_amount'] : null;
$search_text = trim($_GET['search_text'] ?? '');
$category_ids_input = $_GET['category_ids'] ?? [];

// לפי אפיון הממשק: תמיד כוללים את עמודות המשתמש/קטגוריה ושורת הסיכום.
$include_summary = 1;
$include_user = 1;
$include_category = 1;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    http_response_code(422);
    exit('טווח תאריכים לא תקין.');
}

if (strtotime($start_date) === false || strtotime($end_date) === false || $start_date > $end_date) {
    http_response_code(422);
    exit('טווח תאריכים לא חוקי.');
}

if (!in_array($scope, ['all', 'expense', 'income'], true)) {
    $scope = 'all';
}

$category_ids = [];
if (is_array($category_ids_input)) {
    foreach ($category_ids_input as $cat_id) {
        $cat_int = (int)$cat_id;
        if ($cat_int > 0) {
            $category_ids[] = $cat_int;
        }
    }
}
$category_ids = array_values(array_unique($category_ids));

$sql = "SELECT t.transaction_date, t.type, t.description, t.amount, c.name AS category_name, u.first_name AS user_name
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.home_id = ? AND t.transaction_date BETWEEN ? AND ?";

$bind_types = 'iss';
$bind_values = [$home_id, $start_date, $end_date];

if ($scope !== 'all') {
    $sql .= " AND t.type = ?";
    $bind_types .= 's';
    $bind_values[] = $scope;
}

if (!empty($category_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $sql .= " AND t.category IN ($in_placeholders)";
    $bind_types .= str_repeat('i', count($category_ids));
    foreach ($category_ids as $cat_id) {
        $bind_values[] = $cat_id;
    }
}

if ($user_id !== null && $user_id > 0) {
    $sql .= " AND t.user_id = ?";
    $bind_types .= 'i';
    $bind_values[] = $user_id;
}

if ($min_amount !== null) {
    $sql .= " AND t.amount >= ?";
    $bind_types .= 'd';
    $bind_values[] = $min_amount;
}

if ($max_amount !== null) {
    $sql .= " AND t.amount <= ?";
    $bind_types .= 'd';
    $bind_values[] = $max_amount;
}

if ($search_text !== '') {
    $sql .= " AND (t.description LIKE ? OR c.name LIKE ? OR u.first_name LIKE ?)";
    $bind_types .= 'sss';
    $search_like = '%' . $search_text . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$sql .= " ORDER BY t.transaction_date ASC, t.id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit('שגיאה בהכנת שאילתה.');
}

$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet_title = 'דוח_' . $start_date . '_' . $end_date;
$sheet_title = str_replace(['\\', '/', '?', '*', ':', '[', ']'], '-', $sheet_title);
$sheet->setTitle(mb_substr($sheet_title, 0, 31));
$sheet->setRightToLeft(true);

$headers = ['תאריך', 'סוג'];
if ($include_category) {
    $headers[] = 'קטגוריה';
}
$headers[] = 'תיאור';
$headers[] = 'סכום';
if ($include_user) {
    $headers[] = 'משתמש';
}

$column_index = 1;
foreach ($headers as $header) {
    $cell = Coordinate::stringFromColumnIndex($column_index) . '1';
    $sheet->setCellValue($cell, $header);
    $column_index++;
}

$last_header_col = $column_index - 1;
$last_header_col_letter = Coordinate::stringFromColumnIndex($last_header_col);
$sheet->getStyle('A1:' . $last_header_col_letter . '1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E8F5E9']
    ]
]);

$row_index = 2;
$income_total = 0.0;
$expense_total = 0.0;

foreach ($transactions as $transaction) {
    $type_label = $transaction['type'] === 'income' ? 'הכנסה' : 'הוצאה';
    $amount = (float)$transaction['amount'];
    if ($transaction['type'] === 'income') {
        $income_total += $amount;
    } else {
        $expense_total += $amount;
    }

    $col = 1;
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, date('d/m/Y', strtotime($transaction['transaction_date'])));
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, $type_label);

    if ($include_category) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, (string)($transaction['category_name'] ?? ''));
    }

    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, (string)($transaction['description'] ?? ''));
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, $amount);

    if ($include_user) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $row_index, (string)($transaction['user_name'] ?? ''));
    }

    $row_index++;
}

if ($row_index > 2) {
    $amount_col_index = 4 + ($include_category ? 1 : 0);
    $amount_col_letter = Coordinate::stringFromColumnIndex($amount_col_index);
    $sheet->getStyle($amount_col_letter . '2:' . $amount_col_letter . ($row_index - 1))
        ->getNumberFormat()
        ->setFormatCode('#,##0.00');
}

if ($include_summary) {
    $row_index += 1;
    $sheet->setCellValue('A' . $row_index, 'סיכום');
    $sheet->getStyle('A' . $row_index)->getFont()->setBold(true);
    $row_index++;

    $sheet->setCellValue('A' . $row_index, 'סה"כ הכנסות');
    $sheet->setCellValue('B' . $row_index, $income_total);
    $row_index++;

    $sheet->setCellValue('A' . $row_index, 'סה"כ הוצאות');
    $sheet->setCellValue('B' . $row_index, $expense_total);
    $row_index++;

    $sheet->setCellValue('A' . $row_index, 'מאזן');
    $sheet->setCellValue('B' . $row_index, $income_total - $expense_total);
    $sheet->getStyle('B' . ($row_index - 2) . ':B' . $row_index)
        ->getNumberFormat()
        ->setFormatCode('#,##0.00');
}

for ($i = 1; $i <= $last_header_col; $i++) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$sheet->getDefaultRowDimension()->setRowHeight(22);
$sheet->freezePane('A2');

$filename = 'transactions_report_' . $start_date . '_to_' . $end_date . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
