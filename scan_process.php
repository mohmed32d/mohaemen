<?php
declare(strict_types=1);

function secure_session_start(): void {
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_name('CYBERSCAN_SESSION');
        session_start();
    }
}

secure_session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    set_flash_message('يجب تسجيل الدخول أولاً', 'error');
    redirect('auth.php?action=login');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('طريقة الطلب غير مسموحة', 'error');
    redirect('scan.php');
}

$required_fields = ['target', 'scan_type'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        set_flash_message('جميع الحقول مطلوبة', 'error');
        redirect('scan.php');
    }
}

$target = Security::cleanInput($_POST['target']);
$scan_type = Security::cleanInput($_POST['scan_type']);

if (!Security::isValidTarget($target)) {
    set_flash_message('الهدف المدخل غير صالح', 'error');
    redirect('scan.php');
}

$allowed_scan_types = ['nmap', 'nuclei'];
if (!in_array($scan_type, $allowed_scan_types)) {
    set_flash_message('نوع الفحص غير مدعوم', 'error');
    redirect('scan.php');
}

if ($scan_type === 'nmap' && Security::isPrivateIP($target)) {
    set_flash_message('لا يمكن فحص عناوين داخلية', 'error');
    redirect('scan.php');
}

$user_id = $_SESSION['user']['id'];
$session_token = $_SESSION['session_token'];

session_write_close();
set_time_limit(300); 

if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Encoding: none');
try {
    switch ($scan_type) {
        case 'nmap':
            $nmap_options = "-T4 --min-rate 1000 --max-retries 1";
            $results = scan_with_nmap($target, $nmap_options);
            break;
            
        case 'nuclei':
            $results = scan_with_nuclei($target);
            break;
            
        default:
            throw new Exception('نوع الفحص غير معروف');
    }
    
    secure_session_start();
    
    if (!isset($_SESSION['user']['id'])) {
        throw new Exception('انتهت صلاحية الجلسة');
    }
    
    if ($_SESSION['session_token'] !== $session_token) {
        throw new Exception('تغيير غير متوقع في الجلسة');
    }
    
    $scan_id = save_scan_result(
        $user_id,
        $target,
        $results,
        $scan_type
    );
    
    if (!$scan_id) {
        throw new Exception('فشل حفظ النتائج');
    }
    
    redirect("report.php?id=$scan_id");
    
} catch (Exception $e) {
    secure_session_start();
    set_flash_message('فشل عملية الفحص: ' . $e->getMessage(), 'error');
    error_log('Scan Error [' . $user_id . ']: ' . $e->getMessage());
    redirect('scan.php');
}