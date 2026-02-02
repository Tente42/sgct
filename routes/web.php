<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CdrController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PbxConnectionController;

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

// Easter Egg: DOOM
Route::get('/doom', function () {
    return view('doom');
})->name('doom');


// ==========================================
// 2. RUTAS DE GESTIÓN DE CENTRALES PBX
// ==========================================
// Estas rutas requieren auth pero NO requieren central seleccionada
Route::middleware(['auth'])->prefix('pbx')->name('pbx.')->group(function () {
    Route::get('/', [PbxConnectionController::class, 'index'])->name('index');
    Route::post('/', [PbxConnectionController::class, 'store'])->name('store');
    Route::put('/{pbx}', [PbxConnectionController::class, 'update'])->name('update');
    Route::delete('/{pbx}', [PbxConnectionController::class, 'destroy'])->name('destroy');
    Route::get('/select/{pbx}', [PbxConnectionController::class, 'select'])->name('select');
    Route::get('/setup/{pbx}', [PbxConnectionController::class, 'setup'])->name('setup');
    Route::post('/sync/{pbx}', [PbxConnectionController::class, 'syncInitial'])->name('syncInitial');
    Route::get('/sync-status/{pbx}', [PbxConnectionController::class, 'checkSyncStatus'])->name('syncStatus');
    Route::post('/disconnect', [PbxConnectionController::class, 'disconnect'])->name('disconnect');
});


// ==========================================
// 3. RUTAS PRIVADAS (Requieren auth + central seleccionada)
// ==========================================
Route::middleware(['auth', 'pbx.selected'])->group(function () {
    
    // Panel Principal
    Route::get('/', [CdrController::class, 'index'])->name('home');
    Route::get('/dashboard', [CdrController::class, 'index'])->name('dashboard');
    Route::get('/graficos', [CdrController::class, 'showCharts'])->name('cdr.charts');
    Route::get('/configuracion', [ExtensionController::class, 'index'])->name('extension.index');

    // Funciones del Sistema
    Route::post('/sync', [CdrController::class, 'syncCDRs'])->name('cdr.sync');
    Route::get('/export-pdf', [CdrController::class, 'descargarPDF'])->name('cdr.pdf');
    Route::post('/extension/update', [ExtensionController::class, 'update'])->name('extension.update');
    Route::post('/extension/update-ips', [ExtensionController::class, 'updateIps'])->name('extension.updateIps');
    Route::get('/exportar-excel', [App\Http\Controllers\CdrController::class, 'exportarExcel'])->name('calls.export');

    // Configuración de Tarifas
    Route::get('/tarifas', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/tarifas', [SettingController::class, 'update'])->name('settings.update');

});