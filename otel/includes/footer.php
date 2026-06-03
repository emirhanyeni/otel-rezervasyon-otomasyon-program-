<?php

$yil = date('Y');
$otel_adi = $otel_icerik['otel_adi'] ?? 'Umuttepe Otel';
?>


<footer class="footer-main py-5 mt-5" id="iletisim">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="footer-brand mb-3"><i class="bi bi-building me-2"></i><?= e($otel_adi) ?></h5>
                <p class="text-white mb-3"><?= e($otel_icerik['slogan'] ?? '') ?></p>
            </div>
            <div class="col-lg-4">
                <h6 class="text-white mb-3">İletişim</h6>
                <ul class="list-unstyled text-muted small">
                    <li class="mb-2"><i class="bi bi-geo-alt me-2 text-gold"></i><?= e($otel_icerik['adres'] ?? '') ?></li>
                    <li class="mb-2"><i class="bi bi-telephone me-2 text-gold"></i><?= e($otel_icerik['telefon'] ?? '') ?></li>
                    <li class="mb-2"><i class="bi bi-envelope me-2 text-gold"></i><?= e($otel_icerik['email_iletisim'] ?? '') ?></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="text-white mb-3">Hızlı Linkler</h6>
                <ul class="list-unstyled small">
                    <li class="mb-1"><a href="/otel/index.php" class="footer-link">Ana Sayfa</a></li>
                    <li class="mb-1"><a href="/otel/index.php#odalar" class="footer-link">Odalarımız</a></li>
                    <li class="mb-1"><a href="/otel/rezervasyon.php" class="footer-link">Rezervasyon</a></li>
                    <li class="mb-1"><a href="/otel/dashboard.php" class="footer-link">Hesabım</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="text-center text-muted small">
            <p class="mb-0">&copy; <?= $yil ?> <?= e($otel_adi) ?>. Tüm hakları saklıdır. | GRUP 124 VTYS Dönem Projesi</p>
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="/otel/Js/main.js"></script>
</body>
</html>