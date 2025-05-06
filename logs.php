<?php
include(__DIR__ . '/header.php');

if (!isset($_SESSION['user'])) {
    header('Location: auth.php?action=login');
    exit();
}
require_once __DIR__ . 'functions.php';
require_once __DIR__ . 'db.php';
$query = "SELECT * FROM logs WHERE user_id = {$_SESSION['user']['id']}";
$result = mysqli_query($conn, $query);

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <link rel="stylesheet" href="/cyberscan/assets/css/style.css">
    <script src="/cyberscan/assets/js/main.js"></script>
    <script src="/cyberscan/assets/js/particles.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض السجلات</title>
</head>
<body>
    <div class="container">
        <header>
            <h1>سجلات الفحص السابقة</h1>
            <a href="home.php" class="btn btn-back">العودة للصفحة الرئيسية</a>
        </header>

        <section class="logs-list">
            <table>
                <thead>
                    <tr>
                        <th>رقم السجل</th>
                        <th>الموقع / الـ IP</th>
                        <th>التاريخ</th>
                        <th>النتيجة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['site']; ?></td>
                            <td><?php echo $row['date']; ?></td>
                            <td><?php echo $row['result']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>
