# Papan Sesi 3: Peran, Izin, dan Kewenangan atas Objek

- **Integrator Sesi**: Bala (`@akun-bala`)
- **Titik Berangkat**: Tag `sesi-2`
- **Target Tag**: `sesi-3`

---

## A. Sasaran Sesi 3

1. Memunculkan sistem **RBAC (Role-Based Access Control)** berbasis izin ($LP-2$). Kode hanya bertanya "punya izin ini?", tidak pernah "apakah ia admin?".
2. Memastikan seluruh pemeriksaan izin dilakukan oleh middleware **sebelum kerangka kerja mencari objek dari alamat** ($SubstituteBindings$).
3. Menegakkan pemisahan tugas (Separation of Duties): tidak ada peran super-admin yang memegang seluruh izin, dan admin tidak memiliki izin membaca buku besar uang.
4. Menerapkan pintu tunggal perubahan peran (`PengelolaPeran`) yang sekaligus melupakan ingatan izin ($cache$) dan mencabut seluruh sesi akun sasaran.
5. Menjaga otorisasi tingkat objek menggunakan kueri berlingkup dan `Policy` (mencegah benturan kepentingan).
6. Menegakkan **Matriks Otorisasi** sebagai pengujian otomatis CI atas seluruh rute terlindung API.

---

## B. Jawaban atas Titipan Sesi 2

1. **Persetujuan Toko ($LP-3$)**: Toko baru berstatus `menunggu`. Admin memoderasi toko via `POST /admin/toko/{id}/setujui`, yang secara otomatis menganugerahkan peran `penjual` kepada pemiliknya.
2. **Penggantian Kunci Sesi saat Kedudukan Naik**: Pada perubahan peran, seluruh sesi milik akun sasaran dicabut (`PenerbitSesi::cabutSemua()`). Pemilik sesi diminta masuk ulang untuk mendapatkan token/kunci baru dengan kewenangan segar.

---

## C. Matriks Otorisasi yang Disepakati di Kelas

| Rute / Endpoint | Izin yang Dijaga | Pembeli | Penjual | Admin | Keuangan | Auditor |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| `GET api/v1/saya` | *(terbuka/auth)* | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ |
| `GET api/v1/admin/pengguna` | `peran.kelola` | | | $\checkmark$ | | |
| `PUT api/v1/admin/pengguna/{id}/peran/{peran}` | `peran.kelola` | | | $\checkmark$ | | |
| `DELETE api/v1/admin/pengguna/{id}/peran/{peran}` | `peran.kelola` | | | $\checkmark$ | | |
| `GET api/v1/toko-saya` | *(terbuka/auth)* | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ |
| `PATCH api/v1/toko-saya` | *(terbuka/auth)* | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ | $\checkmark$ |
| `POST api/v1/toko-saya` | `toko.buka` | $\checkmark$ | | | | |
| `GET api/v1/toko-saya/produk` | `produk.kelola` | | $\checkmark$ | | | |
| `POST api/v1/toko-saya/produk` | `produk.kelola` | | $\checkmark$ | | | |
| `PATCH api/v1/toko-saya/produk/{id}` | `produk.kelola` | | $\checkmark$ | | | |
| `DELETE api/v1/toko-saya/produk/{id}` | `produk.kelola` | | $\checkmark$ | | | |
| `GET api/v1/admin/toko` | `toko.moderasi` | | | $\checkmark$ | | |
| `POST api/v1/admin/toko/{id}/setujui` | `toko.moderasi` | | | $\checkmark$ | | |
| `POST api/v1/admin/toko/{id}/tangguhkan` | `toko.moderasi` | | | $\checkmark$ | | |
| `GET api/v1/keranjang` | `keranjang.kelola` | $\checkmark$ | | | | |
| `PUT api/v1/keranjang/butir/{produkId}` | `keranjang.kelola` | $\checkmark$ | | | | |
| `DELETE api/v1/keranjang/butir/{produkId}` | `keranjang.kelola` | $\checkmark$ | | | | |
| `DELETE api/v1/keranjang` | `keranjang.kelola` | $\checkmark$ | | | | |
| `GET api/v1/dompet` | `dompet.lihat` | | $\checkmark$ | | | |
| `GET api/v1/dompet/mutasi` | `dompet.lihat` | | $\checkmark$ | | | |
| `GET api/v1/buku-besar/dompet` | `buku-besar.lihat` | | | | $\checkmark$ | $\checkmark$ |
| `GET api/v1/buku-besar/dompet/{id}/mutasi` | `buku-besar.lihat` | | | | $\checkmark$ | $\checkmark$ |

---

## D. Urutan Merge Pull Request (PR)

### Jendela A: Kontrak Framework & Otorisasi
- [x] **PR #25**: Pembuka Sesi 3 & Matriks Otorisasi Papan Sesi (`sesi-3/pembuka-bala`)
- [x] **PR #26 (A1)**: Enums, Middleware Izin, Migration RBAC, & Matriks Base (`sesi-3/kontrak-izin-nabhan`)
- [x] **PR #27 (A2)**: Pengelola Peran, Relasi Pengguna, & Factory State (`sesi-3/kontrak-peran-arfan`)

### Jendela B: Implementasi Domain Parallel
- [x] **PR #28 (B1)**: Pendaftar Pembeli, Response `/saya`, Pintu Admin Peran (`sesi-3/peran-pengguna-arfan`)
- [x] **PR #29 (B2)**: Ingatan Izin Cache & Penjaga Pintu Peran Arsitektur (`sesi-3/ingatan-izin-nabhan`)
- [x] **PR #30 (B3)**: Moderasi Toko oleh Admin & Izin Produk (`sesi-3/moderasi-toko-elang-bagas`)
- [x] **PR #31 (B4)**: Gerbang Keranjang Belanja & Domain Aturan Status Pesanan (`sesi-3/gerbang-keranjang-bala`)
- [x] **PR #32 (B5)**: Izin Dompet & Pintu Buku Besar Staf (`sesi-3/buku-besar-dimas`)

### Penutup
- [x] **PR #33**: Integration Test Izin Terpakai, Update Aturan Bisnis, & Tag Sesi 3 (`sesi-3/penutup-bala`)

---

## E. Catatan & Titipan untuk Sesi 4 (Dimas - Integrator)

1. **Aturan Transisi Pesanan**: `App\Domain\Pesanan\AturanTransisi` telah diuji secara terisolasi namun belum dihubungkan ke rute HTTP.
2. **Pesanan Milik Orang Lain**: Saat rute pesanan dibuat pada Sesi 4, gunakan `Policy` / kueri berlingkup agar pesanan milik pengguna lain dijawab **404 TIDAK DITEMUKAN**, bukan 403.
3. **Pemberlakuan Kunci Stok & Saldo**: Penggunaan UPDATE bersyarat pada Sesi 3 perlu ditingkatkan dengan penguncian stok ($pessimistic\ locking$) pada proses *checkout* transaksi.
4. **Log Audit**: Persetujuan toko dan perubahan peran di Sesi 3 siap disambungkan ke tabel audit pada Sesi 5.