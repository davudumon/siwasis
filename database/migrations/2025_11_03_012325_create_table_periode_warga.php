<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_warga', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel periode dan warga
            $table->foreignId('periode_id')
                ->constrained('periode')
                ->onDelete('cascade');

            $table->foreignId('warga_id')
                ->constrained('warga')
                ->onDelete('cascade');

            /**
             * Status keikutsertaan arisan:
             * - tidak_ikut   → warga ini hanya ikut kas
             * - belum_dapat  → ikut arisan tapi belum menang
             * - sudah_dapat  → ikut arisan dan sudah menang
             */
            $table->enum('status_arisan', ['tidak_ikut', 'belum_dapat', 'sudah_dapat'])
                  ->default('tidak_ikut');

            // Unik: satu warga hanya muncul sekali per periode
            $table->unique(['periode_id', 'warga_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_warga');
    }
};
