<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'home';
$allowed_actions = ['home', 'login', 'register', 'logout', 'scan', 'results', 'report'];

if (!in_array($action, $allowed_actions)) {
    render_error_page('الصفحة غير موجودة', 404);
    exit;
}

switch ($action) {
    case 'home':
        render_home_page();
        break;
    case 'login':
    case 'register':
    case 'logout':
        require __DIR__ . '/auth.php';
        break;
    case 'scan':
    case 'results':
    case 'report':
        check_authentication();
        require __DIR__ . '/' . $action . '.php';
        break;
}

function render_home_page() {
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars(APP_NAME) ?> - الصفحة الرئيسية</title>
        <link rel="stylesheet" href="/cyberscan/assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
    </head>
    <body class="home-page">
        <?php if (is_logged_in()): ?>
<?php endif; ?>


        
        <main class="main-content">
            <header class="main-header">
                <div class="container">
                    <h1>مرحباً بكم في <?= htmlspecialchars(APP_NAME) ?></h1>
                    <nav class="main-nav">
                        <?php if (is_logged_in()): ?>
                            <a href="?action=scan" class="btn btn-primary">فحص جديد</a>
                            <a href="?action=report" class="btn btn-secondary">التقارير</a>
                            <a href="?action=logout" class="btn btn-outline">تسجيل الخروج</a>
                        <?php else: ?>
                            <a href="?action=login" class="btn btn-primary">تسجيل الدخول</a>
                            <a href="?action=register" class="btn btn-secondary">إنشاء حساب</a>
                        <?php endif; ?>
                    </nav>
                </div>
            </header>

            <section class="hero-section">
                <div class="container">
                    <h2>نظام متكامل للفحص الأمني</h2>
                    <p>حلول متقدمة لاكتشاف الثغرات الأمنية وتقديم تقارير مفصلة</p>
                </div>
            </section>

            <section class="features-section">
                <div class="container">
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">🔍</div>
                            <h3>فحص الثغرات</h3>
                            <p>اكتشاف الثغرات الأمنية في الأنظمة والشبكات</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">📊</div>
                            <h3>تقارير مفصلة</h3>
                            <p>تقرير شامل مع حلول مقترحة لكل ثغرة</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🛡️</div>
                            <h3>حماية متكاملة</h3>
                            <p>نظام متكامل لحماية أنظمتك من الاختراقات</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="main-footer">
            <div class="container">
                <p>جميع الحقوق محفوظة &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></p>
            </div>
        </footer>

        <script src="/cyberscan/assets/js/main.js"></script>
    </body>
    </html>
    <?php
}

function render_error_page(string $message, int $status_code = 500) {
    http_response_code($status_code);
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
    <link rel="stylesheet" href="/cyberscan/assets/css/style.css">
    <script src="/cyberscan/assets/js/main.js"></script>
    <script src="/cyberscan/assets/js/particles.js"></script>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطأ - <?= htmlspecialchars(APP_NAME) ?></title>
    </head>
    <body class="error-page">
        <div class="error-container">
            <h1>حدث خطأ</h1>
            <div class="error-message">
                <?= htmlspecialchars($message) ?>
            </div>
            <a href="?action=home" class="btn btn-primary">العودة للصفحة الرئيسية</a>
        </div>
    </body>
    </html>
    <?php
}

function check_authentication() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        set_flash('يجب تسجيل الدخول أولاً', 'error');
        redirect('login');
    }

    $user = $_SESSION['user'] ?? [];
    if ($user['ip'] !== $_SERVER['REMOTE_ADDR'] || 
        $user['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        redirect('login');
    }
}
?>
