<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom photo di tabel admins
     */
    public function up(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('email');
        });
    }

    /**
     * Rollback perubahan
     */
    public function down(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
