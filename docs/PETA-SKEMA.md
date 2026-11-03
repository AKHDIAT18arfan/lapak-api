# Peta Skema Basis Data Lapak

Dokumen ini mencatat pembagian tabel basis data per domain, arah relasi *foreign key*, serta tangga urutan kunci baris untuk mencegah kebuntuan (*deadlock*).

---

## 1. Pembagian Tabel per Domain

| Domain | Pemilik | Tabel | Diperkenalkan |
| :--- | :--- | :--- | :--- |
| **D1 Identitas dan Sesi** | Arfan | `pengguna`, `peran`, `izin`, `peran_pengguna`, `sesi`, `alamat` | Sesi 1 – 4 |
| **D2 Toko dan Katalog** | Bagas & Elang | `toko`, `kategori`, `produk` | Sesi 1 |
| **D3 Keranjang dan Pesanan** | Bala | `keranjang`, `butir_keranjang`, `checkout`, `pesanan`, `butir_pesanan` | Sesi 1 – 4 |
| **D4 Pembayaran dan Saldo** | Dimas | `dompet`, `mutasi_saldo`, `pembayaran` | Sesi 1 – 4 |
| **D5 Idempotensi & Keamanan** | Nabhan | `kunci_idempotensi` | Sesi 4 |

---

## 2. Arah Relasi Foreign Key

```text
pengguna (D1) <- dompet (D4) <- mutasi_saldo (D4)
pengguna (D1) <- alamat (D1)
pengguna (D1) <- checkout (D3) <- pesanan (D3) <- butir_pesanan (D3)
toko (D2) <- pesanan (D3)
produk (D2) <- butir_pesanan (D3)
checkout (D3) <- pembayaran (D4)
pengguna (D1) <- kunci_idempotensi (D5)
```

### Catatan Arah Foreign Key:
* `pesanan` **tidak menunjuk** ke `pembayaran`, walau itu yang pertama terpikir: domain bernomor kecil (D3) tidak boleh menunjuk ke domain bernomor besar (D4). Keduanya menunjuk ke `checkout`, induk bersama milik D3 ($LP-7$).
* `pesanan` juga **tidak menunjuk** ke `alamat`: data alamat disalin ke kolom JSON `alamat_kirim` pada saat checkout agar riwayat pengiriman tidak berubah bila pembeli mengedit/menghapus alamatnya di kemudian hari.
* Bila sebuah tabel domain bernomor kecil terasa perlu menunjuk ke domain bernomor besar, carilah induk bersama di domain bernomor kecil.
* Tidak ada tipe data `float`, `double`, atau `decimal`. Seluruh nilai mata uang menggunakan satuan terkecil (rupiah) ber-tipe `unsignedBigInteger`. Uji arsitektur menolak migration yang melanggar aturan ini.

---

## 3. Urutan Kunci Baris (Tangga Kunci Transaksi)

Berlaku sejak Sesi 4. Transaksi yang mengunci lebih dari satu baris mengambil kuncinya menurut urutan di bawah ini. Sebuah transaksi boleh melompati anak tangga, tetapi **tidak pernah naik kembali**.

| Urut | Baris | Dikunci oleh | Pemilik |
| :---: | :--- | :--- | :--- |
| **1** | `pengguna`, lalu `alamat` miliknya | `BukuAlamat` | Arfan |
| **2** | `keranjang`, lalu `butir_keranjang` | `LayananCheckout` | Bala |
| **3** | `checkout` | `GembokCheckout` | Bala |
| **4** | `pembayaran` | `LayananPembayaran` | Dimas |
| **5** | `pesanan` | `MesinStatusPesanan` | Bala |
| **6** | `produk`, urut nomor menaik (`id`) | `PenjagaStok` | Bagas & Elang |
| **7** | `dompet`, urut nomor menaik (`id`) | `BukuBesar` | Dimas |

Middleware `idempoten` mencatat kuncinya sebelum anak tangga pertama. Catatan itu berupa baris baru, dan tidak ada transaksi yang memintanya setelah memegang kunci dari tabel-tabel di atas.

### Tiga Aturan Penguncian:
1. **Kunci hanya diambil di dalam `DB::transaction()`**. Di luar transaksi, kunci lepas begitu pernyataannya selesai dan tidak melindungi apa pun.
2. **Kunci dulu, baca kemudian**. Data yang menjadi dasar keputusan dibaca dengan `lockForUpdate()` setelah kunci induknya didapat. Pada MySQL, `SELECT` biasa di dalam transaksi melihat potret lama (*snapshot*), bukan keadaan terkini.
3. **Tidak ada panggilan ke layanan luar dan tidak ada pekerjaan lama di dalam transaksi**. Selama transaksi terbuka, pembeli lain menunggu di depan baris yang dikuncinya.