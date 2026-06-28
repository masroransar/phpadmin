<?php
$host = '127.0.0.1';
$db = 'mamostakam';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,
    image_url VARCHAR(1024) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    country VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS social_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(1024) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$socialTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_links'")->fetchColumn();
if ($socialTableExists > 0) {
    $existingColumns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_links'")->fetchAll(PDO::FETCH_COLUMN);
    $existingColumns = array_map('strtolower', $existingColumns);

    $requiredColumns = [
        'key' => "ALTER TABLE social_links ADD COLUMN `key` VARCHAR(50) NOT NULL DEFAULT ''",
        'label' => "ALTER TABLE social_links ADD COLUMN label VARCHAR(100) NOT NULL DEFAULT ''",
        'url' => "ALTER TABLE social_links ADD COLUMN url VARCHAR(1024) NOT NULL DEFAULT ''",
        'sort_order' => "ALTER TABLE social_links ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
        'created_at' => "ALTER TABLE social_links ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE social_links ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    foreach ($requiredColumns as $column => $query) {
        if (!in_array($column, $existingColumns, true)) {
            $pdo->exec($query);
            $existingColumns[] = $column;
        }
    }

    $legacyKeys = [
        'facebook' => ['label' => 'فيسبوك', 'order' => 1],
        'telegram' => ['label' => 'تيليجرام', 'order' => 2],
        'youtube' => ['label' => 'يوتيوب', 'order' => 3],
        'snapchat' => ['label' => 'سناب شات', 'order' => 4],
        'tiktok' => ['label' => 'تيك توك', 'order' => 5],
        'website' => ['label' => 'الموقع الإلكتروني', 'order' => 6],
    ];

    $legacyColumns = array_intersect($legacyKeys ? array_keys($legacyKeys) : [], $existingColumns);
    if (!empty($legacyColumns)) {
        $legacyRow = $pdo->query('SELECT * FROM social_links LIMIT 1')->fetch();
        if ($legacyRow) {
            $inserted = 0;
            $insertStmt = $pdo->prepare('INSERT IGNORE INTO social_links (`key`, label, url, sort_order) VALUES (?, ?, ?, ?)');
            foreach ($legacyKeys as $legacyKey => $meta) {
                if (array_key_exists($legacyKey, $legacyRow) && trim($legacyRow[$legacyKey]) !== '') {
                    $insertStmt->execute([
                        $legacyKey,
                        $meta['label'],
                        trim($legacyRow[$legacyKey]),
                        $meta['order'],
                    ]);
                    $inserted++;
                }
            }
            if ($inserted > 0) {
                $pdo->exec('DELETE FROM social_links WHERE id = ' . (int) $legacyRow['id']);
            }
        }

        foreach ($legacyColumns as $legacyColumn) {
            $pdo->exec("ALTER TABLE social_links DROP COLUMN `$legacyColumn`");
        }
    }
}

$socialCount = (int) $pdo->query("SELECT COUNT(*) FROM social_links")->fetchColumn();
if ($socialCount === 0) {
    $stmt = $pdo->prepare("INSERT INTO social_links (`key`, label, url, sort_order) VALUES (?, ?, ?, ?)");
    $defaultLinks = [
        ['facebook', 'فيسبوك', '', 1],
        ['telegram', 'تيليجرام', '', 2],
        ['youtube', 'يوتيوب', '', 3],
        ['snapchat', 'سناب شات', '', 4],
        ['tiktok', 'تيك توك', '', 5],
        ['website', 'الموقع الإلكتروني', '', 6],
    ];
    foreach ($defaultLinks as $link) {
        $stmt->execute($link);
    }
}

$phoneExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone'")->fetchColumn();
if ($phoneExists === 0) {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NOT NULL AFTER password_hash");
}

$countryExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'country'")->fetchColumn();
if ($countryExists === 0) {
    $pdo->exec("ALTER TABLE users ADD COLUMN country VARCHAR(100) NOT NULL AFTER phone");
}

function uploadFile($fieldName, $allowedTypes, $uploadDir = __DIR__ . '/uploads') {
    if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'])) {
        return '';
    }

    $file = $_FILES[$fieldName];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeType = mime_content_type($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes) && !in_array($extension, array_map(function ($type) {
            return explode('/', $type)[1];
        }, $allowedTypes))) {
        return '';
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetName = uniqid('upload_', true) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $targetName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $targetName;
    }

    return '';
}
