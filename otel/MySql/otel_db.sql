-- ============================================================
-- TEMİZ KURULUM--
-- ============================================================

CREATE DATABASE IF NOT EXISTS otel_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;


USE otel_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS rezervasyon;
DROP TABLE IF EXISTS odalar;
DROP TABLE IF EXISTS oda_tipleri;
DROP TABLE IF EXISTS musteri;
DROP TABLE IF EXISTS otel_icerik;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLO 1: oda_tipleri
-- ============================================================
CREATE TABLE oda_tipleri (
    tip_id    INT AUTO_INCREMENT PRIMARY KEY,
    tip_adi   VARCHAR(50)  NOT NULL UNIQUE,
    aciklama  TEXT,
    kapasite  INT          NOT NULL DEFAULT 2,
    CHECK (kapasite BETWEEN 1 AND 10)
) ENGINE=InnoDB;

-- ============================================================
-- TABLO 2: odalar
-- ============================================================
CREATE TABLE odalar (
    oda_id    INT AUTO_INCREMENT PRIMARY KEY,
    oda_no    VARCHAR(10)      NOT NULL UNIQUE,
    tip_id    INT              NOT NULL,
    fiyat     DECIMAL(10,2)    NOT NULL,
    durum     ENUM('bos','dolu') NOT NULL DEFAULT 'bos',
    kat       INT              NOT NULL DEFAULT 1,
    aciklama  TEXT,
    CHECK (fiyat > 0),
    CHECK (kat BETWEEN 1 AND 20),
    FOREIGN KEY (tip_id) REFERENCES oda_tipleri(tip_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLO 3: musteri
-- ============================================================
CREATE TABLE musteri (
    musteri_id    INT AUTO_INCREMENT PRIMARY KEY,
    ad            VARCHAR(100)  NOT NULL,
    soyad         VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    telefon       VARCHAR(20)   NOT NULL,
    sifre         VARCHAR(255)  NOT NULL,
    kayit_tarihi  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rol           ENUM('musteri','admin') NOT NULL DEFAULT 'musteri',
    CHECK (LENGTH(telefon) >= 10)
) ENGINE=InnoDB;

-- ============================================================
-- TABLO 4: rezervasyon
-- ============================================================
CREATE TABLE rezervasyon (
    rezervasyon_id    INT AUTO_INCREMENT PRIMARY KEY,
    musteri_id        INT           NOT NULL,
    oda_id            INT           NOT NULL,
    giris_tarihi      DATE          NOT NULL,
    cikis_tarihi      DATE          NOT NULL,
    toplam_fiyat      DECIMAL(10,2) NOT NULL DEFAULT 0,
    durum             ENUM('beklemede','onaylandi','iptal') NOT NULL DEFAULT 'beklemede',
    notlar            TEXT,
    olusturma_tarihi  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (cikis_tarihi > giris_tarihi),
    CHECK (toplam_fiyat >= 0),
    FOREIGN KEY (musteri_id) REFERENCES musteri(musteri_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (oda_id) REFERENCES odalar(oda_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLO 5: otel_icerik
-- ============================================================
CREATE TABLE otel_icerik (
    icerik_id   INT AUTO_INCREMENT PRIMARY KEY,
    alan        VARCHAR(100) NOT NULL UNIQUE,
    deger       TEXT         NOT NULL,
    guncelleme  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- INDEX TANIMLARI
-- ============================================================
CREATE INDEX idx_rezervasyon_tarih    ON rezervasyon(giris_tarihi, cikis_tarihi);
CREATE INDEX idx_musteri_email        ON musteri(email);
CREATE INDEX idx_oda_durum            ON odalar(durum);
CREATE INDEX idx_rezervasyon_musteri  ON rezervasyon(musteri_id);

-- ============================================================
-- VIEW TANIMLARI
-- ============================================================
CREATE OR REPLACE VIEW v_bos_odalar AS
SELECT o.oda_id, o.oda_no, ot.tip_adi, ot.kapasite, o.fiyat, o.kat,
       o.aciklama AS oda_aciklama, ot.aciklama AS tip_aciklama
FROM odalar o
JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
WHERE o.durum = 'bos';

CREATE OR REPLACE VIEW v_rezervasyon_detay AS
SELECT r.rezervasyon_id,
       CONCAT(m.ad, ' ', m.soyad)                         AS musteri_adi,
       m.email, m.telefon,
       o.oda_no, ot.tip_adi,
       r.giris_tarihi, r.cikis_tarihi,
       DATEDIFF(r.cikis_tarihi, r.giris_tarihi)            AS gece_sayisi,
       r.toplam_fiyat, r.durum, r.olusturma_tarihi
FROM rezervasyon r
JOIN musteri  m  ON r.musteri_id = m.musteri_id
JOIN odalar   o  ON r.oda_id     = o.oda_id
JOIN oda_tipleri ot ON o.tip_id  = ot.tip_id;

CREATE OR REPLACE VIEW v_doluluk_istatistik AS
SELECT ot.tip_adi,
       COUNT(o.oda_id)                                                        AS toplam_oda,
       SUM(CASE WHEN o.durum='dolu' THEN 1 ELSE 0 END)                        AS dolu_oda,
       SUM(CASE WHEN o.durum='bos'  THEN 1 ELSE 0 END)                        AS bos_oda,
       ROUND(SUM(CASE WHEN o.durum='dolu' THEN 1 ELSE 0 END)*100.0/COUNT(*),1) AS doluluk_orani
FROM odalar o
JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
GROUP BY ot.tip_id, ot.tip_adi;

-- ============================================================
-- TRIGGER TANIMLARI
-- ============================================================
DELIMITER $$

-- TRIGGER 1: Insert öncesi toplam fiyatı hesapla
CREATE TRIGGER trg_fiyat_hesapla
BEFORE INSERT ON rezervasyon
FOR EACH ROW
BEGIN
    DECLARE gun_sayisi  INT;
    DECLARE oda_fiyati  DECIMAL(10,2);
    SET gun_sayisi = DATEDIFF(NEW.cikis_tarihi, NEW.giris_tarihi);
    SELECT fiyat INTO oda_fiyati FROM odalar WHERE oda_id = NEW.oda_id;
    SET NEW.toplam_fiyat = gun_sayisi * oda_fiyati;
END$$

-- TRIGGER 2: Rezervasyon eklendikten sonra odayı dolu yap
CREATE TRIGGER trg_rezervasyon_ekle
AFTER INSERT ON rezervasyon
FOR EACH ROW
BEGIN
    UPDATE odalar SET durum = 'dolu' WHERE oda_id = NEW.oda_id;
END$$

-- TRIGGER 3: Rezervasyon iptal edilince odayı bos yap
CREATE TRIGGER trg_rezervasyon_iptal
AFTER UPDATE ON rezervasyon
FOR EACH ROW
BEGIN
    IF NEW.durum = 'iptal' AND OLD.durum != 'iptal' THEN
        IF NOT EXISTS (
            SELECT 1 FROM rezervasyon
            WHERE oda_id = NEW.oda_id
              AND durum IN ('beklemede','onaylandi')
              AND rezervasyon_id != NEW.rezervasyon_id
        ) THEN
            UPDATE odalar SET durum = 'bos' WHERE oda_id = NEW.oda_id;
        END IF;
    END IF;
END$$

-- TRIGGER 4: Rezervasyon silinince odayı kontrol et
CREATE TRIGGER trg_rezervasyon_sil
AFTER DELETE ON rezervasyon
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM rezervasyon
        WHERE oda_id = OLD.oda_id AND durum IN ('beklemede','onaylandi')
    ) THEN
        UPDATE odalar SET durum = 'bos' WHERE oda_id = OLD.oda_id;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- STORED PROCEDURE TANIMLARI
-- ============================================================
DELIMITER $$

-- PROCEDURE 1: Tarih aralığına göre müsait odaları listele
CREATE PROCEDURE sp_musait_odalar(IN p_giris DATE, IN p_cikis DATE)
BEGIN
    SELECT o.oda_id, o.oda_no, ot.tip_adi, ot.kapasite, o.fiyat, o.kat, o.aciklama,
           DATEDIFF(p_cikis, p_giris) * o.fiyat AS tahmini_toplam
    FROM odalar o
    JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
    WHERE o.oda_id NOT IN (
        SELECT oda_id FROM rezervasyon
        WHERE durum IN ('beklemede','onaylandi')
          AND NOT (cikis_tarihi <= p_giris OR giris_tarihi >= p_cikis)
    )
    ORDER BY o.fiyat ASC;
END$$

-- PROCEDURE 2: Güvenli rezervasyon oluştur
CREATE PROCEDURE sp_rezervasyon_olustur(
    IN  p_musteri_id INT,
    IN  p_oda_id     INT,
    IN  p_giris      DATE,
    IN  p_cikis      DATE,
    IN  p_notlar     TEXT,
    OUT p_sonuc      VARCHAR(200)
)
BEGIN
    DECLARE cakisma INT DEFAULT 0;
    SELECT COUNT(*) INTO cakisma
    FROM rezervasyon
    WHERE oda_id = p_oda_id
      AND durum IN ('beklemede','onaylandi')
      AND NOT (cikis_tarihi <= p_giris OR giris_tarihi >= p_cikis);

    IF cakisma > 0 THEN
        SET p_sonuc = 'HATA:Secilen tarihler icin oda dolu.';
    ELSEIF p_cikis <= p_giris THEN
        SET p_sonuc = 'HATA:Cikis tarihi giris tarihinden buyuk olmalidir.';
    ELSE
        INSERT INTO rezervasyon(musteri_id, oda_id, giris_tarihi, cikis_tarihi, notlar)
        VALUES (p_musteri_id, p_oda_id, p_giris, p_cikis, p_notlar);
        SET p_sonuc = CONCAT('BASARILI:', LAST_INSERT_ID());
    END IF;
END$$

-- PROCEDURE 3: Müşteri rezervasyon geçmişi
CREATE PROCEDURE sp_musteri_rezervasyonlari(IN p_musteri_id INT)
BEGIN
    SELECT r.rezervasyon_id, o.oda_no, ot.tip_adi,
           r.giris_tarihi, r.cikis_tarihi,
           DATEDIFF(r.cikis_tarihi, r.giris_tarihi) AS gece,
           r.toplam_fiyat, r.durum, r.olusturma_tarihi
    FROM rezervasyon r
    JOIN odalar o ON r.oda_id = o.oda_id
    JOIN oda_tipleri ot ON o.tip_id = ot.tip_id
    WHERE r.musteri_id = p_musteri_id
    ORDER BY r.olusturma_tarihi DESC;
END$$

-- PROCEDURE 4: Aylık gelir raporu
CREATE PROCEDURE sp_aylik_gelir(IN p_yil INT, IN p_ay INT)
BEGIN
    SELECT COUNT(*) AS rezervasyon_sayisi,
           COALESCE(SUM(toplam_fiyat),0)  AS toplam_gelir,
           COALESCE(AVG(toplam_fiyat),0)  AS ortalama_gelir
    FROM rezervasyon
    WHERE YEAR(olusturma_tarihi) = p_yil
      AND MONTH(olusturma_tarihi) = p_ay
      AND durum = 'onaylandi';
END$$

DELIMITER ;

-- ============================================================
-- TEST VERİLERİ
-- ============================================================
INSERT INTO oda_tipleri (tip_adi, aciklama, kapasite) VALUES
('Standart Oda',    'Temel konfor, şehir manzarası',             2),
('Deluxe Oda',      'Geniş balkon ve deniz manzarası',           2),
('Suite',           'Ayrı oturma odası, jakuzi',                 4),
('Aile Odası',      'İki yatak odası, çocuk karyolası',          5),
('Ekonomi Oda',     'Bütçe dostu, işlevsel tasarım',             2),
('Penthouse Suite', 'Tepe katı, panoramik manzara, özel havuz',  6),
('Balayı Odası',    'Romantik dekor, küvet, şampanya servisi',   2),
('Engelli Dostu',   'Tekerlekli sandalye erişimi, geniş banyo',  2);

INSERT INTO odalar (oda_no, tip_id, fiyat, durum, kat, aciklama) VALUES
('101', 1, 1200.00, 'bos',  1, 'Bahçe manzaralı, çift kişilik'),
('102', 1, 1200.00, 'bos',  1, 'İç avlu manzaralı'),
('201', 2, 1800.00, 'bos',  2, 'Deniz manzarası, balkon'),
('202', 2, 1850.00, 'bos',  2, 'Gün batımı manzarası, balkon'),
('301', 3, 3500.00, 'bos',  3, 'Jakuzili suite, panoramik'),
('302', 4, 2800.00, 'bos',  3, 'Aile odası, iki oda'),
('401', 5,  900.00, 'bos',  4, 'Ekonomi, çift kişilik'),
('402', 5,  900.00, 'bos',  4, 'Ekonomi, tek kişilik'),
('501', 6, 7500.00, 'bos',  5, 'Penthouse, özel havuz'),
('502', 7, 4200.00, 'bos',  5, 'Balayı odası, tepe kat'),
('103', 8, 1100.00, 'bos',  1, 'Engelli erişimli, zemin kat'),
('203', 2, 1900.00, 'bos',  2, 'Deniz manzarası, köşe balkon');


INSERT INTO musteri (ad, soyad, email, telefon, sifre, rol) VALUES
('Admin',   'Kullanıcı', 'admin@gmail.com',    '05001234567', 'admin123', 'admin'),
('Ahmet',   'Yılmaz',    'ahmet123123@gmail.com',   '05321234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Fatma',   'Kaya',      'fatma123123@gmail.com',   '05331234568', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Mehmet',  'Demir',     'mehmet123213@gmail.com',  '05341234569', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Ayşe',    'Çelik',     'ayse123213@gmail.com',    '05351234560', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Ali',     'Şahin',     'ali213213@gmail.com',     '05361234561', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Zeynep',  'Arslan',    'zeynep123213@gmail.com',  '05371234562', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Mustafa', 'Koç',       'mustafa123213@gmail.com', '05381234563', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Elif',    'Öztürk',    'elif2145123@gmail.com',    '05391234564', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Hasan',   'Yıldız',    'hasan512342@gmail.com',   '05421234565', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri'),
('Merve',   'Güneş',     'merve3123@gmail.com',   '05431234566', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'musteri');

INSERT INTO rezervasyon (musteri_id, oda_id, giris_tarihi, cikis_tarihi, durum, notlar) VALUES
(2,  4,  '2026-05-10', '2026-05-14', 'onaylandi', 'Erken çıkış talep edildi'),
(3,  1,  '2026-05-15', '2026-05-18', 'onaylandi', NULL),
(4,  3,  '2026-06-01', '2026-06-05', 'beklemede', 'Balayı paketi'),
(5,  5,  '2026-05-20', '2026-05-22', 'onaylandi', NULL),
(6,  7,  '2026-06-10', '2026-06-12', 'beklemede', 'Çocuk karyolası gerekli'),
(7,  9,  '2026-07-01', '2026-07-05', 'onaylandi', 'VIP müşteri'),
(8,  10, '2026-05-25', '2026-05-27', 'onaylandi', NULL),
(9,  2,  '2026-06-15', '2026-06-20', 'beklemede', NULL),
(10, 6,  '2026-07-10', '2026-07-15', 'onaylandi', 'Aile - 2 yetişkin 2 çocuk'),
(11, 8,  '2026-05-30', '2026-06-02', 'iptal',     'Müşteri iptal etti');

INSERT INTO otel_icerik (alan, deger) VALUES
('otel_adi',        'UMUTTEPE OTEL'),
('slogan',          'Kampüse 5 dakika'),
('aciklama',        'Umuttepe Otel, kampüse 5 dakika mesafesi ve beş yıldızlı hizmet anlayışıyla misafirlerine unutulmaz bir konaklama deneyimi sunmaktadır. 2026 yılında kurulan otelimiz, yıllar içinde kazandığı öğrenci dostları ile sektörün önde gelen markalarından biri haline gelmiştir.'),
('adres',           'Kabaoğlu, Prof. Baki Komşuoğlu Blv. CADDESİ No:518, 41000 İzmit/Kocaeli'),
('telefon',         '+90 538 300 0100'),
('email_iletisim',  'emirhanyeni63@gmail.com'),
('check_in',        '06:00'),
('check_out',       '00:00'),
('hero_baslik',     'Hayalinizin Tatilini Yaşayın'),
('hero_alt_baslik', 'Kampüs kenarında lüks, huzur ve unutulmaz anlar sizi bekliyor.');