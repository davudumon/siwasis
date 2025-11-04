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
use App\Http\Controllers\KasRtController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\SettingsController;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register'); // opsional

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', 'logout');
        Route::get('profile', 'profile');
        Route::put('profile', 'updateProfile');
        Route::post('password/change', 'changePassword');
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // GET /api/admins -> daftar semua admin
    Route::get('/admins', [SettingsController::class, 'index']);

    // POST /api/admins -> buat admin baru
    Route::post('/admins', [SettingsController::class, 'store']);

    // DELETE /api/admins/{id} -> hapus admin lain
    Route::delete('/admins/{id}', [SettingsController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Menggunakan prefix 'jimpitan' dan controller JimpitanTransactionController untuk semua rute di dalam group.
    Route::prefix('jimpitan')->controller(JimpitanTransactionController::class)->group(function () {

        // 1. Laporan dan Daftar Transaksi (GET /api/jimpitan/laporan)
        // Digunakan untuk menampilkan tabel data dengan filter: tipe, tanggal, year, q, per_page.
        Route::get('laporan', 'index');

        // 2. Ekspor Laporan ke CSV (GET /api/jimpitan/laporan/export)
        // Digunakan untuk mengunduh laporan berdasarkan filter yang diberikan.
        Route::get('laporan/export', 'export');

        // 3. Membuat Transaksi Baru (POST /api/jimpitan/create)
        Route::post('create', 'store');

        // 4. Menampilkan Detail Transaksi (GET /api/jimpitan/{id})
        Route::get('{id}', 'show');

        // 5. Memperbarui Transaksi (PUT /api/jimpitan/update/{id})
        Route::put('update/{id}', 'update');

        // 6. Menghapus Transaksi (DELETE /api/jimpitan/delete/{id})
        Route::delete('delete/{id}', 'destroy');
    });

    // --- Rute API Sampah (Controller SampahTransactionController) ---
    Route::prefix('sampah')->controller(SampahTransactionController::class)->group(function () {

        // GET /api/sampah/laporan (Mengambil data laporan keuangan sampah)
        Route::get('laporan', 'index');

        // GET /api/sampah/laporan/export (Mengembalikan file CSV)
        Route::get('laporan/export', 'export');

        // POST /api/sampah/create (Membuat transaksi baru)
        Route::post('create', 'store');

        // GET /api/sampah/{id} (Menampilkan Detail Transaksi)
        Route::get('{id}', 'show');

        // PUT /api/sampah/update/{id} (Update transaksi)
        Route::put('update/{id}', 'update');

        // DELETE /api/sampah/delete/{id} (Hapus transaksi)
        Route::delete('delete/{id}', 'destroy');
    });

    // Rute untuk Modul Arisan
    Route::prefix('arisan')->controller(ArisanTransactionController::class)->group(function () {

        // GET /api/arisan/rekap (Mengambil data rekapitulasi arisan)
        Route::get('rekap', 'rekap');

        // POST /api/arisan/rekap/save (Menyimpan batch update data arisan)
        Route::post('rekap/save', 'saveRekap');

        // GET /api/arisan/rekap/export (Mengekspor data rekapitulasi arisan ke CSV)
        Route::get('rekap/export', 'exportRekap');
    });


    Route::prefix('arisan/spin')->controller(GiliranArisanController::class)->group(function () {

        // 🔹 GET /api/arisan/giliran
        // Ambil daftar semua giliran arisan (bisa difilter pakai ?periode_id=)
        Route::get('/', 'index');

        // 🔹 GET /api/arisan/giliran/belum-dapat
        // Ambil warga yang belum dapat giliran (untuk spin)
        Route::get('candidates', 'getBelumDapat');

        // 🔹 POST /api/arisan/giliran/spin
        // Tandai warga sudah dapat giliran (hasil spin)
        Route::post('draw', 'store');
    });


    Route::prefix('kas/rekap')->controller(KasWargaController::class)->group(function () {

        // GET /api/kas/rekap
        Route::get('/', 'rekap'); // ambil data rekap kas warga (dengan filter periode / year)

        // POST /api/kas/rekap/save
        Route::post('save', 'rekapSave'); // simpan batch centang (sudah_bayar/belum_bayar)

        // GET /api/kas/rekap/export
        Route::get('export', 'export'); // (opsional) ekspor data ke CSV
    });

    Route::prefix('kas/laporan')->controller(KasRtController::class)->group(function () {

        // GET /api/kas/laporan
        Route::get('/', 'index'); // daftar transaksi kas RT

        // POST /api/kas/laporan/create
        Route::post('create', 'store'); // tambah transaksi baru

        // PUT /api/kas/laporan/update/{id}
        Route::put('update/{id}', 'update'); // update transaksi

        // DELETE /api/kas/laporan/delete/{id}
        Route::delete('delete/{id}', 'destroy'); // hapus transaksi

        // GET /api/kas/laporan/export
        Route::get('export', 'export'); // ekspor laporan ke CSV
    });

    Route::prefix('documents')->controller(DocumentController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('{id}', 'update');
        Route::delete('{id}', 'destroy');
        Route::get('{id}/download', 'download');
    });

    Route::prefix('youtube')->controller(YoutubeLinkController::class)->group(function () {
        // GET /api/youtube → Ambil semua link YouTube
        Route::get('/', 'index');

        // POST /api/youtube → Tambah link baru
        Route::post('/', 'store');

        // GET /api/youtube/{id} → Tampilkan detail link
        Route::get('{id}', 'show');

        // POST /api/youtube/{id} → Update link (pakai FormData + _method=PUT)
        Route::post('{id}', 'update');

        // DELETE /api/youtube/{id} → Hapus link
        Route::delete('{id}', 'destroy');
    });

    Route::prefix('warga')->group(function () {
        // GET /api/warga (Ambil semua warga)
        Route::get('/', [WargaController::class, 'index']);
        // POST /api/warga (Membuat warga baru)
        Route::post('/arisan', [WargaController::class, 'storeArisan']);
        Route::post('/kas', [WargaController::class, 'storeKas']);
        // GET /api/warga/{id} (Ambil detail warga)
        Route::get('/{id}', [WargaController::class, 'show']);
        // PUT /api/warga/{id} (Update data warga)
        Route::put('/{id}', [WargaController::class, 'update']);
        // DELETE /api/warga/{id} (Menghapus data warga)
        Route::delete('/{id}', [WargaController::class, 'destroy']);
    });

    Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
        Route::get('summary', 'summary'); // GET /api/dashboard/summary
    });
});

Route::prefix('periode')->group(function () {
    
    // Rute Publik (Akses Tanpa Autentikasi)
    
    // 1. Ambil semua periode (index)
    // URL: GET /api/periode
    Route::get('/', [PeriodeController::class, 'index']); 
    
    // 2. Ambil detail periode (show)
    // URL: GET /api/periode/{id}
    Route::get('/{id}', [PeriodeController::class, 'show']); 

    // Rute Terproteksi (Hanya Admin Terotentikasi)
    Route::middleware('auth:sanctum')->group(function () {
        
        // 3. Buat periode baru (store)
        // URL: POST /api/periode
        Route::post('/', [PeriodeController::class, 'store']);
        
        // 4. Update periode (update)
        // URL: PUT /api/periode/{id}
        Route::put('/{id}', [PeriodeController::class, 'update']);
        
        // 5. Hapus periode (destroy)
        // URL: DELETE /api/periode/{id}
        Route::delete('/{id}', [PeriodeController::class, 'destroy']);
    });
});
