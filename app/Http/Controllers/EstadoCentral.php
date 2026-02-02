<?php

namespace App\Http\Controllers;

use App\Traits\GrandstreamTrait;

class EstadoCentral extends Controller
{
    use GrandstreamTrait;

    public function index()
    {
        return view('welcome', ['systemData' => $this->getSystemData()]);
    }

    /**
     * Obtener datos del sistema (uptime)
     */
    public function getSystemData(): array
    {
        if (!$this->isPbxConfigured()) {
            return ['uptime' => 'Sin conexión'];
        }

        $response = $this->connectApi('getSystemStatus');
        
        return [
            'uptime' => ($response['status'] ?? -1) == 0 
                ? ($response['response']['up-time'] ?? 'N/A')
                : 'N/A'
        ];
    }
}
