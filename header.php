<?php defined('BASE_URL') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - الصفحة الرئيسية</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__.'/assets/css/style.css') ?>">
</head>
<link rel="stylesheet" href="/cyberscan/assets/css/style.css">
    <script src="/cyberscan/assets/js/main.js"></script>
    <script src="/cyberscan/assets/js/particles.js"></script>
<body class="home-page">
    <header class="main-header">
        <div class="container">
            <h1><?= htmlspecialchars(APP_NAME) ?></h1>
            <nav class="main-nav">
                <?php if (is_logged_in()): ?>
                    <a href="?action=scan" class="btn btn-primary">فحص جديد</a>
                    <a href="?action=report" class="btn btn-secondary">التقارير</a>
                    <a href="auth.php?action=logout" class="nav-link">
    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
</a>
                <?php else: ?>
                    <a href="?action=login" class="btn btn-primary">تسجيل الدخول</a>
                    <a href="?action=register" class="btn btn-secondary">إنشاء حساب</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
 