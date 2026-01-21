<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;

trait GrandstreamTrait
{
    // Métodos centralizados de conexión

    private function connectApi($action, $params = [], $cookie = null)
    {
        // Usamos config() para leer de config/services.php
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $url = "https://{$ip}:{$port}/api";

        try {
            // Auto-login si no hay cookie
            if (!$cookie && $action !== 'login' && $action !== 'challenge') {
                $user = config('services.grandstream.user');
                $pass = config('services.grandstream.pass');
                $cookie = $this->getCookie($url, $user, $pass);
                
                if (!$cookie) return ['status' => -99, 'response' => ['body' => 'Fallo Login Automático']];
            }

            return Http::withoutVerifying()
                ->timeout(5)
                ->post($url, [
                    'request' => array_merge(['action' => $action, 'cookie' => $cookie], $params)
                ])->json();

        } catch (\Exception $e) {
            return ['status' => -500, 'response' => ['body' => $e->getMessage()]];
        }
    }

    private function getCookie($url, $user, $pass)
    {
        try {
            $ch = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'challenge', 'user' => $user, 'version' => '1.0']
            ])->json();
            
            $challenge = $ch['response']['challenge'] ?? '';
            if (!$challenge) return null;

            $token = md5($challenge . $pass);
            $login = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'login', 'user' => $user, 'token' => $token]
            ])->json();

            return $login['response']['cookie'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}