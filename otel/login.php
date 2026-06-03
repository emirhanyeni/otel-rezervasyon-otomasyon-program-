<?php

session_start();
require_once 'baglanti.php';
require_once 'includes/auth.php';


zaten_giris_yapilmis();

$hata = '';
$sayfa_baslik = 'Giriş Yap';


$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $sifre = $_POST['sifre'] ?? '';

    if (empty($email) || empty($sifre)) {
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi girin.';
    } else {
        
        $musteri = db_fetch_one(
            $baglanti,
            "SELECT musteri_id, ad, soyad, email, sifre, rol FROM musteri WHERE email = ?",
            "s",
            [$email]
        );

        if ($musteri && password_verify($sifre, $musteri['sifre'])) {
           
            session_regenerate_id(true); 
            $_SESSION['musteri_id'] = $musteri['musteri_id'];
            $_SESSION['ad']         = $musteri['ad'];
            $_SESSION['soyad']      = $musteri['soyad'];
            $_SESSION['email']      = $musteri['email'];
            $_SESSION['rol']        = $musteri['rol'];

            flash_set('basari', 'Hoş geldiniz, ' . $musteri['ad'] . '!');

            if ($musteri['rol'] === 'admin') {
                header('Location: /otel/admin/admin_panel.php');
            } else {
                header('Location: /otel/dashboard.php');
            }
            exit;
        } else {
            $hata = 'E-posta veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap | <?= e($otel_icerik['otel_adi'] ?? 'Umuttepe Otel') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="/otel/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="auth-card">
                    <!-- Logo -->
                    <div class="text-center mb-4">
                        <a href="/otel/index.php" class="text-decoration-none">
                            <div class="auth-logo"><i class="bi bi-building me-2 text-gold"></i><?= e($otel_icerik['otel_adi'] ?? 'Umuttepe') ?></div>
                        </a>
                        <h4 class="mt-3 mb-1">Giriş Yap</h4>
                        <p class="text-muted small">Hesabınıza giriş yaparak rezervasyon yapın</p>
                    </div>

                    <?php if ($hata): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($hata) ?></div>
                    <?php endif; ?>

                    <?php
                   
                    if (isset($_GET['mesaj']) && $_GET['mesaj'] === 'giris_gerekli') {
                        echo '<div class="alert alert-warning py-2 small"><i class="bi bi-info-circle me-2"></i>Bu sayfaya erişmek için giriş yapmalısınız.</div>';
                    }
                    ?>

                    <form method="POST" action="" novalidate>
                        <div class="mb-3">
                            <label class="form-label fw-500 small">E-posta Adresi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0"
                                       placeholder="ornek@email.com"
                                       value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-500 small">Şifre</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="sifre" id="sifreInput"
                                       class="form-control border-start-0" placeholder="••••••••" required>
                                <button class="input-group-text bg-light" type="button"
                                        onclick="toggleSifre()">
                                    <i class="bi bi-eye" id="sifreGoz"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 py-2 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="text-muted small mb-0">Hesabınız yok mu? <a href="kayit.php" class="text-gold fw-semibold">Kayıt Ol</a></p>
                        <a href="index.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Ana Sayfaya Dön</a>
                    </div>

                    
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSifre() {
    const inp = document.getElementById('sifreInput');
    const goz = document.getElementById('sifreGoz');
    if (inp.type === 'password') { inp.type = 'text'; goz.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; goz.className = 'bi bi-eye'; }
}
</script>
</body>
</html>