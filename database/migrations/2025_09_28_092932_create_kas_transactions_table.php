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

        Schema::create('kas_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admin')   // karena nama tabelmu "admin"
                ->onDelete('cascade');
            $table->foreignId('warga_id')
                ->constrained('warga')   // karena nama tabelmu "admin"
                ->onDelete('cascade');
            $table->enum('jenis', ["warga", "rt"]);
            $table->date('tanggal');
            $table->enum('tipe', ["pemasukan", "pengeluaran"]);
            $table->decimal('jumlah');
            $table->text('keterangan');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kas_transactions');
    }
};
