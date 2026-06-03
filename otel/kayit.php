<?php

session_start();
require_once 'baglanti.php';
require_once 'includes/auth.php';

zaten_giris_yapilmis();

$hata    = '';
$basari  = '';
$sayfa_baslik = 'Kayıt Ol';

$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad      = trim($_POST['ad']      ?? '');
    $soyad   = trim($_POST['soyad']   ?? '');
    $email   = trim($_POST['email']   ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $sifre   = $_POST['sifre']        ?? '';
    $sifre2  = $_POST['sifre2']       ?? '';

    // Validasyon
    if (empty($ad) || empty($soyad) || empty($email) || empty($telefon) || empty($sifre)) {
        $hata = 'Lütfen tüm zorunlu alanları doldurun.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($sifre) < 6) {
        $hata = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($sifre !== $sifre2) {
        $hata = 'Şifreler eşleşmiyor.';
    } elseif (strlen(preg_replace('/\D/', '', $telefon)) < 10) {
        $hata = 'Telefon numarası en az 10 haneli olmalıdır.';
    } else {
        // E-posta benzersizlik kontrolü
        $mevcut = db_fetch_one($baglanti, "SELECT musteri_id FROM musteri WHERE email = ?", "s", [$email]);

        if ($mevcut) {
            $hata = 'Bu e-posta adresi zaten kayıtlı.';
        } else {
            // hash
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);

            $stmt = db_query(
                $baglanti,
                "INSERT INTO musteri (ad, soyad, email, telefon, sifre) VALUES (?, ?, ?, ?, ?)",
                "sssss",
                [$ad, $soyad, $email, $telefon, $sifre_hash]
            );

            if ($stmt) {
                flash_set('basari', 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.');
                header('Location: /otel/login.php');
                exit;
            } else {
                $hata = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol | <?= e($otel_icerik['otel_adi'] ?? 'Umuttepe Otel') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="/otel/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="auth-card" style="max-width:520px;">
                    <div class="text-center mb-4">
                        <a href="/otel/index.php" class="text-decoration-none">
                            <div class="auth-logo"><i class="bi bi-building me-2 text-gold"></i><?= e($otel_icerik['otel_adi'] ?? 'Umuttepe') ?></div>
                        </a>
                        <h4 class="mt-3 mb-1">Üye Ol</h4>
                        <p class="text-muted small">Ücretsiz hesap oluşturun, kolayca rezervasyon yapın</p>
                    </div>

                    <?php if ($hata): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= e($hata) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-500">Ad *</label>
                                <input type="text" name="ad" class="form-control"
                                       value="<?= e($_POST['ad'] ?? '') ?>" placeholder="Adınız" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-500">Soyad *</label>
                                <input type="text" name="soyad" class="form-control"
                                       value="<?= e($_POST['soyad'] ?? '') ?>" placeholder="Soyadınız" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-500">E-posta *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0"
                                           value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@email.com" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-500">Telefon *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-telephone text-muted"></i></span>
                                    <input type="tel" name="telefon" class="form-control border-start-0"
                                           value="<?= e($_POST['telefon'] ?? '') ?>" placeholder="05XX XXX XX XX" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-500">Şifre *</label>
                                <input type="password" name="sifre" class="form-control"
                                       placeholder="En az 6 karakter" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-500">Şifre Tekrar *</label>
                                <input type="password" name="sifre2" class="form-control"
                                       placeholder="Şifrenizi tekrarlayın" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gold w-100 py-2 mt-4 mb-3">
                            <i class="bi bi-person-plus me-2"></i>Kayıt Ol
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="text-muted small mb-1">Zaten hesabınız var mı? <a href="login.php" class="text-gold fw-semibold">Giriş Yap</a></p>
                        <a href="index.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Ana Sayfaya Dön</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>