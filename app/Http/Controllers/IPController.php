<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;
use Illuminate\Support\Facades\Http;

class IPController extends Controller
{
    public function index()
    {
        // 1. Conexión a la Central Grandstream
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $user = config('services.grandstream.user');
        $pass = config('services.grandstream.pass');
        $apiUrl = "https://{$ip}:{$port}/api";
        
        $cookie = $this->getCookie($apiUrl, $user, $pass);

        // 2. Datos locales
        $localExtensions = Extension::with('department')->get()->keyBy('extension');

        $monitorList = [];

        if ($cookie) {
            // 3. Datos en vivo (listAccount)
            $liveData = $this->connectApi($apiUrl, 'listAccount', [
                'options'  => 'extension,status,addr,fullname',
                'item_num' => 1000,
                'sidx'     => 'extension',
                'sord'     => 'asc'
            ], $cookie);
            
            // Ajuste para leer 'account' o 'body->account' según versión de firmware
            $rawAccounts = $liveData['response']['account'] ?? 
                           $liveData['response']['body']['account'] ?? [];

            foreach ($rawAccounts as $account) {
                $extNum = $account['extension'];
                $local = $localExtensions->get($extNum);

                // Si el campo addr existe y no es un guion, lo usamos tal cual.
                $fullAddress = '---';
                
                if (!empty($account['addr']) && $account['addr'] !== '-') {
                    $fullAddress = $account['addr']; 
                }

                $status = $account['status'];

                $monitorList[] = [
                    'extension'  => $extNum,
                    'name'       => $local ? $local->fullname : ($account['fullname'] ?? 'Desconocido'),
                    'department' => $local ? ($local->department->name ?? '-') : '-',
                    'ip'         => $fullAddress,
                    'status'     => $status,
                ];
            }
        }

        return view('monitor.index', compact('monitorList'));
    }

    /**
     * Obtener las IPs de las extensiones en tiempo real
     * Retorna un array asociativo [extension => ip]
     */
    public function getExtensionIps()
    {
        $ip = config('services.grandstream.host');
        $port = config('services.grandstream.port', '7110');
        $user = config('services.grandstream.user');
        $pass = config('services.grandstream.pass');
        $apiUrl = "https://{$ip}:{$port}/api";
        
        $cookie = $this->getCookie($apiUrl, $user, $pass);
        
        $ips = [];

        if ($cookie) {
            $liveData = $this->connectApi($apiUrl, 'listAccount', [
                'options'  => 'extension,addr',
                'item_num' => 1000,
                'sidx'     => 'extension',
                'sord'     => 'asc'
            ], $cookie);

            $rawAccounts = $liveData['response']['account'] ?? 
                           $liveData['response']['body']['account'] ?? [];

            foreach ($rawAccounts as $account) {
                $ext = $account['extension'] ?? null;
                $addr = $account['addr'] ?? null;
                
                if ($ext && $addr && $addr !== '-') {
                    $ips[$ext] = $addr;
                }
            }
        }

        return $ips;
    }

    // ==========================================
    //  MÉTODOS PRIVADOS DE CONEXIÓN 
    // ==========================================

    private function connectApi($url, $action, $params = [], $cookie = null)
    {
        try {
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
            // 1. Challenge
            $ch = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'challenge', 'user' => $user, 'version' => '1.0']
            ])->json();
            
            $challenge = $ch['response']['challenge'] ?? '';
            if (!$challenge) return null;

            // 2. Login
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