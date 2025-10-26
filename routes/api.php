<?php

use App\Http\Controllers\ArisanTransactionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WargaController;
use App\Http\Controllers\YoutubeLinkController;
use App\Http\Controllers\GiliranArisanController;
use App\Http\Controllers\KasWargaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('warga', WargaController::class);
    Route::apiResource('articles', ArticleController::class);
    Route::apiResource('yt_links', YoutubeLinkController::class);
    Route::apiResource('document', DocumentController::class);
    Route::get('/giliran-arisan', [GiliranArisanController::class, 'index']);
    Route::get('/giliran-arisan/belum-dapat', [GiliranArisanController::class, 'getBelumDapat']);
    Route::post('/giliran-arisan', [GiliranArisanController::class, 'store']);
    Route::post('/giliran-arisan/reset', [GiliranArisanController::class, 'resetPeriode']);
    Route::post('/giliran-arisan/generate', [GiliranArisanController::class, 'generatePeriode']);
    Route::get('/arisan', [ArisanTransactionController::class, 'index']);
    Route::post('/arisan/toggle', [ArisanTransactionController::class, 'toggle']);
    Route::post('/arisan/generate', [ArisanTransactionController::class, 'generateJadwal']);
    Route::get('/kas/warga', [KasWargaController::class, 'index']);
    Route::post('/kas/warga/toggle', [KasWargaController::class, 'toggleStatus']);
    Route::post('/kas/warga/generate', [KasWargaController::class, 'generateKasOtomatis']);
});