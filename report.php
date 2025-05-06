<?php
declare(strict_types=1);
function secure_session_start(): void {
    $session_name = 'CYBERSCAN_SESSION';
    $secure = true;
    $httponly = true;
    $samesite = 'Strict';

    if (session_status() === PHP_SESSION_NONE) {
        session_name($session_name);
        
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams["lifetime"],
            'path' => $cookieParams["path"],
            'domain' => $cookieParams["domain"],
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
        
        session_start();
        
        if (empty($_SESSION['initiated'])) {
            session_regenerate_id();
            $_SESSION['initiated'] = true;
        }
    }
}

secure_session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    set_flash_message('يجب تسجيل الدخول أولاً', 'error');
    redirect('auth.php?action=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['csrf_token'])) {
    set_flash_message('طلب غير صالح', 'error');
    redirect('dashboard.php');
}

$scan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($scan_id <= 0) {
    set_flash_message('معرف الفحص غير صالح', 'error');
    redirect('scan.php');
}

try {
    $db = Database::getInstance();
    $scan = $db->fetch(
        "SELECT * FROM scans WHERE id = ? AND user_id = ? LIMIT 1",
        [$scan_id, $_SESSION['user']['id']]
    );
    
    if (!$scan) {
        $backup_file = __DIR__ . '/scan_backups/scan_' . $scan_id . '.json';
        if (file_exists($backup_file)) {
            $scan_data = file_get_contents($backup_file);
            if ($scan_data !== false) {
                $scan = json_decode($scan_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('خطأ في تحليل بيانات النسخة الاحتياطية');
                }
            }
        }
        
        if (!$scan) {
            throw new Exception('لا توجد نتائج فحص لعرضها');
        }
    }
    
    if (isset($scan['results']) && is_string($scan['results'])) {
        $results = json_decode($scan['results'], true);
        $scan['results'] = (json_last_error() === JSON_ERROR_NONE) ? $results : [];
    }
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Report Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/error.log');
    set_flash_message('حدث خطأ أثناء جلب بيانات الفحص: ' . $e->getMessage(), 'error');
    redirect('scan.php');
}

$per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $per_page;

try {
    $total_scans = $db->fetchColumn(
        "SELECT COUNT(*) FROM scans WHERE user_id = ?",
        [$_SESSION['user']['id']]
    );
    
    $scans_history = $db->fetchAll(
        "SELECT id, target, scan_type, created_at FROM scans 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT ? OFFSET ?",
        [$_SESSION['user']['id'], $per_page, $offset]
    );
} catch (Exception $e) {
    $scans_history = [];
    error_log("[" . date('Y-m-d H:i:s') . "] History Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/error.log');
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

include __DIR__ . '/header.php';
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>تقرير الفحص - <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/cyberscan/assets/css/style.css">
    <style>
        :root {
            --primary-color: #2a5298;
            --secondary-color: #1e3c72;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--light-bg);
        }
        
        .vulnerability-critical { 
            background-color: rgba(220, 53, 69, 0.1); 
            border-left: 4px solid var(--danger-color);
        }
        
        .vulnerability-high { 
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--warning-color);
        }
        
        .vulnerability-medium { 
            background-color: rgba(23, 162, 184, 0.1);
            border-left: 4px solid var(--info-color);
        }
        
        .scan-results pre { 
            white-space: pre-wrap; 
            background: #f8f9fa; 
            padding: 15px;
            border-radius: 5px;
            direction: ltr;
            text-align: left;
        }
        
        .severity-badge {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        .badge-critical {
            background-color: var(--danger-color);
        }
        
        .badge-high {
            background-color: var(--warning-color);
            color: #000;
        }
        
        .badge-medium {
            background-color: var(--info-color);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(42, 82, 152, 0.05);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; }
            .card { border: none !important; box-shadow: none !important; }
            .container { max-width: 100% !important; }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (get_flash_message()): ?>
            <?php $flash = get_flash_message(); ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($scan): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        تقرير فحص <?= htmlspecialchars($scan['scan_type'] === 'nmap' ? 'الشبكة' : 'الموقع', ENT_QUOTES, 'UTF-8') ?>
                    </h4>
                    <span class="badge bg-light text-dark">
                        <?= date('Y/m/d H:i', strtotime($scan['created_at'])) ?>
                    </span>
                </div>
                
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5><i class="fas fa-bullseye me-2"></i>الهدف:</h5>
                            <p class="ps-4">
                                <?php if (filter_var($scan['target'], FILTER_VALIDATE_URL)): ?>
                                    <a href="<?= htmlspecialchars($scan['target'], ENT_QUOTES, 'UTF-8') ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer">
                                        <?= htmlspecialchars($scan['target'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($scan['target'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-info-circle me-2"></i>نوع الفحص:</h5>
                            <p class="ps-4">
                                <?= htmlspecialchars($scan['scan_type'] === 'nmap' ? 'فحص الشبكة (Nmap)' : 'فحص الموقع (Nuclei)', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <?php if ($scan['scan_type'] === 'nmap'): ?>
                        <h5 class="mb-3"><i class="fas fa-network-wired me-2"></i>المنافذ المفتوحة:</h5>
                        
                        <?php if (!empty($scan['results']['ports'])): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="15%">المنفذ</th>
                                            <th width="15%">الحالة</th>
                                            <th width="15%">البروتوكول</th>
                                            <th width="55%">الخدمة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scan['results']['ports'] as $port): ?>
                                            <tr class="<?= $port['status'] === 'open' ? 'table-warning' : '' ?>">
                                                <td><?= (int)$port['port'] ?></td>
                                                <td>
                                                    <span class="badge <?= $port['status'] === 'open' ? 'bg-danger' : 'bg-secondary' ?>">
                                                        <?= htmlspecialchars($port['status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($port['protocol'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($port['service'] ?? 'غير معروف', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                لا توجد منافذ مفتوحة
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mt-4 mb-3"><i class="fas fa-terminal me-2"></i>خروج Nmap الكامل:</h5>
                        <div class="scan-results">
                            <pre><?= htmlspecialchars($scan['results']['output'] ?? 'لا توجد نتائج', ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                        
                    <?php elseif ($scan['scan_type'] === 'nuclei'): ?>
                        <h5 class="mb-3"><i class="fas fa-bug me-2"></i>الثغرات المكتشفة:</h5>
                        
                        <?php if (!empty($scan['results'])): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="15%">مستوى الخطورة</th>
                                            <th width="25%">اسم الثغرة</th>
                                            <th width="40%">الوصف</th>
                                            <th width="20%">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($scan['results'] as $finding): ?>
                                            <?php 
                                                $severity_class = '';
                                                $severity_badge = '';
                                                $severity_text = '';
                                                
                                                $severity = strtolower($finding['info']['severity'] ?? 'unknown');
                                                
                                                switch ($severity) {
                                                    case 'critical':
                                                        $severity_class = 'vulnerability-critical';
                                                        $severity_badge = 'badge-critical';
                                                        $severity_text = 'حرجة';
                                                        break;
                                                    case 'high':
                                                        $severity_class = 'vulnerability-high';
                                                        $severity_badge = 'badge-high';
                                                        $severity_text = 'عالية';
                                                        break;
                                                    case 'medium':
                                                        $severity_class = 'vulnerability-medium';
                                                        $severity_badge = 'badge-medium';
                                                        $severity_text = 'متوسطة';
                                                        break;
                                                    case 'low':
                                                        $severity_text = 'منخفضة';
                                                        break;
                                                    default:
                                                        $severity_text = htmlspecialchars($severity, ENT_QUOTES, 'UTF-8');
                                                }
                                            ?>
                                            <tr class="<?= $severity_class ?>">
                                                <td>
                                                    <?php if ($severity_badge): ?>
                                                        <span class="badge <?= $severity_badge ?>">
                                                            <?= $severity_text ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?= $severity_text ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($finding['info']['name'] ?? 'غير معروف', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <?= htmlspecialchars(
                                                        mb_substr($finding['info']['description'] ?? 'لا يوجد وصف', 0, 100) . 
                                                        (mb_strlen($finding['info']['description'] ?? '') > 100 ? '...' : ''), 
                                                        ENT_QUOTES, 
                                                        'UTF-8'
                                                    ) ?>
                                                    <?php if (isset($finding['info']['reference'])): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted">
                                                                <strong>مراجع:</strong> 
                                                                <?= htmlspecialchars(
                                                                    mb_substr(implode(', ', (array)$finding['info']['reference']), 0, 50) . 
                                                                    (mb_strlen(implode(', ', (array)$finding['info']['reference'])) > 50 ? '...' : ''), 
                                                                    ENT_QUOTES, 
                                                                    'UTF-8'
                                                                ) ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?= htmlspecialchars($finding['host'] ?? '#', ENT_QUOTES, 'UTF-8') ?>" 
                                                       target="_blank" 
                                                       rel="noopener noreferrer"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i>عرض
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                لم يتم اكتشاف أي ثغرات أمنية
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="mt-4 no-print">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="<?= BASE_URL ?>scan.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>فحص جديد
                                </a>
                                <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-home me-2"></i>لوحة التحكم
                                </a>
                            </div>
                            <div>
                                <button onclick="window.print()" class="btn btn-secondary me-2">
                                    <i class="fas fa-print me-2"></i>طباعة
                                </button>
                                <a href="export.php?id=<?= $scan_id ?>" class="btn btn-success">
                                    <i class="fas fa-file-export me-2"></i>تصدير PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>سجل الفحوصات السابقة</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($scans_history)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40%">الهدف</th>
                                    <th width="20%">نوع الفحص</th>
                                    <th width="25%">التاريخ</th>
                                    <th width="15%">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scans_history as $history): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars(
                                                mb_strlen($history['target']) > 30 ? 
                                                mb_substr($history['target'], 0, 30) . '...' : 
                                                $history['target'], 
                                                ENT_QUOTES, 
                                                'UTF-8'
                                            ) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars(
                                                $history['scan_type'] === 'nmap' ? 'Nmap' : 'Nuclei', 
                                                ENT_QUOTES, 
                                                'UTF-8'
                                            ) ?>
                                        </td>
                                        <td><?= date('Y/m/d H:i', strtotime($history['created_at'])) ?></td>
                                        <td>
                                            <a href="report.php?id=<?= (int)$history['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_scans > $per_page): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-3">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $scan_id ?>&page=<?= $current_page - 1 ?>">
                                            السابق
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= ceil($total_scans / $per_page); $i++): ?>
                                    <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?id=<?= $scan_id ?>&page=<?= $i ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < ceil($total_scans / $per_page)): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $scan_id ?>&page=<?= $current_page + 1 ?>">
                                            التالي
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        لا توجد فحوصات سابقة لعرضها
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/cyberscan/assets/js/main.js"></script>
    <script>
        function confirmDelete(scanId) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذا الفحص؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('delete_scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${scanId}&csrf_token=<?= $csrf_token ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'حدث خطأ أثناء محاولة الحذف');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في الاتصال بالخادم');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-tabs .nav-link');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.getAttribute('data-bs-target');
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('active', 'show');
                    });
                    document.querySelector(target).classList.add('active', 'show');
                });
            });
        });
    </script>
</body>
</html>