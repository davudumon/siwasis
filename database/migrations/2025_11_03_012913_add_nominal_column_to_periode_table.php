<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pastikan tabel 'periode' sudah ada sebelum menjalankan ini.
        Schema::table('periode', function (Blueprint $table) {
            // Menambahkan kolom 'nominal'
            // Menggunakan decimal(10, 2) untuk mata uang, memungkinkan nilai hingga 99.999.999,99
            // Anda bisa mengganti dengan $table->integer('nominal') jika nominal selalu bilangan bulat
            $table->decimal('nominal', 10, 2)->default(0)
                  ->after('tanggal_selesai');// Kolom akan ditempatkan setelah 'end_date'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            // Hapus kolom 'nominal' jika migrasi di-rollback
            $table->dropColumn('nominal');
        });
    }
};
