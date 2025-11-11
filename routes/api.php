<?php

use App\Http\Controllers\ArisanTransactionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WargaController;
use App\Http\Controllers\YoutubeLinkController;
use App\Http\Controllers\GiliranArisanController;
use App\Http\Controllers\KasWargaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SampahTransactionController;
use App\Http\Controllers\JimpitanTransactionController;
use App\Http\Controllers\KasRTController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\SettingsController;

// Rute Auth Publik
Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register'); // opsional
});

// Rute Periode Publik (sudah ada)
Route::prefix('periode')->group(function () {
    Route::get('/', [PeriodeController::class, 'index']);
    Route::get('/{id}', [PeriodeController::class, 'show']);
});


// Rute Publik lainnya (Dipindahkan dari blok auth:sanctum)

Route::prefix('warga')->group(function () {
    Route::get('/', [WargaController::class, 'index']);
    Route::get('/{id}', [WargaController::class, 'show']);
});

Route::prefix('jimpitan')->controller(JimpitanTransactionController::class)->group(function () {
    Route::get('laporan', 'index');
    Route::get('laporan/export', 'export');
});

Route::prefix('sampah')->controller(SampahTransactionController::class)->group(function () {
    Route::get('laporan', 'index');
});

Route::prefix('arisan')->controller(ArisanTransactionController::class)->group(function () {
    Route::get('rekap', 'rekap');
});

Route::prefix('arisan/spin')->controller(GiliranArisanController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('kas/rekap')->controller(KasWargaController::class)->group(function () {
    Route::get('/', 'rekap');
});

Route::prefix('kas/laporan')->controller(KasRTController::class)->group(function () {
    Route::get('/', 'index');
});

Route::prefix('documents')->controller(DocumentController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('{id}/download', 'download');
});

Route::prefix('youtube')->controller(YoutubeLinkController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

// DASHBOARD
Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
    Route::get('summary', 'summary');
});


// ==================================================================================
// === RUTE TERPROTEKSI (AUTH:SANCTUM) - Hanya untuk Admin/Pengguna Terotentikasi ===
// ==================================================================================

Route::middleware('auth:sanctum')->group(function () {

    // AUTH (profile, logout, changePassword)
    Route::controller(AuthController::class)->group(function () {
        Route::delete('logout', 'logout');
        Route::get('profile', 'profile');
        Route::put('profile', 'updateProfile');
        Route::post('password/change', 'changePassword');
    });

    // ADMINS
    Route::get('/admins', [SettingsController::class, 'index']);
    Route::post('/admins', [SettingsController::class, 'store']);
    Route::delete('/admins/{id}', [SettingsController::class, 'destroy']);

    // JIMPITAN (Hanya sisa POST/PUT/DELETE/SHOW Detail)
    Route::prefix('jimpitan')->controller(JimpitanTransactionController::class)->group(function () {
        Route::post('create', 'store');
        Route::get('{id}', 'show'); // Detail transaksi spesifik, mungkin sensitif
        Route::put('update/{id}', 'update');
        Route::delete('delete/{id}', 'destroy');
    });

    // SAMPAH (Hanya sisa POST/PUT/DELETE/SHOW Detail)
    Route::prefix('sampah')->controller(SampahTransactionController::class)->group(function () {
        Route::post('create', 'store');
        Route::get('{id}', 'show'); // Detail transaksi spesifik, mungkin sensitif
        Route::put('update/{id}', 'update');
        Route::delete('delete/{id}', 'destroy');
    });

    // ARISAN
    Route::prefix('arisan')->controller(ArisanTransactionController::class)->group(function () {
        Route::post('rekap/save', 'saveRekap');
        Route::get('rekap/export', 'exportRekap'); // Ekspor mungkin dibatasi ke admin
    });

    Route::prefix('arisan/spin')->controller(GiliranArisanController::class)->group(function () {
        Route::get('candidates', 'getBelumDapat'); // Daftar kandidat spin hanya untuk admin
        Route::post('draw', 'store');
    });

    // KAS
    Route::prefix('kas/rekap')->controller(KasWargaController::class)->group(function () {
        Route::post('save', 'rekapSave');
        Route::get('export', 'export');
    });

    Route::prefix('kas/laporan')->controller(KasRtController::class)->group(function () {
        Route::post('create', 'store');
        Route::put('update/{id}', 'update');
        Route::delete('delete/{id}', 'destroy');
        Route::get('export', 'export');
    });

    // DOCUMENTS
    Route::prefix('documents')->controller(DocumentController::class)->group(function () {
        Route::post('/', 'store');
        Route::put('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    //  YOUTUBE
    Route::prefix('youtube')->controller(YoutubeLinkController::class)->group(function () {
        Route::post('/', 'store');
        Route::post('{id}', 'update');
        Route::delete('{id}', 'destroy');
    });

    // WARGA
    Route::prefix('warga')->group(function () {
        Route::post('/arisan', [WargaController::class, 'storeArisan']);
        Route::post('/kas', [WargaController::class, 'storeKas']);
        Route::put('/{id}', [WargaController::class, 'update']);
        Route::delete('/{id}', [WargaController::class, 'destroy']);
    });

    // PERIODE
    Route::prefix('periode')->group(function () {
        Route::post('/', [PeriodeController::class, 'store']);
        Route::put('/{id}', [PeriodeController::class, 'update']);
        Route::delete('/{id}', [PeriodeController::class, 'destroy']);
    });
});
