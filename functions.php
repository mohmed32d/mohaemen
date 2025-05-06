<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل المكتبات الأساسية
try {
    require_once __DIR__ . '/vendor/autoload.php';
} catch (Exception $e) {
    die('حدث خطأ في تحميل المكتبات المطلوبة: ' . $e->getMessage());
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// تجنب التحميل المزدوج
if (defined('FUNCTIONS_LOADED')) return;
define('FUNCTIONS_LOADED', true);

// زيادة وقت التنفيذ للمهام الطويلة
set_time_limit(600);

// التحقق من توفر الدوال المطلوبة
if (!function_exists('exec')) {
    die('الدالة exec غير مفعلة في إعدادات PHP');
}

// تحميل ملفات التهيئة
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * فئة لمعالجة عمليات الأمان
 */
class Security {
    public static function cleanInput($data) {
        if (is_array($data)) {
            return array_map('self::cleanInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    public static function isValidTarget(string $target): bool {
        return filter_var($target, FILTER_VALIDATE_IP) || 
               filter_var($target, FILTER_VALIDATE_URL) ||
               preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $target);
    }

    public static function isPrivateIP(string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;

        $long_ip = ip2long($ip);
        return
            ($long_ip >= ip2long('10.0.0.0') && $long_ip <= ip2long('10.255.255.255')) ||
            ($long_ip >= ip2long('172.16.0.0') && $long_ip <= ip2long('172.31.255.255')) ||
            ($long_ip >= ip2long('192.168.0.0') && $long_ip <= ip2long('192.168.255.255')) ||
            ($long_ip === ip2long('127.0.0.1'));
    }

    public static function sanitizeCommand(string $input): string {
        return escapeshellarg(preg_replace('/[^a-zA-Z0-9.-]/', '', $input));
    }
}

/**
 * فئة لإدارة المصادقة الثنائية
 */
class TwoFactorAuth {
    private const CODE_LENGTH = 6;
    private const CODE_EXPIRE = 300; // 5 دقائق بالثواني

    public static function generateCode(): string {
        return str_pad(random_int(0, pow(10, self::CODE_LENGTH)-1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function sendVerificationCode(string $email): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("عنوان البريد غير صالح: $email");
            return false;
        }

        $code = self::generateCode();
        $mail = self::prepareMailer();

        try {
            $mail->setFrom(MAIL_FROM, APP_NAME);
            $mail->addAddress($email);
            $mail->Subject = 'رمز التحقق بخطوتين - ' . APP_NAME;
            $mail->Body = self::getEmailTemplate($code);
            $mail->AltBody = "رمز التحقق الخاص بك هو: $code (صالح لمدة 5 دقائق)";

            if ($mail->send()) {
                self::storeCodeInSession($code, $email);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("2FA Email Error: " . $e->getMessage());
            return false;
        }
    }

    private static function prepareMailer(): PHPMailer {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_PASS;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;
        return $mail;
    }

    private static function getEmailTemplate(string $code): string {
        return "
            <h2>رمز التحقق الخاص بك</h2>
            <p>رمز التحقق الخاص بك هو: <strong>$code</strong></p>
            <p>صالح لمدة 5 دقائق</p>
            <p>إذا لم تطلب هذا الرمز، يرجى تجاهل هذه الرسالة.</p>
        ";
    }

    private static function storeCodeInSession(string $code, string $email): void {
        $_SESSION['2fa_code'] = $code;
        $_SESSION['2fa_email'] = $email;
        $_SESSION['2fa_expire'] = time() + self::CODE_EXPIRE;
    }

    public static function verifyCode(string $userInput): bool {
        if (!isset($_SESSION['2fa_code'], $_SESSION['2fa_expire'])) {
            return false;
        }

        $isValid = (
            $_SESSION['2fa_code'] === $userInput &&
            time() < $_SESSION['2fa_expire']
        );

        if ($isValid) {
            self::clear2FASession();
            $_SESSION['2fa_verified'] = true;
            return true;
        }

        return false;
    }

    public static function resendCode(): bool {
        return isset($_SESSION['2fa_email']) && 
               self::sendVerificationCode($_SESSION['2fa_email']);
    }

    public static function clear2FASession(): void {
        unset(
            $_SESSION['2fa_code'],
            $_SESSION['2fa_email'],
            $_SESSION['2fa_expire'],
            $_SESSION['2fa_attempts']
        );
    }

    public static function is2FARequired(): bool {
        return isset($_SESSION['2fa_required']) && $_SESSION['2fa_required'] === true;
    }
}

/**
 * وظيفة مسح Nuclei
 */

if (!function_exists('get_flash_message')) {
    function get_flash_message(bool $clear = true): ?array {
        if (empty($_SESSION['flash_messages'])) {
            return null;
        }

        $messages = array_filter($_SESSION['flash_messages'], function($msg) {
            return $msg['expires_at'] > time();
        });

        if ($clear) {
            unset($_SESSION['flash_messages']);
        } else {
            $_SESSION['flash_messages'] = array_values($messages);
        }

        return !empty($messages) ? $messages : null;
    }
}

if (!function_exists('display_flash_messages')) {
    function display_flash_messages(): void {
        $messages = get_flash_message();
        if (!$messages) return;

        foreach ($messages as $message) {
            $alertClass = match ($message['type']) {
                'error' => 'alert-danger',
                'warning' => 'alert-warning',
                'info' => 'alert-info',
                default => 'alert-success',
            };

            echo '<div class="alert '.$alertClass.' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($message['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
        }
    }
}

/**
 * وظائف المصادقة
 */
if (!function_exists('is_fully_authenticated')) {
    function is_fully_authenticated(): bool {
        return isset($_SESSION['user']) && 
               is_array($_SESSION['user']) &&
               !empty($_SESSION['user']['id']) &&
               (!TwoFactorAuth::is2FARequired() || 
               (isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true));
    }
}

if (!function_exists('require_2fa_verification')) {
    function require_2fa_verification(): void {
        if (TwoFactorAuth::is2FARequired() && 
            (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true)) {
            if (basename($_SERVER['PHP_SELF']) !== 'verify_2fa.php') {
                header('Location: verify_2fa.php');
                exit();
            }
        }
    }
}

/**
 * وظائف الرسائل والتحويلات
 */
function set_flash_message(string $message, string $type = 'success'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['flash_messages'][] = [
        'message' => $message,          // نص الرسالة
        'type' => $type,                // نوع الرسالة: success, error, warning...
        'created_at' => time(),         // وقت الإنشاء
        'expires_at' => time() + 60     // تنتهي بعد 60 ثانية
    ];
}


function get_flash_message(bool $clear = true): ?array {
    if (empty($_SESSION['flash_messages'])) {
        return null;
    }

    $messages = array_filter($_SESSION['flash_messages'], function($msg) {
        return $msg['expires_at'] > time();
    });

    if ($clear) {
        unset($_SESSION['flash_messages']);
    } else {
        $_SESSION['flash_messages'] = array_values($messages);
    }

    return !empty($messages) ? $messages : null;
}

function display_flash_messages(): void {
    $messages = get_flash_message();
    if (!$messages) return;

    foreach ($messages as $message) {
        $alertClass = match ($message['type']) {
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-success',
        };

        echo '<div class="alert '.$alertClass.' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

function redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    }
    echo "<script>window.location.href='$url';</script>";
    exit();
}

function is_logged_in(): bool {
    return isset($_SESSION['user']) && 
           !empty($_SESSION['user']['id']) && 
           !TwoFactorAuth::is2FARequired();
}

/**
 * فئة المصادقة
 */
class Auth {
    public static function authenticate(string $username, string $password): ?array {
        try {
            $db = Database::getInstance();
            $user = $db->fetch(
                "SELECT id, username, password, email FROM users 
                 WHERE username = ? LIMIT 1", 
                [$username]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    error_log("البريد الإلكتروني للمستخدم غير صالح: " . $user['email']);
                    return null;
                }
                
                unset($user['password']);
                return $user;
            }
            return null;
        } catch (PDOException $e) {
            error_log("Auth Error: " . $e->getMessage());
            return null;
        }
    }

    public static function register(string $username, string $password, string $email): ?array {
        try {
            $db = Database::getInstance();
            
            if (self::userExists($db, $username, $email)) {
                return null;
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $success = $db->query(
                "INSERT INTO users (username, password, email) 
                 VALUES (?, ?, ?)",
                [$username, $hashedPassword, $email]
            );
            
            return $success ? [
                'id' => $db->lastInsertId(),
                'username' => $username,
                'email' => $email
            ] : null;
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            return null;
        }
    }

    private static function userExists(Database $db, string $username, string $email): bool {
        return $db->fetchColumn(
            "SELECT COUNT(*) FROM users 
             WHERE username = ? OR email = ?", 
            [$username, $email]
        ) > 0;
    }
}