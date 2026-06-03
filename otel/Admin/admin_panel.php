<?php

session_start();
require_once '../baglanti.php';
require_once '../includes/auth.php';

admin_kontrol(); 

$icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
$otel_icerik = [];
foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }

$otel_adi = $otel_icerik['otel_adi'] ?? 'Umuttepe Otel';
$tab = $_GET['tab'] ?? 'dashboard';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oda_ekle'])) {
    $oda_no  = trim($_POST['oda_no']  ?? '');
    $tip_id  = (int)($_POST['tip_id'] ?? 0);
    $fiyat   = (float)($_POST['fiyat'] ?? 0);
    $kat     = (int)($_POST['kat']    ?? 1);
    $aciklama = trim($_POST['oda_aciklama'] ?? '');

    if ($oda_no && $tip_id && $fiyat > 0) {
        $stmt = db_query($baglanti,
            "INSERT INTO odalar (oda_no, tip_id, fiyat, kat, aciklama) VALUES (?, ?, ?, ?, ?)",
            "sidis", [$oda_no, $tip_id, $fiyat, $kat, $aciklama]
        );
        flash_set($stmt ? 'basari' : 'hata', $stmt ? 'Oda eklendi.' : 'Hata: Oda eklenemedi (oda_no benzersiz olmalı).');
    } else {
        flash_set('hata', 'Tüm zorunlu alanları doldurun.');
    }
    header('Location: admin_panel.php?tab=odalar');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oda_sil'])) {
    $oda_id = (int)($_POST['oda_id'] ?? 0);
    // Aktif rezervasyonu var mı kontrol et
    $kontrol = db_fetch_one($baglanti,
        "SELECT COUNT(*) AS c FROM rezervasyon WHERE oda_id=? AND durum IN('beklemede','onaylandi')",
        "i", [$oda_id]
    );
    if ($kontrol['c'] > 0) {
        flash_set('hata', 'Aktif rezervasyonu olan oda silinemez.');
    } else {
        db_query($baglanti, "DELETE FROM odalar WHERE oda_id=?", "i", [$oda_id]);
        flash_set('basari', 'Oda silindi.');
    }
    header('Location: admin_panel.php?tab=odalar');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oda_duzenle'])) {
    $oda_id  = (int)($_POST['oda_id']  ?? 0);
    $fiyat   = (float)($_POST['fiyat'] ?? 0);
    $durum   = $_POST['durum']          ?? 'bos';
    $kat     = (int)($_POST['kat']     ?? 1);
    $aciklama = trim($_POST['oda_aciklama'] ?? '');

    db_query($baglanti,
        "UPDATE odalar SET fiyat=?, durum=?, kat=?, aciklama=? WHERE oda_id=?",
        "dsisi", [$fiyat, $durum, $kat, $aciklama, $oda_id]
    );
    flash_set('basari', 'Oda güncellendi.');
    header('Location: admin_panel.php?tab=odalar');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rez_guncelle'])) {
    $rez_id = (int)($_POST['rez_id'] ?? 0);
    $durum  = $_POST['durum']         ?? '';
    if (in_array($durum, ['beklemede','onaylandi','iptal'])) {
        db_query($baglanti, "UPDATE rezervasyon SET durum=? WHERE rezervasyon_id=?", "si", [$durum, $rez_id]);
        flash_set('basari', 'Rezervasyon güncellendi.');
    }
    header('Location: admin_panel.php?tab=rezervasyonlar');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['icerik_guncelle'])) {
    foreach ($_POST['icerik'] ?? [] as $alan => $deger) {
        $alan_clean  = preg_replace('/[^a-z0-9_]/', '', $alan);
        $deger_clean = trim($deger);
        db_query($baglanti,
            "UPDATE otel_icerik SET deger=? WHERE alan=?",
            "ss", [$deger_clean, $alan_clean]
        );
    }
    flash_set('basari', 'İçerik güncellendi.');
    // Cache için otel_icerik yeniden yükle
    $icerik_rows = db_fetch_all($baglanti, "SELECT alan, deger FROM otel_icerik");
    $otel_icerik = [];
    foreach ($icerik_rows as $row) { $otel_icerik[$row['alan']] = $row['deger']; }
    header('Location: admin_panel.php?tab=icerik');
    exit;
}


$tum_odalar   = db_fetch_all($baglanti,
    "SELECT o.*, ot.tip_adi FROM odalar o JOIN oda_tipleri ot ON o.tip_id=ot.tip_id ORDER BY o.oda_no");
$oda_tipleri  = db_fetch_all($baglanti, "SELECT * FROM oda_tipleri ORDER BY tip_adi");
$tum_rezervasyonlar = db_fetch_all($baglanti,
    "SELECT * FROM v_rezervasyon_detay ORDER BY olusturma_tarihi DESC");
$tum_musteriler = db_fetch_all($baglanti, "SELECT * FROM musteri ORDER BY kayit_tarihi DESC");


$doluluk = db_fetch_all($baglanti, "SELECT * FROM v_doluluk_istatistik");


$toplam_oda  = count($tum_odalar);
$bos_oda     = count(array_filter($tum_odalar, fn($o) => $o['durum']==='bos'));
$aktif_rez   = count(array_filter($tum_rezervasyonlar, fn($r) => in_array($r['durum'], ['beklemede','onaylandi'])));
$toplam_mus  = count($tum_musteriler);


$stmt_gelir = mysqli_prepare($baglanti, "CALL sp_aylik_gelir(?, ?)");
$yil = (int)date('Y'); $ay = (int)date('n');
mysqli_stmt_bind_param($stmt_gelir, "ii", $yil, $ay);
mysqli_stmt_execute($stmt_gelir);
$gelir_result = mysqli_stmt_get_result($stmt_gelir);
$aylik_gelir  = mysqli_fetch_assoc($gelir_result) ?? [];
mysqli_stmt_close($stmt_gelir);
mysqli_next_result($baglanti);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli | <?= e($otel_adi) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="/otel/css/style.css" rel="stylesheet">
</head>
<body style="background:#f4f6fb;">

<div class="d-flex">
   
    <div class="admin-sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-building me-2"></i><?= e($otel_adi) ?>
            <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);font-family:'Jost',sans-serif;margin-top:2px;">Admin Paneli</div>
        </div>
        <nav class="nav flex-column p-2 mt-2">
            <a class="nav-link <?= $tab==='dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </a>
            <a class="nav-link <?= $tab==='odalar' ? 'active' : '' ?>" href="?tab=odalar">
                <i class="bi bi-door-open me-2"></i>Oda Yönetimi
            </a>
            <a class="nav-link <?= $tab==='rezervasyonlar' ? 'active' : '' ?>" href="?tab=rezervasyonlar">
                <i class="bi bi-calendar-check me-2"></i>Rezervasyonlar
            </a>
            <a class="nav-link <?= $tab==='musteriler' ? 'active' : '' ?>" href="?tab=musteriler">
                <i class="bi bi-people me-2"></i>Müşteriler
            </a>
            <a class="nav-link <?= $tab==='icerik' ? 'active' : '' ?>" href="?tab=icerik">
                <i class="bi bi-pencil-square me-2"></i>Site İçeriği
            </a>
            <hr style="border-color:rgba(255,255,255,0.1);">
            <a class="nav-link" href="/otel/index.php" target="_blank">
                <i class="bi bi-box-arrow-up-right me-2"></i>Siteye Git
            </a>
            <a class="nav-link text-danger-subtle" href="/otel/logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Çıkış Yap
            </a>
        </nav>
    </div>

    
    <div class="admin-content flex-grow-1">
        <?php
       
        if (isset($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            $renk = $f['tip'] === 'basari' ? 'success' : 'danger';
            echo '<div class="alert alert-'.$renk.' alert-dismissible fade show mb-4"><i class="bi bi-'.($f['tip']==='basari'?'check':'x').'-circle me-2"></i>'.e($f['mesaj']).'<button class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['flash']);
        }
        ?>

        <?php if ($tab === 'dashboard'): ?>
        
        <h4 class="mb-4"><i class="bi bi-speedometer2 me-2 text-gold"></i>Dashboard</h4>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 rounded-4 text-white" style="background:linear-gradient(135deg,#0a1628,#122040);">
                    <div class="card-body p-4">
                        <div class="fs-1 text-gold mb-1"><i class="bi bi-door-open"></i></div>
                        <div style="font-size:2rem;font-family:'Cormorant Garamond',serif;font-weight:700;"><?= $toplam_oda ?></div>
                        <div class="text-white-50 small">Toplam Oda</div>
                        <div class="text-success small mt-1"><?= $bos_oda ?> müsait</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 rounded-4 text-white" style="background:linear-gradient(135deg,#c9a84c,#9e7a20);">
                    <div class="card-body p-4">
                        <div class="fs-1 mb-1"><i class="bi bi-calendar-check"></i></div>
                        <div style="font-size:2rem;font-family:'Cormorant Garamond',serif;font-weight:700;"><?= $aktif_rez ?></div>
                        <div class="text-white-50 small">Aktif Rezervasyon</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 rounded-4 text-white" style="background:linear-gradient(135deg,#198754,#145f3c);">
                    <div class="card-body p-4">
                        <div class="fs-1 mb-1"><i class="bi bi-people"></i></div>
                        <div style="font-size:2rem;font-family:'Cormorant Garamond',serif;font-weight:700;"><?= $toplam_mus ?></div>
                        <div class="text-white-50 small">Kayıtlı Müşteri</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 rounded-4 text-white" style="background:linear-gradient(135deg,#0d6efd,#084298);">
                    <div class="card-body p-4">
                        <div class="fs-1 mb-1"><i class="bi bi-cash-coin"></i></div>
                        <div style="font-size:1.4rem;font-family:'Cormorant Garamond',serif;font-weight:700;">
                            <?= format_para($aylik_gelir['toplam_gelir'] ?? 0) ?>
                        </div>
                        <div class="text-white-50 small">Bu Ayki Gelir</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doluluk tablosu (VIEW kullanır) -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <h6><i class="bi bi-bar-chart me-2 text-gold"></i>Oda Tipi Doluluk Oranları</h6>
                        <small class="text-muted">v_doluluk_istatistik VIEW'ından</small>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-sm table-otel">
                            <thead><tr><th>Tip</th><th>Toplam</th><th>Dolu</th><th>Boş</th><th>Doluluk %</th></tr></thead>
                            <tbody>
                            <?php foreach ($doluluk as $d): ?>
                            <tr>
                                <td><?= e($d['tip_adi']) ?></td>
                                <td><?= $d['toplam_oda'] ?></td>
                                <td><span class="text-danger"><?= $d['dolu_oda'] ?></span></td>
                                <td><span class="text-success"><?= $d['bos_oda'] ?></span></td>
                                <td>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-warning" style="width:<?= $d['doluluk_orani'] ?>%"></div>
                                    </div>
                                    <small><?= $d['doluluk_orani'] ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <h6><i class="bi bi-clock-history me-2 text-gold"></i>Son Rezervasyonlar</h6>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-sm table-otel">
                            <thead><tr><th>#</th><th>Müşteri</th><th>Oda</th><th>Durum</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($tum_rezervasyonlar, 0, 8) as $r): ?>
                            <tr>
                                <td class="text-muted small"><?= $r['rezervasyon_id'] ?></td>
                                <td class="small"><?= e($r['musteri_adi']) ?></td>
                                <td class="small"><?= e($r['oda_no']) ?></td>
                                <td>
                                    <?php $badge = ['onaylandi'=>'success','beklemede'=>'warning','iptal'=>'danger'][$r['durum']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $badge ?> small"><?= e($r['durum']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'odalar'): ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-door-open me-2 text-gold"></i>Oda Yönetimi</h4>
            <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#odaEkleModal">
                <i class="bi bi-plus-circle me-2"></i>Yeni Oda Ekle
            </button>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-otel align-middle">
                        <thead>
                            <tr><th>Oda No</th><th>Tip</th><th>Kat</th><th>Fiyat/Gece</th><th>Durum</th><th>İşlem</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tum_odalar as $oda): ?>
                            <tr>
                                <td><strong><?= e($oda['oda_no']) ?></strong></td>
                                <td><?= e($oda['tip_adi']) ?></td>
                                <td><?= $oda['kat'] ?>. Kat</td>
                                <td class="text-gold fw-semibold"><?= format_para($oda['fiyat']) ?></td>
                                <td>
                                    <span class="badge <?= $oda['durum']==='bos' ? 'badge-bos' : 'badge-dolu' ?>">
                                        <?= $oda['durum']==='bos' ? 'Müsait' : 'Dolu' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1"
                                            data-bs-toggle="modal" data-bs-target="#odaDuzenleModal"
                                            data-oda-id="<?= $oda['oda_id'] ?>"
                                            data-fiyat="<?= $oda['fiyat'] ?>"
                                            data-durum="<?= e($oda['durum']) ?>"
                                            data-kat="<?= $oda['kat'] ?>"
                                            data-aciklama="<?= e($oda['aciklama'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="oda_id" value="<?= $oda['oda_id'] ?>">
                                        <button type="submit" name="oda_sil" class="btn btn-sm btn-outline-danger btn-sil-onayla">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

       
        <div class="modal fade" id="odaEkleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4">
                    <div class="modal-header border-0"><h5 class="modal-title">Yeni Oda Ekle</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small fw-500">Oda No *</label>
                                    <input type="text" name="oda_no" class="form-control" placeholder="101" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-500">Oda Tipi *</label>
                                    <select name="tip_id" class="form-select" required>
                                        <?php foreach ($oda_tipleri as $tip): ?>
                                        <option value="<?= $tip['tip_id'] ?>"><?= e($tip['tip_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-500">Fiyat (₺/gece) *</label>
                                    <input type="number" name="fiyat" class="form-control" step="0.01" min="1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-500">Kat *</label>
                                    <input type="number" name="kat" class="form-control" value="1" min="1" max="20" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-500">Açıklama</label>
                                    <textarea name="oda_aciklama" class="form-control" rows="2" placeholder="Kısa oda açıklaması..."></textarea>
                                </div>
                            </div>
                            <button type="submit" name="oda_ekle" class="btn btn-gold w-100 mt-3">Oda Ekle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

      
        <div class="modal fade" id="odaDuzenleModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4">
                    <div class="modal-header border-0"><h5 class="modal-title">Oda Düzenle</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <form method="POST">
                            <input type="hidden" name="oda_id" id="editOdaId">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small fw-500">Fiyat (₺/gece)</label>
                                    <input type="number" name="fiyat" id="editFiyat" class="form-control" step="0.01" min="1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-500">Kat</label>
                                    <input type="number" name="kat" id="editKat" class="form-control" min="1" max="20" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-500">Durum</label>
                                    <select name="durum" id="editDurum" class="form-select">
                                        <option value="bos">Müsait</option>
                                        <option value="dolu">Dolu</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-500">Açıklama</label>
                                    <textarea name="oda_aciklama" id="editAciklama" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <button type="submit" name="oda_duzenle" class="btn btn-gold w-100 mt-3">Güncelle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'rezervasyonlar'): ?>
        
        <h4 class="mb-4"><i class="bi bi-calendar-check me-2 text-gold"></i>Tüm Rezervasyonlar</h4>
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-otel align-middle">
                        <thead>
                            <tr><th>#</th><th>Müşteri</th><th>Oda</th><th>Giriş</th><th>Çıkış</th><th>Gece</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tum_rezervasyonlar as $r): ?>
                            <tr>
                                <td class="text-muted small"><?= $r['rezervasyon_id'] ?></td>
                                <td>
                                    <strong><?= e($r['musteri_adi']) ?></strong><br>
                                    <small class="text-muted"><?= e($r['email']) ?></small>
                                </td>
                                <td><?= e($r['oda_no']) ?><br><small class="text-muted"><?= e($r['tip_adi']) ?></small></td>
                                <td class="small"><?= format_tarih($r['giris_tarihi']) ?></td>
                                <td class="small"><?= format_tarih($r['cikis_tarihi']) ?></td>
                                <td class="small"><?= $r['gece_sayisi'] ?></td>
                                <td class="small fw-semibold text-gold"><?= format_para($r['toplam_fiyat']) ?></td>
                                <td>
                                    <?php $badge = ['onaylandi'=>'success','beklemede'=>'warning','iptal'=>'danger'][$r['durum']] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $badge ?>"><?= e($r['durum']) ?></span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="rez_id" value="<?= $r['rezervasyon_id'] ?>">
                                        <select name="durum" class="form-select form-select-sm" style="min-width:100px;">
                                            <option value="beklemede" <?= $r['durum']==='beklemede'?'selected':'' ?>>Beklemede</option>
                                            <option value="onaylandi" <?= $r['durum']==='onaylandi'?'selected':'' ?>>Onaylandı</option>
                                            <option value="iptal"     <?= $r['durum']==='iptal'    ?'selected':'' ?>>İptal</option>
                                        </select>
                                        <button type="submit" name="rez_guncelle" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'musteriler'): ?>
        
        <h4 class="mb-4"><i class="bi bi-people me-2 text-gold"></i>Müşteri Listesi</h4>
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-otel align-middle">
                        <thead>
                            <tr><th>#</th><th>Ad Soyad</th><th>E-posta</th><th>Telefon</th><th>Rol</th><th>Kayıt Tarihi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tum_musteriler as $m): ?>
                            <tr>
                                <td class="text-muted small"><?= $m['musteri_id'] ?></td>
                                <td><strong><?= e($m['ad'] . ' ' . $m['soyad']) ?></strong></td>
                                <td class="small"><?= e($m['email']) ?></td>
                                <td class="small"><?= e($m['telefon']) ?></td>
                                <td><span class="badge <?= $m['rol']==='admin'?'bg-warning text-dark':'bg-secondary' ?>"><?= e($m['rol']) ?></span></td>
                                <td class="small"><?= format_tarih($m['kayit_tarihi']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'icerik'): ?>
        
        <h4 class="mb-4"><i class="bi bi-pencil-square me-2 text-gold"></i>Site İçeriği Düzenle</h4>
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-4">
                        <?php foreach ($otel_icerik as $alan => $deger): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-500 text-uppercase text-muted" style="letter-spacing:.1em;"><?= e(str_replace('_', ' ', $alan)) ?></label>
                            <?php if (strlen($deger) > 80): ?>
                                <textarea name="icerik[<?= e($alan) ?>]" class="form-control" rows="3"><?= e($deger) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="icerik[<?= e($alan) ?>]" class="form-control" value="<?= e($deger) ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="icerik_guncelle" class="btn btn-gold mt-4 px-5">
                        <i class="bi bi-save me-2"></i>İçeriği Kaydet
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/otel/js/main.js"></script>
<script>

document.getElementById('odaDuzenleModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editOdaId').value    = btn.dataset.odaId;
    document.getElementById('editFiyat').value    = btn.dataset.fiyat;
    document.getElementById('editKat').value      = btn.dataset.kat;
    document.getElementById('editAciklama').value = btn.dataset.aciklama;
    const durumSelect = document.getElementById('editDurum');
    for (let opt of durumSelect.options) {
        opt.selected = (opt.value === btn.dataset.durum);
    }
});
</script>
</body>
</html>