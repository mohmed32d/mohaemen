<?php
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 3600);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.save_path', __DIR__ . '/sessions');
    session_name('CYBERSCAN_SESSION');
    session_start();
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'CyberScan System');
}

if (!file_exists(__DIR__ . '/sessions')) {
    mkdir(__DIR__ . '/sessions', 0700, true);
}

define('APP_URL', 'http://localhost/cyberscan');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/cyberscan/');
define('DEBUG_MODE', true);
define('NMAP_PATH', 'C:\\Program Files (x86)\\Nmap\\nmap.exe');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'cyberscan_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('NUCLEI_PATH', 'C:\\Users\\theki\\Downloads\\nuclei_3.4.2_windows_amd64\\nuclei.exe');
define('NUCLEI_TEMPLATES', 'C:\\Users\\theki\\Downloads\\nuclei_3.4.2_windows_amd64');
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_password');
define('SMTP_FROM', 'noreply@cyberscan.com');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

$required_files = [
    __DIR__ . '/functions.php',
    __DIR__ . '/db.php'
];

foreach ($required_files as $file) {
    require_once $file;
}

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("ملف مطلوب غير موجود: " . basename($file));
    }
    require_once $file;
}

define('CONFIG_LOADED', true);

if (!function_exists('clean_input')) {
    function clean_input($data) {
        if (is_array($data)) {
            return array_map('clean_input', $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return !empty($_SESSION['user']['id']) && 
               $_SESSION['user']['ip'] === $_SERVER['REMOTE_ADDR'] &&
               $_SESSION['user']['user_agent'] === $_SERVER['HTTP_USER_AGENT'] &&
               $_SESSION['user']['last_activity'] > time() - 3600 &&
               (!isset($_SESSION['2fa_required']) || $_SESSION['2fa_required'] === false);
    }
}
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (DEBUG_MODE) {
        error_log(sprintf(
            "[%s] Error %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errno,
            $errstr,
            $errfile,
            $errline
        ));
    }
    return true;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("[Fatal Error] " . print_r($error, true));
        if (!DEBUG_MODE) {
            http_response_code(500);
            die(file_get_contents(__DIR__ . '/views/errors/500.html'));
        }
    }
});

try {
    $db = Database::getInstance()->getConnection();
    $db->query("SELECT 1")->execute();
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    } else {
        die(file_get_contents(__DIR__ . '/views/errors/database.html'));
    }
}
?>