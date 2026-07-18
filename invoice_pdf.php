<?php
require_once __DIR__ . '/includes/db.php';
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf_helper.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT i.*, cu.name AS customer_name, cu.company AS customer_company, cu.address AS customer_address
     FROM invoices i LEFT JOIN customers cu ON cu.id = i.customer_id WHERE i.id = ?'
);
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    die('請求書が見つかりません');
}

$stmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$company = get_company_settings($pdo);

$pdf = make_pdf();
$pdf->AddPage();
$pdf->SetMargins(18, 16, 18);

// ---- ロゴ（あれば左上に表示し、以降のレイアウトを下にずらす） ----
$off = 0;
$logoW = pdf_draw_logo($pdf, $company, 18, 12, 42, 16);
if ($logoW > 0) $off = 18;

// ---- タイトル ----
$pdf->SetY(16 + $off);
$pdf->SetFont('ipaexg', 'B', 20);
$pdf->Cell(0, 12, '請求書', 0, 1, 'C');
$pdf->Ln(2);

// ---- 請求書番号・発行日など（右上） ----
$pdf->SetFont('ipaexg', '', 10);
$rightX = 140;
$pdf->SetXY($rightX, 22 + $off);
$pdf->Cell(52, 6, '請求書番号: ' . ($invoice['invoice_number'] ?? '-'), 0, 2, 'L');
$pdf->SetX($rightX);
$pdf->Cell(52, 6, '発行日: ' . e_pdf($invoice['issue_date']), 0, 2, 'L');
$pdf->SetX($rightX);
$pdf->Cell(52, 6, '支払期限: ' . ($invoice['due_date'] ?: '-'), 0, 2, 'L');

// ---- 宛先（左） ----
$pdf->SetXY(18, 22 + $off);
$pdf->SetFont('ipaexg', '', 13);
$customerLabel = $invoice['customer_company'] ? $invoice['customer_company'] . ' ' . $invoice['customer_name'] : $invoice['customer_name'];
$pdf->Cell(110, 8, $customerLabel . ' 様', 0, 1, 'L');
$pdf->SetFont('ipaexg', '', 9);
$pdf->Cell(110, 6, $invoice['customer_address'] ?: '', 0, 1, 'L');

// ---- 発行者情報（右下寄せ、宛先の下） ----
$pdf->SetXY($rightX, 46 + $off);
$pdf->SetFont('ipaexg', '', 9);
$pdf->Cell(52, 5, $company['company_name'], 0, 2, 'L');
if ($company['postal_code'] || $company['address']) {
    $pdf->SetX($rightX);
    $pdf->Cell(52, 5, trim(($company['postal_code'] ? '〒' . $company['postal_code'] . ' ' : '') . $company['address']), 0, 2, 'L');
}
if ($company['tel']) {
    $pdf->SetX($rightX);
    $pdf->Cell(52, 5, 'TEL: ' . $company['tel'], 0, 2, 'L');
}
if ($company['registration_number']) {
    $pdf->SetX($rightX);
    $pdf->Cell(52, 5, '登録番号: ' . $company['registration_number'], 0, 2, 'L');
}

// ---- 合計金額の帯 ----
$pdf->SetY(72 + $off);
$pdf->SetX(18);
$pdf->SetFillColor(240, 242, 238);
$pdf->SetFont('ipaexg', 'B', 14);
$pdf->Cell(174, 12, 'ご請求金額　' . money($invoice['total_amount']) . '（税込）', 1, 1, 'L', true);
$pdf->Ln(6);

// ---- 明細テーブル ----
$pdf->SetFont('ipaexg', 'B', 10);
$pdf->SetFillColor(60, 100, 80);
$pdf->SetTextColor(255, 255, 255);
$colW = [86, 22, 33, 33];
$pdf->Cell($colW[0], 8, '品目', 1, 0, 'L', true);
$pdf->Cell($colW[1], 8, '数量', 1, 0, 'C', true);
$pdf->Cell($colW[2], 8, '単価', 1, 0, 'R', true);
$pdf->Cell($colW[3], 8, '金額', 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('ipaexg', '', 10);
foreach ($items as $it) {
    $pdf->Cell($colW[0], 8, $it['name'], 1, 0, 'L');
    $pdf->Cell($colW[1], 8, rtrim(rtrim((string)$it['quantity'], '0'), '.'), 1, 0, 'C');
    $pdf->Cell($colW[2], 8, number_format((float)$it['unit_price']), 1, 0, 'R');
    $pdf->Cell($colW[3], 8, number_format((float)$it['amount']), 1, 1, 'R');
}

// ---- 小計・税・合計 ----
$pdf->Ln(2);

function pdf_summary_row($pdf, $leftX, $blankW, $labelW, $valueW, $label, $value, $bold = false) {
    $pdf->SetX($leftX);
    $pdf->SetFont('ipaexg', $bold ? 'B' : '', 10);
    $pdf->Cell($blankW, 8, '', 0, 0);
    $pdf->Cell($labelW, 8, $label, 1, 0, 'C');
    $pdf->Cell($valueW, 8, $value, 1, 1, 'R');
}
// 明細テーブルと同じ合計174mm幅（86+22+33+33）に揃える
pdf_summary_row($pdf, 18, $colW[0] + $colW[1], $colW[2], $colW[3], '小計', money($invoice['subtotal']));
pdf_summary_row($pdf, 18, $colW[0] + $colW[1], $colW[2], $colW[3], '消費税（' . rtrim(rtrim((string)$invoice['tax_rate'], '0'), '.') . '%）', money($invoice['tax_amount']));
pdf_summary_row($pdf, 18, $colW[0] + $colW[1], $colW[2], $colW[3], '合計', money($invoice['total_amount']), true);

// ---- 振込先 ----
$pdf->Ln(10);
$pdf->SetFont('ipaexg', 'B', 11);
$pdf->Cell(0, 7, 'お振込先', 0, 1, 'L');
$pdf->SetFont('ipaexg', '', 10);
$bankLine = trim(sprintf(
    '%s %s %s %s %s',
    $company['bank_name'] ?: '(未設定)',
    $company['branch_name'] ?: '',
    $company['account_type'] ?: '',
    $company['account_number'] ? '口座番号 ' . $company['account_number'] : '',
    $company['account_holder'] ? '名義 ' . $company['account_holder'] : ''
));
$pdf->MultiCell(0, 7, $bankLine, 1, 'L');

// ---- 備考 ----
if (!empty($invoice['notes'])) {
    $pdf->Ln(6);
    $pdf->SetFont('ipaexg', 'B', 10);
    $pdf->Cell(0, 6, '備考', 0, 1, 'L');
    $pdf->SetFont('ipaexg', '', 9);
    $pdf->MultiCell(0, 6, $invoice['notes'], 0, 'L');
}

function e_pdf($v) {
    return $v ?: '-';
}

output_pdf_inline($pdf, ($invoice['invoice_number'] ?: 'invoice') . '.pdf');
