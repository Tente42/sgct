<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateUser4444 extends Command
{
    protected $signature = 'grandstream:update-4444';
    protected $description = 'Actualiza el usuario 4444 filtrando datos complejos';

    public function handle()
    {
        $this->info(" Iniciando actualización QUIRÚRGICA para el usuario 4444...");

        // --- CONFIGURACIÓN ---
        $url     = 'https://10.36.1.10:8443/cdrapi';
        $usuario = 'cdrapi'; 
        $clave   = '123api'; // <--- ¡PON TU CLAVE AQUÍ!

        try {
            // ==========================================
            // PASO 1: AUTENTICACIÓN
            // ==========================================
            $this->comment("1. Autenticando...");
            
            $respChallenge = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'challenge', 'user' => $usuario, 'version' => '1.0']
            ]);
            $challenge = $respChallenge->json()['response']['challenge'] ?? null;
            
            if (!$challenge) { $this->error(" Error Challenge."); return; }

            $token  = md5($challenge . $clave);
            $respLogin = Http::withoutVerifying()->post($url, [
                'request' => ['action' => 'login', 'user' => $usuario, 'token' => $token]
            ]);
            $cookie = $respLogin->json()['response']['cookie'] ?? null;

            if (!$cookie) { $this->error(" Error Login."); return; }
            $this->info("    Login correcto.");

            // ==========================================
            // PASO 2: LEER CONFIGURACIÓN (GET)
            // ==========================================
            $this->comment("2. Leyendo datos actuales...");

            $respGet = Http::withoutVerifying()->post($url, [
                'request' => [
                    'action'    => 'getSIPAccount',
                    'cookie'    => $cookie,
                    'extension' => '4444'
                ]
            ]);

            $currentData = $respGet->json()['response']['extension'] ?? null;

            if (!$currentData) {
                $this->error(" Error al leer datos.");
                return;
            }

            // ==========================================
            // PASO 3: LIMPIEZA Y UPDATE (La Magia)
            // ==========================================
            $nuevoNombre = 'PRUEBA FINAL API';
            $this->comment("3. Limpiando arrays y estableciendo nombre: '{$nuevoNombre}'...");

            // FILTRO DE SEGURIDAD:
            // La documentación dice que 'sip_presence_settings' es un array complejo.
            // Si lo enviamos de vuelta mal formado, falla. Aquí lo borramos.
            $datosLimpios = array_filter($currentData, function($value) {
                return !is_array($value); // Solo dejamos textos y números
            });

            // Sobrescribimos el nombre
            $datosLimpios['fullname'] = $nuevoNombre;

            // Aseguramos que 'extension' venga explícita (a veces se pierde al filtrar si venía null)
            $datosLimpios['extension'] = '4444';

            // Armamos la petición final
            $payload = [
                'request' => array_merge(
                    [
                        'action' => 'updateSIPAccount',
                        'cookie' => $cookie
                    ],
                    $datosLimpios
                )
            ];

            $respUpdate = Http::withoutVerifying()->post($url, $payload);
            $dataUpdate = $respUpdate->json();

            // ==========================================
            // PASO 4: RESULTADO
            // ==========================================
            // La documentación dice que status 0 es éxito
            if ($respUpdate->successful() && ($dataUpdate['status'] ?? -1) == 0) {
                $this->newLine();
                $this->info(" ¡VICTORIA! La central aceptó los datos.");
                $this->info("   Usuario 4444 actualizado correctamente.");
            } else {
                $this->error(" La central rechazó el update.");
                $this->line("Status: " . ($dataUpdate['status'] ?? 'N/A'));
                $this->line("Respuesta: " . $respUpdate->body());
                
                // Si falla, sugerimos el plan B local
                $this->warn(" Si esto falló, usa el Plan B (Base de Datos Local).");
            }

        } catch (\Exception $e) {
            $this->error(" Excepción: " . $e->getMessage());
        }
    }
}