<?php


define('DB_HOST', 'localhost');
define('DB_USER', 'root');       
define('DB_PASS', '');           
define('DB_NAME', 'otel_db');
define('DB_CHARSET', 'utf8mb4');

$baglanti = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$baglanti) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee;color:#c00;border:1px solid #c00;border-radius:6px;">
        <strong>Veritabanı Bağlantı Hatası!</strong><br>
        Lütfen XAMPP\'in çalıştığından ve otel_db veritabanının oluşturulduğundan emin olun.<br>
        Hata: ' . mysqli_connect_error() . '
    </div>');
}

mysqli_set_charset($baglanti, DB_CHARSET);


function db_query($conn, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("SQL Hazırlama Hatası: " . mysqli_error($conn));
        return false;
    }
    if ($types && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}


function db_fetch_one($conn, $sql, $types = '', $params = []) {
    $stmt = db_query($conn, $sql, $types, $params);
    if (!$stmt) return null;
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}


function db_fetch_all($conn, $sql, $types = '', $params = []) {
    $stmt = db_query($conn, $sql, $types, $params);
    if (!$stmt) return [];
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}


function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}


function format_tarih($tarih) {
    if (!$tarih) return '-';
    return date('d.m.Y', strtotime($tarih));
}


function format_para($miktar) {
    return number_format($miktar, 2, ',', '.') . ' ₺';
}