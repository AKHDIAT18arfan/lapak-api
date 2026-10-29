## Otorisasi
- Kewenangan ditentukan izin yang melekat pada peran, bukan nama peran (LP-2). Satu akun boleh memegang beberapa peran; izinnya adalah gabungan izin seluruh perannya.
- Rute terlindung menyebut paling banyak satu izin: `->middleware('izin:<nama>')`. Bila dua peran membutuhkan pintu yang sama, keduanya diberi izin yang sama. Rute tidak pernah menyebut nama peran.
- Setiap rute terlindung memiliki tepat satu baris pada `tests/Feature/Sistem/matriks/<domain>.php`. Rute yang terbuka bagi siapa pun yang sudah masuk ditulis `'*'`, tidak dibiarkan tanpa baris.
- Urutan pemeriksaan selalu sama: kunci sah (401), memegang izin (403 `DITOLAK`), sesi segar bila rutenya menuntut (403 `LP-15`). Baru setelah ketiganya lolos, masukan divalidasi dan objek dicari. Orang yang tidak berwenang karenanya menerima 403 yang sama untuk nomor yang ada dan yang tidak ada.
- Pertanyaan "objek yang ini boleh?" dijawab di dalam controller: kueri berlingkup (objek milik orang lain dijawab 404) atau Policy (403 `DITOLAK`). Lihat `docs/domain/` untuk pilihan tiap domain.
- Badan 403 `DITOLAK` selalu sama dan tidak menyebut izin apa yang kurang.
- `GET /saya` menyertakan `pengguna.peran` dan `izin`. Klien memakainya untuk menata tampilan saja; server memutuskan ulang pada setiap permintaan.
- Peran hanya berubah lewat `App\Services\PengelolaPeran`. Setiap perubahan mencabut seluruh sesi akun itu. Klien yang tiba-tiba menerima 401 mengarahkan penggunanya masuk ulang.
| Peran | Izin |
| --- | --- |
| `pembeli` | `keranjang.kelola`, `toko.buka` |
| `penjual` | `produk.kelola`, `dompet.lihat` |
| `admin` | `toko.moderasi`, `peran.kelola` |
| `keuangan` | `buku-besar.lihat` |
| `auditor` | `buku-besar.lihat` |
Tabel di atas disalin dari `App\Enums\NamaPeran::izinBawaan()`, satu-satunya tempat pemetaan ini ditulis. `PeranIzinSeeder` menyalinnya ke basis data. Tidak ada peran yang memegang seluruh izin.