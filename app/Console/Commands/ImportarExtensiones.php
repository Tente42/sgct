<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Extension;

class ImportarExtensiones extends Command
{
    protected $signature = 'extensions:import {target? : La extensión específica a sincronizar}';
    protected $description = 'Sincroniza usuarios de forma inteligente (Solo guarda si hay cambios).';

    protected $ip   = '10.36.1.10'; 
    protected $user = 'cdrapi';
    protected $pass = '123api';
    protected $apiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->apiUrl = "https://{$this->ip}:7110/api";
    }

    public function handle()
    {
        $target = $this->argument('target');
        $modo = $target ? "QUIRÚRGICO ($target)" : "MASIVO INTELIGENTE";

        $this->info("============================================");
        $this->info(" 🧠 SINCRONIZADOR V7 - MODO: $modo");
        $this->info("============================================");

        $cookie = $this->hacerLogin();
        if (!$cookie) {
            $this->error("❌ Error de Login.");
            return;
        }

        // --- OBTENCIÓN DE DATOS ---
        $listaUsuarios = [];

        if ($target) {
            // Modo Rápido (1 usuario)
            $userInfo = $this->enviarAccion($cookie, 'getUser', ['user_name' => $target]);
            if (($userInfo['status'] ?? -1) == 0) {
                 $userDat = $userInfo['response']['user_name'] ?? $userInfo['response'][$target] ?? $userInfo['response'];
                 $listaUsuarios = [$userDat];
            } else {
                $this->error("❌ Extensión no encontrada.");
                return;
            }
        } else {
            // Modo Masivo
            $this->line("📡 Descargando lista maestra...");
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
        if ($total == 0) { $this->error("❌ Lista vacía."); return; }

        $this->info("📋 Analizando {$total} usuarios...");
        
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
                'max_contacts'   => $maxContacts
            ];

            // 3. COMPARACIÓN INTELIGENTE (LA MAGIA) 🪄
            if ($usuarioLocal) {
                // Si existe, verificamos si ALGO cambió
                $hayCambios = false;
                
                // Comparamos campo por campo clave
                if ($usuarioLocal->fullname != $datosNuevos['fullname']) $hayCambios = true;
                if ($usuarioLocal->email != $datosNuevos['email']) $hayCambios = true;
                if ($usuarioLocal->max_contacts != $datosNuevos['max_contacts']) $hayCambios = true;
                if ($usuarioLocal->do_not_disturb != $datosNuevos['do_not_disturb']) $hayCambios = true;
                if ($usuarioLocal->permission != $datosNuevos['permission']) $hayCambios = true;

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
        $this->info("🏁 RESUMEN FINAL:");
        $this->table(
            ['Estado', 'Cantidad'],
            [
                ['💤 Sin Cambios (Ignorados)', $sinCambios],
                ['🔄 Actualizados (Detectado cambio)', $actualizados],
                ['✨ Nuevos Creados', $nuevos],
                ['TOTAL', $total]
            ]
        );
    }

    // --- CONEXIÓN ---
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