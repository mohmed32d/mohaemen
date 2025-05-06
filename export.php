<?php
define('ALLOW_EXPORT', true);
if (!defined('ALLOW_EXPORT')) {
    die('No direct script access allowed');
}
include(__DIR__ . '/header.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    header("Location: auth.php");
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (empty($_GET['id']) || (int)$_GET['id'] <= 0) {
    set_flash_message('معرف الفحص غير محدد أو غير صالح', 'error');
    header("Location: " . BASE_URL . "scan.php");
    exit();
}

$scan_id = (int)$_GET['id'];
$scan = get_scan_by_id($scan_id, $_SESSION['user']['id']);

if (!$scan) {
    set_flash_message('الفحص المطلوب غير موجود أو ليس لديك صلاحية الوصول إليه', 'error');
    header("Location: " . BASE_URL . "scan.php");
    exit();
}
require_once __DIR__ . '/tcpdf/tcpdf.php';
class ArabicPDF extends TCPDF {
    public function Header() {
        $this->SetFont('aealarabiya', 'B', 12);
        $this->Cell(0, 15, 'تقرير فحص الأمان - CyberScan', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('aealarabiya', 'I', 8);
        $this->Cell(0, 10, 'الصفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}
$pdf = new ArabicPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('CyberScan System');
$pdf->SetAuthor($_SESSION['user']['username']);
$pdf->SetTitle('تقرير الفحص - ' . $scan['target']);
$pdf->SetSubject('نتائج فحص الأمان');
$pdf->AddPage();
$pdf->SetRTL(true);

$html = '
<style>
    .header { color: #2a5298; text-align: center; font-size: 18px; }
    .subheader { color: #4a6ea9; font-size: 14px; }
    .table-header { background-color: #2a5298; color: white; text-align: center; }
    .port-open { background-color: #f8d7da; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
    pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>

<h1 class="header">تقرير فحص الأمان</h1>
<h2 class="subheader" style="text-align:center;">' . htmlspecialchars(APP_NAME) . '</h2>
<hr>
<table border="0" cellpadding="5">
    <tr>
        <td width="30%"><strong>الهدف:</strong></td>
        <td width="70%">' . htmlspecialchars($scan['target']) . '</td>
    </tr>
    <tr>
        <td><strong>نوع الفحص:</strong></td>
        <td>' . htmlspecialchars($scan['scan_type']) . '</td>
    </tr>
    <tr>
        <td><strong>تاريخ الفحص:</strong></td>
        <td>' . date('Y-m-d H:i', strtotime($scan['created_at'])) . '</td>
    </tr>
    <tr>
        <td><strong>حالة الفحص:</strong></td>
        <td>' . ($scan['results']['success'] ? 'ناجح' : 'فشل') . '</td>
    </tr>
</table>
<hr>
<h3 style="color:#2a5298;">النتائج التفصيلية</h3>';

if ($scan['scan_type'] === 'nmap') {
    $html .= '
    <h4>ملخص المنافذ المفتوحة</h4>
    <table border="1">
        <tr class="table-header">
            <th width="15%">المنفذ</th>
            <th width="15%">النوع</th>
            <th width="20%">الحالة</th>
            <th width="50%">الخدمة</th>
        </tr>';
    
    foreach (($scan['results']['ports'] ?? []) as $port) {
        $html .= '
        <tr class="' . ($port['status'] === 'open' ? 'port-open' : '') . '">
            <td>' . htmlspecialchars($port['port']) . '</td>
            <td>' . htmlspecialchars($port['protocol']) . '</td>
            <td>' . htmlspecialchars($port['status']) . '</td>
            <td>' . htmlspecialchars($port['service']) . '</td>
        </tr>';
    }

    $html .= '
    </table>
    <h4>النتائج الكاملة</h4>
    <pre>' . htmlspecialchars($scan['results']['output']) . '</pre>';
} elseif ($scan['scan_type'] === 'website') {
    $html .= '
    <h4>معلومات الموقع</h4>
    <p><strong>الرابط:</strong> ' . htmlspecialchars($scan['target']) . '</p>';
    
    if (!empty($scan['results']['headers'])) {
        $html .= '
        <h4>رأس الاستجابة</h4>
        <table border="1">
            <tr class="table-header">
                <th width="30%">الخيار</th>
                <th width="70%">القيمة</th>
            </tr>';
        
        foreach ($scan['results']['headers'] as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $html .= '
            <tr>
                <td>' . htmlspecialchars($key) . '</td>
                <td>' . htmlspecialchars($value) . '</td>
            </tr>';
        }
        $html .= '</table>';
    }

    if (!empty($scan['results']['security'])) {
        $html .= '
        <h4>معلومات الأمان</h4>
        <table border="1">
            <tr>
                <td width="30%"><strong>HTTPS</strong></td>
                <td width="70%">' . ($scan['results']['security']['https'] ? 'مدعوم' : 'غير مدعوم') . '</td>
            </tr>';

        if (!empty($scan['results']['security']['certificate'])) {
            $cert = $scan['results']['security']['certificate'];
            $html .= '
            <tr>
                <td><strong>شهادة SSL</strong></td>
                <td>
                    <strong>صادرة عن:</strong> ' . htmlspecialchars($cert['issuer'] ?? 'غير معروف') . '<br>
                    <strong>صالحة من:</strong> ' . htmlspecialchars($cert['valid_from'] ?? 'غير معروف') . '<br>
                    <strong>صالحة حتى:</strong> ' . htmlspecialchars($cert['valid_to'] ?? 'غير معروف') . '
                </td>
            </tr>';
        }

        $html .= '</table>';
    }
}

$pdf->writeHTML($html, true, false, true, false, '');
$filename = 'scan_report_' . preg_replace('/[^a-zA-Z0-9]/', '_', $scan['target']) . '.pdf';
$pdf->Output($filename, 'D');
exit();
