<?php
declare(strict_types=1);

$host = '127.0.0.1';
$port = 3306;
$dbname = 'waste_to_energy';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('WasteIQ DB Error: ' . $e->getMessage());

    http_response_code(500);

    exit(
        'Koneksi database gagal. ' .
        'Periksa nama database, konfigurasi MySQL, dan log PHP.'
    );
}