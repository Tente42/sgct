<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Extension;
use App\Traits\GrandstreamTrait;

class ImportarExtensiones extends Command
{
    use GrandstreamTrait;

    protected $signature = 'extensions:import {target? : La extensión específica a sincronizar}';
    protected $description = 'Sincroniza usuarios de forma inteligente (Solo guarda si hay cambios). Usa Cookie Auth.';

    public function handle()
    {
        $target = $this->argument('target');
        $modo = $target ? " ($target)" : "MASIVO (Todos los usuarios)";

        $this->info("============================================");
        $this->info("  SINCRONIZADOR - MODO: $modo");
        $this->info("  (Método: Cookie Auth - Trait)");
        $this->info("============================================");

        // Verificar conexión
        if (!$this->testConnection()) {
            $this->error(" Error de Login. Verifica configuración.");
            return 1;
        }
        $this->info(" ✅ Conexión exitosa!");

        // --- OBTENCION DE DATOS ---
        $listaUsuarios = [];

        if ($target) {
            // Modo Rapido (1 usuario)
            $userInfo = $this->connectApi('getUser', ['user_name' => $target]);
            if (($userInfo['status'] ?? -1) == 0) {
                 $userDat = $userInfo['response']['user_name'] ?? $userInfo['response'][$target] ?? $userInfo['response'];
                 $listaUsuarios = [$userDat];
            } else {
                $this->error(" Extensión no encontrada.");
                return 1;
            }
        } else {
            // Modo Masivo
            $this->line(" Descargando lista maestra...");
            $response = $this->connectApi('listUser', [], 30);
            $responseBlock = $response['response'] ?? [];

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
        if ($total == 0) { $this->error(" Lista vacía."); return 1; }

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
            $sipData = $this->connectApi('getSIPAccount', ['extension' => $extension], 10);
            
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

        return 0;
    }
}