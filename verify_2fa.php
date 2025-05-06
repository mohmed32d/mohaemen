<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require 'vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    set_flash_message('يجب تسجيل الدخول أولاً', 'error');
    header('Location: auth.php');
    exit();
}

if (!isset($_SESSION['2fa_required']) || $_SESSION['2fa_required'] !== true) {
    header('Location: dashboard.php');
    exit();
}

if (isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true) {
    header('Location: dashboard.php');
    exit();
}

if (!isset($_SESSION['user']['email']) || !filter_var($_SESSION['user']['email'], FILTER_VALIDATE_EMAIL)) {
    set_flash_message('لا يوجد بريد إلكتروني صالح مسجل لهذا الحساب', 'error');
    header('Location: auth.php');
    exit();
}

if (!isset($_SESSION['2fa_code']) || time() > $_SESSION['2fa_expire']) {
    $_SESSION['2fa_code'] = TwoFactorAuth::generateCode();
    $_SESSION['2fa_expire'] = time() + 300; 
    $_SESSION['2fa_attempts'] = 0;
    
    if (!TwoFactorAuth::sendVerificationCode($_SESSION['user']['email'])) {
        set_flash_message('فشل إرسال رمز التحقق. يرجى المحاولة لاحقاً', 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_code'])) {
        TwoFactorAuth::resendCode();
        set_flash_message("تم إعادة إرسال الرمز إلى بريدك الإلكتروني", 'success');
        header('Location: verify_2fa.php');
        exit;
    }

    if (isset($_POST['verify_code'])) {
        $_SESSION['2fa_verified'] = true; 
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التحقق بخطوتين - <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Tajawal', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .verify-container {
            width: 100%;
            max-width: 450px;
        }
        
        .verify-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .verify-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .verify-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }
        
        .verify-header p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .verify-body {
            padding: 2rem;
        }
        
        .code-input {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .code-input input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 0 5px;
            direction: ltr;
        }
        
        .code-input input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-resend {
            color: var(--primary-color);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-resend:hover {
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        .timer {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        @media (max-width: 576px) {
            .verify-header {
                padding: 1.5rem;
            }
            
            .verify-body {
                padding: 1.5rem;
            }
            
            .code-input input {
                width: 40px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
<?php display_flash_messages(); ?>
    <div class="verify-container">
        <div class="verify-box">
            <div class="verify-header">
                <h1><i class="fas fa-shield-alt me-2"></i> التحقق بخطوتين</h1>
                <p>تم إرسال رمز التحقق إلى <?= htmlspecialchars($_SESSION['user']['email']) ?></p>
            </div>
            
            <div class="verify-body">
                <form method="POST" action="verify_2fa.php" id="verifyForm">
                    <div class="code-input">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" name="code<?= $i ?>" maxlength="1" pattern="\d" required
                                   class="form-control text-center" style="font-size: 1.5rem;" 
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'');moveToNext(this, <?= $i ?>)">
                        <?php endfor; ?>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="verify_code" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-check-circle me-2"></i> تأكيد الرمز
                        </button>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="resend_code" class="btn btn-resend">
                            <i class="fas fa-redo-alt me-2"></i> إعادة إرسال الرمز
                        </button>
                    </div>

                    <div class="timer text-center mt-3">
                        <span>الرمز سينتهي في <span id="timer">05:00</span></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function moveToNext(input, index) {
            if (input.value.length == input.maxLength) {
                if (index < 6) {
                    document.getElementsByName('code' + (index + 1))[0].focus();
                } else {
                    document.getElementById('submitBtn').focus();
                }
            }
        }

        let timer = document.getElementById('timer');
        let timeLeft = 300; 
        let countdown = setInterval(() => {
            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            if (seconds < 10) seconds = '0' + seconds;
            timer.textContent = minutes + ':' + seconds;
            timeLeft--;
            if (timeLeft < 0) clearInterval(countdown);
        }, 1000);
    </script>
</body>
</html>
