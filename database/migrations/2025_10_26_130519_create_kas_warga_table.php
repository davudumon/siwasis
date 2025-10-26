<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_warga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admin')
                ->onDelete('cascade');
            $table->foreignId('warga_id')
                ->constrained('warga')
                ->onDelete('cascade');
            $table->string('periode'); // contoh: "Januari 2025"   
            $table->decimal('jumlah');
            $table->date('tanggal');
            $table->enum('status', ['belum_bayar', 'sudah_bayar'])->default('belum_bayar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_warga');
    }
};

