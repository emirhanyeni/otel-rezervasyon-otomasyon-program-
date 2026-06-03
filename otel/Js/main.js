
document.addEventListener('DOMContentLoaded', function () {

    
    const nav = document.getElementById('mainNav');
    if (nav) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 60) {
                nav.classList.add('scrolled');
                nav.style.background = 'rgb(0, 0, 0)';
            } else {
                nav.style.background = 'rgba(0, 0, 0, 0.97)';
            }
        });
    }

  
    const bugun = new Date().toISOString().split('T')[0];
    const girisTarihi  = document.getElementById('giris_tarihi');
    const cikisTarihi  = document.getElementById('cikis_tarihi');

    if (girisTarihi) {
        girisTarihi.min = bugun;
        girisTarihi.addEventListener('change', function () {
            if (cikisTarihi) {
                cikisTarihi.min = this.value;
                if (cikisTarihi.value && cikisTarihi.value <= this.value) {
                    cikisTarihi.value = '';
                }
            }
        });
    }

    if (cikisTarihi) {
        cikisTarihi.min = bugun;
    }

   
    const tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(el => new bootstrap.Tooltip(el));

    // --- Alert otomatik kapat ---
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (a) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            if (bsAlert) bsAlert.close();
        });
    }, 5000);

   
    function geceHesapla() {
        if (!girisTarihi || !cikisTarihi) return;
        const g = new Date(girisTarihi.value);
        const c = new Date(cikisTarihi.value);
        const geceSayisi = document.getElementById('gece_sayisi');
        const toplamFiyat = document.getElementById('tahmini_fiyat');

        if (girisTarihi.value && cikisTarihi.value && c > g) {
            const fark = Math.round((c - g) / (1000 * 60 * 60 * 24));
            if (geceSayisi) geceSayisi.textContent = fark + ' gece';

            
            const odaSelect = document.getElementById('oda_id');
            if (odaSelect && toplamFiyat) {
                const selected = odaSelect.options[odaSelect.selectedIndex];
                const fiyat = parseFloat(selected.dataset.fiyat || 0);
                if (fiyat > 0) {
                    const toplam = fark * fiyat;
                    toplamFiyat.textContent = toplam.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                }
            }
        } else {
            if (geceSayisi)  geceSayisi.textContent = '-';
            if (toplamFiyat) toplamFiyat.textContent = '-';
        }
    }

    if (girisTarihi) girisTarihi.addEventListener('change', geceHesapla);
    if (cikisTarihi) cikisTarihi.addEventListener('change', geceHesapla);

    const odaSelect = document.getElementById('oda_id');
    if (odaSelect) odaSelect.addEventListener('change', geceHesapla);

   
    document.querySelectorAll('.btn-sil-onayla').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('Bu kaydı silmek istediğinize emin misiniz?')) {
                e.preventDefault();
            }
        });
    });

    
    const fadeEls = document.querySelectorAll('.fade-in-up');
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.15 });

    fadeEls.forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(24px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});