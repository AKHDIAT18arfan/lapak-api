# Papan Sesi 1 — Fondasi Tim dan Repositori

## Status
Selesai

## Daftar Periksa Integrasi

### Unggahan Pembuka
- [x] PR #1: Sesi 1: fondasi tim dan repositori

### Jendela A: Kontrak
- [x] PR #2: A1 kontrak respons (Sekar)
- [x] PR #3: A2 kontrak uang (Rani)
- [x] PR #4: A3 kontrak identitas (Arfan)
- [x] PR #5: A4 kontrak katalog (Bagas)
- [x] PR #6: A5 kontrak dompet (Dimas)

### Jendela B: Implementasi
- [x] PR #7: B1 galat dan CI (Sekar)
- [x] PR #8: B2 registrasi (Arfan)
- [x] PR #9: B3 katalog (Bagas)
- [x] PR #10: B4 belanja (Rani)
- [x] PR #11: B5 buku besar (Dimas)

## Catatan Penutup & Titipan untuk Integrator Sesi 2 (Bagas)
- Seluruh 11 PR fitur Sesi 1 sudah terintegrasi dan lolos di `main`.
- Pengujian Pest (55 passed) dan `php artisan lapak:periksa-buku` hijau di CI.
- Bentrok pada `DatabaseSeeder.php` berhasil disintesis dengan menggabungkan seeder katalog dan dompet.
- Titipan untuk Bagas: Pastikan saat penyatuan seeder di Sesi 2, seeder dari Sesi 1 tidak terhapus agar data katalog dan dompet tetap aman.