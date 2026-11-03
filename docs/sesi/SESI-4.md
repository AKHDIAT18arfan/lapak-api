# Papan Sesi 4: Keamanan Transaksi

* **Integrator Sesi**: Dimas (`@DimasAryaPutra`)
* **Titik Berangkat**: Tag `sesi-3`
* **Target Tag**: `sesi-4`

---

## A. Sasaran Sesi 4

1. Melindungi transaksi checkout dan pembayaran dari kondisi balapan (*race condition*) dan kebuntuan (*deadlock*) menggunakan penguncian baris (*pessimistic locking*) MySQL.
2. Menegakkan **Tangga Urutan Kunci Baris (1 s.d. 7)** secara disiplin di seluruh domain untuk mencegah kebuntuan (*deadlock*).
3. Menerapkan idempotensi pada checkout dan pemrosesan pembayaran untuk menjamin pemrosesan transaksi tepat satu kali (*exactly-once semantics*).
4. Menerapkan verifikasi webhook gerbang pembayaran dengan tanda tangan digital HMAC-SHA256 untuk menangkal serangan replay dan manipulasi data.
5. Menegakkan transaksi escrow: saldo ditahan saat pembayaran lunas dan diteruskan/dikembalikan secara terisolasi.
6. Menguji ketahanan sistem dengan skenario uji balapan (*concurrency race tests*) menggunakan runner balapan.

---

## B. Jawaban atas Titipan Sesi 3

1. **Aturan Transisi Pesanan**: `App\Domain\Pesanan\AturanTransisi` disambungkan ke rute/controller HTTP via `MesinStatusPesanan` dan diverifikasi dengan penguncian baris.
2. **Pesanan Milik Orang Lain**: Semua kueri pesanan menggunakan scope pengguna aktif sehingga akses ke pesanan pengguna lain dijawab **404 Not Found** (bukan 403 Forbidden).
3. **Pemberlakuan Kunci Stok & Saldo**: Mengganti pembaruan bersyarat sederhana dengan penguncian eksplisit (`SELECT ... FOR UPDATE`) yang mematuhi urutan tangga kunci baris.

---

## C. Tujuh Keputusan Rancangan Sesi 4

1. **Basis Data Transaksional**: Menggunakan MySQL 8 / MariaDB dengan tingkat isolasi `REPEATABLE-READ` sebagai basis data standar transaksi.
2. **Induk Bersama `checkout` (LP-7)**: Tabel `pesanan` (D3) dan `pembayaran` (D4) keduanya menunjuk ke `checkout` (D3) untuk menghindari ketergantungan melingkar antar-domain.
3. **Kunci Baris Induk-Dulu**: Kunci baris induk (`checkout`) harus didapatkan via `GembokCheckout` sebelum menyentuh status pesanan, pembayaran, stok produk, atau saldo dompet.
4. **Penguncian Berurutan ID Menaik**: Penguncian banyak baris (pada tabel `produk` dan `dompet`) wajib diurutkan berdasarkan ID secara menaik (`sortById`) untuk mengeliminasi potensi *deadlock*.
5. **Validasi HMAC Badan Mentah**: Verifikasi tanda tangan HMAC pada webhook dilakukan atas isi badan mentah HTTP (`$request->getContent()`) sebelum diparse ke JSON.
6. **Idempotensi Berbasis Middleware**: Header `X-Idempotency-Key` dicatat di awal transaksi dalam tabel `kunci_idempotensi` untuk mencegah eksekusi ulang operasi mutating yang sama.
7. **Penanganan Serangan Replay**: Webhook memvalidasi selisih waktu (*timestamp*) masukan maksimal 5 menit (300 detik) untuk menangkal pembentukan ulang permintaan.

---

## D. Urutan Kunci Baris (Tangga 7 Anak)

| Urut | Baris | Dikunci oleh | Pemilik |
| :---: | :--- | :--- | :--- |
| **1** | `pengguna`, lalu `alamat` miliknya | `BukuAlamat` | Arfan |
| **2** | `keranjang`, lalu `butir_keranjang` | `LayananCheckout` | Bala |
| **3** | `checkout` | `GembokCheckout` | Bala |
| **4** | `pembayaran` | `LayananPembayaran` | Dimas |
| **5** | `pesanan` | `MesinStatusPesanan` | Bala |
| **6** | `produk`, urut nomor menaik | `PenjagaStok` | Bagas & Elang |
| **7** | `dompet`, urut nomor menaik | `BukuBesar` | Dimas |

---

## E. Urutan Merge Pull Request (PR)

### Jendela A: Kontrak Framework & Transaksi
- [x] **PR #34**: Pembuka Sesi 4, Papan Sesi, Tangga Kunci, & CODEOWNERS (`sesi-4/pembuka-dimas`)
- [x] **PR #35 (A1)**: Kontrak Idempotensi & Runner Balapan (`sesi-4/kontrak-idempotensi-nabhan`)
- [x] **PR #36 (A2)**: Kontrak Alamat & Penjaga Transaksi (`sesi-4/kontrak-alamat-arfan`)
- [x] **PR #37 (A3)**: Kontrak Stok & Penjaga Stok (`sesi-4/kontrak-stok-bagas-elang`)
- [x] **PR #38 (A4)**: Kontrak Checkout & Status Pesanan (`sesi-4/kontrak-pesanan-bala`)
- [x] **PR #39 (A5)**: Kontrak Pembayaran & Gateway (`sesi-4/kontrak-pembayaran-dimas`)

### Jendela B: Implementasi Domain Parallel & Uji Balapan
- [x] **PR #40 (B1)**: Buku Alamat & Rute Alamat Saya (`sesi-4/alamat-arfan`)
- [x] **PR #41 (B2)**: Penjaga Stok & Uji Balapan Rebutan Stok (`sesi-4/stok-bagas-elang`)
- [x] **PR #42 (B3)**: Middleware Idempotensi & Balapan Idempotens (`sesi-4/idempotensi-nabhan`)
- [x] **PR #43 (B4)**: Webhook HMAC, Escrow, & Uji Webhook Ganda (`sesi-4/webhook-dimas`)
- [x] **PR #44 (B5)**: Layanan Checkout, Pembatalan Otomatis, & Balapan Lintas Domain (`sesi-4/checkout-bala`)
- [x] **PR #45 (P1)**: Perbaikan Deadlock Pengurutan Retur Produk (`sesi-4/perbaikan-deadlock-bala`)

### Penutup
- [x] **PR #46**: Integration Test Transaksi, Periksa Buku Besar, Rekap Sesi 4, & Tag Sesi 4 (`sesi-4/penutup-dimas`)

---

## F. Catatan & Titipan untuk Sesi 5 (Nabhan - Integrator)

1. **Penerusan Dana Escrow**: Pembayaran yang telah berada di akun `escrow` perlu dicairkan ke dompet penjual saat pesanan selesai/diterima pembeli pada Sesi 5.
2. **Pencatatan Audit Transaksi**: Seluruh perubahan status pesanan, eksekusi webhook, dan mutasi escrow siap dikoneksikan ke skema Log Audit pada Sesi 5.
3. **Manajemen Penanganan Kedaluwarsa Job Queue**: Perintah `lapak:batalkan-kedaluwarsa` disiapkan untuk dijadwalkan secara otomatis melalui Worker / Scheduler pada Sesi 5.
4. **Proteksi Idempotensi Tingkat Lanjut**: Header idempotensi disiapkan untuk mendukung penanganan rujukan pembayaran eksternal tambahan.