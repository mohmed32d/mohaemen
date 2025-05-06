<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: auth.php?action=login');
    exit(); 
}

if ($_SESSION['user']['ip'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user']['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_destroy();
    header('Location: auth.php?action=login');
    exit(); 
}

$_SESSION['user']['last_activity'] = time();

$page_title = "لوحة التحكم | " . htmlspecialchars($_SESSION['user']['username']);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<script src="/cyberscan/assets/js/particles.js"></script>

<script src="/cyberscan/assets/js/main.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if(typeof particlesJS !== 'undefined') {
        particlesJS.load('particles-js', '/cyberscan/assets/js/particles.js', function() {
            console.log('Particles.js loaded successfully');
        });
    }
});
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --primary-color: #2a5298;
            --secondary-color: #1e3c72;
            --text-color: #333;
            --light-bg: #f5f7fa;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            color: var(--text-color);
        }
        
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            color: white;
            padding: 1.5rem 1rem;
        }
        
        .sidebar-header {
            text-align: center;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-nav {
            margin-top: 2rem;
        }
        
        .nav-item {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .nav-item i {
            margin-left: 0.5rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .welcome-message h1 {
            color: var(--primary-color);
            margin: 0;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 0.75rem;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-title {
            color: var(--primary-color);
            margin-top: 0;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .logout-btn {
            background-color: #e74c3c;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
<div id="particles-js" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></div>
                <h2><?= htmlspecialchars(APP_NAME) ?></h2>
                <p>لوحة التحكم</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a href="scan.php" class="nav-item">
                    <i class="fas fa-search"></i> فحص جديد
                </a>
                <a href="report.php" class="nav-item">
                    <i class="fas fa-file-alt"></i> التقارير
                </a>
                <a href="export.php" class="nav-item">
                    <i class="fas fa-file-export"></i> تصدير PDF
                </a>
                <a href="auth.php?action=logout" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </nav>
        </aside>
                <main class="main-content">
            <div class="header">
                <div class="welcome-message">
                    <h1>مرحباً <?= htmlspecialchars($_SESSION['user']['username']) ?></h1>
                    <p>آخر نشاط: <?= date('Y-m-d H:i', $_SESSION['user']['last_activity']) ?></p>
                </div>
                
                <div class="user-profile">
                    <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <h3 class="card-title">فحص جديد</h3>
                    <p>ابدأ فحصاً جديداً للمواقع أو عناوين IP للكشف عن الثغرات الأمنية</p>
                    <a href="scan.php" class="btn">بدء الفحص</a>
                </div>
                
                <div class="card">
                    <h3 class="card-title">التقارير السابقة</h3>
                    <p>عرض سجل الفحوصات السابقة ونتائج التحليل الأمني</p>
                    <a href="report.php" class="btn btn-outline">عرض التقارير</a>
                </div>
                
                <div class="card">
                    <h3 class="card-title">إحصائيات</h3>
                    <p>عدد الفحوصات هذا الشهر: 15</p>
                    <p>آخر فحص: 2023-06-15</p>
                </div>
                
                <div class="card">
                    <h3 class="card-title">مساعدة فنية</h3>
                    <p>تواصل مع الدعم الفني أو اقرأ الوثائق</p>
                    <a href="help.php" class="btn btn-outline">الذهاب إلى المساعدة</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>