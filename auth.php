<?php
// تمكين عرض الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 1);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// مسار الجذر
define('BASE_PATH', __DIR__);

// تحميل الملفات الأساسية
$required_files = [
    BASE_PATH . '/config.php',
    BASE_PATH . '/functions.php'
];

foreach ($required_files as $file) {
    require_once $file;
}


foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("ملف مطلوب غير موجود: " . basename($file));
    }
    require $file;
}

// معالجة الإجراءات
$action = $_GET['action'] ?? 'login';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_login();
} elseif ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_register();
} else {
    display_auth_page($action);
}

// تحسين دالة handle_login()
function handle_login() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = Auth::authenticate($username, $password);
    
    if ($user) {
        // إعداد بيانات الجلسة الأساسية
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'last_activity' => time()
        ];
        
        // إعداد متطلبات 2FA
        $_SESSION['2fa_required'] = true;
        $_SESSION['2fa_verified'] = false; // تمت إضافة هذه العلامة
        $_SESSION['2fa_code'] = rand(100000, 999999);
        
        // إرسال الرمز
        send_verification_email($user['email'], $_SESSION['2fa_code']);
        
        // التوجيه لصفحة التحقق
        redirect('verify_2fa.php');
    } else {
        set_flash_message('بيانات الدخول غير صحيحة', 'error');
        redirect('auth.php?action=login');
    }
}

function handle_register() {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (empty($username)) $errors[] = 'اسم المستخدم مطلوب';
    if (empty($email)) $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if ($password !== $confirm) $errors[] = 'كلمتا المرور غير متطابقتين';

    if (!empty($errors)) {
        set_flash_message(implode('<br>', $errors), 'error');
        redirect('auth.php?action=register');
    }

    $result = Auth::register($username, $password, $email);
    
    if ($result) {
        set_flash_message('تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول', 'success');
        redirect('auth.php?action=login');
    } else {
        set_flash_message('حدث خطأ أثناء إنشاء الحساب. قد يكون اسم المستخدم موجوداً مسبقاً', 'error');
        redirect('auth.php?action=register');
    }
}
function display_auth_page($type) {
    $flash = get_flash_message();
    $is_login = ($type === 'login');
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
    <link rel="stylesheet" href="/cyberscan/assets/css/style.css">
    <script src="/cyberscan/assets/js/main.js"></script>
    <script src="/cyberscan/assets/js/particles.js"></script>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars(APP_NAME) ?> - <?= $is_login ? 'تسجيل الدخول' : 'تسجيل جديد' ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary-color: #2a5298;
                --secondary-color: #1e3c72;
            }
            body.auth-page {
                background: #f5f7fa;
                font-family: 'Tajawal', sans-serif;
            }
            .auth-container {
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            }
            .auth-box {
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
                padding: 2rem;
            }
            .auth-header {
                text-align: center;
                margin-bottom: 1.5rem;
            }
            .auth-header h1 {
                color: var(--primary-color);
                margin-bottom: 0.5rem;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                color: #555;
            }
            .form-group input {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 1rem;
            }
            .btn-primary {
                background-color: var(--primary-color);
                color: white;
                border: none;
                padding: 0.75rem;
                width: 100%;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
                transition: background 0.3s;
            }
            .btn-primary:hover {
                background-color: var(--secondary-color);
            }
            .auth-footer {
                text-align: center;
                margin-top: 1.5rem;
                color: #666;
            }
            .auth-footer a {
                color: var(--primary-color);
                text-decoration: none;
            }
            .alert {
                padding: 0.75rem;
                border-radius: 4px;
                margin-bottom: 1rem;
            }
            .alert-error {
                background-color: #ffebee;
                color: #c62828;
                border: 1px solid #ef9a9a;
            }
            .alert-success {
                background-color: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #a5d6a7;
            }
        </style>
    </head>
    <body class="auth-page">
        <div class="auth-container">
            <div class="auth-box">
                <div class="auth-header">
                    <h1><?= $is_login ? 'تسجيل الدخول' : 'إنشاء حساب' ?></h1>
                    <p>مرحباً بك في <?= htmlspecialchars(APP_NAME) ?></p>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="auth.php?action=<?= $type ?>" class="auth-form">
                    <?php if (!$is_login): ?>
                        <div class="form-group">
                            <label for="email">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="username">اسم المستخدم</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <?php if (!$is_login): ?>
                        <div class="form-group">
                            <label for="confirm_password">تأكيد كلمة المرور</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">
                        <?= $is_login ? 'تسجيل الدخول' : 'إنشاء حساب' ?>
                    </button>
                </form>

                <div class="auth-footer">
                    <?php if ($is_login): ?>
                        <span>ليس لديك حساب؟</span>
                        <a href="?action=register">سجل الآن</a>
                    <?php else: ?>
                        <span>لديك حساب بالفعل؟</span>
                        <a href="?action=login">سجل الدخول</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
        if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    redirect('auth.php?action=login');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') handle_login();
    elseif ($action === 'register') handle_register();
} else {
    if ($action === 'login' || $action === 'register') {
        display_auth_page($action);
    } elseif ($action === 'logout') {
        session_destroy();
        redirect('auth.php?action=login');
    } else {
        redirect('auth.php?action=login');
    }
}
?>