<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('arisan_transactions', function (Blueprint $table) {
            $table->id();

            // Relasi ke admin & warga
            $table->foreignId('admin_id')
                ->constrained('admin')
                ->onDelete('cascade');

            $table->foreignId('warga_id')
                ->constrained('warga')
                ->onDelete('cascade');

            // 🔹 Relasi ke tabel periode
            $table->foreignId('periode_id')
                ->constrained('periode')
                ->onDelete('cascade');

            // Informasi transaksi arisan
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->enum('status', ['belum_bayar', 'sudah_bayar'])->default('belum_bayar');

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('arisan_transactions');
    }
};
