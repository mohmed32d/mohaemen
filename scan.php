<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    redirect('auth.php?action=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['target'] ?? '';
    $scan_type = $_POST['scan_type'] ?? '';
    
    if (empty($target) || empty($scan_type)) {
        set_flash_message('الرجاء إدخال الهدف ونوع الفحص', 'error');
        redirect('scan.php');
    }
    
    try {
        if ($scan_type === 'nmap') {
            $result = scan_with_nmap($target);
        } else {
            $result = scan_with_nuclei($target);
        }
        
        $_SESSION['scan_results'] = [
            'target' => $target,
            'scan_type' => $scan_type,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        set_flash_message('فشل الفحص: ' . $e->getMessage(), 'error');
    }
    
    redirect('scan.php');
}

$scan_result = $_SESSION['scan_results'] ?? null;
if ($scan_result) {
    unset($_SESSION['scan_results']);
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص أمني - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script src="<?= APP_URL ?>/assets/js/particles.js"></script>
    <style>
        body {
            background-color: #1a1a2e;
            color: #ffffff;
        }
        .scan-container {
            background: #16213e;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
        .form-control, .form-select {
            background-color: #0f3460;
            border: 1px solid #2d4059;
            color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0f3460;
            color: #ffffff;
            border-color: #e94560;
            box-shadow: 0 0 0 0.25rem rgba(233, 69, 96, 0.25);
        }
        .btn-scan {
            background: #e94560;
            border: none;
            font-weight: bold;
            padding: 12px 30px;
        }
        .result-card {
            background: #0f3460;
            border-left: 4px solid #e94560;
        }
        .table-dark {
            background: #0f3460;
            color: #ffffff;
        }
        .alert {
            background-color: #2d4059;
            border: none;
            color: #ffffff;
        }
        pre {
            background: #0a192f;
            color: #64ffda;
            padding: 15px;
            border-radius: 5px;
        }
        .badge {
            font-weight: normal;
        }
        .modal-content {
            background-color: #16213e;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="scan-container p-4 p-md-5 my-4">
            <div class="text-center mb-5">
                <h1 class="text-white fw-bold"><i class="fas fa-shield-alt me-3"></i>فحص أمني متقدم</h1>
                <p class="text-white-50">اختبر قوة دفاعاتك الأمنية باستخدام أدوات متخصصة</p>
            </div>

            <form method="POST" action="scan.php" class="row g-3">
                <div class="col-md-8">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="target" name="target" 
                               value="<?= htmlspecialchars($scan_result['target'] ?? '') ?>" 
                               required placeholder="أدخل IP أو رابط الموقع">
                        <label for="target" class="text-white-50"><i class="fas fa-bullseye me-2"></i>الهدف</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="scan_type" name="scan_type" required>
                            <option value="">اختر نوع الفحص</option>
                            <option value="nmap" <?= ($scan_result['scan_type'] ?? '') === 'nmap' ? 'selected' : '' ?>>فحص الشبكة (Nmap)</option>
                            <option value="nuclei" <?= ($scan_result['scan_type'] ?? '') === 'nuclei' ? 'selected' : '' ?>>فحص الموقع (Nuclei)</option>
                        </select>
                        <label for="scan_type" class="text-white-50"><i class="fas fa-list-ul me-2"></i>نوع الفحص</label>
                    </div>
                </div>
                <div class="col-12 text-center mt-3">
                    <button type="submit" class="btn btn-scan btn-lg">
                        <i class="fas fa-play me-2"></i>بدء الفحص
                    </button>
                </div>
            </form>

            <?php if ($scan_result): ?>
                <div class="mt-5">
                    <div class="card result-card border-0 mb-4">
                        <div class="card-header bg-dark text-white">
                            <h4 class="mb-0"><i class="fas fa-file-alt me-2"></i>نتائج الفحص</h4>
                        </div>
                        <div class="card-body text-white">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-bullseye me-2"></i>الهدف:</strong> 
                                    <?= htmlspecialchars($scan_result['target']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-tasks me-2"></i>نوع الفحص:</strong> 
                                    <?= $scan_result['scan_type'] === 'nmap' ? 'فحص الشبكة (Nmap)' : 'فحص الموقع (Nuclei)' ?></p>
                                </div>
                            </div>
                            
                            <?php if ($scan_result['scan_type'] === 'nmap'): ?>
                                <h5 class="mb-3"><i class="fas fa-network-wired me-2"></i>المنافذ المفتوحة</h5>
                                <?php if (!empty($scan_result['result']['open_ports'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-dark table-hover">
                                            <thead>
                                                <tr>
                                                    <th>المنفذ</th>
                                                    <th>الحالة</th>
                                                    <th>الخدمة</th>
                                                    <th>التفاصيل</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($scan_result['result']['open_ports'] as $port): ?>
                                                    <tr>
                                                        <td><?= $port['port'] ?></td>
                                                        <td><span class="badge bg-<?= $port['status'] === 'open' ? 'danger' : 'secondary' ?>"><?= $port['status'] ?></span></td>
                                                        <td><?= htmlspecialchars($port['service']) ?></td>
                                                        <td><?= htmlspecialchars($port['details'] ?? '') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">لم يتم العثور على منافذ مفتوحة</div>
                                <?php endif; ?>
                                
                                <h5 class="mt-4 mb-3"><i class="fas fa-terminal me-2"></i>خروج Nmap الكامل</h5>
                                <pre><?= htmlspecialchars($scan_result['result']['full_output'] ?? 'لا يوجد نتائج') ?></pre>
                            
                                <?php elseif ($scan_result['scan_type'] === 'nuclei'): ?>
    <div class="nuclei-results">
        <h5 class="mb-3"><i class="fas fa-bug me-2"></i>الثغرات المكتشفة</h5>
        
        <?php if (isset($scan_result['result']['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($scan_result['result']['error']) ?>
                <?php if (strpos($scan_result['result']['error'], 'القوالب') !== false): ?>
                    <div class="mt-2">
                        <small>المسار المتوقع للقوالب: C:\Users\theki\AppData\Roaming\nuclei\templates</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($scan_result['result']['vulnerabilities'])): ?>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                <?php if ($scan_result['result']['stats']['templates'] > 0): ?>
                    تم فحص <?= $scan_result['result']['stats']['templates'] ?> قالب ولم يتم العثور على ثغرات
                <?php else: ?>
                    <div class="debug-info">
                        <p>لم يتم فحص أي قوالب. الأسباب المحتملة:</p>
                        <ul>
                            <li>تأكد من وجود ملفات YAML في مجلد القوالب</li>
                            <li>جرب تنفيذ هذا الأمر يدوياً:
                                <code>C:\Users\theki\Downloads\nuclei_3.4.2_windows_amd64\nuclei.exe -u <?= htmlspecialchars($scan_result['target']) ?> -t C:\Users\theki\AppData\Roaming\nuclei\templates -silent</code>
                            </li>
                            <li>تحقق من ملف <a href="/logs/last_scan.log" target="_blank">last_scan.log</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <button onclick="window.print()" class="btn btn-outline-light me-2">
                                    <i class="fas fa-print me-2"></i>طباعة
                                </button>
                                <button onclick="copyResults()" class="btn btn-outline-light me-2">
                                    <i class="fas fa-copy me-2"></i>نسخ النتائج
                                </button>
                                <a href="report.php?scan_id=<?= md5($scan_result['timestamp']) ?>" class="btn btn-primary">
                                    <i class="fas fa-file-alt me-2"></i>إنشاء تقرير مفصل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyResults() {
        const results = document.querySelector('.card-body').innerText;
        navigator.clipboard.writeText(results)
            .then(() => alert('تم نسخ النتائج بنجاح'))
            .catch(err => alert('حدث خطأ أثناء النسخ: ' + err));
    }
    document.querySelectorAll('.show-details').forEach(btn => {
        btn.addEventListener('click', () => {
            const details = btn.getAttribute('data-details');
            const modal = `
                <div class="modal fade" id="detailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h5 class="modal-title">تفاصيل الثغرة</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <pre>${details}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modal);
            const modalInstance = new bootstrap.Modal(document.getElementById('detailsModal'));
            modalInstance.show();
            
            document.getElementById('detailsModal').addEventListener('hidden.bs.modal', () => {
                document.getElementById('detailsModal').remove();
            });
        });
    });
    </script>
</body>
</html>