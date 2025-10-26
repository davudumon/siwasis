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

        Schema::create('warga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admin')
                ->onDelete('cascade');
            $table->string('nama');
            $table->text('alamat');
            $table->enum('role', ['ketua', 'wakil_ketua', 'sekretaris', 'bendahara', 'warga'])->default('warga');
            $table->date('tanggal_lahir');
            $table->string('rt');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warga');
    }
};
