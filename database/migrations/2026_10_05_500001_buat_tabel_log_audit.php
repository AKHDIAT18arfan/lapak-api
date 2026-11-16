public function up(): void
{
    Schema::create('log audit', function (Blueprint $tabel) {
        $tabel->id();
        $tabel->string('peristiwa', 60)->index();
        // Kosong bila pelakunya sistem atau gerbang pembayaran.
        $tabel->foreignId('pelaku_id')->nullable()->constrained('pengguna')->restrictOnDelete();
        // Yang dikenai tindakan, misalnya "pesanan:LPK-20261027-000412" atau "pengguna:8".
        $tabel->string('sasaran', 80)->nullable()->index();
        $tabel->json('rincian');
        // Pengenal permintaan dari middleware IdPermintaan, pencocok dengan log aplikasi.
        $tabel->char('id_permintaan', 36)->nullable();
        // Setiap baris menyebut hash baris sebelumnya. unique(): dua baris tidak dapat
        // mengaku meneruskan baris yang sama, sehingga rantai tidak dapat bercabang.
        $tabel->char('hash_sebelumnya', 64)->unique();
        $tabel->char('hash', 64)->unique();
        $tabel->dateTime('dicatat pada');
    });
    
    // Kepala rantai: satu baris yang selalu ada. Dialah yang dikunci setiap kali log
    // bertambah, dan dialah yang membuat pemotongan ekor rantai ketahuan.
    Schema::create('rantai_audit', function (Blueprint $tabel) {
        $tabel->unsignedTinyInteger('id')->primary();
        $tabel->char('hash_terakhir', 64);
        $tabel->unsignedBigInteger('jumlah');
    });

    DB: : table('rantai_audit' )->insert([
        'id' => 1, 'hash_terakhir' => str_repeat('0', 64), 'jumlah' => 0,
    ]);
}