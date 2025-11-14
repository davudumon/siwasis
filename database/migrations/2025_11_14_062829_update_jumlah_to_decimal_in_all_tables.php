<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sampah_transactions', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('jimpitan_transactions', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });

        Schema::table('kas_rt', function (Blueprint $table) {
            $table->decimal('jumlah', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sampah_transactions', function (Blueprint $table) {
            $table->integer('jumlah')->change();
        });

        Schema::table('jimpitan_transactions', function (Blueprint $table) {
            $table->integer('jumlah')->change();
        });

        Schema::table('kas_rt', function (Blueprint $table) {
            $table->integer('jumlah')->change();
        });
    }
};
