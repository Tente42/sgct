<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CdrController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\AuthController; 

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. RUTAS PÚBLICAS (Entrada libre)
// ==========================================

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
// Le decimos: "Permite máximo 10 intentos por 1 minuto".
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1') 
    ->name('iniciar-sesion');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


// ==========================================
// 2. RUTAS PRIVADAS (Con Candado)
// ==========================================
// Todo lo que esté aquí dentro REQUIERE haber iniciado sesión.
Route::middleware(['auth'])->group(function () {
    
    // Panel Principal
    Route::get('/', [CdrController::class, 'index'])->name('home');
    Route::get('/dashboard', [CdrController::class, 'index'])->name('dashboard');
    Route::get('/graficos', [CdrController::class, 'showCharts'])->name('cdr.charts');
    Route::get('/configuracion', [ExtensionController::class, 'index'])->name('extension.index');

    // Funciones del Sistema
    Route::get('/sync', [CdrController::class, 'syncCDRs'])->name('cdr.sync');
    Route::get('/export-pdf', [CdrController::class, 'descargarPDF'])->name('cdr.pdf');
    Route::post('/extension/update', [ExtensionController::class, 'update'])->name('extension.update');
    Route::get('/exportar-excel', [App\Http\Controllers\CdrController::class, 'exportarExcel'])->name('calls.export');

});