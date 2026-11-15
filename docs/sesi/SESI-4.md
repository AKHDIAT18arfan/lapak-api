# Sesi 4 — Transaksi, Konkurensi, Idempotensi, dan Webhook Pembayaran

## 1. Ringkasan Tujuan Sesi
Sesi 4 berfokus pada pengamanan integritas data transaksi pada kondisi akses bersamaan (*high concurrency*) serta keandalan komunikasi dengan *payment gateway*:
1. **Migrasi Mesin Uji ke MySQL (`lapak` & `lapak_uji`):** Menggantikan SQLite untuk pengujian konkurensi agar penguncian baris (*row-level locking*) berjalan nyata.
2. **Transaksi & Penguncian Pesimistis (*Pessimistic Locking*):** Membungkus mutasi kritis dengan `DB::transaction()` dan `lockForUpdate()` untuk mencegah *race condition* dan stok minus (*overselling*).
3. **Pencegahan Deadlock (*Lock Ordering*):** Mengurutkan penguncian baris produk berdasarkan `produk_id` secara menaik (`ASC`) pada alur checkout maupun pembatalan.
4. **Idempotensi Request (`Idempotency-Key`):** Mencegah pemrosesan ganda akibat *retry* jaringan pada endpoint `POST` yang bersifat mutatif.
5. **Kontrak Pembayaran & Keamanan Webhook:** Penyelarasan atribut pembayaran (`total_rupiah`), validasi tanda tangan HMAC (`LAPAK_GATEWAY_RAHASIA`), serta penanganan callback pembayaran secara idempoten.

---

## 2. Pembagian Peran Tim (BEBAND)

| Anggota | Fokus Domain / Tugas Utama |
| :--- | :--- |
| **Arfan** | Konfigurasi database MySQL (`lapak` & `lapak_uji`), transaksi & *pessimistic locking* pada alur checkout/stok |
| **Nabhan** | Pengurutan kunci (*lock ordering*) pada pembatalan & pengembalian stok untuk mencegah deadlock |
| **Bagas & Elang** | Middleware idempotensi (`Idempotency-Key`), penyimpanan respons kunci, dan klasifikasi rute `POST_BERKUNCI` / `POST_TANPA_KUNCI` |
| **Dimas** | Integrator cabang, kontrak pembayaran (`total_rupiah`), verifikasi HMAC signature webhook pembayaran, serta penutup dokumentasi |
| **Bala** | Pengujian Pest MySQL (`RuteIdempotenTest`), simulasi konkurensi `lapak:balapan`, dan verifikasi manual end-to-end (`curl`) |

---

## 3. Perubahan Teknis & Mekanisme Perlindungan

### A. Penguncian Pesimistis & Urutan Kunci (`Lock Ordering`)
- Seluruh operasi pemotongan stok saat checkout dan pengembalian stok saat pembatalan dibungkus dalam `DB::transaction()`.
- Baris stok dikunci menggunakan `->lockForUpdate()` setelah diurutkan berdasarkan `produk_id` secara menaik (`orderBy('produk_id')`) agar dua transaksi konkuren tidak saling mengunci secara silang (*circular wait*).

### B. Middleware Idempotensi
- Permintaan ke endpoint pada daftar `POST_BERKUNCI` wajib menyertakan header `Idempotency-Key`.
- Permintaan pertama menyimpan sidik jari payload dan hasil respons; permintaan ulang dengan kunci dan payload identik langsung mengembalikan respons tersimpan tanpa mengeksekusi ulang logika bisnis.
- Seluruh endpoint `POST` didaftarkan secara eksplisit ke `POST_BERKUNCI` atau `POST_TANPA_KUNCI` sesuai cakupan aksesnya.

### C. Kontrak Pembayaran & Webhook HMAC
- Penyelarasan skema data pembayaran menggunakan kolom `total_rupiah`.
- Endpoint webhook memverifikasi tanda tangan HMAC menggunakan secret `LAPAK_GATEWAY_RAHASIA`; permintaan dengan signature tidak valid atau kosong langsung ditolak dengan status `401` (`TANDA_TANGAN_SALAH`).
- Pembaruan status pembayaran dari webhook dijalankan di dalam transaksi dengan penguncian baris agar callback ganda tidak memicu transisi status atau pencatatan mutasi dua kali.

---

## 4. Hasil Verifikasi & Pengujian

1. **Suite Pengujian Otomatis (`php artisan test` / Pest di MySQL):**
   - Seluruh pengujian fitur, konkurensi, validasi HMAC webhook, dan `RuteIdempotenTest` lulus (`PASS`) menggunakan database `lapak_uji`.
2. **Simulasi Konkurensi (`php artisan lapak:balapan`):**
   - Simulasi checkout paralel terhadap stok terbatas berjalan konsisten tanpa stok bernilai negatif.
   - Skenario `php artisan lapak:balapan batal-saat-checkout` berjalan bersih tanpa galat `MySQL 1213 Deadlock`.
3. **Verifikasi Manual (`curl`):**
   - Uji kirim ulang `POST` dengan `Idempotency-Key` sama menghasilkan respons identik tanpa duplikasi data.
   - Uji webhook dengan signature salah mengembalikan `401 TANDA_TANGAN_SALAH`, sedangkan signature valid memperbarui status pembayaran secara konsisten.

---

## 5. Catatan Kendala & Penyelesaian Sesi 4
- **Penyelarasan `.env` Lokal:** Menambahkan `LAPAK_GATEWAY_RAHASIA` dan konfigurasi koneksi MySQL (`DB_DATABASE=lapak`) dari `.env.example` ke `.env` lokal sebelum menjalankan migrasi dan simulasi.
- **Rebase Cabang Kontrak Pembayaran:** Melakukan `git rebase --onto origin/main` pada cabang kontrak pembayaran setelah pembaruan skema `total_rupiah` agar riwayat commit tetap bersih tanpa konflik duplikat.
- **Sinkronisasi Daftar Rute Idempoten:** Mendaftarkan seluruh rute `POST` tambahan dari sesi sebelumnya ke konstanta `POST_BERKUNCI` / `POST_TANPA_KUNCI` agar cakupan pemeriksaan `RuteIdempotenTest` menyeluruh.