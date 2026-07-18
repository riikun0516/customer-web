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
    'SELECT r.*, cu.name AS customer_name, cu.company AS customer_company
     FROM receipts r LEFT JOIN customers cu ON cu.id = r.customer_id WHERE r.id = ?'
);
$stmt->execute([$id]);
$receipt = $stmt->fetch();
if (!$receipt) {
    http_response_code(404);
    die('領収書が見つかりません');
}

$company = get_company_settings($pdo);

$pdf = make_pdf();
$pdf->AddPage();
$pdf->SetMargins(20, 18, 20);

// ---- ロゴ（あれば左上に表示し、以降のレイアウトを下にずらす） ----
$off = 0;
$logoW = pdf_draw_logo($pdf, $company, 20, 14, 42, 16);
if ($logoW > 0) $off = 18;

$pdf->SetY(18 + $off);
$pdf->SetFont('ipaexg', 'B', 22);
$pdf->Cell(0, 14, '領収書', 0, 1, 'C');
$pdf->Ln(4);

// 発行日・領収書番号（右）
$pdf->SetFont('ipaexg', '', 10);
$pdf->SetXY(140, 22 + $off);
$pdf->Cell(50, 6, '発行日: ' . $receipt['issue_date'], 0, 2, 'L');
$pdf->SetX(140);
$pdf->Cell(50, 6, '領収書番号: ' . ($receipt['receipt_number'] ?? '-'), 0, 2, 'L');

// 宛名
$pdf->SetXY(20, 40 + $off);
$customerLabel = $receipt['customer_company'] ? $receipt['customer_company'] . ' ' . $receipt['customer_name'] : $receipt['customer_name'];
$pdf->SetFont('ipaexg', '', 16);
$pdf->Cell(140, 10, $customerLabel . ' 様', 'B', 1, 'L');

// 金額ボックス
$pdf->Ln(10);
$pdf->SetFont('ipaexg', '', 11);
$pdf->Cell(30, 12, '金額', 1, 0, 'C');
$pdf->SetFont('ipaexg', 'B', 18);
$pdf->Cell(110, 12, money($receipt['amount']) . '　也', 1, 1, 'L');

// 但し書き
$pdf->Ln(6);
$pdf->SetFont('ipaexg', '', 11);
$pdf->Cell(20, 8, '但し', 0, 0, 'L');
$pdf->Cell(0, 8, ($receipt['description'] ?: '') . ' として', 'B', 1, 'L');
$pdf->Ln(4);
$pdf->Cell(0, 8, '上記正に領収いたしました。', 0, 1, 'L');

if ((float)$receipt['amount'] >= 50000) {
    $pdf->SetFont('ipaexg', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->MultiCell(0, 5, '※ 5万円以上の現金領収については、収入印紙の貼付が必要な場合があります。取引形態に応じてご確認ください。');
    $pdf->SetTextColor(0, 0, 0);
}

// 発行者情報（右下）
$pdf->SetY(150 + $off);
$pdf->SetX(120);
$pdf->SetFont('ipaexg', 'B', 11);
$pdf->Cell(70, 6, $company['company_name'], 0, 2, 'L');
$pdf->SetX(120);
$pdf->SetFont('ipaexg', '', 9);
if ($company['postal_code'] || $company['address']) {
    $pdf->Cell(70, 5, trim(($company['postal_code'] ? '〒' . $company['postal_code'] . ' ' : '') . $company['address']), 0, 2, 'L');
    $pdf->SetX(120);
}
if ($company['tel']) {
    $pdf->Cell(70, 5, 'TEL: ' . $company['tel'], 0, 2, 'L');
    $pdf->SetX(120);
}
if ($company['registration_number']) {
    $pdf->Cell(70, 5, '登録番号: ' . $company['registration_number'], 0, 2, 'L');
}

if (!empty($receipt['notes'])) {
    $pdf->SetY(190 + $off);
    $pdf->SetX(20);
    $pdf->SetFont('ipaexg', 'B', 10);
    $pdf->Cell(0, 6, '備考', 0, 1, 'L');
    $pdf->SetFont('ipaexg', '', 9);
    $pdf->MultiCell(0, 6, $receipt['notes'], 0, 'L');
}

output_pdf_inline($pdf, ($receipt['receipt_number'] ?: 'receipt') . '.pdf');
