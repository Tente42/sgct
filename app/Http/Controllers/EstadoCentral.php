<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\GrandstreamTrait; // <--- Importamos el Trait

class EstadoCentral extends Controller
{
    use GrandstreamTrait; // <--- Activamos los superpoderes aquí

    public function index()
    {
        // 1. Obtenemos el estado del sistema
        // Nota: Al usar el Trait, ya no necesitas pasar URL ni login, él sabe hacerlo solo.
        $response = $this->connectApi('getSystemStatus');

        $systemData = [
            'cpu' => 0,
            'memory' => 0,
            'disk' => 0,
            'uptime' => 'Desconocido'
        ];

        if (($response['status'] ?? -1) == 0) {
            $data = $response['response'];
            
            // Mapeamos los datos (La API a veces devuelve strings con %)
            $systemData['cpu']    = intval($data['cpu_usage'] ?? 0);
            $systemData['memory'] = intval($data['memory_usage'] ?? 0);
            $systemData['disk']   = intval($data['disk_usage'] ?? 0);
            $systemData['uptime'] = $data['up_time'] ?? 'N/A';
        }

        // 2. Retornamos la vista (puedes crear una 'home' o 'dashboard')
        return view('welcome', compact('systemData'));
    }
}