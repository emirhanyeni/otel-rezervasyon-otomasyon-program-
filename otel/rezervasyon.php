<?php
session_start();
require_once 'baglanti.php';
require_once 'includes/auth.php';

giris_kontrol();

$musteri_id   = $_SESSION['musteri_id'];
$sayfa_baslik = 'Rezervasyon Yap';

$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }

$hata   = '';
$basari = '';


$secili_oda = (int)($_GET['oda_id'] ?? 0);


$giris  = $_GET['giris']  ?? '';
$cikis  = $_GET['cikis']  ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oda_id  = (int)($_POST['oda_id']        ?? 0);
    $giris_p = trim($_POST['giris_tarihi']    ?? '');
    $cikis_p = trim($_POST['cikis_tarihi']    ?? '');
    $notlar  = trim($_POST['notlar']          ?? '');

    if (!$oda_id || !$giris_p || !$cikis_p) {
        $hata = 'Lütfen oda ve tarih seçin.';
    } elseif ($cikis_p <= $giris_p) {
        $hata = 'Çıkış tarihi giriş tarihinden büyük olmalıdır.';
    } elseif ($giris_p < date('Y-m-d')) {
        $hata = 'Geçmiş bir tarihe rezervasyon yapamazsınız.';
    } else {
    
        $sonuc_var = '';
        $stmt = mysqli_prepare(
    $baglanti,
    "CALL sp_rezervasyon_olustur(?, ?, ?, ?, ?, @p_sonuc)"
);

mysqli_stmt_bind_param(
    $stmt,
    "iisss",
    $musteri_id,
    $oda_id,
    $giris_p,
    $cikis_p,
    $notlar
);

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);


mysqli_next_result($baglanti);


$sonuc_row = db_fetch_one($baglanti, "SELECT @p_sonuc AS sonuc");
$sonuc = $sonuc_row['sonuc'] ?? '';

        if (str_starts_with($sonuc, 'BASARILI:')) {
            $rez_id = substr($sonuc, 9);
            flash_set('basari', "Rezervasyonunuz oluşturuldu! Rezervasyon No: #{$rez_id}");
            header('Location: /otel/dashboard.php');
            exit;
        } else {
            $hata = str_replace('HATA:', '', $sonuc) ?: 'Bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}


$tum_odalar = db_fetch_all($baglanti,
    "SELECT o.*, ot.tip_adi, ot.kapasite
     FROM odalar o
     JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
     ORDER BY o.fiyat ASC"
);


$musait_odalar = [];
if ($giris && $cikis && $cikis > $giris) {
    // sp_musait_odalar çağır
    $stmt2 = mysqli_prepare($baglanti, "CALL sp_musait_odalar(?, ?)");
    mysqli_stmt_bind_param($stmt2, "ss", $giris, $cikis);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    while ($row = mysqli_fetch_assoc($result2)) {
        $musait_odalar[] = $row;
    }
    mysqli_stmt_close($stmt2);
    mysqli_next_result($baglanti); 
}
?>
<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <?php flash_goster(); ?>

    <div class="text-center mb-5">
        <span class="section-tag">Online Rezervasyon</span>
        <h2 class="section-title">Rezervasyon Yap</h2>
        <p class="text-muted">Tarih seçin, müsait odaları görün ve hemen rezervasyon yapın.</p>
    </div>

  
    <div class="rezervasyon-form-wrap mb-5 shadow">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-500"><i class="bi bi-calendar-plus me-2 text-gold"></i>Giriş Tarihi</label>
                <input type="date" name="giris" id="giris_tarihi" class="form-control form-control-lg"
                       value="<?= e($giris) ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-500"><i class="bi bi-calendar-minus me-2 text-gold"></i>Çıkış Tarihi</label>
                <input type="date" name="cikis" id="cikis_tarihi" class="form-control form-control-lg"
                       value="<?= e($cikis) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-gold btn-lg w-100">
                    <i class="bi bi-search me-2"></i>Müsait Odaları Ara
                </button>
            </div>
        </form>
    </div>

    <?php if ($hata): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($hata) ?></div>
    <?php endif; ?>

   
    <?php if ($giris && $cikis): ?>
        <!-- Tarih seçili: Müsait odaları göster -->
        <?php $gece = (new DateTime($giris))->diff(new DateTime($cikis))->days; ?>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="bi bi-check-circle text-success me-2"></i>
                <?= format_tarih($giris) ?> → <?= format_tarih($cikis) ?> (<?= $gece ?> gece)
            </h5>
            <span class="badge bg-success fs-6"><?= count($musait_odalar) ?> oda müsait</span>
        </div>

        <?php if (empty($musait_odalar)): ?>
            <div class="alert alert-warning text-center py-4">
                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                <strong>Seçili tarihlerde müsait oda bulunmamaktadır.</strong><br>
                <small class="text-muted">Farklı tarihler deneyebilirsiniz.</small>
            </div>
        <?php else: ?>
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
                foreach ($musait_odalar as $oda):
                    $gorsel = $oda_gorselleri[$oda['tip_adi']] ?? 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80';
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card room-card oda-bos border-0">
                        <img src="<?= $gorsel ?>" class="card-img-top" alt="<?= e($oda['tip_adi']) ?>" style="height:200px;object-fit:cover;">
                        <span class="position-absolute top-0 end-0 m-3 badge badge-bos px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>MÜSAİT
                        </span>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <h5 class="mb-0"><?= e($oda['tip_adi']) ?></h5>
                                    <small class="text-muted">Oda <?= e($oda['oda_no']) ?> · <?= e($oda['kat']) ?>. Kat</small>
                                </div>
                                <div class="text-end">
                                    <div class="room-price"><?= format_para($oda['fiyat']) ?></div>
                                    <small class="text-muted">/ gece</small>
                                </div>
                            </div>
                            <p class="text-muted small mb-3"><?= e($oda['aciklama'] ?? '') ?></p>
                            <div class="d-flex gap-3 mb-3 text-muted small">
                                <span><i class="bi bi-people me-1"></i><?= e($oda['kapasite']) ?> Kişi</span>
                                <span><i class="bi bi-wifi me-1"></i>WiFi</span>
                            </div>
                            <div class="bg-light rounded-3 p-3 mb-3 text-center">
                                <span class="text-muted small"><?= $gece ?> gece toplam: </span>
                                <strong class="text-gold fs-5"><?= format_para($oda['tahmini_toplam']) ?></strong>
                            </div>
                            <!-- Modal tetikle -->
                            <button class="btn btn-gold w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rezModal"
                                    data-oda-id="<?= $oda['oda_id'] ?>"
                                    data-oda-no="<?= e($oda['oda_no']) ?>"
                                    data-tip="<?= e($oda['tip_adi']) ?>"
                                    data-fiyat="<?= format_para($oda['tahmini_toplam']) ?>"
                                    data-giris="<?= e($giris) ?>"
                                    data-cikis="<?= e($cikis) ?>">
                                <i class="bi bi-calendar-plus me-2"></i>Rezervasyon Yap
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
       
        <h5 class="mb-4 text-muted"><i class="bi bi-grid me-2"></i>Tüm Odalarımız</h5>
        <div class="row g-3">
            <?php foreach ($tum_odalar as $oda): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-3 <?= $oda['durum']==='bos' ? 'oda-bos' : 'oda-dolu' ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Oda <?= e($oda['oda_no']) ?></strong>
                                <div class="text-muted small"><?= e($oda['tip_adi']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-gold"><?= format_para($oda['fiyat']) ?></div>
                                <span class="badge <?= $oda['durum']==='bos' ? 'badge-bos' : 'badge-dolu' ?> small">
                                    <?= $oda['durum'] === 'bos' ? 'Müsait' : 'Dolu' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <p class="text-muted">Müsait odaları görmek için lütfen yukarıdan tarih seçin.</p>
        </div>
    <?php endif; ?>
</div>


<div class="modal fade" id="rezModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-calendar-check text-gold me-2"></i>Rezervasyonu Onayla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bg-light rounded-3 p-3 mb-3" id="modalOzet"></div>
                <form method="POST" action="" id="rezForm">
                    <input type="hidden" name="oda_id"        id="modalOdaId">
                    <input type="hidden" name="giris_tarihi"  id="modalGiris">
                    <input type="hidden" name="cikis_tarihi"  id="modalCikis">
                    <div class="mb-3">
                        <label class="form-label small fw-500">Özel Notlar (İsteğe Bağlı)</label>
                        <textarea name="notlar" class="form-control" rows="3"
                                  placeholder="Erken giriş, özel istek vs..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-gold w-100 py-2">
                        <i class="bi bi-check-circle me-2"></i>Rezervasyonu Tamamla
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>

document.getElementById('rezModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalOdaId').value = btn.dataset.odaId;
    document.getElementById('modalGiris').value  = btn.dataset.giris;
    document.getElementById('modalCikis').value  = btn.dataset.cikis;
    document.getElementById('modalOzet').innerHTML =
        '<div class="row g-2 small">' +
        '<div class="col-6"><span class="text-muted">Oda:</span> <strong>' + btn.dataset.odaNo + ' – ' + btn.dataset.tip + '</strong></div>' +
        '<div class="col-6"><span class="text-muted">Tutar:</span> <strong class="text-gold">' + btn.dataset.fiyat + '</strong></div>' +
        '<div class="col-6"><span class="text-muted">Giriş:</span> <strong>' + btn.dataset.giris + '</strong></div>' +
        '<div class="col-6"><span class="text-muted">Çıkış:</span> <strong>' + btn.dataset.cikis + '</strong></div>' +
        '</div>';
});
</script>

<?php include 'includes/footer.php'; ?>