<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Extension;

class SyncExtensions extends Command
{
    protected $signature = 'extensions:sync';
    protected $description = 'Sincroniza usuarios usando Challenge -> Login -> Cookie';

    public function handle()
    {
        $this->info(" Iniciando protocolo de autenticación (Challenge/Login)...");


        // ESTO POR AHORA QUEDA ASÍ, PERO NO SE ESTA USANDO ESTA PARTE DEL CÓDIGO
        // esto es basicamente pedir la lista de extensiones a la central telefónica
        // pero no las esta tirando correctamente, por eso por ahora no se usa
        // CONFIGURACIÓN
        $url     = 'https://10.36.1.10:7110/api'; // IP API
        $usuario = 'cdrapi';       // El usuario que creaste para la API
        $clave   = '123api';    // La clave de ese usuario

        try {
            // ==========================================
            // PASO 1: PEDIR CHALLENGE [METODO NUMERO 2 PARA PEDIR LOS DATOS A LA CENTRAL]
            // De todos modos recomiendo usar el metodo DIGEST AUTH del otro comando (cdrController)
            // Al ser mas simple y rapido
            // ==========================================
            $this->comment("1. Solicitando Challenge...");
            $respChallenge = Http::withoutVerifying()->post($url, [
                'request' => [
                    'action'  => 'challenge',
                    'user'    => $usuario,
                    'version' => '1.0'
                ]
            ]);

            $challenge = $respChallenge->json()['response']['challenge'] ?? null;

            if (!$challenge) {
                $this->error(" No se recibió challenge. Revisa usuario/puerto.");
                return;
            }

            // ==========================================
            // PASO 2: CALCULAR MD5 Y HACER LOGIN
            // ==========================================
            $this->comment("2. Calculando Token y obteniendo Cookie...");
            
            // Fórmula: MD5( challenge + password )
            $token = md5($challenge . $clave);

            $respLogin = Http::withoutVerifying()->post($url, [
                'request' => [
                    'action' => 'login',
                    'user'   => $usuario,
                    'token'  => $token
                ]
            ]);

            $cookie = $respLogin->json()['response']['cookie'] ?? null;

            if (!$cookie) {
                $this->error(" Login fallido. Revisa tu contraseña.");
                $this->line("Respuesta: " . $respLogin->body());
                return;
            }

            $this->info("    Login Éxitoso! Cookie: {$cookie}");

            // ==========================================
            // PASO 3: PEDIR LOS USUARIOS (Usando la Cookie)
            // ==========================================
            $this->comment("3. Descargando lista de cuentas...");

            $respData = Http::withoutVerifying()->post($url, [
                'request' => [
                    'action'   => 'listAccount',
                    'cookie'   => $cookie,  // <--- AQUÍ VA LA LLAVE MAESTRA
                    'item_num' => '1000',
                    'sidx'     => 'extension',
                    'sord'     => 'asc',
                    'page'     => 1
                ]
            ]);

            if ($respData->successful()) {
                $data = $respData->json();
                
                // Buscamos en 'account' o 'user'
                $cuentas = $data['response']['account'] ?? $data['response']['user'] ?? [];
                $count = count($cuentas);

                if ($count > 0) {
                    $this->info("    ¡Encontrados {$count} usuarios!");
                    $bar = $this->output->createProgressBar($count);
                    $bar->start();

                    foreach ($cuentas as $cuenta) {
                        Extension::updateOrCreate(
                            ['extension' => $cuenta['extension']], 
                            [
                                'fullname' => $cuenta['fullname'] ?? $cuenta['members_name'] ?? 'Sin Nombre',
                                'email'    => $cuenta['email'] ?? null,
                            ]
                        );
                        $bar->advance();
                    }
                    $bar->finish();
                    $this->newLine();
                    $this->info(" Base de datos actualizada correctamente.");
                } else {
                    $this->warn(" Login OK, pero la lista está vacía. Verifica permisos del usuario.");
                    $this->line(json_encode($data, JSON_PRETTY_PRINT));
                }

            } else {
                $this->error(" Error HTTP en paso final: " . $respData->status());
            }

        } catch (\Exception $e) {
            $this->error(" Excepción: " . $e->getMessage());
        }
    }
}