# Peta Skema Basis Data Lapak

Dokumen ini mencatat pembagian tabel basis data per domain, arah relasi *foreign key*, serta tangga urutan kunci baris untuk mencegah kebuntuan (*deadlock*).

---

## 1. Pembagian Tabel per Domain

| Domain | Pemilik | Tabel | Diperkenalkan |
| :--- | :--- | :--- | :--- |
| **D1 Identitas dan Sesi** | Arfan | `pengguna`, `peran`, `izin`, `peran_pengguna`, `sesi`, `alamat` | Sesi 1 – 4 |
| **D2 Toko dan Katalog** | Bagas & Elang | `toko`, `kategori`, `produk` | Sesi 1 |
| **D3 Keranjang dan Pesanan** | Bala | `keranjang`, `butir_keranjang`, `checkout`, `pesanan`, `butir_pesanan` | Sesi 1 – 4 |
| **D4 Pembayaran dan Saldo** | Dimas | `dompet`, `mutasi_saldo`, `pembayaran`, `pencairan` | Sesi 1 – 5 |
| **D5 Idempotensi & Keamanan** | Nabhan | `kunci_idempotensi`, `log_audit`, `rantai_audit` | Sesi 4 – 5 |

---

## 2. Arah Relasi Foreign Key

```text
pengguna (D1) <- dompet (D4) <- mutasi_saldo (D4)
pengguna (D1) <- alamat (D1)
pengguna (D1) <- checkout (D3) <- pesanan (D3) <- butir_pesanan (D3)
toko (D2) <- pesanan (D3)
produk (D2) <- butir_pesanan (D3)
checkout (D3) <- pembayaran (D4)
pengguna (D1) <- pencairan (D4)
pengguna (D1) <- kunci_idempotensi (D5)
pengguna (D1) <- log_audit (D5)