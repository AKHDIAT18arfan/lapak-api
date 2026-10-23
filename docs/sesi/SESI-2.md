# Papan Sesi 2 — Identitas dan Session dengan Kunci

## Status Pengerjaan
- [x] B1: Penerbitan dan Verifikasi Sesi (Bambang)
- [x] B2: Pemangkasan Sesi dan Gerbang Galat (Sekar)
- [x] B3: Toko Saya dan Produk Saya (Bagas & Elang)
- [x] B4: Mengubah Isi Keranjang (Bala)
- [x] B5: Riwayat Mutasi di Belakang Sesi Segar (Dimas)

---

## Catatan Penutup & Titipan untuk Rani (Sesi 3)

1. **Akses Toko:** Siapa pun pengguna yang sedang masuk (*authenticated*) masih diperbolehkan untuk membuka toko baru.
2. **Rotasi Kunci:** Kunci sesi perlu dirotasi saat terjadi perubahan peran pengguna. Fitur penunjang `PenerbitSesi::rotasi` sudah diimplementasikan dan siap dipakai.
3. **Proteksi Rute Publik:** Daftar `RUTE_PUBLIK` bersifat ketat dan hanya boleh diperbarui melalui Pull Request yang ditinjau oleh Sekar.