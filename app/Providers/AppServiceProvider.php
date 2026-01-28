<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GrandstreamService;
use App\Models\PbxConnection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Binding dinámico del GrandstreamService
        // Se configura con la central activa de la sesión
        $this->app->bind(GrandstreamService::class, function ($app) {
            $service = new GrandstreamService();

            // Obtener el ID de la central activa desde la sesión
            $activePbxId = session('active_pbx_id');

            if ($activePbxId) {
                $connection = PbxConnection::find($activePbxId);
                
                if ($connection) {
                    // Configurar el servicio con los datos de la central
                    // El password se desencripta automáticamente por el cast del modelo
                    $service->setConnectionFromModel($connection);
                }
            }

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
