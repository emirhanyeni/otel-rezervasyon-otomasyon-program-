<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function giris_kontrol() {
    if (!isset($_SESSION['musteri_id'])) {
        header('Location: /otel/login.php?mesaj=giris_gerekli');
        exit;
    }
}


function admin_kontrol() {
    giris_kontrol();
    if ($_SESSION['rol'] !== 'admin') {
        header('Location: /otel/dashboard.php?mesaj=yetkisiz');
        exit;
    }
}


function zaten_giris_yapilmis() {
    if (isset($_SESSION['musteri_id'])) {
        if ($_SESSION['rol'] === 'admin') {
            header('Location: /otel/admin/admin_panel.php');
        } else {
            header('Location: /otel/dashboard.php');
        }
        exit;
    }
}

function flash_set($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
}


function flash_goster() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $renk = $f['tip'] === 'basari' ? 'success' : ($f['tip'] === 'hata' ? 'danger' : 'warning');
        echo '<div class="alert alert-' . $renk . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($f['mesaj'], ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
}