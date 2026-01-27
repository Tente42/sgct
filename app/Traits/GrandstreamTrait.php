<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;

trait GrandstreamTrait
{
    /**
     * Cookie de sesión para reutilizar en múltiples llamadas
     */
    private ?string $apiCookie = null;

    /**
     * Método centralizado para conectar a la API Grandstream
     * Usa autenticación challenge/login/cookie en puerto 7110
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
    private function connectApi(string $action, array $params = [], int $timeout = 30): array
    {
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port');
        $url = "https://{$ip}:{$port}/api";

        try {
            // Auto-login si no hay cookie en cache
            if (!$this->apiCookie && $action !== 'login' && $action !== 'challenge') {
                $this->apiCookie = $this->getApiCookie();
                
                if (!$this->apiCookie) {
                    return ['status' => -99, 'response' => ['body' => 'Fallo Login Automático']];
                }
            }

            $response = Http::withoutVerifying()
                ->timeout($timeout)
                ->post($url, [
                    'request' => array_merge(
                        ['action' => $action, 'cookie' => $this->apiCookie], 
                        $params
                    )
                ])->json();

            // Si la cookie expiró (status -6), intentar re-login una vez
            if (($response['status'] ?? 0) == -6) {
                $this->apiCookie = $this->getApiCookie();
                if ($this->apiCookie) {
                    return Http::withoutVerifying()
                        ->timeout($timeout)
                        ->post($url, [
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
     * Sigue el flujo oficial de la documentación Grandstream:
     * 1. Challenge: Obtener string aleatorio
     * 2. Token: MD5(challenge + password)
     * 3. Login: Enviar token y obtener cookie
     * 
     * @return string|null Cookie de sesión o null si falla
     */
    private function getApiCookie(): ?string
    {
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port');
        $user = config('services.grandstream.user');
        $pass = config('services.grandstream.pass');
        $url = "https://{$ip}:{$port}/api";

        try {
            // 1. Challenge
            $challengeResponse = Http::withoutVerifying()
                ->timeout(10)
                ->post($url, [
                    'request' => ['action' => 'challenge', 'user' => $user, 'version' => '1.0']
                ])->json();
            
            $challenge = $challengeResponse['response']['challenge'] ?? '';
            if (!$challenge) {
                return null;
            }

            // 2. Token = MD5(challenge + password)
            $token = md5($challenge . $pass);

            // 3. Login
            $loginResponse = Http::withoutVerifying()
                ->timeout(10)
                ->post($url, [
                    'request' => ['action' => 'login', 'user' => $user, 'token' => $token]
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
    private function testConnection(): bool
    {
        $result = $this->connectApi('getSystemStatus');
        return ($result['status'] ?? -1) == 0;
    }
}