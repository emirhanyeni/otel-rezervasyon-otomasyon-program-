<?php

session_start();
require_once 'baglanti.php';
require_once 'includes/auth.php';

giris_kontrol();

$musteri_id   = $_SESSION['musteri_id'];
$sayfa_baslik = 'Hesabım';

$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }

// Müşteri bilgileri
$musteri = db_fetch_one($baglanti,
    "SELECT * FROM musteri WHERE musteri_id = ?", "i", [$musteri_id]);

// Müşterinin rezervasyonları
$rezervasyonlar = db_fetch_all($baglanti,
    "SELECT r.*, o.oda_no, ot.tip_adi,
            DATEDIFF(r.cikis_tarihi, r.giris_tarihi) AS gece
     FROM rezervasyon r
     JOIN odalar o       ON r.oda_id = o.oda_id
     JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
     WHERE r.musteri_id = ?
     ORDER BY r.olusturma_tarihi DESC",
    "i", [$musteri_id]
);

// İstatistikler
$toplam_rez   = count($rezervasyonlar);
$aktif_rez    = count(array_filter($rezervasyonlar, fn($r) => in_array($r['durum'], ['beklemede','onaylandi'])));
$toplam_harcama = array_sum(array_column(
    array_filter($rezervasyonlar, fn($r) => $r['durum'] === 'onaylandi'),
    'toplam_fiyat'
));

// İptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iptal_id'])) {
    $iptal_id = (int)($_POST['iptal_id'] ?? 0);
    
    $kontrol = db_fetch_one($baglanti,
        "SELECT rezervasyon_id FROM rezervasyon WHERE rezervasyon_id=? AND musteri_id=? AND durum!='iptal'",
        "ii", [$iptal_id, $musteri_id]
    );
    if ($kontrol) {
        db_query($baglanti, "UPDATE rezervasyon SET durum='iptal' WHERE rezervasyon_id=?", "i", [$iptal_id]);
        flash_set('basari', 'Rezervasyon iptal edildi.');
    } else {
        flash_set('hata', 'İptal işlemi başarısız.');
    }
    header('Location: /otel/dashboard.php');
    exit;
}
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <?php flash_goster(); ?>

   
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="section-title mb-1">Merhaba, <?= e($musteri['ad']) ?>! 👋</h2>
            <p class="text-muted">Rezervasyonlarınızı buradan takip edebilirsiniz.</p>
        </div>
        <a href="rezervasyon.php" class="btn btn-gold px-4 py-2">
            <i class="bi bi-plus-circle me-2"></i>Yeni Rezervasyon
        </a>
    </div>

    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="dash-card" style="background:linear-gradient(135deg,#0a1628,#122040); color:#fff;">
                <div class="dash-icon text-gold mb-2"><i class="bi bi-calendar-check"></i></div>
                <div class="dash-num text-gold"><?= $toplam_rez ?></div>
                <div class="text-white-50 small">Toplam Rezervasyon</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-card" style="background:linear-gradient(135deg,#198754,#145f3c); color:#fff;">
                <div class="dash-icon mb-2"><i class="bi bi-door-open"></i></div>
                <div class="dash-num"><?= $aktif_rez ?></div>
                <div class="text-white-50 small">Aktif Rezervasyon</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-card" style="background:linear-gradient(135deg,#c9a84c,#9e7a20); color:#fff;">
                <div class="dash-icon mb-2"><i class="bi bi-cash-coin"></i></div>
                <div class="dash-num"><?= format_para($toplam_harcama) ?></div>
                <div class="text-white-50 small">Toplam Harcama</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2 text-gold"></i>Rezervasyonlarım</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($rezervasyonlar)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                            <p>Henüz bir rezervasyonunuz bulunmuyor.</p>
                            <a href="rezervasyon.php" class="btn btn-gold px-4">İlk Rezervasyonumu Yap</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-otel align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Oda</th>
                                        <th>Giriş</th>
                                        <th>Çıkış</th>
                                        <th>Gece</th>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rezervasyonlar as $r): ?>
                                    <tr>
                                        <td class="text-muted small"><?= $r['rezervasyon_id'] ?></td>
                                        <td>
                                            <strong><?= e($r['oda_no']) ?></strong>
                                            <br><small class="text-muted"><?= e($r['tip_adi']) ?></small>
                                        </td>
                                        <td class="small"><?= format_tarih($r['giris_tarihi']) ?></td>
                                        <td class="small"><?= format_tarih($r['cikis_tarihi']) ?></td>
                                        <td class="small"><?= $r['gece'] ?></td>
                                        <td class="small fw-semibold"><?= format_para($r['toplam_fiyat']) ?></td>
                                        <td>
                                            <?php
                                            $badge = match($r['durum']) {
                                                'onaylandi' => 'success',
                                                'beklemede' => 'warning',
                                                'iptal'     => 'danger',
                                                default     => 'secondary'
                                            };
                                            $lbl = match($r['durum']) {
                                                'onaylandi' => 'Onaylandı',
                                                'beklemede' => 'Beklemede',
                                                'iptal'     => 'İptal',
                                                default     => $r['durum']
                                            };
                                            ?>
                                            <span class="badge bg-<?= $badge ?>"><?= $lbl ?></span>
                                        </td>
                                        <td>
                                            <?php if (in_array($r['durum'], ['beklemede', 'onaylandi'])): ?>
                                            <form method="POST" action="" style="display:inline">
                                                <input type="hidden" name="iptal_id" value="<?= $r['rezervasyon_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-sil-onayla"
                                                        title="İptal Et">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profil Bilgileri -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0"><i class="bi bi-person me-2 text-gold"></i>Profil Bilgilerim</h5>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--navy),var(--navy2));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                            <span style="color:var(--gold);font-size:1.8rem;font-family:'Cormorant Garamond',serif;font-weight:700;">
                                <?= strtoupper(mb_substr($musteri['ad'], 0, 1)) ?>
                            </span>
                        </div>
                        <h6 class="mb-0"><?= e($musteri['ad'] . ' ' . $musteri['soyad']) ?></h6>
                        <small class="text-muted"><?= e($musteri['email']) ?></small>
                    </div>
                    <ul class="list-unstyled small">
                        <li class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted"><i class="bi bi-telephone me-2"></i>Telefon</span>
                            <span class="fw-500"><?= e($musteri['telefon']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted"><i class="bi bi-calendar me-2"></i>Üyelik</span>
                            <span class="fw-500"><?= format_tarih($musteri['kayit_tarihi']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between py-2">
                            <span class="text-muted"><i class="bi bi-shield me-2"></i>Rol</span>
                            <span class="badge bg-dark"><?= e($musteri['rol']) ?></span>
                        </li>
                    </ul>
                    <a href="logout.php" class="btn btn-outline-danger w-100 mt-3 btn-sm">
                        <i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes\footer.php'; ?>