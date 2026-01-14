<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PruebaComandos extends Command
{
    protected $signature = 'api:prueba';
    protected $description = 'Laboratorio de pruebas API Grandstream (Master Tool)';

    // CONFIGURACIÓN (Ajusta si cambia la IP)
    protected $ip            = '10.36.1.10';
    protected $user          = 'cdrapi';
    protected $pass          = '123api';

    public function handle()
    {
        $this->info("==========================================");
        $this->info("  LABORATORIO GRANDSTREAM: MASTER TOOL");
        $this->info("==========================================");

        $opcion = $this->choice('¿Qué herramienta quieres usar?', [
            '1' => ' Consultar Usuario (Debug Completo)',
            '2' => ' Editar Usuario (Solución Definitiva)',
            '3' => ' Buscar Llamadas (CDR Port 8443)',
            '4' => ' Consultar estado de la central',
            '5' => 'Salir'
        ], '1');

        switch ($opcion) {
            case ' Consultar Usuario (Debug Completo)':
                $this->opcionConsultar();
                break;

            case ' Editar Usuario (Solución Definitiva)':
                $this->opcionEditarUsuario();
                break;

            case ' Buscar Llamadas (CDR Port 8443)':
                $this->opcionBuscarLlamadas();
                break;
            case ' Consultar estado de la central': 
                $this->opcionConsultarEstado();
                break;   

            default:
                $this->info("¡Hasta luego!");
                break;
        }
    }

    // --- OPCIÓN 1: CONSULTAR ---
    private function opcionConsultar()
    {
        $ext = $this->ask('¿Qué extensión consultar?');
        $this->info(" Consultando datos...");
        $datos = $this->consultarUsuario($ext);

        if ($datos) {
            $this->info(" Datos encontrados:");
            dump($datos);
        } else {
            $this->error(" No se encontró la extensión $ext");
        }
    }

    // --- OPCIÓN 2: EDITAR (Lógica corregida que funcionó) ---
    private function opcionEditarUsuario()
    {
        $this->alert(" MODIFICACIÓN DE USUARIO (FIX ID + EMAIL)");
        $ext = $this->ask('¿Qué extensión quieres modificar?', '4444');

        // PASO 1: OBTENER EL ID INTERNO
        $this->info(" Buscando ID interno de la extensión $ext...");
        $userData = $this->consultarUsuario($ext);

        if (!$userData) {
            $this->error(" No se encontró información para $ext.");
            return;
        }

        $userId = $userData['user_id'] ?? null;
        
        $this->line("    Datos actuales:");
        $this->line("      - User ID:    " . ($userId ? "<fg=green>$userId</>" : "<fg=red>NULL</>"));
        $this->line("      - Nombre:     " . ($userData['first_name'] ?? '(Vacío)'));
        $this->line("      - Email:      " . ($userData['email'] ?? '(Vacío)'));

        if (empty($userId)) {
            $this->error(" ALTO: No hay User ID. Imposible editar.");
            return;
        }

        // PASO 2: DATOS NUEVOS
        $this->newLine();
        $nuevoNombre = $this->ask('Nuevo Nombre', 'VicentePrueba');
        
        $emailActual = $userData['email'] ?? '';
        
        // Aquí dejamos el email opcional (si das Enter se envía vacío)
        $nuevoEmail = $this->ask('Email (Deja vacío si quieres)', $emailActual);

        // PASO 3: PAYLOAD (Blindado contra Error -9)
        $payload = [
            'user_id'    => (int)$userId,          // Forzar entero
            'user_name'  => (string)$ext,          // Forzar string
            'first_name' => $nuevoNombre,
            'email'      => $nuevoEmail,           // Puede ir vacío
            'privilege'  => (int)($userData['privilege'] ?? 0),
            'last_name'  => $userData['last_name'] ?? '',
        ];

        // PASO 4: ENVIAR
        $this->info(" Enviando actualización...");
        $this->ejecutarComando('updateUser', $payload);
    }

    // --- OPCIÓN 3: BUSCADOR CDR (Port 8443) ---
    private function opcionBuscarLlamadas()
    {
        $caller = $this->ask('¿Quién llamó? (caller)', '4004');
        $minDur = $this->ask('Min Segundos', '1');
        
        $url = "https://{$this->ip}:8443/cdrapi";
        $this->info(" Conectando a $url...");

        try {
            $response = Http::withDigestAuth($this->user, $this->pass)
                ->timeout(10)->withoutVerifying()
                ->get($url, [
                    'format' => 'JSON',
                    'caller' => $caller,
                    'minDur' => $minDur
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $total = count($data['cdr_root'] ?? []);
                $this->info(" ¡ÉXITO! Se encontraron $total llamadas.");
                if ($total > 0) {
                    $this->line(" Última llamada encontrada:");
                    dump(end($data['cdr_root']));
                }
            } else {
                $this->error(" Error HTTP: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error(" Error: " . $e->getMessage());
        }
    }
    // --- OPCION 4: CONSULTAR ESTADO DE LA CENTRAL ---
    private function opcionConsultarEstado()
    {
        $this->info(" Consultando estado de la central...");

        $json = $this->enviarPeticion('getSystemStatus', []);

        if (($json['status'] ?? -1) == 0) {
            $this->info(" ¡ÉXITO! Status: 0");
            $status = $json['response'] ?? [];
            $this->line(" Estado del sistema:");
            foreach ($status as $clave => $valor) {
                $this->line("  - $clave : $valor");
            }
        } else {
            $this->error(" Error API: " . ($json['status'] ?? 'Desconocido'));
            if (isset($json['response'])) dump($json['response']);
        }
    }

    // --- MOTORES INTERNOS ---

    private function consultarUsuario($extension)
    {
        $json = $this->enviarPeticion('getUser', ['user_name' => $extension]);

        if (($json['status'] ?? -1) == 0) {
            $raw = $json['response'] ?? [];
            // Búsqueda inteligente de datos
            if (isset($raw['user_name'])) return $raw['user_name'];
            if (isset($raw[$extension])) return $raw[$extension];
            if (isset($raw['user_id'])) return $raw;
            return reset($raw);
        }
        return null;
    }

    private function ejecutarComando($accion, $params)
    {
        $json = $this->enviarPeticion($accion, $params);
        $status = $json['status'] ?? -1;

        if ($status == 0) {
            $this->info(" ¡ÉXITO! Status: 0");
            if (($json['response']['need_apply'] ?? '') == 'yes') {
                $this->warn(" Aplicando cambios...");
                $this->enviarPeticion('applyChanges', [], 60);
                $this->info(" ¡Cambios aplicados!");
            }
        } else {
            $this->error(" Error API: $status");
            if (isset($json['response'])) dump($json['response']);
        }
    }

    private function enviarPeticion($accion, $data=[], $timeout=10)
    {
        try {
            $url = "https://{$this->ip}:7110/api";
            
            // Challenge
            $ch = Http::withoutVerifying()->post($url, ['request'=>['action'=>'challenge','user'=>$this->user,'version'=>'1.0']]);
            $token = md5(($ch['response']['challenge']??'') . $this->pass);
            
            // Login
            $login = Http::withoutVerifying()->post($url, ['request'=>['action'=>'login','user'=>$this->user,'token'=>$token]]);
            $cookie = $login['response']['cookie'] ?? null;
            
            if (!$cookie) return ['status' => -99];

            // Action
            return Http::withoutVerifying()->timeout($timeout)
                ->post($url, ['request'=>array_merge(['action'=>$accion,'cookie'=>$cookie],$data)])
                ->json();

        } catch (\Exception $e) {
            return ['status' => -500, 'response' => $e->getMessage()];
        }
    }
}