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
            '5' => ' Editar SIP (DND, Permisos) [CORREGIDO V2]',
            '6' => ' Ver Configuración SIP (Auditoría) [NUEVO]',
            '7' => ' Editor Universal (Parámetros Ocultos)',
            '8' => 'Salir'
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

            case ' Editar SIP (DND, Permisos) [CORREGIDO V2]':
                $this->opcionEditarSIP();
                break;
            
            case ' Ver Configuración SIP (Auditoría) [NUEVO]': // <--- AGREGADO
                $this->opcionVerSIP();
                break;

            default:
                $this->info("¡Hasta luego!");
                break;
            case ' Editor Universal (Parámetros Ocultos)':
                $this->opcionEditorUniversal();
                break;
        
        }
    }
    // --- OPCIÓN 6: VER SIP (MODO REVELACIÓN) ---
    private function opcionVerSIP()
    {
        $this->alert("  AUDITORÍA PROFUNDA (RAW DATA)");
        $ext = $this->ask('¿Qué extensión quieres revisar?', '4444');

        $this->info("  Descargando configuración completa...");
        $json = $this->enviarPeticion('getSIPAccount', ['extension' => $ext]);

        if (($json['status'] ?? -1) != 0) {
            $this->error(" Error al leer. Código: " . ($json['status'] ?? '?'));
            return;
        }

        // Recuperamos los datos crudos
        $data = $json['response']['extension'] 
             ?? $json['response']['sip_account'][0] 
             ?? $json['response']['sip_account'] 
             ?? null;

        if (!$data) {
            $this->error(" No se encontraron datos.");
            return;
        }

        // 1. MOSTRAR TABLA RESUMEN (Lo bonito)
        $this->info(" --- RESUMEN ---");
        $headers = ['Campo', 'Valor'];
        $rows = [
            ['Extensión', $data['extension'] ?? 'N/A'],
            ['DND',       $data['dnd'] ?? 'N/A'],
            ['Permisos',  $data['permission'] ?? 'N/A'],
        ];
        $this->table($headers, $rows);

        // 2. MOSTRAR TODO EL JSON (Para encontrar el Pickup Group)
        $this->newLine();
        $this->warn("  DATOS CRUDOS:");
        
        // Esto imprimirá TODA la lista de campos en tu pantalla
        dump($data); 

        $this->comment(" ↑ ↑ Sube en la consola y busca el nombre del parámetro para los grupos.");
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

    // --- OPCIÓN 2: EDITAR USUARIO (Perfil Web) ---
    private function opcionEditarUsuario()
    {
        $this->alert(" MODIFICACIÓN DE USUARIO (FIX ID + EMAIL + NAME + PHONE)");
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
        $this->line("      - Telefono:   " . ($userData['phone_number'] ?? '(Vacío)'));

        if (empty($userId)) {
            $this->error(" ALTO: No hay User ID. Imposible editar.");
            return;
        }

        // PASO 2: DATOS NUEVOS
        $this->newLine();
        $nuevoNombre = $this->ask('Nuevo Nombre', 'VicentePrueba');
        $emailActual = $userData['email'] ?? '';
        $nuevoEmail = $this->ask('Email (Deja vacío si quieres)', $emailActual);
        $lastName = $this->ask('Apellido', $userData['last_name'] ?? '');
        $phoneActual = $userData['phone_number'] ?? '';
        $nuevoTelefono = $this->ask('Teléfono (Deja vacío si quieres)', $phoneActual);


        // PASO 3: PAYLOAD (Blindado)
        $payload = [
            'user_id'    => (int)$userId,          // Forzar entero
            'user_name'  => (string)$ext,          // Forzar string
            'first_name' => $nuevoNombre,          // Puede ir vacío
            'email'      => $nuevoEmail,           // Puede ir vacío
            'privilege'  => (int)($userData['privilege'] ?? 0),
            'last_name'  => $lastName,             // Puede ir vacío
            'phone_number' => $nuevoTelefono,      // Puede ir vacío
        ];

        // PASO 4: ENVIAR
        $this->info(" Enviando actualización...");
        $this->ejecutarComando('updateUser', $payload);
    }

    // --- OPCIÓN 5: EDITAR SIP (Payload Quirúrgico + Mapa de Permisos) ---
    private function opcionEditarSIP()
    {
        $this->alert(" MODIFICACIÓN DE CUENTA SIP (STRATEGY: MINIMAL + MAPPING)");
        
        $ext = $this->ask('¿Qué extensión quieres configurar?', '4444');

        // PASO 1: LEER
        $this->info("  Consultando estado actual...");
        $json = $this->enviarPeticion('getSIPAccount', ['extension' => $ext]);
        
        if (($json['status'] ?? -1) != 0) {
            $this->error(" Error al leer. Código: " . ($json['status'] ?? '?'));
            return;
        }

        // Búsqueda inteligente de datos
        $current = $json['response']['extension'] 
                 ?? $json['response']['sip_account'][0] 
                 ?? $json['response']['sip_account'] 
                 ?? [];

        $dndActual = $current['dnd'] ?? 'desconocido';
        $permActual = $current['permission'] ?? 'desconocido';

        $this->info("  Estado Actual:");
        $this->line("    - DND:       " . $dndActual);
        $this->line("    - Permisos:  " . $permActual);

        // PASO 2: PREPARAR PAYLOAD MÍNIMO
        $payload = [
            'extension' => $ext
        ];
        $cambios = 0;

        // PASO 3: PEDIR CAMBIOS
        $this->newLine();
        
        // --- DND (Sí/No) ---
        $opcionDnd = $this->choice('¿Cambiar DND (No Molestar)?', [
            'no' => 'No cambiar',
            '0'  => 'Desactivar (Disponible)',
            '1'  => 'Activar (Ocupado)'
        ], 'no');

        if ($opcionDnd !== 'no') {
            $val = ($opcionDnd === '1') ? 'yes' : 'no';
            $payload['dnd'] = $val; 
            $this->comment(" -> DND se cambiará a: $val");
            $cambios++;
        }

        // --- PERMISOS (MAPPING CORRECTO) ---
        $mapaPermisos = [
            'internal'      => 'internal',
            'local'         => 'internal-local',
            'national'      => 'internal-local-national',
            'international' => 'internal-local-national-international',
        ];

        $opcionPerm = $this->choice('¿Cambiar Permisos de Llamada?', [
            'no'            => 'No cambiar',
            'internal'      => 'Internal (Solo interna)',
            'local'         => 'Local (Urbana)',
            'national'      => 'National (Nacional)',
            'international' => 'International (Todo)'
        ], 'no');

        if ($opcionPerm !== 'no') {
            $valorApi = $mapaPermisos[$opcionPerm];
            $payload['permission'] = $valorApi;
            $this->comment(" -> Permiso se cambiará a: $valorApi");
            $cambios++;
        }
        $maxContactos = $this->choice('¿Cambiar número máximo de contactos SIP (1-10)?', [
            'no' => 'No cambiar',
            '1'  => '1',
            '2'  => '2',
            '3'  => '3',
            '4'  => '4',
            '5'  => '5',
            '6'  => '6',
            '7'  => '7',
            '8'  => '8',
            '9'  => '9',
            '10' => '10'
        ], 'no');
        if ($maxContactos !== 'no') {
            $payload['max_contacts'] = (int)$maxContactos;
            $this->comment(" -> Max Contactos se cambiará a: $maxContactos");
            $cambios++;
        }

        // PASO 4: ENVIAR
        if ($cambios === 0) {
            $this->warn(" Sin cambios. Abortando.");
            return;
        }

        $this->newLine();
        if ($this->confirm('¿Enviar actualización?')) {
            $this->info("  Enviando payload: " . json_encode($payload));
            $this->ejecutarComando('updateSIPAccount', $payload);
        }
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
    // --- OPCIÓN 7: EDITOR UNIVERSAL (LA LLAVE MAESTRA) ---
    private function opcionEditorUniversal()
    {
        $this->alert("  EDITOR UNIVERSAL DE PARÁMETROS SIP");
        $this->comment(" Úsalo para intentar configurar parámetros ocultos como 'pickupgroup'.");

        $ext = $this->ask('¿Qué extensión modificar?', '4444');
        
        // Aquí vamos a probar suerte
        $campo = $this->ask('¿Cuál es el NOMBRE del campo? (Prueba con: pickupgroup)', 'pickupgroup');
        $valor = $this->ask("¿Qué VALOR ponerle? (Ej: 1)", "1");

        $this->line(" Preparando payload: ['$campo' => '$valor']");

        if ($this->confirm('¿Enviar a la central?')) {
            // Payload minimalista: Solo ID + el campo nuevo
            $payload = [
                'extension' => $ext,
                $campo      => $valor
            ];
            
            $this->info("  Enviando...");
            $this->ejecutarComando('updateSIPAccount', $payload);
        }
    }

    // --- MOTORES INTERNOS ---

    private function consultarUsuario($extension)
    {
        $json = $this->enviarPeticion('getUser', ['user_name' => $extension]);

        if (($json['status'] ?? -1) == 0) {
            $raw = $json['response'] ?? [];
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
            $this->error(" Mensaje: " . ($json['response']['body'] ?? 'Sin detalle'));
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