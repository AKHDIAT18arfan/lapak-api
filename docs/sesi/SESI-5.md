# Papan Sesi 5: Escrow, Saldo, Audit, dan Rilis

- **Integrator Sesi (Peran Sekar)**: Nabhan (`@nabhanherwastyo`)
- **Titik Berangkat**: Tag `sesi-4`
- **Target Tag**: `sesi-5`

---

## A. Pemetaan Peran Modul

| Peran di Modul | Anggota | Akun GitHub | Domain |
| :--- | :--- | :--- | :--- |
| **Sekar** (Integrator) | Nabhan | `@nabhanherwastyo` | D5 Audit & Rilis |
| **Bambang** | Arfan | `@AKHDIAT18arfan` | D1 Identitas, Sesi, & Changelog |
| **Bagas** | Bagas & Elang | `@ahmadbagasmahardika50`, `@elangfebryan20` | D2 Toko, Katalog, & Pengiriman |
| **Rani** | Bala | `@balasyahda05` | D3 Pesanan & Penyelesaian Otomatis |
| **Dimas** | Dimas | `@DimasAryaPutra` | D4 Escrow, Dana, & Pencairan |

---

## B. Sasaran Sesi 5

1. **Siklus Hidup Pesanan Lengkap ($LP-10$):** Melengkapi transisi status dari `dibayar` → `dikemas` → `dikirim` → `selesai` (konfirmasi pembeli atau otomatis 3 hari setelah dikirim).
2. **Escrow & Pelepasan Dana ($LP-8, LP-12$):** Menahan dana di akun escrow saat pembayaran berhasil, lalu melepasnya ke dompet penjual setelah dipotong komisi marketplace 5% (pembulatan setengah ke atas) saat pesanan selesai.
3. **Pencairan Saldo & Pemisahan Tugas ($LP-16$):** Penjual mengajukan pencairan minimal Rp 50.000; peran `keuangan` menyetujui/menolak dengan syarat penyetuju tidak boleh orang yang sama dengan pengaju.
4. **Log Audit Berantai Hash ($LP-17$):** Mencatat tindakan yang mengubah uang, stok, kewenangan, serta percobaan masuk gagal ke `log_audit` (*append-only* + rantai hash) di dalam transaksi bisnis, serta menyediakan verifikasi integritas rantai bagi peran `auditor`.
5. **Kesiapan Rilis:** Menyusun `docs/RILIS.md`, `CHANGELOG.md`, memastikan 17/17 aturan di `docs/ATURAN-BISNIS.md` ditegakkan, dan menandai rilis pada tag `sesi-5`.

---

## C. Jawaban atas Titipan Sesi 4

1. **Dana Escrow Tertahan:** Dana pembayaran yang masuk pada Sesi 4 baru berhenti di dompet escrow; pada Sesi 5 dihubungkan ke status `selesai` pesanan lewat `LayananDana` untuk dilepas ke penjual.
2. **Jejak Audit pada Mutasi Kritis:** Pemanggilan `PencatatAudit::catat()` dijalankan di dalam `DB::transaction()` yang sama dan diletakkan pada baris paling akhir transaksi (tangga kunci ke-8: `rantai_audit`).
3. **Pencegahan Konflik Berkas Bersama:** `NamaPeran::izinBawaan()` ditata satu izin per baris dan `RuteIdempotenTest` dipecah per domain ke `tests/Feature/Sistem/idempoten/*.php` sejak PR Pembuka.

---

## D. Daftar Peristiwa Audit (`<benda>.<kejadian>`)

| Domain | Peristiwa | Keterangan |
| :--- | :--- | :--- |
| **D1 Identitas** | `sesi.gagal_masuk`, `sesi.dicabut`, `sesi.semua_dicabut`, `peran.diberikan`, `peran.dicabut` | Mencatat surel (bukan kata sandi) saat gagal masuk, pencabutan sesi, dan perubahan peran |
| **D2 Toko & Katalog** | `toko.disetujui`, `toko.ditangguhkan`, `stok.disesuaikan` | Perubahan status moderasi toko dan penyesuaian stok manual |
| **D3 Pesanan** | `pesanan.dibatalkan`, `pesanan.kedaluwarsa`, `pesanan.selesai` | Pembatalan manual/otomatis yang mengembalikan stok dan penyelesaian yang melepas dana |
| **D4 Dana & Pencairan** | `pembayaran.berhasil`, `dana.dilepas`, `pencairan.diajukan`, `pencairan.disetujui`, `pencairan.ditolak` | Seluruh perpindahan uang masuk escrow, pelepasan ke penjual, dan proses pencairan |
| **D5 Sistem Audit** | `audit.diverifikasi` | Pemeriksaan keutuhan rantai hash oleh auditor |

---

## E. Tabel Dua Peninjau (Lingkar)

Setiap Pull Request membutuhkan **2 approval** sebelum merge ke `main`:

| Pembuat PR | Peninjau 1 | Peninjau 2 |
| :--- | :--- | :--- |
| **Nabhan** (`@nabhanherwastyo`) | Bala (`@balasyahda05`) | Bagas / Elang (`@ahmadbagasmahardika50` / `@elangfebryan20`) |
| **Bala** (`@balasyahda05`) | Bagas / Elang (`@ahmadbagasmahardika50` / `@elangfebryan20`) | Dimas (`@DimasAryaPutra`) |
| **Bagas & Elang** (`@ahmadbagasmahardika50`, `@elangfebryan20`) | Dimas (`@DimasAryaPutra`) | Arfan (`@AKHDIAT18arfan`) |
| **Dimas** (`@DimasAryaPutra`) | Arfan (`@AKHDIAT18arfan`) | Nabhan (`@nabhanherwastyo`) |
| **Arfan** (`@AKHDIAT18arfan`) | Nabhan (`@nabhanherwastyo`) | Bala (`@balasyahda05`) |

---

## F. Urutan Merge Pull Request (PR)

### Pembuka
- [ ] **PR Pembuka**: Papan Sesi 5, Daftar Periksa Rilis, Peta Skema, CODEOWNERS, Refactor Enum & Rute Idempoten (`sesi-5/pembuka-nabhan`) — **Nabhan**

### Jendela A: Kontrak & Fondasi
- [ ] **A1**: Skema `log_audit` & `rantai_audit`, `PeristiwaAudit`, `PencatatAudit`, `PemeriksaRantaiAudit` (`sesi-5/kontrak-audit-nabhan`) — **Nabhan**
- [ ] **A2**: Pintu Tunggal `LayananPesanan`, `PenyelesaianOtomatis`, & Pembantu Uji `tests/Pendukung/` (`sesi-5/kontrak-pesanan-bala`) — **Bala**
- [ ] **A3**: Skema `pencairan`, `LayananDana` (Escrow), & Kontrak `LayananPencairan` (`sesi-5/kontrak-dana-dimas`) — **Dimas**

### Jendela B: Implementasi Domain & Jejak Audit
- [ ] **B1**: Jejak Audit Identitas, Gagal Masuk, Cabut Sesi, & Perubahan Peran (`sesi-5/audit-identitas-arfan`) — **Arfan**
- [ ] **B2**: `PesananTokoController` (Kemas & Kirim) serta Jejak Audit Toko & Stok (`sesi-5/kirim-pesanan-bagas-elang`) — **Bagas & Elang**
- [ ] **B3**: Konfirmasi Terima Pembeli, Penyelesaian Otomatis 3 Hari, & Audit Pembatalan (`sesi-5/selesai-pesanan-bala`) — **Bala**
- [ ] **B4**: Pelepasan Dana Escrow (Komisi 5%) serta Pengajuan & Persetujuan Pencairan Saldo (`sesi-5/pencairan-dimas`) — **Dimas**
- [ ] **B5**: Endpoint Pembacaan Log Audit & Verifikasi Rantai Hash untuk Auditor (`sesi-5/pintu-audit-nabhan`) — **Nabhan**

### Jendela Rilis & Penutup
- [ ] **R1**: Penyusunan `CHANGELOG.md` & Pemeriksaan Kesiapan Rilis (`sesi-5/changelog-arfan`) — **Arfan**
- [ ] **Penutup**: Uji Integrasi Akhir, Pembaruan `ATURAN-BISNIS.md`, Verifikasi `RILIS.md`, & Tag `sesi-5` (`sesi-5/penutup-nabhan`) — **Nabhan**