<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi (hapus tabel giliran_arisan).
     */
    public function up(): void
    {
        Schema::dropIfExists('giliran_arisan');
    }

    /**
     * Kembalikan migrasi (buat ulang tabel giliran_arisan jika dibatalkan).
     */
    public function down(): void
    {
        Schema::create('giliran_arisan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warga_id')->constrained('warga')->onDelete('cascade');
            $table->foreignId('periode_id')->constrained('periode')->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('admin')->onDelete('set null');
            $table->enum('status', ['belum_dapat', 'sudah_dapat'])->default('belum_dapat');
            $table->timestamps();
        });
    }
};
