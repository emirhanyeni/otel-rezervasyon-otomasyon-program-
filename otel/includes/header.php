<?php

$otel_adi    = $otel_icerik['otel_adi']    ?? 'Umuttepe Otel';
$sayfa_baslik = $sayfa_baslik ?? $otel_adi;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($sayfa_baslik) ?> | <?= e($otel_adi) ?></title>
   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link href="/otel/Css/style.css" rel="stylesheet">
</head>
<body>


<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/otel/index.php">
            <i class="bi bi-building fs-4"></i>
            <span class="brand-text"><?= e($otel_adi) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item"><a class="nav-link" href="/otel/index.php#odalar">Odalarımız</a></li>
                <li class="nav-item"><a class="nav-link" href="/otel/index.php#hizmetler">Hizmetler</a></li>
                <li class="nav-item"><a class="nav-link" href="/otel/index.php#iletisim">İletişim</a></li>
                <?php if (isset($_SESSION['musteri_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="/otel/dashboard.php"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['ad']) ?></a></li>
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link text-warning" href="/otel/admin/admin_panel.php"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm px-3" href="/otel/logout.php">Çıkış</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/otel/login.php">Giriş Yap</a></li>
                    <li class="nav-item"><a class="btn btn-gold btn-sm px-3" href="/otel/kayit.php">Kayıt Ol</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>