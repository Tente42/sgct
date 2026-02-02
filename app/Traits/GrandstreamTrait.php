<?php

namespace App\Traits;

use App\Services\GrandstreamService;

/**
 * Trait para acceder al GrandstreamService de forma conveniente
 * 
 * Este trait actúa como wrapper del GrandstreamService inyectado,
 * permitiendo usar los métodos de la API desde cualquier controlador.
 * 
 * La configuración de la central se obtiene automáticamente de la sesión
 * a través del binding en AppServiceProvider.
 */
trait GrandstreamTrait
{
    /**
     * Instancia cacheada del servicio
     */
    private ?GrandstreamService $grandstreamService = null;

    /**
     * Obtener la instancia del GrandstreamService
     * 
     * @return GrandstreamService
     */
    protected function getGrandstreamService(): GrandstreamService
    {
        if (!$this->grandstreamService) {
            $this->grandstreamService = app(GrandstreamService::class);
        }

        return $this->grandstreamService;
    }

    /**
     * Método centralizado para conectar a la API Grandstream
     * Usa autenticación challenge/login/cookie en puerto del Grandstream
     * 
     * IMPORTANTE para cdrapi con fechas:
     * - Formato de fecha: YYYY-MM-DDTHH:MM o YYYY-MM-DDTHH:MM:SS
     * - La 'T' es obligatoria como separador
     * 
     * @param string $action Acción de la API (ej: 'cdrapi', 'listAccount', 'updateUser')
     * @param array $params Parámetros adicionales para la acción
     * @param int $timeout Timeout en segundos (default 30, usar más para CDRs)
     * @return array Respuesta JSON de la API
     */
    protected function connectApi(string $action, array $params = [], int $timeout = 30): array
    {
        return $this->getGrandstreamService()->connectApi($action, $params, $timeout);
    }

    /**
     * Verificar conexión a la central
     * 
     * @return bool True si la conexión es exitosa
     */
    protected function testConnection(): bool
    {
        return $this->getGrandstreamService()->testConnection();
    }

    /**
     * Verificar si el servicio está configurado con una central
     * 
     * @return bool
     */
    protected function isPbxConfigured(): bool
    {
        return $this->getGrandstreamService()->isConfigured();
    }

    /**
     * Obtener el ID de la conexión PBX activa
     * 
     * @return int|null
     */
    protected function getActivePbxId(): ?int
    {
        return $this->getGrandstreamService()->getPbxConnectionId();
    }

    /**
     * Configurar el servicio con una central específica (útil para comandos de consola)
     * 
     * @param int $pbxId ID de la central PBX
     * @return bool True si se configuró correctamente
     */
    protected function configurePbx(int $pbxId): bool
    {
        $connection = \App\Models\PbxConnection::find($pbxId);
        
        if (!$connection) {
            return false;
        }

        $this->grandstreamService = null; // Reset cached instance
        $service = $this->getGrandstreamService();
        $service->setConnectionFromModel($connection);

        return true;
    }
}