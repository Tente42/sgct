<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Models\PbxConnection;

class SyncPbxDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número de segundos que el job puede ejecutarse antes de timeout
     */
    public $timeout = 3600; // 1 hora

    /**
     * Número de intentos
     */
    public $tries = 1;

    protected int $pbxId;
    protected bool $syncExtensions;
    protected bool $syncCalls;
    protected int $year;
    protected string $userName;

    /**
     * Create a new job instance.
     */
    public function __construct(int $pbxId, bool $syncExtensions, bool $syncCalls, int $year, string $userName = 'Sistema')
    {
        $this->pbxId = $pbxId;
        $this->syncExtensions = $syncExtensions;
        $this->syncCalls = $syncCalls;
        $this->year = $year;
        $this->userName = $userName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lockKey = "pbx_sync_lock_{$this->pbxId}";
        $progressKey = "pbx_sync_progress_{$this->pbxId}";

        try {
            // Actualizar progreso
            Cache::put($lockKey, [
                'user' => $this->userName,
                'started_at' => now()->toDateTimeString(),
                'type' => 'background'
            ], 3600);

            $pbx = PbxConnection::find($this->pbxId);
            $pbxName = $pbx ? $pbx->name : "PBX #{$this->pbxId}";

            // Sincronizar extensiones (modo rápido por defecto)
            if ($this->syncExtensions) {
                Cache::put($progressKey, "Sincronizando extensiones de {$pbxName}...", 3600);
                
                Artisan::call('extensions:import', [
                    '--pbx' => $this->pbxId,
                    '--quick' => true  // Modo rápido para evitar timeout
                ]);
                
                Cache::put($progressKey, "✓ Extensiones completadas. Preparando llamadas...", 3600);
            }

            // Sincronizar llamadas
            if ($this->syncCalls) {
                Cache::put($progressKey, "Sincronizando llamadas desde {$this->year}... (esto puede tardar varios minutos)", 3600);
                
                Artisan::call('calls:sync', [
                    '--pbx' => $this->pbxId,
                    '--year' => $this->year
                ]);
                
                Cache::put($progressKey, "✓ Sincronización completada!", 3600);
            }

            // Esperar un momento para que el usuario vea el mensaje de completado
            sleep(3);

        } catch (\Exception $e) {
            Cache::put($progressKey, " Error: " . $e->getMessage(), 300);
            sleep(5);
        } finally {
            // Limpiar locks
            Cache::forget($lockKey);
            Cache::forget($progressKey);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        $lockKey = "pbx_sync_lock_{$this->pbxId}";
        $progressKey = "pbx_sync_progress_{$this->pbxId}";
        
        Cache::put($progressKey, " Error en sincronización: " . ($exception?->getMessage() ?? 'Error desconocido'), 300);
        
        sleep(5);
        
        Cache::forget($lockKey);
        Cache::forget($progressKey);
    }
}
