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
    'SELECT c.*, cu.name AS customer_name, cu.company AS customer_company, cu.address AS customer_address
     FROM contracts c LEFT JOIN customers cu ON cu.id = c.customer_id WHERE c.id = ?'
);
$stmt->execute([$id]);
$contract = $stmt->fetch();
if (!$contract) {
    http_response_code(404);
    die('契約書が見つかりません');
}

$company = get_company_settings($pdo);
$customerLabel = $contract['customer_company'] ? $contract['customer_company'] . ' ' . $contract['customer_name'] : $contract['customer_name'];

$pdf = make_pdf();
$pdf->AddPage();
$pdf->SetMargins(20, 16, 20);

// ---- ロゴ ----
$off = 0;
$logoW = pdf_draw_logo($pdf, $company, 20, 12, 42, 16);
if ($logoW > 0) $off = 18;

// ---- タイトル ----
$pdf->SetY(16 + $off);
$pdf->SetFont('ipaexg', 'B', 20);
$pdf->Cell(0, 12, '契約書', 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('ipaexg', '', 10);
$pdf->Cell(0, 6, '契約書番号: ' . ($contract['contract_number'] ?? '-'), 0, 1, 'R');
$pdf->Ln(4);

// ---- 前文 ----
$pdf->SetFont('ipaexg', '', 10.5);
$preamble = sprintf(
    '%s（以下「甲」という）と、%s（以下「乙」という）は、「%s」（以下「本契約」という）について、下記の通り契約を締結する。',
    $customerLabel, $company['company_name'], $contract['title']
);
$pdf->MultiCell(0, 6.5, $preamble, 0, 'L');
$pdf->Ln(4);

// ---- 契約概要 ----
$pdf->SetFont('ipaexg', 'B', 10);
$pdf->SetFillColor(240, 242, 238);
$summaryRows = [
    ['契約期間', trim(($contract['start_date'] ?: '未定') . '　〜　' . ($contract['end_date'] ?: '未定'))],
];
if ($contract['amount'] !== null) {
    $summaryRows[] = ['契約金額', money($contract['amount'])];
}
if (!empty($contract['payment_terms'])) {
    $summaryRows[] = ['支払条件', $contract['payment_terms']];
}
foreach ($summaryRows as $row) {
    $pdf->Cell(40, 8, $row[0], 1, 0, 'C', true);
    $pdf->SetFont('ipaexg', '', 10);
    $pdf->Cell(0, 8, $row[1], 1, 1, 'L');
    $pdf->SetFont('ipaexg', 'B', 10);
}
$pdf->Ln(6);

// ---- 契約条項本文 ----
if (!empty($contract['body'])) {
    $pdf->SetFont('ipaexg', '', 10);
    $pdf->MultiCell(0, 6.5, $contract['body'], 0, 'L');
    $pdf->Ln(6);
}

// ---- 署名欄 ----
if ($pdf->GetY() > 230) {
    $pdf->AddPage();
}
$pdf->SetFont('ipaexg', '', 10);
$pdf->Cell(0, 8, '上記契約の証として本書2通を作成し、甲乙記名押印の上、各自1通を保有する。', 0, 1, 'L');
$pdf->Ln(4);
$pdf->Cell(0, 7, '締結日:　　　　　年　　月　　日', 0, 1, 'L');
$pdf->Ln(8);

$colW = 85;
$startY = $pdf->GetY();

// 甲（顧客）
$pdf->SetXY(20, $startY);
$pdf->SetFont('ipaexg', 'B', 10);
$pdf->Cell($colW, 7, '甲', 'B', 1, 'L');
$pdf->SetX(20);
$pdf->SetFont('ipaexg', '', 9);
$pdf->Cell($colW, 6, $customerLabel, 0, 1, 'L');
$pdf->SetX(20);
$pdf->Cell($colW, 6, $contract['customer_address'] ?: '', 0, 1, 'L');
$pdf->SetX(20);
$pdf->Cell($colW, 14, '（署名・捺印）', 0, 1, 'L');

// 乙（自社）
$rightX = 20 + $colW + 10;
$pdf->SetXY($rightX, $startY);
$pdf->SetFont('ipaexg', 'B', 10);
$pdf->Cell($colW, 7, '乙', 'B', 1, 'L');
$pdf->SetX($rightX);
$pdf->SetFont('ipaexg', '', 9);
$pdf->Cell($colW, 6, $company['company_name'], 0, 1, 'L');
$pdf->SetX($rightX);
$pdf->Cell($colW, 6, trim(($company['postal_code'] ? '〒' . $company['postal_code'] . ' ' : '') . $company['address']), 0, 1, 'L');
$pdf->SetX($rightX);
$pdf->Cell($colW, 14, '（署名・捺印）', 0, 1, 'L');

output_pdf_inline($pdf, ($contract['contract_number'] ?: 'contract') . '.pdf');
