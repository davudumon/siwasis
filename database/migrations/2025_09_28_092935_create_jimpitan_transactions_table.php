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

        Schema::create('jimpitan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->references('id')->on('admin');
            $table->foreignId('warga_id')->references('id')->on('warga');
            $table->decimal('jumlah');
            $table->string('periode');
            $table->date('tanggal');
            $table->binary('keterangan');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jimpitan_transactions');
    }
};
