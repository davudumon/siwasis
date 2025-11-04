<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->enum('tipe_warga', ['kas', 'arisan'])
                  ->default('kas')
                  ->after('rt');
        });
    }

    public function down(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->dropColumn('tipe_warga');
        });
    }
};
