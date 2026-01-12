<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGrandstreamUser extends Command
{
    protected $signature = 'grandstream:test-user';
    protected $description = 'Espía la configuración interna del usuario 4444';

    public function handle()
    {
        $this->info(" Iniciando operación de reconocimiento en usuario 4444...");

        // CONFIGURACIÓN (Ajustada a tu puerto 7110)
        $url     = 'https://10.36.1.10:7110/api';
        $usuario = 'cdrapi'; // usuario API que creaste
        $clave   = '123api'; 

        try {
            // 1. LOGIN (Rutina estándar)
            $respChallenge = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'challenge', 'user' => $usuario, 'version' => '1.0']
            ]);
            $challenge = $respChallenge->json()['response']['challenge'] ?? null;
            
            if (!$challenge) {
                $this->error(" Falló el Challenge."); return;
            }

            $token  = md5($challenge . $clave);
            $respLogin = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'login', 'user' => $usuario, 'token' => $token]
            ]);
            $cookie = $respLogin->json()['response']['cookie'] ?? null;

            if (!$cookie) {
                $this->error(" Falló el Login."); return;
            }
            $this->info(" Login OK.");

            // 2. EL ESPECÍFICO: Pedir datos de la cuenta 4444
            // Usamos 'getSIPAccount' que aparece en tu tabla 125
            
            $this->comment(" Descargando configuración del anexo 4444...");

            $response = Http::withoutVerifying()->post($url, [
                'request' => [
                    'action'    => 'getSIPAccount',  // <--- Función de lectura
                    'cookie'    => $cookie,
                    'extension' => '4444'            // <--- Tu usuario
                ]
            ]);

            // 3. MOSTRAR TODO (Para ver los nombres de los campos)
            if ($response->successful()) {
                $data = $response->json();
                $this->info(" Datos recibidos:");
                
                // Imprimimos el JSON bonito para leerlo
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                
            } else {
                $this->error("Error HTTP: " . $response->status());
            }

        } catch (\Exception $e) {
            $this->error("Excepción: " . $e->getMessage());
        }
    }
}