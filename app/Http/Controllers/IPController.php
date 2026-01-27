<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;
use Illuminate\Support\Facades\Http;
use App\Traits\GrandstreamTrait;

class IPController extends Controller
{
    use GrandstreamTrait;

    public function index()
    {
        // 1. Datos locales
        $localExtensions = Extension::with('department')->get()->keyBy('extension');

        $monitorList = [];

        // 2. Verificar conexión y obtener datos en vivo
        if ($this->testConnection()) {
            // 3. Datos en vivo (listAccount)
            $liveData = $this->connectApi('listAccount', [
                'options'  => 'extension,status,addr,fullname',
                'item_num' => 1000,
                'sidx'     => 'extension',
                'sord'     => 'asc'
            ]);
            
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
        $ips = [];

        if ($this->testConnection()) {
            $liveData = $this->connectApi('listAccount', [
                'options'  => 'extension,addr',
                'item_num' => 1000,
                'sidx'     => 'extension',
                'sord'     => 'asc'
            ]);

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
}