<?php

namespace App\Console\Commands\Concerns;

use App\Models\PbxConnection;
use App\Traits\GrandstreamTrait;

/**
 * Trait para comandos que necesitan configurar una central PBX
 */
trait ConfiguresPbx
{
    use GrandstreamTrait;

    /**
     * Configurar la central PBX desde las opciones del comando
     * 
     * @return int|null Retorna el ID de la central o null si falla
     */
    protected function setupPbxConnection(): ?int
    {
        $pbxId = $this->option('pbx');

        if ($pbxId) {
            if (!$this->configurePbx((int)$pbxId)) {
                $this->error(" Central PBX con ID {$pbxId} no encontrada.");
                return null;
            }
            $pbx = PbxConnection::find($pbxId);
            $this->info(" Usando central: {$pbx->name} ({$pbx->ip})");
            return (int)$pbxId;
        }

        if (!$this->isPbxConfigured()) {
            $this->showAvailablePbxConnections();
            return null;
        }

        return $this->getActivePbxId();
    }

    /**
     * Mostrar las centrales disponibles cuando no se especifica una
     */
    protected function showAvailablePbxConnections(): void
    {
        $connections = PbxConnection::all();

        if ($connections->isEmpty()) {
            $this->error(" No hay centrales configuradas. Crea una desde la web.");
            return;
        }

        $this->warn(" No se especificó central. Centrales disponibles:");
        $this->table(
            ['ID', 'Nombre', 'IP'],
            $connections->map(fn($c) => [$c->id, $c->name, $c->ip])->toArray()
        );
        $this->info(" Uso: php artisan {$this->getName()} --pbx=1");
    }

    /**
     * Verificar conexión y mostrar error si falla
     */
    protected function verifyConnection(): bool
    {
        if (!$this->testConnection()) {
            $this->error(" Error de Login. Verifica configuración de la central.");
            return false;
        }
        return true;
    }
}
