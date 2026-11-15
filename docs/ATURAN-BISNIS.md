# Aturan Bisnis Lapak

Tujuh belas aturan berikut adalah kontrak domain Lapak. Setiap aturan berkode agar dapat dirujuk dari kode, pesan galat, kasus uji, dan judul Pull Request.

| Kode | Aturan | Ditegakkan di |
| --- | --- | --- |
| LP-1 | Seluruh nilai uang adalah bilangan bulat rupiah. Tipe pecahan dilarang di kolom, kode, dan JSON. | `App\Domain\Uang`, kolom berakhiran `_rupiah`, uji arsitektur |
| LP-2 | Kewenangan ditentukan izin yang melekat pada peran, bukan nama peran. Satu akun boleh memegang lebih dari satu peran. | `NamaPeran::izinBawaan`, `PemeriksaIzin`, middleware `izin`, `PengelolaPeran`, `MatriksOtorisasiTest` |
| LP-3 | Satu pengguna memiliki paling banyak satu toko. Toko baru berstatus menunggu sampai disetujui admin. | `unique` pada `toko.pemilik_id`, izin `toko.buka`, `ModerasiToko`, `TokoPolicy` |
| LP-4 | Produk hanya tampil dan dapat dibeli bila berstatus aktif, tokonya aktif, dan stoknya mencukupi. Stok tidak pernah negatif. | `Produk::scopeTampil`, `PenyusunBelanja`, transaksi checkout dengan `lockForUpdate()` (urutan kunci `produk_id`), validasi stok non-negatif (`lapak:balapan`) |
| LP-5 | Penjual tidak dapat membeli produk dari tokonya sendiri. | `PenyusunBelanja::pilah`, `LayananKeranjang::aturButir` |
| LP-6 | Harga, ongkir, dan total dihitung server dari basis data. Angka kiriman klien diabaikan. Harga dibekukan pada butir pesanan saat checkout. | `SimulasiBelanjaRequest`, `PenyusunBelanja`, `LayananKeranjang::lihat`, pembekuan harga pada butir pesanan saat checkout |
| LP-7 | Satu checkout menghasilkan satu pesanan per toko dan dibayar dengan satu pembayaran. | `PenghitungBelanja`, proses checkout (`Checkout`, `Pesanan`, `Pembayaran`) |
| LP-8 | Komisi marketplace 5% dari subtotal produk, dibulatkan setengah ke atas, dipotong dari bagian penjual saat dana dilepas. Ongkir sepenuhnya milik penjual. | Sebagian: `PenghitungBelanja`, `RincianToko::bagianPenjual`, pencatatan komisi dan bagian penjual pada `Pesanan` (pemotongan saat pelepasan dana menyusul) |
| LP-9 | Nomor pesanan berformat LPK-YYYYMMDD-NNNNNN dan unik seumur hidup sistem. | Generator nomor pesanan saat checkout dan indeks `unique` pada kolom nomor pesanan |
| LP-10 | Status pesanan bergerak menunggu pembayaran, dibayar, dikemas, dikirim, selesai. Pembatalan hanya sebelum dikirim. Pesanan yang tidak dibayar dalam 24 jam batal otomatis dan stoknya kembali. | Sebagian: `App\Domain\Pesanan\AturanTransisi`, alur checkout dan webhook pembayaran (`menunggu_pembayaran` ke `dibayar`), pembatalan dengan pengembalian stok terurut (`lockForUpdate`), serta pembatalan otomatis kedaluwarsa 24 jam (tahap pengiriman & selesai menyusul) |
| LP-11 | Setiap permintaan yang mengubah uang atau stok wajib membawa Idempotency-Key. Kunci yang sama menghasilkan respons yang sama dan efek satu kali. | Middleware idempotensi (`Idempotency-Key`), penyimpanan sidik jari payload dan respons kunci, daftar rute `POST_BERKUNCI` / `POST_TANPA_KUNCI`, `RuteIdempotenTest` |
| LP-12 | Dana pembeli ditahan di escrow dan dilepas ke penjual setelah pembeli mengonfirmasi terima, atau otomatis 3 hari setelah dikirim. | Sebagian: pencatatan dana masuk ke dompet escrow lewat `BukuBesar` saat webhook pembayaran sah (verifikasi HMAC `LAPAK_GATEWAY_RAHASIA`); konfirmasi terima & pelepasan otomatis 3 hari menyusul |
| LP-13 | Saldo hanya berubah lewat pemindahan antardompet yang tercatat sebagai sepasang mutasi berjumlah nol. Mutasi tidak diubah dan tidak dihapus. Hanya dompet eksternal yang boleh negatif. | `BukuBesar`, `MutasiSaldo::booted`, `lapak:periksa-buku` (dipakai juga pada pemindahan dana webhook pembayaran) |
| LP-14 | Kunci sesi ditampilkan satu kali dan disimpan sebagai hash. Sesi berlaku paling lama 7 hari, gugur setelah 2 jam tidak dipakai, dan dapat dicabut per perangkat. | `KunciSesi`, `PenerbitSesi`, `Sesi::scopeBerlaku`, `lapak:pangkas-sesi` |
| LP-15 | Tindakan sensitif menuntut sesi segar: kata sandi dikonfirmasi ulang dalam 10 menit terakhir. | Sebagian: `SesiSegar`, `SesiController::konfirmasi`; dipakai pada cabut sesi, riwayat mutasi, perubahan peran, keputusan moderasi, dan buku besar |
| LP-16 | Pencairan saldo minimal Rp 50.000, disetujui peran keuangan, dan penyetuju tidak boleh orang yang sama dengan pengaju. | - |
| LP-17 | Setiap tindakan sensitif tercatat pada log audit yang hanya dapat ditambah dan berantai hash. | - |