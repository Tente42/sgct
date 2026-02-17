<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CdrController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PbxConnectionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StatsController;

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
    Route::get('/select/{pbx}', [PbxConnectionController::class, 'select'])->name('select');
    Route::get('/sync-status/{pbx}', [PbxConnectionController::class, 'checkSyncStatus'])->name('syncStatus');
    
    // Solo administradores pueden crear, modificar, eliminar y sincronizar centrales
    Route::middleware(['admin'])->group(function () {
        Route::post('/', [PbxConnectionController::class, 'store'])->name('store');
        Route::put('/{pbx}', [PbxConnectionController::class, 'update'])->name('update');
        Route::delete('/{pbx}', [PbxConnectionController::class, 'destroy'])->name('destroy');
        Route::get('/setup/{pbx}', [PbxConnectionController::class, 'setup'])->name('setup');
        
        // Endpoints AJAX para sincronización sin colas
        Route::post('/sync-extensions/{pbx}', [PbxConnectionController::class, 'syncExtensions'])->name('syncExtensions');
        Route::post('/sync-calls/{pbx}', [PbxConnectionController::class, 'syncCalls'])->name('syncCalls');
        Route::post('/finish-sync/{pbx}', [PbxConnectionController::class, 'finishSync'])->name('finishSync');
        
        Route::post('/disconnect', [PbxConnectionController::class, 'disconnect'])->name('disconnect');
    });
});


// ==========================================
// 3. RUTAS DE GESTIÓN DE USUARIOS (Solo Admin)
// ==========================================
Route::middleware(['auth', 'admin'])->prefix('usuarios')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/crear', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
});

// API endpoints para gestión de usuarios (modal en PBX index)
Route::middleware(['auth', 'admin'])->prefix('api/usuarios')->name('users.api.')->group(function () {
    Route::get('/', [UserController::class, 'apiIndex'])->name('index');
    Route::post('/', [UserController::class, 'apiStore'])->name('store');
    Route::put('/{user}', [UserController::class, 'apiUpdate'])->name('update');
    Route::delete('/{user}', [UserController::class, 'apiDestroy'])->name('destroy');
});


// ==========================================
// 3. RUTAS PRIVADAS (Requieren auth + central seleccionada)
// ==========================================
Route::middleware(['auth', 'pbx.selected'])->group(function () {
    
    // Panel Principal (Todos los usuarios)
    Route::get('/', [CdrController::class, 'index'])->name('home');
    Route::get('/dashboard', [CdrController::class, 'index'])->name('dashboard');
    Route::get('/graficos', [CdrController::class, 'showCharts'])->name('cdr.charts');
    Route::get('/configuracion', [ExtensionController::class, 'index'])->name('extension.index');

    // Funciones de solo lectura (Todos los usuarios)
    Route::get('/export-pdf', [CdrController::class, 'descargarPDF'])->name('cdr.pdf');
    Route::get('/exportar-excel', [CdrController::class, 'exportarExcel'])->name('calls.export');
    Route::get('/tarifas', [SettingController::class, 'index'])->name('settings.index');

    // Funciones que requieren permisos específicos (verificados en el controlador)
    Route::post('/sync', [CdrController::class, 'syncCDRs'])->name('cdr.sync');
    Route::post('/extension/update', [ExtensionController::class, 'update'])->name('extension.update');
    Route::post('/extension/sync', [ExtensionController::class, 'syncExtensions'])->name('extension.sync');
    Route::get('/extension/sync-status', [ExtensionController::class, 'checkSyncStatus'])->name('extension.syncStatus');
    Route::post('/extension/update-ips', [ExtensionController::class, 'updateIps'])->name('extension.updateIps');
    Route::post('/tarifas', [SettingController::class, 'update'])->name('settings.update');

    // Desvíos de llamadas (Call Forwarding)
    Route::get('/extension/forwarding', [ExtensionController::class, 'getCallForwarding'])->name('extension.forwarding.get');
    Route::post('/extension/forwarding', [ExtensionController::class, 'updateCallForwarding'])->name('extension.forwarding.update');

    // Estadísticas y KPIs
    Route::get('/stats/kpi-turnos', [StatsController::class, 'index'])->name('stats.kpi-turnos');
    Route::get('/stats/kpi-turnos/api', [StatsController::class, 'apiKpis'])->name('stats.kpi-turnos.api');
    Route::post('/stats/kpi-turnos/sync', [StatsController::class, 'sincronizarColas'])->name('stats.sync-colas');

});