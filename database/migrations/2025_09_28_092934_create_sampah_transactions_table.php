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
        Schema::disableForeignKeyConstraints();

        Schema::create('sampah_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admin')   
                ->onDelete('cascade');
            $table->foreignId('warga_id')
                ->constrained('warga')   
                ->onDelete('cascade');
            $table->decimal('jumlah');
            $table->enum('tipe', ['pemasukan', 'pengeluaran']);
            $table->text('keterangan');
            $table->date('tanggal');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sampah_transactions');
    }
};
