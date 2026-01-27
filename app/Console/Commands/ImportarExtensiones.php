<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Extension;

class ImportarExtensiones extends Command
{
    protected $signature = 'extensions:import {target? : La extensión específica a sincronizar}';
    protected $description = 'Sincroniza usuarios de forma inteligente (Solo guarda si hay cambios).';

    protected $ip;
    protected $user;
    protected $pass;
    protected $apiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->ip = config('services.grandstream.host');
        $this->user = config('services.grandstream.user');
        $this->pass = config('services.grandstream.pass');
        $port = config('services.grandstream.port', '7110');
        $this->apiUrl = "https://{$this->ip}:{$port}/api";
    }

    public function handle()
    {
        $target = $this->argument('target');
        $modo = $target ? " ($target)" : "MASIVO (Todos los usuarios)";

        $this->info("============================================");
        $this->info("  SINCRONIZADOR - MODO: $modo");
        $this->info("============================================");

        $cookie = $this->hacerLogin();
        if (!$cookie) {
            $this->error(" Error de Login.");
            return;
        }

        // --- OBTENCION DE DATOS ---
        $listaUsuarios = [];

        if ($target) {
            // Modo Rapido (1 usuario)
            $userInfo = $this->enviarAccion($cookie, 'getUser', ['user_name' => $target]);
            if (($userInfo['status'] ?? -1) == 0) {
                 $userDat = $userInfo['response']['user_name'] ?? $userInfo['response'][$target] ?? $userInfo['response'];
                 $listaUsuarios = [$userDat];
            } else {
                $this->error(" Extensión no encontrada.");
                return;
            }
        } else {
            // Modo Masivo
            $this->line(" Descargando lista maestra...");
            $response = Http::withoutVerifying()->timeout(30)->post($this->apiUrl, [
                'request' => ['action' => 'listUser', 'cookie' => $cookie]
            ]);
            $json = json_decode($response->body(), true);
            $responseBlock = $json['response'] ?? [];

            if (isset($responseBlock['user']) && is_array($responseBlock['user'])) {
                $listaUsuarios = $responseBlock['user'];
            } else {
                foreach ($responseBlock as $key => $value) {
                    if (is_array($value) && !empty($value) && isset($value[0]['user_name'])) {
                        $listaUsuarios = $value;
                        break;
                    }
                }
            }
        }

        $total = count($listaUsuarios);
        if ($total == 0) { $this->error(" Lista vacía."); return; }

        $this->info(" Analizando {$total} usuarios...");
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // CONTADORES
        $sinCambios = 0;
        $actualizados = 0;
        $nuevos = 0;

        foreach ($listaUsuarios as $userData) {
            $extension = $userData['user_name'] ?? $target; 

            // 1. Obtener Datos Frescos de la API
            $sipData = $this->enviarAccion($cookie, 'getSIPAccount', ['extension' => $extension]);
            
            $detalles = [];
            if (($sipData['status'] ?? -1) == 0) {
                $sipResp = $sipData['response'] ?? [];
                $detalles = $sipResp['extension'] ?? $sipResp['sip_account'][0] ?? $sipResp['sip_account'] ?? [];
            }

            // Preparar datos limpios
            $dnd = (isset($detalles['dnd']) && $detalles['dnd'] === 'yes') ? 1 : 0;
            $maxContacts = (int)($detalles['max_contacts'] ?? 1);
            
            $permisoRaw = $detalles['permission'] ?? 'internal';
            $permiso = 'Internal';
            if (str_contains($permisoRaw, 'international')) $permiso = 'International';
            elseif (str_contains($permisoRaw, 'national'))  $permiso = 'National';
            elseif (str_contains($permisoRaw, 'local'))     $permiso = 'Local';

            // Obtener secret (contraseña SIP)
            $secret = $detalles['secret'] ?? null;

            // 2. BUSCAR EN BD LOCAL
            $usuarioLocal = Extension::where('extension', $extension)->first();

            $datosNuevos = [
                'fullname'       => $userData['fullname'] ?? $extension,
                'email'          => $userData['email'] ?? null,
                'first_name'     => $userData['first_name'] ?? null,
                'last_name'      => $userData['last_name'] ?? null,
                'phone'          => $userData['phone_number'] ?? null,
                'do_not_disturb' => $dnd,
                'permission'     => $permiso,
                'max_contacts'   => $maxContacts,
                'secret'         => $secret
            ];

            // 3. COMPARACION INTELIGENTE
            if ($usuarioLocal) {
                // Si existe, verificamos si ALGO cambio
                $hayCambios = false;
                
                // Comparamos campo por campo clave
                if ($usuarioLocal->fullname != $datosNuevos['fullname']) $hayCambios = true;
                if ($usuarioLocal->email != $datosNuevos['email']) $hayCambios = true;
                if ($usuarioLocal->max_contacts != $datosNuevos['max_contacts']) $hayCambios = true;
                if ($usuarioLocal->do_not_disturb != $datosNuevos['do_not_disturb']) $hayCambios = true;
                if ($usuarioLocal->permission != $datosNuevos['permission']) $hayCambios = true;
                if ($usuarioLocal->secret != $datosNuevos['secret']) $hayCambios = true;

                if ($hayCambios) {
                    $usuarioLocal->update($datosNuevos);
                    $actualizados++;
                } else {
                    $sinCambios++;
                }
            } else {
                // Si no existe, lo creamos
                Extension::create(array_merge(['extension' => $extension], $datosNuevos));
                $nuevos++;
            }

            if (!$target) usleep(5000); 
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        // REPORTE FINAL
        $this->info(" RESUMEN FINAL:");
        $this->table(
            ['Estado', 'Cantidad'],
            [
                [' Sin Cambios (Ignorados)', $sinCambios],
                [' Actualizados (Detectado cambio)', $actualizados],
                [' Nuevos Creados', $nuevos],
                ['TOTAL', $total]
            ]
        );
    }

    // --- CONEXION ---
    private function hacerLogin() { /* (Igual que antes) */
        try {
            $ch = Http::withoutVerifying()->post($this->apiUrl, ['request'=>['action'=>'challenge','user'=>$this->user,'version'=>'1.0']])->json();
            $token = md5(($ch['response']['challenge']??'') . $this->pass);
            $login = Http::withoutVerifying()->post($this->apiUrl, ['request'=>['action'=>'login','user'=>$this->user,'token'=>$token]])->json();
            return $login['response']['cookie'] ?? null;
        } catch (\Exception $e) { return null; }
    }

    private function enviarAccion($cookie, $accion, $params = []) { /* (Igual que antes) */
        try {
            return Http::withoutVerifying()->timeout(10)->post($this->apiUrl, ['request'=>array_merge(['action'=>$accion,'cookie'=>$cookie],$params)])->json();
        } catch (\Exception $e) { return ['status'=>-500]; }
    }
}