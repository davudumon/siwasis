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

        Schema::create('giliran_arisan', function (Blueprint $table) {
            $table->id();

            // Relasi ke admin & warga
            $table->foreignId('admin_id')->constrained('admin')->onDelete('cascade');
            $table->foreignId('warga_id')->constrained('warga')->onDelete('cascade');

            // Informasi arisan
            $table->string('periode'); // contoh: "2025-10"
            $table->enum('status', ['belum_dapat', 'sudah_dapat'])->default('belum_dapat');
            $table->date('tanggal_dapat')->nullable();

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giliran_arisan');
    }
};
