# Changelog

Semua perubahan penting pada proyek **Lapak API** dicatat di berkas ini.

## [1.0.0] - 2026-11-22

### Ditambahkan
- Sistem dompet dan mutasi buku besar berpasangan (`eksternal`, `escrow`, `pendapatan`, `pencairan`, serta dompet penjual) dengan perintah verifikasi keseimbangan (`lapak:periksa-buku`).
- Alur checkout multi-toko, pembayaran dan penanganan pembayaran/webhook terlambat, pembatalan pesanan/checkout, pengiriman, konfirmasi penerimaan, serta penyelesaian otomatis (`lapak:selesaikan-otomatis`).
- Alur pengajuan pencairan dana oleh penjual dan persetujuan oleh petugas keuangan.
- Manajemen sesi perangkat, rotasi kunci, deteksi pemakaian kunci lama, serta konfirmasi kata sandi (`POST /sesi/konfirmasi`) untuk tindakan sensitif.
- Rantai log audit berbasis *hash* berantai, endpoint pembacaan & verifikasi (`/audit`, `/audit/rantai`, `lapak:verifikasi-audit`), dan dukungan jangkar eksternal (`--jangkar`).
- Perintah pengujian konkurensi `lapak:balapan` untuk menguji kondisi balapan, idempotensi, dan integritas data.
