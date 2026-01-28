<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\PbxConnection;

class GrandstreamService
{
    /**
     * Configuración de conexión
     */
    private ?string $host = null;
    private ?int $port = null;
    private ?string $username = null;
    private ?string $password = null;
    private bool $verifySsl = false;

    /**
     * Cookie de sesión para reutilizar en múltiples llamadas
     */
    private ?string $apiCookie = null;

    /**
     * ID de la conexión PBX activa
     */
    private ?int $pbxConnectionId = null;

    /**
     * Indica si el servicio está configurado
     */
    private bool $isConfigured = false;

    /**
     * Constructor - puede recibir configuración o quedar vacío
     */
    public function __construct(?PbxConnection $connection = null)
    {
        if ($connection) {
            $this->setConnectionFromModel($connection);
        }
    }

    /**
     * Configurar el servicio desde un modelo PbxConnection
     */
    public function setConnectionFromModel(PbxConnection $connection): self
    {
        $this->pbxConnectionId = $connection->id;
        $this->host = $connection->ip;
        $this->port = $connection->port;
        $this->username = $connection->username;
        $this->password = $connection->password; // Se desencripta automáticamente por el cast
        $this->verifySsl = $connection->verify_ssl;
        $this->isConfigured = true;
        $this->apiCookie = null; // Reset cookie al cambiar configuración

        return $this;
    }

    /**
     * Configurar el servicio manualmente
     */
    public function setConfig(string $host, int $port, string $username, string $password, bool $verifySsl = false): self
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->verifySsl = $verifySsl;
        $this->isConfigured = true;
        $this->apiCookie = null;

        return $this;
    }

    /**
     * Obtener el ID de la conexión PBX activa
     */
    public function getPbxConnectionId(): ?int
    {
        return $this->pbxConnectionId;
    }

    /**
     * Verificar si el servicio está configurado
     */
    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Verificar configuración antes de usar la API
     * 
     * @throws \RuntimeException Si el servicio no está configurado
     */
    private function ensureConfigured(): void
    {
        if (!$this->isConfigured) {
            throw new \RuntimeException(
                'GrandstreamService no está configurado. Selecciona una central PBX primero.'
            );
        }
    }

    /**
     * Construir la URL base de la API
     */
    private function getApiUrl(): string
    {
        return "https://{$this->host}:{$this->port}/api";
    }

    /**
     * Método centralizado para conectar a la API Grandstream
     * Usa autenticación challenge/login/cookie
     * 
     * @param string $action Acción de la API (ej: 'cdrapi', 'listAccount', 'updateUser')
     * @param array $params Parámetros adicionales para la acción
     * @param int $timeout Timeout en segundos (default 30)
     * @return array Respuesta JSON de la API
     */
    public function connectApi(string $action, array $params = [], int $timeout = 30): array
    {
        $this->ensureConfigured();

        $url = $this->getApiUrl();

        try {
            // Auto-login si no hay cookie en cache
            if (!$this->apiCookie && $action !== 'login' && $action !== 'challenge') {
                $this->apiCookie = $this->getApiCookie();
                
                if (!$this->apiCookie) {
                    return ['status' => -99, 'response' => ['body' => 'Fallo Login Automático']];
                }
            }

            $httpClient = $this->verifySsl ? Http::timeout($timeout) : Http::withoutVerifying()->timeout($timeout);

            $response = $httpClient->post($url, [
                'request' => array_merge(
                    ['action' => $action, 'cookie' => $this->apiCookie], 
                    $params
                )
            ])->json();

            // Si la cookie expiró (status -6), intentar re-login una vez
            if (($response['status'] ?? 0) == -6) {
                $this->apiCookie = $this->getApiCookie();
                if ($this->apiCookie) {
                    return $httpClient->post($url, [
                        'request' => array_merge(
                            ['action' => $action, 'cookie' => $this->apiCookie], 
                            $params
                        )
                    ])->json();
                }
            }

            return $response ?? ['status' => -500, 'response' => ['body' => 'Respuesta vacía']];

        } catch (\Exception $e) {
            return ['status' => -500, 'response' => ['body' => $e->getMessage()]];
        }
    }

    /**
     * Obtener cookie de sesión mediante challenge/login
     * 
     * @return string|null Cookie de sesión o null si falla
     */
    private function getApiCookie(): ?string
    {
        $url = $this->getApiUrl();
        $httpClient = $this->verifySsl ? Http::timeout(10) : Http::withoutVerifying()->timeout(10);

        try {
            // 1. Challenge
            $challengeResponse = $httpClient->post($url, [
                'request' => ['action' => 'challenge', 'user' => $this->username, 'version' => '1.0']
            ])->json();
            
            $challenge = $challengeResponse['response']['challenge'] ?? '';
            if (!$challenge) {
                return null;
            }

            // 2. Token = MD5(challenge + password)
            $token = md5($challenge . $this->password);

            // 3. Login
            $loginResponse = $httpClient->post($url, [
                'request' => ['action' => 'login', 'user' => $this->username, 'token' => $token]
            ])->json();

            return $loginResponse['response']['cookie'] ?? null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verificar conexión a la central
     * 
     * @return bool True si la conexión es exitosa
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->connectApi('getSystemStatus');
            return ($result['status'] ?? -1) == 0;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Obtener información del sistema
     */
    public function getSystemStatus(): array
    {
        return $this->connectApi('getSystemStatus');
    }

    /**
     * Obtener lista de cuentas/extensiones
     */
    public function listAccounts(): array
    {
        return $this->connectApi('listAccount');
    }

    /**
     * Obtener CDRs (registros de llamadas)
     * 
     * @param string $startTime Formato: YYYY-MM-DDTHH:MM:SS
     * @param string $endTime Formato: YYYY-MM-DDTHH:MM:SS
     * @param array $options Opciones adicionales (numRecords, caller, callee, etc.)
     */
    public function getCDRs(string $startTime, string $endTime, array $options = []): array
    {
        $params = array_merge([
            'startTime' => $startTime,
            'endTime' => $endTime,
        ], $options);

        return $this->connectApi('cdrapi', $params, 120); // Timeout mayor para CDRs
    }

    /**
     * Obtener información de una extensión específica
     */
    public function getExtension(string $extension): array
    {
        return $this->connectApi('getUser', ['extension' => $extension]);
    }

    /**
     * Actualizar una extensión
     */
    public function updateExtension(string $extension, array $data): array
    {
        return $this->connectApi('updateUser', array_merge(['extension' => $extension], $data));
    }
}
