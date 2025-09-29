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

        Schema::create('youtube_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')
                ->constrained('admin')   // sesuai nama tabel "admin"
                ->onDelete('cascade');   // opsional, biar kalau admin dihapus, link ikut hilang
            $table->string('title');
            $table->string('url');
            $table->timestamps();          // biar ada created_at & updated_at
        });


        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_links');
    }
};
