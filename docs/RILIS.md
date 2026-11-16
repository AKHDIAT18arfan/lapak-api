# Daftar Periksa Rilis Lapak API (`sesi-5`)

Dokumen ini menjadi gerbang verifikasi akhir sebelum menandai tag rilis `sesi-5` pada cabang `main`.

---

## 1. Kualitas Kode & Pengujian Otomatis
- [ ] Seluruh berkas lolos pemeriksaan gaya kode Laravel Pint (`./vendor/bin/pint --test`).
- [ ] Seluruh suite pengujian Pest lulus pada SQLite (`./vendor/bin/pest`).
- [ ] Seluruh suite pengujian Pest lulus pada MySQL (`DB_CONNECTION=mysql DB_DATABASE=lapak_uji ./vendor/bin/pest`).
- [ ] Uji arsitektur lolos: tidak ada kolom/tipe pecahan (`float`, `double`, `decimal`) untuk nilai uang ($LP-1$).
- [ ] Matriks otorisasi (`MatriksOtorisasiTest`) dan cakupan rute idempoten (`RuteIdempotenTest`) mencakup 100% rute terlindung dan mutatif ($LP-2, LP-11$).

## 2. Integritas Basis Data, Buku Besar, & Audit
- [ ] Migrasi bersih dan seeder bawaan berjalan tanpa galat (`php artisan migrate:fresh --seed`).
- [ ] Pemeriksaan keseimbangan buku besar (`php artisan lapak:periksa-buku`) menyatakan jumlah seluruh mutasi nol dan tidak ada dompet internal bersaldo negatif ($LP-13$).
- [ ] Verifikasi rantai hash audit (`PemeriksaRantaiAudit`) memastikan tidak ada baris `log_audit` yang diubah, dihapus, atau terputus rantainya ($LP-17$).

## 3. Simulasi Konkurensi (MySQL)
- [ ] `php artisan lapak:balapan` lulus untuk seluruh skenario persaingan akses (stok, checkout, pembatalan bersamaan, webhook ganda, dan pencairan saldo) tanpa stok negatif maupun galat *deadlock*.

## 4. Keamanan & Konfigurasi Lingkungan
- [ ] Berkas `.env.example` memuat seluruh kunci konfigurasi yang diperlukan (`DB_*`, `LAPAK_GATEWAY_RAHASIA`, konfigurasi sesi dan cache) tanpa nilai rahasia produksi.
- [ ] Riwayat Git bebas dari berkas `.env`, kunci privat, maupun kredensial.
- [ ] Perlindungan cabang `main` aktif dengan syarat minimal 2 peninjau dan validasi `.github/CODEOWNERS`.

## 5. Kelengkapan Dokumentasi & Tagging
- [ ] Seluruh tujuh belas aturan bisnis (`LP-1` s.d. `LP-17`) pada `docs/ATURAN-BISNIS.md` berstatus ditegakkan beserta rujukan kode dan ujinya.
- [ ] `docs/PETA-SKEMA.md` memuat seluruh tabel Sesi 1–5 dan 8 anak tangga kunci transaksi.
- [ ] `CHANGELOG.md` merangkum seluruh fitur dan perubahan dari Sesi 1 hingga Sesi 5.
- [ ] Tag `sesi-5` dibuat pada commit penutup di `main` dan di-push ke `origin`.