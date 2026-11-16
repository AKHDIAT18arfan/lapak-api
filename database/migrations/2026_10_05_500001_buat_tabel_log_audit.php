<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PERBAIKAN 1: Hapus spasi pada nama tabel menjadi 'log_audit'
        Schema::create('log_audit', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('peristiwa', 60)->index();
            $tabel->foreignId('pelaku_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $tabel->string('sasaran', 80)->nullable()->index();
            $tabel->json('rincian');
            $tabel->char('id_permintaan', 36)->nullable();
            $tabel->char('hash_sebelumnya', 64)->unique();
            $tabel->char('hash', 64)->unique();

            // PERBAIKAN 2: Hapus spasi pada nama kolom menjadi 'dicatat_pada'
            $tabel->dateTime('dicatat_pada');
        });

        Schema::create('rantai_audit', function (Blueprint $tabel) {
            $tabel->unsignedTinyInteger('id')->primary();
            $tabel->char('hash_terakhir', 64);
            $tabel->unsignedBigInteger('jumlah');
        });

        // PERBAIKAN 3: Rapatkan DB::table (sebelumnya DB: : table)
        DB::table('rantai_audit')->insert([
            'id' => 1, 'hash_terakhir' => str_repeat('0', 64), 'jumlah' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rantai_audit');
        Schema::dropIfExists('log_audit');
    }
};
