<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\PbxConnection;

class GrandstreamService
{
    private ?string $host = null;
    private ?int $port = null;
    private ?string $username = null;
    private ?string $password = null;
    private bool $verifySsl = false;
    private ?string $apiCookie = null;
    private ?int $pbxConnectionId = null;
    private bool $isConfigured = false;

    public function __construct(?PbxConnection $connection = null)
    {
        if ($connection) {
            $this->setConnectionFromModel($connection);
        }
    }

    /**
     * Configurar desde un modelo PbxConnection
     */
    public function setConnectionFromModel(PbxConnection $connection): self
    {
        $this->pbxConnectionId = $connection->id;
        $this->host = $connection->ip;
        $this->port = $connection->port;
        $this->username = $connection->username;
        $this->password = $connection->password;
        $this->verifySsl = $connection->verify_ssl;
        $this->isConfigured = true;
        $this->apiCookie = null;

        return $this;
    }

    public function getPbxConnectionId(): ?int
    {
        return $this->pbxConnectionId;
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Conectar a la API Grandstream con autenticación challenge/login/cookie
     */
    public function connectApi(string $action, array $params = [], int $timeout = 30): array
    {
        if (!$this->isConfigured) {
            throw new \RuntimeException('GrandstreamService no está configurado.');
        }

        $url = "https://{$this->host}:{$this->port}/api";

        try {
            // Auto-login si no hay cookie
            if (!$this->apiCookie && !in_array($action, ['login', 'challenge'])) {
                $this->apiCookie = $this->authenticate();
                if (!$this->apiCookie) {
                    return ['status' => -99, 'response' => ['body' => 'Fallo Login']];
                }
            }

            $http = $this->verifySsl ? Http::timeout($timeout) : Http::withoutVerifying()->timeout($timeout);

            $response = $http->post($url, [
                'request' => array_merge(['action' => $action, 'cookie' => $this->apiCookie], $params)
            ])->json();

            // Re-login si cookie expiró (status -6)
            if (($response['status'] ?? 0) == -6) {
                $this->apiCookie = $this->authenticate();
                if ($this->apiCookie) {
                    return $http->post($url, [
                        'request' => array_merge(['action' => $action, 'cookie' => $this->apiCookie], $params)
                    ])->json();
                }
            }

            return $response ?? ['status' => -500, 'response' => ['body' => 'Respuesta vacía']];

        } catch (\Exception $e) {
            return ['status' => -500, 'response' => ['body' => $e->getMessage()]];
        }
    }

    /**
     * Autenticación challenge/login
     */
    private function authenticate(): ?string
    {
        $url = "https://{$this->host}:{$this->port}/api";
        $http = $this->verifySsl ? Http::timeout(10) : Http::withoutVerifying()->timeout(10);

        try {
            // 1. Challenge
            $challenge = $http->post($url, [
                'request' => ['action' => 'challenge', 'user' => $this->username, 'version' => '1.0']
            ])->json()['response']['challenge'] ?? null;

            if (!$challenge) return null;

            // 2. Login con token MD5
            return $http->post($url, [
                'request' => [
                    'action' => 'login',
                    'user' => $this->username,
                    'token' => md5($challenge . $this->password)
                ]
            ])->json()['response']['cookie'] ?? null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verificar conexión
     */
    public function testConnection(): bool
    {
        try {
            return ($this->connectApi('getSystemStatus')['status'] ?? -1) == 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
