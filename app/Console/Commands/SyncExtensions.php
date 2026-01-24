<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Extension;

class SyncExtensions extends Command
{
    protected $signature = 'extensions:sync';
    protected $description = 'Sincroniza usuarios usando Challenge -> Login -> Cookie';

    protected $ip;
    protected $user;
    protected $pass;
    protected $port;

    public function __construct()
    {
        parent::__construct();
        $this->ip = config('services.grandstream.host');
        $this->user = config('services.grandstream.user');
        $this->pass = config('services.grandstream.pass');
        $this->port = config('services.grandstream.port', '7110');
    }

    public function handle()
    {
        $this->info(" Iniciando protocolo de autenticación (Challenge/Login)...");
        $url     = 'https://'.$this->ip.':'.$this->port.'/api'; // IP API
        $usuario = $this->user;       // El usuario que creaste para la API
        $clave   = $this->pass;    // La clave de ese usuario

        try {
            // ==========================================
            // PASO 1: PEDIR CHALLENGE [METODO NUMERO 2 PARA PEDIR LOS DATOS A LA CENTRAL]
            // De todos modos recomiendo usar el metodo DIGEST AUTH del otro comando (cdrController)
            // Al ser mas simple y rapido
            // [CUIDADO CON LO DE ARRIBA, HAY VARIOS FACTORES A CONSIDERAR ANTES DE ELEGIR UN METODO
            // REVISAR DOCUMENTACION DE GRANDSTREAM, PERO EN GENERAL DIGEST AUTH FUNCIONA PARA EL CDR
            // CASI TODOS LOS DEMAS REQUIEREN ESTE METODO DE CHALLENGE/LOGIN, PARA MAS INFORMACION
            // LEER LA DOCUMENTACION DEL PROYECTO]
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
            
            // Formula: MD5( challenge + password )
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
                    'cookie'   => $cookie,  // <--- AQUI VA LA COOKIE OBTENIDA
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