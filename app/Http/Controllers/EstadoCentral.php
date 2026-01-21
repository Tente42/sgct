<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EstadoCentral extends Controller
{
    public function index()
    {
        // Obtenemos solo el uptime del sistema
        $response = $this->connectApi('getSystemStatus');

        $systemData = [
            'uptime' => 'Desconocido'
        ];

        if (($response['status'] ?? -1) == 0) {
            $data = $response['response'];
            $systemData['uptime'] = $data['up-time'] ?? 'N/A';
        }

        return view('welcome', compact('systemData'));
    }

    /**
     * Obtiene solo el uptime para usar en otros controladores
     */
    public function getSystemData()
    {
        $response = $this->connectApi('getSystemStatus');

        $systemData = [
            'uptime' => 'N/A'
        ];

        if (($response['status'] ?? -1) == 0) {
            $data = $response['response'];
            $systemData['uptime'] = $data['up-time'] ?? 'N/A';
        }

        return $systemData;
    }

    /**
     * Conecta a la API de Grandstream
     */
    private function connectApi($action, $params = [])
    {
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $url = "https://{$ip}:{$port}/api";

        try {
            // Auto-login para obtener cookie
            $user = config('services.grandstream.user');
            $pass = config('services.grandstream.pass');
            $cookie = $this->getCookie($url, $user, $pass);

            if (!$cookie) {
                return ['status' => -99, 'response' => ['body' => 'Fallo Login Automático']];
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

    /**
     * Obtiene la cookie de autenticación de la API
     */
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