#  Otel Rezervasyon Otomasyon Programı — Proje Raporu

**Proje Adı:** Umuttepe Otel- Otel Rezervasyon Otomasyon Programı 
**Ders:** Veritabanı Yönetim Sistemleri — Dönem Projesi  
**Teknolojiler:** PHP · MySQL · HTML/CSS/JavaScript · Bootstrap 5 · XAMPP  

---

## İçindekiler

1. [Problem Tanımı](#1-problem-tanımı)
2. [Yapılan Araştırmalar](#2-yapılan-araştırmalar)
3. [Akış Şeması](#3-akış-şeması)
4. [Yazılım Mimarisi](#4-yazılım-mimarisi)
5. [Veritabanı Diyagramı (ER)](#5-veritabanı-diyagramı-er)
6. [Genel Yapı](#6-genel-yapı)
7. [Kurulum](#7-kurulum)
8. [Referanslar](#8-referanslar)

---

## 1. Problem Tanımı

Geleneksel otel yönetiminde rezervasyonlar çoğunlukla telefon, e-posta veya manuel kayıt defterleriyle takip edilmektedir. Bu yöntem; çift rezervasyon, oda müsaitlik bilgisinin anlık görüntülenememesi, müşteri kayıtlarında tutarsızlık ve yönetimsel raporlama güçlükleri gibi ciddi operasyonel sorunlara yol açmaktadır.

Bu proje kapsamında çözülmesi hedeflenen problemler şunlardır:

| Problem | Uygulanan Çözüm |
|---|---|
| **Oda çakışmaları** | `sp_rezervasyon_olustur()` stored procedure içindeki Allen Interval Algebra tabanlı tarih örtüşme kontrolü |
| **Anlık müsaitlik** | `v_bos_odalar` view'ı ve `odalar.durum` alanının trigger ile senkronizasyonu |
| **Müşteri yönetimi** | Kayıt/giriş sistemi, bcrypt şifre hash'i, rol tabanlı yetkilendirme |
| **Merkezi yönetim** | Tab'lı admin paneli; oda, rezervasyon, müşteri ve içerik yönetimi tek ekranda |
| **Otomatik fiyat hesaplama** | `trg_fiyat_hesapla` trigger'ı INSERT öncesi gece sayısı × oda fiyatını hesaplar |
| **Ölçeklenebilir içerik** | `otel_icerik` tablosu ile otel adı, slogan, iletişim; kod değişikliği olmadan güncellenebilir |

### 1.1 Hedef Kullanıcı Grupları

```
┌────────────────────────────────┐    ┌────────────────────────────────┐
│         MÜŞTERİ                │    │          ADMIN                 │
│                                │    │                                │
│  • Kayıt / Giriş               │    │  • Tüm rezervasyonları yönet   │
│  • Müsait oda sorgulama        │    │  • Odaları ekle / düzenle / sil│
│  • Rezervasyon oluşturma       │    │  • Müşteri listesi görüntüle   │
│  • Rezervasyonlarını takip     │    │  • Aylık gelir raporu al       │
│  • İptal talebi                │    │  • Otel içeriğini güncelle     │
└────────────────────────────────┘    └────────────────────────────────┘
```

---

## 2. Yapılan Araştırmalar

### 2.1 Veritabanı Tasarımı ve Normalizasyon

Proje geliştirme sürecinde en temel sorun, oda tipi bilgilerinin tekrar etmemesi için doğru normalizasyon seviyesinin belirlenmesiydi. İlk tasarımda oda tipi, fiyat ve kapasite bilgisi `odalar` tablosunda tutulmaktaydı; ancak bu yapı **2NF/3NF** ihlallerine yol açıyordu.

**Karşılaşılan sorun:** `odalar` tablosunda `tip_adi` ve `kapasite` gibi alanlar, birincil anahtar olan `oda_id`'ye değil, `tip_id`'ye bağımlıydı.  
**Çözüm:** `oda_tipleri` adında ayrı bir tablo oluşturularak 3NF sağlandı.

### 2.2 Tarih Çakışması Algoritması

**Karşılaşılan sorun:** Aynı oda için çakışan rezervasyonlar nasıl önlenecek?  
**Çözüm:** Aşağıdaki mantık uygulandı:

```sql
-- Çakışma tespiti: Yeni rezervasyonun mevcut rezervasyonlarla örtüşmesi
NOT (cikis_tarihi <= p_giris OR giris_tarihi >= p_cikis)
```

Bu formül, aşağıdaki tüm çakışma durumlarını tek bir koşulla yakalar:

```
Mevcut:   [========]
Yeni A:     [====]        → içinde kalan (çakışır)
Yeni B:   [==========]   → tamamen kapsayan (çakışır)
Yeni C: [====]            → başta biten (çakışmaz)
Yeni D:           [====]  → sonda başlayan (çakışmaz)
```

### 2.3 Güvenlik Araştırmaları

OWASP rehberleri  incelenerek üç katmanlı güvenlik modeli benimsendi:

**SQL Injection Koruması (OWASP):** Kullanıcıdan gelen tüm veriler Prepared Statement ile parametrik sorguya dönüştürüldü. `baglanti.php` içindeki `db_query()` yardımcı fonksiyonu bu işlemi standartlaştırır.

```php
// Tüm sorgular bu pattern'i izler:
$stmt = db_query($baglanti, "SELECT * FROM musteri WHERE email=?", "s", [$email]);
```

**XSS Koruması (OWASP):** Kullanıcıdan gelen ve HTML çıktısına gömülen her veri `htmlspecialchars()` ile temizlenir. `baglanti.php` içindeki `e()` kısayol fonksiyonu tüm view dosyalarında kullanılmaktadır.

**Şifre Güvenliği:** PHP'nin `password_hash()` fonksiyonu bcrypt algoritmasıyla şifreleme yaparken `password_verify()` doğrulama için kullanılmaktadır. [^1]

### 2.4 Trigger ve Stored Procedure Kullanımı

İş mantığının uygulama katmanında değil veritabanında tutulmasının faydaları araştırılarak öğrenildi. [^2] Bu yaklaşım sayesinde:

- PHP kodu veri tutarsızlığı yaratamaz; fiyat hesaplama ve durum güncellemesi her zaman veritabanı tarafından garantilenir.
- Farklı kaynaklardan (PHP, phpMyAdmin, direkt SQL) yapılan INSERT/UPDATE işlemleri de aynı iş kurallarına tabi olur.

### 2.5 Arayüz ve Şablon Araştırması

Bootstrap 5 [^4] responsive grid sistemi ve bileşenleri incelendi. Colorlib'den alınan HTML şablonu [^10] PHP'ye dönüştürülerek header, footer ve auth kısımları `includes/` klasörüne taşındı ve MVC benzeri bir yapı oluşturuldu.

---

## 3. Akış Şeması

### 3.1 Rezervasyon Oluşturma Akışı

```
 ┌─────────────────────────────────────────────────────────────────────────┐
 │                        KULLANICI AKIŞI                                  │
 └─────────────────────────────────────────────────────────────────────────┘

        [Kullanıcı → index.php]
                │
                ▼
        [Oda Listesi Görüntülenir]
                │
                ▼ "Rezervasyon Yap" butonuna tıklar
                │
        ┌───────┴───────┐
        │ Oturum var mı?│
        └───────┬───────┘
                │
        ┌───────▼──────────┐      ┌──────────────────────┐
        │   HAYIR          │─────▶│ login.php             │
        │                  │      │ Giriş / Kayıt Formu   │
        └──────────────────┘      └──────────┬───────────┘
                                             │ Başarılı giriş
        ┌────────────────────────────────────┘
        │
        ▼
[rezervasyon.php — Oda + Tarih Seçim Formu]
        │
        ▼ POST isteği
        │
┌───────┴──────────────────────────┐
│      SUNUCU TARAFLI DOĞRULAMA    │
│  • Oda ID, tarih alanları boş mu?│
│  • Çıkış tarihi > giriş tarihi?  │
│  • Geçmiş tarih mi?              │
└───────┬─────────────┬────────────┘
        │             │
     HATA ◀──────     │ GEÇERLİ
        │             │
   Hata mesajı        ▼
   sayfaya        [CALL sp_rezervasyon_olustur()]
   döndürülür         │
                      ▼
             ┌────────┴──────────────────────────────┐
             │       SP İÇİ KONTROLLER               │
             │  1. Tarih çakışma sorgusu              │
             │     NOT(cikis<=giris OR giris>=cikis)  │
             └────────┬───────────────────────────────┘
                      │
           ┌──────────▼─────────────┐
           │ Çakışma var mı?        │
           └──────────┬─────────────┘
                      │
         EVET ◀───────┤
          │           │ HAYIR
   "Oda bu tarihlerde │
   dolu" hatası       ▼
                  [INSERT INTO rezervasyon]
                      │
                      ▼ (otomatik tetiklenir)
             ┌────────────────────────────────┐
             │  TRIGGER: trg_fiyat_hesapla    │
             │  toplam_fiyat = gece × fiyat   │
             └────────────┬───────────────────┘
                          │
             ┌────────────▼───────────────────┐
             │  TRIGGER: trg_rezervasyon_ekle │
             │  odalar.durum → 'dolu'         │
             └────────────┬───────────────────┘
                          │
                          ▼
                  [Başarı mesajı + Flash]
                          │
                          ▼
                  [Yönlendirme: dashboard.php]
```

### 3.2 Admin Paneli Akışı

```
[Admin Girişi → admin_panel.php]
        │
        ▼
┌───────────────────────────────────────────────────────────────┐
│                     ADMIN PANEL TAB'LARI                      │
├───────────────┬───────────────┬───────────────┬───────────────┤
│  Dashboard    │  Rezervasyonlar│    Odalar     │   İçerik      │
│               │               │               │               │
│ • Toplam oda  │ • Listele     │ • Ekle        │ • Otel adı    │
│ • Doluluk %   │ • Onayla      │ • Düzenle     │ • Slogan      │
│ • Bu ay gelir │ • İptal et    │ • Sil*        │ • İletişim    │
│ • Bekleyen    │ • Filtrele    │               │               │
│   rezervasyon │   (durum/tarih│ *Aktif rezerv.│               │
│   sayısı      │   /müşteri)   │  varsa silinemez              │
└───────────────┴───────────────┴───────────────┴───────────────┘
        │                               │
        │ İptal edilirse                │
        ▼                               ▼
[TRIGGER: trg_rezervasyon_iptal]    [TRIGGER: trg_rezervasyon_sil]
  rezervasyon.durum → 'iptal'         odalar.durum → 'bos'
  odalar.durum → 'bos'
```

### 3.3 Güvenlik Katmanı Akışı

```
HTTP İsteği
     │
     ▼
[XSS: htmlspecialchars() / e()]
     │
     ▼
[Oturum Kontrolü: giris_kontrol() / admin_kontrol()]
     │
     ▼
[SQL Injection: Prepared Statement / db_query()]
     │
     ▼
[İş Mantığı: Stored Procedure]
     │
     ▼
[DB Kısıtları: CHECK, UNIQUE, FK, TRIGGER]
     │
     ▼
HTTP Yanıtı
```

---

## 4. Yazılım Mimarisi

Proje, **MVC (Model-View-Controller)** mimarisine yakın, dosya tabanlı bir PHP yapısıyla geliştirilmiştir.

### 4.1 Dizin Yapısı

```
otel/
├── index.php              # Ana sayfa (oda listesi, hero, hakkımızda)
├── login.php              # Giriş formu (oturum varsa redirect)
├── kayit.php              # Kayıt formu (bcrypt hash, validasyon)
├── logout.php             # session_destroy() + redirect
├── rezervasyon.php        # Rezervasyon formu + sp_rezervasyon_olustur()
├── dashboard.php          # Müşteri paneli (sp_musteri_rezervasyonlari)
├── baglanti.php           # DB bağlantısı + yardımcı fonksiyonlar
│                          #   db_query(), db_fetch_one(), db_fetch_all()
│                          #   e(), format_tarih(), format_para()
│
├── includes/
│   ├── auth.php           # giris_kontrol(), admin_kontrol()
│   │                      # zaten_giris_yapilmis(), flash_set(), flash_goster()
│   ├── header.php         # Ortak HTML başlığı + Bootstrap nav
│   └── footer.php         # Ortak HTML alt bilgisi + JS importları
│
├── Admin/
│   └── admin_panel.php    # Tab'lı admin paneli (tek dosya)
│                          # Dashboard / Rezervasyonlar / Odalar / İçerik
│
├── Css/
│   └── style.css          # Özel CSS (Bootstrap override + proje stilleri)
│
├── Js/
│   └── main.js            # Fiyat hesaplama (JS), tarih validasyonu, UX
│
├── MySql/
│   └── otel_db.sql        # Tam şema: tablo, index, view, trigger, SP, seed
│
└── image/                 # Yüklenen oda görselleri
```

### 4.2 Mimari Katmanlar

| Katman | Sorumluluk | Dosyalar |
|---|---|---|
| **Sunum (View)** | HTML çıktısı, Bootstrap UI, kullanıcı etkileşimi | `*.php` (HTML kısımları), `style.css`, `main.js` |
| **Uygulama (Controller)** | Form işleme, yönlendirme, doğrulama, flash mesaj | `*.php` (PHP kısımları), `auth.php` |
| **Veri Erişimi (Model)** | Hazır fonksiyonlar, parametre bağlama, sonuç döndürme | `baglanti.php` |
| **İş Mantığı** | Fiyat hesaplama, çakışma kontrolü, durum güncelleme | MySQL Stored Procedures + Triggers |
| **Veritabanı** | Veri saklama, bütünlük kısıtları, performans | MySQL 8.0 (`otel_db`) |

### 4.3 Bileşen İlişki Şeması

```
  [Tarayıcı / Bootstrap 5 UI]
          │  HTTP Request
          ▼
  ┌───────────────────────────────────────┐
  │         PHP Uygulama Katmanı          │
  │                                       │
  │   index.php   rezervasyon.php         │
  │   login.php   dashboard.php           │
  │   kayit.php   Admin/admin_panel.php   │
  │                                       │
  │   includes/auth.php ──── Oturum ve   │
  │                          yetki yön.  │
  └───────────────┬───────────────────────┘
                  │  mysqli_* / Prepared Statements
                  ▼
  ┌───────────────────────────────────────┐
  │           MySQL Veritabanı            │
  │                                       │
  │  Tablolar: odalar, musteri,           │
  │            rezervasyon, oda_tipleri,  │
  │            otel_icerik               │
  │                                       │
  │  View'lar: v_bos_odalar,             │
  │            v_rezervasyon_detay,       │
  │            v_doluluk_istatistik       │
  │                                       │
  │  Trigger'lar: trg_fiyat_hesapla,     │
  │               trg_rezervasyon_ekle,   │
  │               trg_rezervasyon_iptal,  │
  │               trg_rezervasyon_sil     │
  │                                       │
  │  SP'ler: sp_musait_odalar,           │
  │          sp_rezervasyon_olustur,      │
  │          sp_musteri_rezervasyonlari,  │
  │          sp_aylik_gelir              │
  └───────────────────────────────────────┘
```

### 4.4 Önemli Kod Desenleri

**Prepared Statement Wrapper (`baglanti.php`):**
```php
function db_query($conn, $sql, $types = '', $params = []) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($types && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}
```

**Stored Procedure Çağrısı (`rezervasyon.php`):**
```php
$stmt = mysqli_prepare($baglanti,
    "CALL sp_rezervasyon_olustur(?, ?, ?, ?, ?, @p_sonuc)"
);
mysqli_stmt_bind_param($stmt, "iisss",
    $musteri_id, $oda_id, $giris_p, $cikis_p, $notlar
);
mysqli_stmt_execute($stmt);

$sonuc_row = db_fetch_one($baglanti, "SELECT @p_sonuc AS sonuc");
```

**Tarih Çakışma Kontrolü (SP içinde):**
```sql
SELECT COUNT(*) INTO v_cakisma
FROM rezervasyon
WHERE oda_id = p_oda_id
  AND durum != 'iptal'
  AND NOT (cikis_tarihi <= p_giris OR giris_tarihi >= p_cikis);
```

---

## 5. Veritabanı Diyagramı (ER)

### 5.1 Varlık-İlişki Diyagramı

```
┌─────────────────────┐          ┌─────────────────────────────┐
│     oda_tipleri     │          │           odalar            │
├─────────────────────┤          ├─────────────────────────────┤
│ PK  tip_id    INT   │──1─────N─│ PK  oda_id      INT         │
│     tip_adi   VC50  │          │     oda_no      VC10  UNIQ  │
│     aciklama  TEXT  │          │ FK  tip_id      INT         │
│     kapasite  INT   │          │     fiyat       DEC(10,2)   │
│     CHECK(1–10)     │          │     durum       ENUM        │
└─────────────────────┘          │                'bos'/'dolu' │
                                 │     kat         INT         │
                                 │     aciklama    TEXT        │
                                 └────────────┬────────────────┘
                                              │
                                              │ 1
                                              │
                                              N
                                 ┌────────────┴────────────────┐
                                 │         rezervasyon         │
                                 ├─────────────────────────────┤
┌──────────────────────┐         │ PK  rezervasyon_id   INT    │
│        musteri       │         │ FK  musteri_id       INT    │
├──────────────────────┤         │ FK  oda_id           INT    │
│ PK  musteri_id   INT │──1────N─│     giris_tarihi     DATE   │
│     ad           VC  │         │     cikis_tarihi     DATE   │
│     soyad        VC  │         │     toplam_fiyat DEC(10,2)  │
│     email        VC  │ UNIQUE  │     durum       ENUM        │
│     telefon      VC  │         │         'beklemede'         │
│     sifre        VC  │ bcrypt  │         'onaylandi'         │
│     kayit_tarihi DT  │         │         'iptal'             │
│     rol          ENUM│         │     notlar      TEXT        │
│    'musteri'/'admin' │         │     olusturma_tarihi DT     │
└──────────────────────┘         └─────────────────────────────┘

┌──────────────────────┐
│     otel_icerik      │  (key-value içerik deposu)
├──────────────────────┤
│ PK  icerik_id   INT  │
│     alan        VC   │  UNIQUE → 'otel_adi', 'slogan'...
│     deger       TEXT │
│     guncelleme  DT   │  ON UPDATE CURRENT_TIMESTAMP
└──────────────────────┘
```

### 5.2 İlişki Türleri

| İlişki | Tür | Kural |
|---|---|---|
| `oda_tipleri` → `odalar` | 1 : N | ON DELETE RESTRICT — bağlı oda varsa tip silinemez |
| `odalar` → `rezervasyon` | 1 : N | ON DELETE RESTRICT — aktif rezervasyon varsa oda silinemez |
| `musteri` → `rezervasyon` | 1 : N | ON DELETE RESTRICT — müşteri rezervasyonu varsa silinemez |

### 5.3 Veritabanı Nesneleri Özeti

**View'lar:**

| View Adı | Açıklama |
|---|---|
| `v_bos_odalar` | Anlık boş odaları tip bilgileriyle listeler |
| `v_rezervasyon_detay` | Rezervasyon + oda + müşteri birleşik görünümü |
| `v_doluluk_istatistik` | Oda tiplerine göre doluluk oranı |

**Trigger'lar:**

| Trigger Adı | Olay | Açıklama |
|---|---|---|
| `trg_fiyat_hesapla` | BEFORE INSERT | Gece sayısı × oda fiyatı = toplam |
| `trg_rezervasyon_ekle` | AFTER INSERT | Oda durumunu `dolu` yapar |
| `trg_rezervasyon_iptal` | AFTER UPDATE | İptal edilince oda durumunu `bos` yapar |
| `trg_rezervasyon_sil` | AFTER DELETE | Başka aktif rezervasyon yoksa `bos` yapar |

**Stored Procedure'lar:**

| SP Adı | Parametre(ler) | Açıklama |
|---|---|---|
| `sp_musait_odalar` | giris_tarihi, cikis_tarihi | Çakışmasız odaları listeler |
| `sp_rezervasyon_olustur` | musteri_id, oda_id, giris, cikis, notlar | Güvenli rezervasyon oluşturur |
| `sp_musteri_rezervasyonlari` | musteri_id | Müşteri geçmişini döndürür |
| `sp_aylik_gelir` | yil, ay | Aylık gelir raporu hesaplar |

**Index'ler:**

| Index Adı | Tablo | Sütun(lar) | Amaç |
|---|---|---|---|
| `idx_rezervasyon_tarih` | rezervasyon | giris_tarihi, cikis_tarihi | Tarih aralığı sorgularını hızlandırır |
| `idx_musteri_email` | musteri | email | Giriş sorgularını hızlandırır |
| `idx_oda_durum` | odalar | durum | Müsaitlik filtrelerini hızlandırır |
| `idx_rezervasyon_musteri` | rezervasyon | musteri_id | Müşteri bazlı sorguları hızlandırır |

---

## 6. Genel Yapı

**Umuttepe Otel**, küçük ve orta ölçekli otellerin dijitalleşme ihtiyacına yanıt vermek amacıyla geliştirilmiş, web tabanlı bir PHP uygulamasıdır.

### 6.1 Teknik Özellikler Özeti

| Özellik | Detay |
|---|---|
| **Sunucu Dili** | PHP 8+ |
| **Veritabanı** | MySQL 8.0 (InnoDB, utf8mb4_turkish_ci) |
| **Frontend** | Bootstrap 5.3 + özel CSS + vanilla JS |
| **Geliştirme Ortamı** | XAMPP (Apache + MySQL) |
| **Şifreleme** | bcrypt (`password_hash()` / `password_verify()`) |
| **Sorgu Güvenliği** | MySQLi Prepared Statements |
| **XSS Koruması** | `htmlspecialchars()` — tüm çıktılarda |
| **DB Nesnesi Sayısı** | 5 tablo, 3 view, 4 trigger, 4 SP, 4 index |

### 6.2 Kullanıcı Akışları

**Misafir Akışı:**
Ana sayfa → Oda listesi inceleme → Kayıt ol / Giriş yap → Rezervasyon formu → Oda + tarih seç → Onay → Dashboard'da takip

**Admin Akışı:**
Giriş yap (admin rolü) → Admin paneli → Tab seçimi → Rezervasyon onayla/iptal et / Oda ekle-düzenle-sil / Müşterileri görüntüle / İçerik güncelle

### 6.3 Ölçeklenebilirlik

- `otel_icerik` tablosu sayesinde otel adı, slogan ve iletişim bilgileri kod değişikliği gerektirmeksizin güncellenebilmektedir.
- Yeni oda tipleri ve odalar kolayca eklenebilir yapıdadır.
- Stored procedure'lar veritabanı tarafında iş mantığını kapsülleyerek uygulama katmanından bağımsız kılar; bu da farklı frontend teknolojileriyle (React, mobile uygulama) aynı backend'i kullanmaya olanak tanır.

### 6.4 Bilinen Sınırlılıklar

- Görsel yükleme işlemi için dosya sistemi kullanılmakta, bulut depolama entegrasyonu bulunmamaktadır.
- Ödeme sistemi entegrasyonu mevcut değildir.
- E-posta bildirim altyapısı kurulmamıştır.

---

## 7. Kurulum

```bash
# 1. XAMPP'i başlatın (Apache + MySQL)

# 2. Projeyi kopyalayın
cp -r otel/ /xampp/htdocs/otel

# 3. Veritabanını oluşturun
# phpMyAdmin > Import > otel/MySql/otel_db.sql

# 4. Tarayıcıdan açın
# http://localhost/otel/
```

---

## 8. Referanslar

[^1]: **PHP Resmi Dokümantasyonu** — Prepared Statements, Session yönetimi, `password_hash()` / `password_verify()`.  
  https://www.php.net/manual/tr/

[^2]: **TK Code Youtube Kanalı** — Trigger, Stored Procedure, View ve Index tasarımı.  
  https://www.youtube.com/watch?v=vBIo_UfCqB8&list=PLHp3SJ11RbQIdYlsaTEnpmZVvm3S3Kewc

[^3]: **Bootstrap 5 ** — Grid sistemi, bileşenler, responsive tasarım.  
  https://getbootstrap.com/docs/5.3/

[^4]: **OWASP — SQL Injection Prevention Cheat Sheet** — Güvenli sorgu.  
  https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html

[^5]: **OWASP — XSS Prevention Cheat Sheet** — Çıktı encoding ve temizleme yöntemleri.  
  https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html

[^6]: **Referans Alınan Site** — Projede referans alınan HTML/CSS şablonu.  
  https://colorlib.com/wp/template/the-grand-azure/

---

## Lisans

- MIT Lisansı