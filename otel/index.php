<?php

session_start();

require_once 'baglanti.php';
require_once 'includes/auth.php';

$sayfa_baslik = 'Ana Sayfa';


$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) {
    $otel_icerik[$row['alan']] = $row['deger'];
}


$odalar = db_fetch_all($baglanti,
    "SELECT o.*, ot.tip_adi, ot.kapasite, ot.aciklama AS tip_aciklama
     FROM odalar o
     JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
     ORDER BY o.fiyat ASC"
);
?>
<?php include 'includes/header.php'; ?>


<section class="hero-section">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-badge fade-in-up">⭐⭐⭐⭐⭐ Beş Yıldızlı Deneyim</span>
                <h1 class="hero-title fade-in-up delay-1">
                    <?= nl2br(e($otel_icerik['hero_baslik'] ?? 'Hayalinizin Tatilini Yaşayın')) ?>
                </h1>
                <p class="hero-sub mt-3 mb-5 fade-in-up delay-2">
                    <?= e($otel_icerik['hero_alt_baslik'] ?? '') ?>
                </p>
                <div class="d-flex gap-3 flex-wrap fade-in-up delay-3">
                    <a href="rezervasyon.php" class="btn btn-gold btn-lg px-5 py-3">
                        <i class="bi bi-calendar-check me-2"></i>Rezervasyon Yap
                    </a>
                    <a href="#odalar" class="btn btn-outline-light btn-lg px-4 py-3">
                        Odaları Keşfet
                    </a>
                </div>
                <div class="row g-3 mt-4 fade-in-up delay-4">
                    <div class="col-4">
                        <div class="hero-stat-card">
                            <div class="stat-num">30+</div>
                            <div class="stat-lbl">Oda</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="hero-stat-card">
                            <div class="stat-num">5★</div>
                            <div class="stat-lbl">Hizmet</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="hero-stat-card">
                            <div class="stat-num">2026</div>
                            <div class="stat-lbl">Kuruluş</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-gallery">
                    
                    <div class="hero-img-card hero-img-main">
                        <img src="https://m.media-amazon.com/images/M/MV5BYzZlNmM5NDUtMzc5MS00YzEwLTg1MGItZDc0NGYyOGM0OWZlXkEyXkFqcGc@._V1_.jpg" alt="Otel Dış Görünüm">
                    </div>
                    <div class="hero-img-card hero-img-sm1">
                        <img src="https://i.sonhaberler.com/storage/files/images/2022/01/18/recep-ivedik-otel-nerede-1-hyLP.jpg" alt="Oda İç">
                    </div>
                    <div class="hero-img-card hero-img-sm2">
                        <img src="https://dynamic-media-cdn.tripadvisor.com/media/photo-o/15/0e/d1/9f/diamond-premium-hotel.jpg?w=300&h=200&s=1" alt="Havuz">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="position-absolute bottom-0 start-50 translate-middle-x pb-4 text-white opacity-50">
        <a href="#odalar" class="text-white text-decoration-none">
            <i class="bi bi-chevron-double-down fs-4 bounce"></i>
        </a>
    </div>
</section>


<section class="py-6" style="padding: 80px 0;">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <img src="https://avatars.mds.yandex.net/get-altay/11522875/2a0000018e69d9fd0b3aa3df18d518cf1f12/XXL_height"
                     alt="Otel" class="img-fluid rounded-4 shadow-lg">
            </div>
            <div class="col-lg-6">
                <span class="section-tag">Hakkımızda</span>
                <h2 class="section-title mb-4"><?= e($otel_icerik['otel_adi'] ?? '') ?></h2>
                <p class="text-muted lh-lg"><?= e($otel_icerik['aciklama'] ?? '') ?></p>
                <div class="row g-3 mt-3">
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
                            <i class="bi bi-clock-history text-gold fs-4"></i>
                            <div>
                                <div class="fw-semibold">Açılış</div>
                                <div class="text-muted small"><?= e($otel_icerik['Açılış'] ?? '06:00') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
                            <i class="bi bi-clock text-gold fs-4"></i>
                            <div>
                                <div class="fw-semibold">Kapanış</div>
                                <div class="text-muted small"><?= e($otel_icerik['Kapanış'] ?? '00:00') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="odalar" style="padding: 80px 0; background: var(--cream);">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Konaklama</span>
            <h2 class="section-title">Odalarımız</h2>
            <p class="text-muted">Her zevke ve bütçeye uygun lüks oda seçenekleri</p>
        </div>
        <div class="row g-4">
            <?php
          
            $oda_gorselleri = [
                'Standart Oda'    => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80',
                'Deluxe Oda'      => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=600&q=80',
                'Suite'           => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&q=80',
                'Aile Odası'      => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?w=600&q=80',
                'Ekonomi Oda'     => 'https://images.unsplash.com/photo-1595576508898-0ad5c879a061?w=600&q=80',
                'Penthouse Suite' => 'https://images.unsplash.com/photo-1602343168117-bb8ffe3e2e9f?w=600&q=80',
                'Balayı Odası'    => 'https://images.unsplash.com/photo-1540518614846-7eded433c457?w=600&q=80',
                'Engelli Dostu'   => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=600&q=80',
            ];
            foreach ($odalar as $oda):
                $gorsel = $oda_gorselleri[$oda['tip_adi']] ?? 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80';
            ?>
            <div class="col-md-6 col-lg-4 fade-in-up">
                <div class="card room-card <?= $oda['durum'] === 'bos' ? 'oda-bos' : 'oda-dolu' ?>">
                    <div class="position-relative">
                        <img src="<?= $gorsel ?>" class="card-img-top" alt="<?= e($oda['tip_adi']) ?>">
                        <span class="position-absolute top-0 end-0 m-3 badge <?= $oda['durum'] === 'bos' ? 'badge-bos' : 'badge-dolu' ?> px-3 py-2">
                            <i class="bi bi-<?= $oda['durum'] === 'bos' ? 'check-circle' : 'x-circle' ?> me-1"></i>
                            <?= $oda['durum'] === 'bos' ? 'MÜSAİT' : 'DOLU' ?>
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1"><?= e($oda['tip_adi']) ?></h5>
                                <small class="text-muted">Oda <?= e($oda['oda_no']) ?> · <?= e($oda['kat']) ?>. Kat</small>
                            </div>
                            <div class="text-end">
                                <div class="room-price"><?= format_para($oda['fiyat']) ?></div>
                                <small class="text-muted">/ gece</small>
                            </div>
                        </div>
                        <p class="text-muted small mb-3"><?= e($oda['aciklama']) ?></p>
                        <div class="d-flex gap-3 mb-3 text-muted small">
                            <span><i class="bi bi-people me-1"></i><?= e($oda['kapasite']) ?> Kişi</span>
                            <span><i class="bi bi-wifi me-1"></i>Ücretsiz WiFi</span>
                            <span><i class="bi bi-snow me-1"></i>Klima</span>
                        </div>
                        <?php if ($oda['durum'] === 'bos'): ?>
                            <a href="rezervasyon.php?oda_id=<?= $oda['oda_id'] ?>"
                               class="btn btn-gold w-100">Rezervasyon Yap</a>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100" disabled>Şu An Dolu</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<section id="hizmetler" style="padding: 80px 0;">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Olanaklar</span>
            <h2 class="section-title">Hizmetlerimiz</h2>
        </div>
        <div class="row g-4">
            <?php
            $hizmetler = [
                ['bi-water',       'Açık Yüzme Havuzu', 'Sonsuzluk havuzumuzda denizi seyredin'],
                ['bi-cup-hot',     'Spa & Wellness',     '5 yıldızlı spa deneyimi ve masaj hizmetleri'],
                ['bi-bicycle',     'Fitness Center',     '7/24 açık modern spor salonu'],
                ['bi-fork-knife',  'Fine Dining',        'Ödüllü şeflerimizden gurme lezzetler'],
                ['bi-car-front',   'Valet Park',         'Ücretsiz vale park hizmeti'],
                ['bi-wifi',        'Ücretsiz WiFi',      'Otel genelinde yüksek hızlı internet'],
                ['bi-balloon',     'Etkinlik Salonu',    'Toplantı ve özel günler için organizasyon'],
                ['bi-airplane',    'Transfer Hizmeti',   'Havalimanı-otel özel araç transferi'],
            ];
            foreach ($hizmetler as $h):
            ?>
            <div class="col-md-6 col-lg-3 fade-in-up">
                <div class="hizmet-card">
                    <i class="bi bi-<?= $h[0] ?> hizmet-icon"></i>
                    <h5 class="mb-2"><?= $h[1] ?></h5>
                    <p class="text-muted small mb-0"><?= $h[2] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>


<section style="padding: 80px 0; background: var(--navy);">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Galeri</span>
            <h2 class="section-title text-white">Otelimizden Kareler</h2>
        </div>
        <div class="row g-3">
            <?php
            $galeri = [
                ['image\XXL_height.webp', 'col-md-8', 'Restoranımız'],
                ['https://static.daktilo.com/sites/549/uploads/2026/02/25/kou-umuttepe-kampusune-nasil-gidilir-kocaeli-universitesine-giden-tum-otobus-hatlari-tam-liste-2026-wwwcagdaskocaelicomtr.webp', 'col-md-4', 'Suite Oda'],
                ['https://iasbh.tmgrup.com.tr/496c0d/650/344/0/121/752/516?u=https://isbh.tmgrup.com.tr/sbh/2025/07/17/kocaeli-universitesi-taban-puanlari-2025-kocaeli-universitesi-2-ve-4-yillik-bolumlerin-basari-siralamasi-ve-ko-1752751771381.jpg', 'col-md-4', 'Banyo'],
                ['https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=600&q=80', 'col-md-8', 'Yüzme Havuzu'],
            ];
            foreach ($galeri as $g):
            ?>
            <div class="<?= $g[1] ?>">
                <div style="height:260px; overflow:hidden; border-radius:12px;">
                    <img src="<?= $g[0] ?>" alt="<?= $g[2] ?>"
                         style="width:100%; height:100%; object-fit:cover; transition:transform 0.4s;"
                         onmouseover="this.style.transform='scale(1.05)'"
                         onmouseout="this.style.transform='scale(1)'">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding: 80px 0; background: linear-gradient(135deg, var(--gold) 0%, #b8860b 100%);">
    <div class="container text-center">
        <h2 class="section-title text-white mb-3">Rezervasyonunuzu Hemen Yapın</h2>
        <p class="text-white opacity-75 mb-4">Özel indirimler ve ayrıcalıklı paketler için bizimle iletişime geçin.</p>
        <a href="rezervasyon.php" class="btn btn-dark btn-lg px-5 py-3">
            <i class="bi bi-calendar-plus me-2"></i>Hemen Rezervasyon Yap
        </a>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
