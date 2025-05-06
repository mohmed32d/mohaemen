<?php
declare(strict_types=1);

if (class_exists('Database')) {
    return;
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        require_once __DIR__ . '/config.php';
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $this->pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please check the configuration and try again.");
        }
    }
    public function query(string $sql, array $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage());
            return false;
        }
    }
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function fetch(string $sql, array $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new PDOException("Failed to prepare statement");
            }
            
            if (!$stmt->execute($params)) {
                throw new PDOException("Failed to execute statement");
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database fetch error: " . $e->getMessage());
            return false;
        }
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new PDOException("Failed to prepare statement");
            }
            
            $executed = $stmt->execute($params);
            if (!$executed) {
                throw new PDOException("Failed to execute statement");
            }
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database fetchColumn Error: " . $e->getMessage());
            return false;
        }
    }

    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE $table SET $set WHERE $where";
        
        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params)->rowCount();
    }

    public function initializeDatabase(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(50) NOT NULL UNIQUE,
                    `email` VARCHAR(100) NOT NULL UNIQUE,
                    `password` VARCHAR(255) NOT NULL,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `last_login` DATETIME NULL,
                    `login_attempts` INT DEFAULT 0,
                    `reset_token` VARCHAR(255) NULL,
                    `reset_expires` DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
    
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `scans` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `target` VARCHAR(255) NOT NULL,
                    `scan_type` VARCHAR(50) NOT NULL,
                    `scan_results` LONGTEXT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
    
            $count = $this->pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
            if ($count == 0) {
                $this->pdo->exec("
                    INSERT INTO `users` 
                    (`username`, `email`, `password`, `is_active`) 
                    VALUES 
                    ('admin', 'admin@cyberscan.com', '" . password_hash('Admin@123', PASSWORD_BCRYPT) . "', 1)
                ");
            }
    
        } catch (PDOException $e) {
            $this->logError($e);
            if (DEBUG_MODE) {
                throw new RuntimeException("فشل في تهيئة قاعدة البيانات: " . $e->getMessage());
            }
        }
    }

    public function tableExists(string $table): bool {
        try {
            $result = $this->pdo->query("SHOW TABLES LIKE '$table'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError($e);
            return false;
        }
    }
    public function lastInsertId(): int {
        return (int)$this->pdo->lastInsertId();
    }

    public function getLastInsertId(): int {
        return (int)$this->pdo->lastInsertId();
    }

    private function logError(PDOException $e, string $sql = '', array $params = []): void {
        $errorMsg = sprintf(
            "[%s] Database Error:\nCode: %s\nMessage: %s\nFile: %s\nLine: %s\nSQL: %s\nParams: %s\nStack Trace:\n%s\n",
            date('Y-m-d H:i:s'),
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $sql,
            json_encode($params, JSON_UNESCAPED_UNICODE),
            $e->getTraceAsString()
        );

        if (!file_exists(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }

        error_log($errorMsg, 3, __DIR__ . '/../logs/database_errors.log');
    }

    public function prepare(string $sql): PDOStatement {
        return $this->getConnection()->prepare($sql);
    }
}
try {
    $db = Database::getInstance();
    
    if (DEBUG_MODE) {
        $db->initializeDatabase();
        $db->getConnection()->query("SELECT 1");
    }
} catch (Exception $e) {
    error_log("فشل تهيئة قاعدة البيانات: " . $e->getMessage());
    
    if (DEBUG_MODE) {
        die("<h3>خطأ في النظام</h3>
            <p><strong>الرسالة:</strong> {$e->getMessage()}</p>
            <p><strong>الملف:</strong> {$e->getFile()}</p>
            <p><strong>السطر:</strong> {$e->getLine()}</p>");
    } else {
        die("عذراً، حدث خطأ في النظام. يرجى المحاولة لاحقاً.");
    }
}

if (!function_exists('is_db_connected')) {
    function is_db_connected(): bool {
        try {
            Database::getInstance()->getConnection()->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
