<?php
/**
 * PDF生成の共通ヘルパー（tFPDF + IPAexゴシックフォントを使用）
 */

require_once __DIR__ . '/../vendor/tfpdf/tfpdf.php';
require_once __DIR__ . '/../vendor/tfpdf/font/unifont/ttfonts.php';

/**
 * 日本語対応済みの tFPDF インスタンスを生成する
 */
function make_pdf($orientation = 'P') {
    $pdf = new tFPDF($orientation, 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 18);
    // tFPDFの既定フォントパスは tfpdf.php と同階層の font/ ディレクトリ。
    // vendor/tfpdf/font/unifont/ipaexg.ttf をそのまま参照できる。
    $pdf->AddFont('ipaexg', '', 'ipaexg.ttf', true);
    $pdf->AddFont('ipaexg', 'B', 'ipaexgb.ttf', true); // Bold用に同フォントを別ファイル名で複製登録（tFPDFのキャッシュ衝突回避）
    $pdf->SetFont('ipaexg', '', 10.5);
    return $pdf;
}

/**
 * 金額を "¥1,234" 形式にフォーマット
 */
function money($n) {
    return '¥' . number_format((float)$n);
}

/**
 * 自社情報（company_settings）を取得。未設定の場合は空の配列で返す
 */
function get_company_settings($pdo) {
    $row = $pdo->query('SELECT * FROM company_settings WHERE id = 1')->fetch();
    if (!$row) {
        return [
            'company_name' => '', 'logo_path' => null, 'postal_code' => '', 'address' => '', 'tel' => '', 'email' => '',
            'registration_number' => '', 'bank_name' => '', 'branch_name' => '', 'account_type' => '普通',
            'account_number' => '', 'account_holder' => '', 'default_tax_rate' => 10.00, 'invoice_note' => ''
        ];
    }
    return $row;
}

/**
 * ロゴ画像があれば左上に描画する（アスペクト比を保ったまま最大幅/高さに収める）。
 * 実際に描画した幅(mm)を返す（描画しなかった場合は0）。
 */
function pdf_draw_logo($pdf, $company, $x = 18, $y = 14, $maxW = 45, $maxH = 20) {
    if (empty($company['logo_path'])) return 0;
    $path = __DIR__ . '/../' . $company['logo_path'];
    if (!is_file($path)) return 0;

    $size = @getimagesize($path);
    if (!$size) return 0;
    [$pxW, $pxH] = $size;
    if ($pxW <= 0 || $pxH <= 0) return 0;

    $ratio = min($maxW / $pxW, $maxH / $pxH);
    $w = $pxW * $ratio;
    $h = $pxH * $ratio;

    try {
        $pdf->Image($path, $x, $y, $w, $h);
    } catch (Exception $e) {
        return 0;
    }
    return $w;
}

/**
 * PDFをブラウザ表示（inline）として出力してスクリプトを終了する
 */
function output_pdf_inline($pdf, $filename) {
    $pdf->Output('I', $filename);
    exit;
}
