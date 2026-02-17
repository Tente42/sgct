<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Extension;
use App\Models\PbxConnection;
use App\Traits\GrandstreamTrait;
use Illuminate\Support\Facades\Cache;

class ExtensionController extends Controller
{
    use GrandstreamTrait;
    
    /**
     * Sincronizar todas las extensiones desde la Central Telefónica (AJAX).
     * Se ejecuta directamente en la petición HTTP con timeout extendido.
     * El progreso se trackea en Cache para que el frontend lo muestre via polling.
     */
    public function syncExtensions(): JsonResponse
    {
        // Extender timeout para la sincronización
        set_time_limit(600);
        ini_set('max_execution_time', '600');

        // Verificar permiso
        if (!auth()->user()->canSyncExtensions()) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para sincronizar anexos.'], 403);
        }

        $pbxId = session('active_pbx_id');
        if (!$pbxId) {
            return response()->json(['success' => false, 'message' => 'No hay una central PBX seleccionada.'], 400);
        }

        $cacheKey = "extension_sync_{$pbxId}";

        // Verificar si ya hay una sincronización en curso
        $current = Cache::get($cacheKey);
        if ($current && ($current['status'] ?? '') === 'syncing') {
            // Auto-expirar sincronizaciones estancadas (más de 10 minutos)
            $startedAt = $current['started_at'] ?? null;
            $isStale = $startedAt && \Carbon\Carbon::parse($startedAt)->diffInMinutes(now()) > 10;

            if (!$isStale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya hay una sincronización de anexos en progreso. Por favor espere a que termine.'
                ], 409);
            }
            // Sincronización estancada, permitir nueva
            Cache::forget($cacheKey);
        }

        try {
            // Marcar como sincronizando
            Cache::put($cacheKey, [
                'status' => 'syncing',
                'message' => 'Conectando con la central...',
                'started_at' => now()->toDateTimeString(),
            ], 1800);

            // Verificar conexión con la central
            if (!$this->testConnection()) {
                Cache::put($cacheKey, [
                    'status' => 'error',
                    'message' => 'No se pudo conectar con la Central Telefónica. Verifique la red.',
                ], 300);
                return response()->json(['success' => false, 'message' => 'Error de conexión con la central.'], 500);
            }

            Cache::put($cacheKey, [
                'status' => 'syncing',
                'message' => 'Obteniendo lista de extensiones...',
            ], 1800);

            // Obtener lista de usuarios desde la central
            $listResponse = $this->connectApi('listUser', [], 60);
            $responseBlock = $listResponse['response'] ?? [];

            // Extraer usuarios (manejar diferentes formatos de respuesta)
            $users = $responseBlock['user'] ?? [];
            if (empty($users)) {
                foreach ($responseBlock as $value) {
                    if (is_array($value) && !empty($value) && isset($value[0]['user_name'])) {
                        $users = $value;
                        break;
                    }
                }
            }

            if (empty($users)) {
                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'message' => 'Conexión exitosa. No se encontraron extensiones en la central.',
                ], 120);
                return response()->json(['success' => true, 'message' => 'Sin extensiones encontradas.', 'count' => 0]);
            }

            $total = count($users);
            $synced = 0;
            $updated = 0;
            $created = 0;

            foreach ($users as $userData) {
                $extensionNumber = $userData['user_name'] ?? null;
                if (!$extensionNumber) continue;

                // Actualizar progreso en Cache
                Cache::put($cacheKey, [
                    'status' => 'syncing',
                    'message' => "Sincronizando extensión {$extensionNumber} ({$synced}/{$total})...",
                ], 1800);

                // Construir datos de extensión
                $data = [
                    'fullname' => $userData['fullname'] ?? $extensionNumber,
                    'email' => $userData['email'] ?? null,
                    'first_name' => $userData['first_name'] ?? null,
                    'last_name' => $userData['last_name'] ?? null,
                    'phone' => $userData['phone_number'] ?? null,
                    'do_not_disturb' => false,
                    'permission' => 'Internal',
                    'max_contacts' => 1,
                ];

                // Obtener detalles SIP adicionales (modo completo)
                try {
                    $sipData = $this->connectApi('getSIPAccount', ['extension' => $extensionNumber], 10);
                    if (($sipData['status'] ?? -1) == 0) {
                        $details = $sipData['response']['extension']
                            ?? $sipData['response']['sip_account'][0]
                            ?? $sipData['response']['sip_account']
                            ?? [];

                        $data['do_not_disturb'] = ($details['dnd'] ?? 'no') === 'yes';
                        $data['max_contacts'] = (int)($details['max_contacts'] ?? 1);
                        $data['secret'] = $details['secret'] ?? null;
                        $data['permission'] = $this->parsePermissionFromApi($details['permission'] ?? 'internal');
                    }
                } catch (\Exception $e) {
                    // Ignorar errores de SIP, continuar con datos básicos
                }

                // Guardar o actualizar extensión
                $existing = Extension::withoutGlobalScope('current_pbx')
                    ->where('extension', $extensionNumber)
                    ->where('pbx_connection_id', $pbxId)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $existing->touch(); // Forzar updated_at aunque los datos no cambien
                    $updated++;
                } else {
                    Extension::withoutGlobalScope('current_pbx')->create(
                        array_merge(['extension' => $extensionNumber, 'pbx_connection_id' => $pbxId], $data)
                    );
                    $created++;
                }

                $synced++;
            }

            $message = "Sincronización completada: {$synced} anexos procesados ({$created} nuevos, {$updated} actualizados).";

            Cache::put($cacheKey, [
                'status' => 'completed',
                'message' => $message,
            ], 120);

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $synced,
                'created' => $created,
                'updated' => $updated,
            ]);

        } catch (\Exception $e) {
            Cache::put($cacheKey, [
                'status' => 'error',
                'message' => 'Error durante la sincronización: ' . $e->getMessage(),
            ], 300);

            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verificar estado de sincronización de extensiones (AJAX polling)
     */
    public function checkSyncStatus(): JsonResponse
    {
        $pbxId = session('active_pbx_id');
        if (!$pbxId) {
            return response()->json(['status' => 'idle']);
        }

        $cacheKey = "extension_sync_{$pbxId}";
        $syncData = Cache::get($cacheKey);

        if (!$syncData) {
            return response()->json(['status' => 'idle']);
        }

        // Auto-expirar sincronizaciones estancadas (más de 10 minutos sin respuesta)
        if (($syncData['status'] ?? '') === 'syncing') {
            $startedAt = $syncData['started_at'] ?? null;
            if ($startedAt && \Carbon\Carbon::parse($startedAt)->diffInMinutes(now()) > 10) {
                Cache::forget($cacheKey);
                return response()->json([
                    'status' => 'error',
                    'message' => 'La sincronización anterior no respondió. Puede intentar de nuevo.',
                ]);
            }
        }

        // Si completó o error, limpiar cache después de informar
        if (in_array($syncData['status'] ?? '', ['completed', 'error'])) {
            Cache::forget($cacheKey);
        }

        return response()->json($syncData);
    }

    /**
     * Parsear permiso de la API al formato local de BD
     */
    private function parsePermissionFromApi(string $raw): string
    {
        if (str_contains($raw, 'international')) return 'International';
        if (str_contains($raw, 'national')) return 'National';
        if (str_contains($raw, 'local')) return 'Local';
        return 'Internal';
    }

    public function update(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canEditExtensions()) {
            abort(403, 'No tienes permiso para editar anexos.');
        }

        // 1. Validacion de datos
        $request->validate([
            'extension' => 'required|string', 
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'permission' => 'required|in:Internal,Local,National,International',
            'max_contacts' => 'required|integer|min:1|max:10',
            'secret' => ['nullable', 'string', 'min:5', 'regex:/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\\|`~]+$/'],
        ], [
            'secret.min' => 'La contraseña SIP debe tener al menos 5 caracteres.',
            'secret.regex' => 'La contraseña SIP debe contener al menos una letra y un número (puede incluir caracteres especiales).',
        ]);

        $extensionLocal = Extension::where('extension', $request->extension)->first();

        if (!$extensionLocal) {
            return back()->with('error', 'Anexo no encontrado en base de datos local');
        }

        // 2. FASE DE CONEXION A GRANDSTREAM (usando el trait)
        // Verificar conexión
        if (!$this->testConnection()) {
            return back()->with('error', ' Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        // 3. OBTENER ID INTERNO (Necesario para updateUser)
        // Buscamos el usuario en la central para obtener su 'user_id'
        $infoUser = $this->connectApi('getUser', ['user_name' => $request->extension]);
        
        // Logica para extraer el ID sin importar como responda el JSON
        $datosRaw = $infoUser['response']['user_name'] 
                 ?? $infoUser['response'][$request->extension] 
                 ?? $infoUser['response'];
        
        $userId = $datosRaw['user_id'] ?? null;

        if (!$userId) {
            return back()->with('error', ' La extensión existe aquí, pero NO en la Central Telefónica.');
        }

        // 4. PREPARAR DATOS PARA LA API
        // A. Permisos (Traduccion de BD a la API)
        $permisoApi = 'internal'; 
        if ($request->permission == 'International') $permisoApi = 'internal-local-national-international';
        elseif ($request->permission == 'National')  $permisoApi = 'internal-local-national';
        elseif ($request->permission == 'Local')     $permisoApi = 'internal-local';

        // B. No Molestar (DND)
        // El request->boolean devuelve true/false, la API quiere 'yes'/'no'
        $dndApi = $request->boolean('do_not_disturb') ? 'yes' : 'no';

        // 5. ENVIAR CAMBIOS A LA CENTRAL
        
        // Petición 1: Datos de Identidad (Nombre, Apellido, Email, Teléfono)
        $respIdentity = $this->connectApi('updateUser', [
            'user_id' => (int)$userId,
            'user_name' => $request->extension,
            'first_name' => $request->first_name, 
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone 
        ]);

        // Petición 2: Configuración SIP (Permisos, Contactos, DND, Secret)
        $sipData = [
            'extension' => $request->extension,
            'max_contacts' => (int)$request->max_contacts,
            'dnd' => $dndApi,
            'permission' => $permisoApi
        ];

        // Solo incluir secret si se proporciona (para cambiar contraseña SIP)
        if ($request->filled('secret')) {
            $sipData['secret'] = $request->secret;
        }

        $respSip = $this->connectApi('updateSIPAccount', $sipData);

        // 6. VERIFICAR SI TODO SALIO BIEN
        if (($respIdentity['status'] ?? -1) == 0 && ($respSip['status'] ?? -1) == 0) {
            
            // Aplicar cambios (Commit en la central)
            $this->connectApi('applyChanges');

            // 7. ACTUALIZAR BASE DE DATOS LOCAL
            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'permission' => $request->permission, 
                'do_not_disturb' => $request->boolean('do_not_disturb'),
                'max_contacts' => $request->max_contacts,
            ];

            // Si se proporcionó un nuevo secret, guardarlo también en local
            if ($request->filled('secret')) {
                $updateData['secret'] = $request->secret;
            }

            $extensionLocal->update($updateData);

            return back()->with('success', " Anexo {$request->extension} actualizado en BD y Central Telefónica.");

        } else {
            // Mostrar error más detallado
            $statusIdentity = $respIdentity['status'] ?? 'N/A';
            $statusSip = $respSip['status'] ?? 'N/A';
            $msgIdentity = $respIdentity['response']['body'] ?? json_encode($respIdentity['response'] ?? []);
            $msgSip = $respSip['response']['body'] ?? json_encode($respSip['response'] ?? []);
            
            $msgError = "Identity(status:{$statusIdentity}): {$msgIdentity} | SIP(status:{$statusSip}): {$msgSip}";
            return back()->with('error', " Fallo la actualización en la Central: $msgError");
        }
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'extension_id' => 'required',
            'fullname' => 'required|string|max:255'
        ]);

        Extension::updateOrCreate(
            [
                'pbx_connection_id' => session('active_pbx_id'),
                'extension' => $request->extension_id
            ],
            ['fullname' => $request->fullname]
        );

        return back()->with('success', 'Nombre actualizado correctamente');
    }

    /**
     * Actualizar las IPs de todas las extensiones desde la Central
     */
    public function updateIps()
    {
        // Verificar permiso
        if (!auth()->user()->canUpdateIps()) {
            abort(403, 'No tienes permiso para actualizar IPs.');
        }

        if (!$this->testConnection()) {
            return back()->with('error', 'Error: No se pudo conectar con la Central Telefónica. Verifique la red.');
        }

        $liveData = $this->connectApi('listAccount', [
            'options'  => 'extension,addr',
            'item_num' => 1000,
            'sidx'     => 'extension',
            'sord'     => 'asc'
        ]);

        $rawAccounts = $liveData['response']['account'] ?? 
                       $liveData['response']['body']['account'] ?? [];

        // Preparar mapa de extensión => IP para actualización por lote
        $ipMap = [];
        foreach ($rawAccounts as $account) {
            $ext = $account['extension'] ?? null;
            $addr = $account['addr'] ?? null;
            
            if ($ext) {
                $ipMap[$ext] = ($addr && $addr !== '-') ? $addr : null;
            }
        }

        // Actualización por lote: una sola consulta para obtener extensiones, luego batch update
        if (!empty($ipMap)) {
            $extensions = Extension::whereIn('extension', array_keys($ipMap))->get();
            
            foreach ($extensions as $extension) {
                $newIp = $ipMap[$extension->extension] ?? null;
                if ($extension->ip !== $newIp) {
                    $extension->update(['ip' => $newIp]);
                }
            }
            
            $updated = $extensions->count();
        } else {
            $updated = 0;
        }

        return back()->with('success', "Se actualizaron las IPs de {$updated} extensiones.");
    }

    public function index(Request $request)
    {
        // Verificar permiso para ver anexos
        if (!auth()->user()->canViewExtensions()) {
            abort(403, 'No tienes permiso para ver los anexos.');
        }

        $anexo = $request->input('anexo');
        $extensions = \App\Models\Extension::query()
            ->when($anexo, fn ($q) => $q->where('extension', 'like', "%{$anexo}%"))
            ->orderBy('extension', 'asc')
            ->paginate(50)
            ->appends($request->only('anexo'));

        return view('configuracion', compact('extensions', 'anexo'));
    }

    /**
     * Obtener configuración de desvíos de llamadas de una extensión
     */
    public function getCallForwarding(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canEditExtensions()) {
            return response()->json(['success' => false, 'message' => 'No tienes permiso para ver desvíos.'], 403);
        }

        $request->validate([
            'extension' => 'required|string'
        ]);

        // Verificar conexión
        if (!$this->testConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la Central Telefónica.'
            ], 503);
        }

        // Obtener configuración actual de la extensión
        $response = $this->connectApi('getSIPAccount', [
            'extension' => $request->extension
        ]);

        if (($response['status'] ?? -1) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de la extensión.'
            ], 500);
        }

        $presenceSettings = $response['response']['sip_presence_settings'] ?? [];
        $currentPresence = $response['response']['presence_status'] ?? 'available';

        // Buscar la configuración del estado de presencia actual
        $currentConfig = [
            'cfb' => '',
            'cfb_destination_type' => '0',
            'cfb_timetype' => '0',
            'cfn' => '',
            'cfn_destination_type' => '0',
            'cfn_timetype' => '0',
            'cfu' => '',
            'cfu_destination_type' => '0',
            'cfu_timetype' => '0',
        ];

        foreach ($presenceSettings as $setting) {
            if (($setting['presence_status'] ?? '') === $currentPresence) {
                $currentConfig = array_merge($currentConfig, [
                    'cfb' => $setting['cfb'] ?? '',
                    'cfb_destination_type' => $setting['cfb_destination_type'] ?? '0',
                    'cfb_timetype' => $setting['cfb_timetype'] ?? '0',
                    'cfn' => $setting['cfn'] ?? '',
                    'cfn_destination_type' => $setting['cfn_destination_type'] ?? '0',
                    'cfn_timetype' => $setting['cfn_timetype'] ?? '0',
                    'cfu' => $setting['cfu'] ?? '',
                    'cfu_destination_type' => $setting['cfu_destination_type'] ?? '0',
                    'cfu_timetype' => $setting['cfu_timetype'] ?? '0',
                ]);
                break;
            }
        }

        // Obtener colas disponibles
        $queuesResponse = $this->connectApi('listQueue', [
            'options' => 'extension,queue_name',
            'sidx' => 'extension',
            'sord' => 'asc'
        ]);

        $queues = [];
        if (($queuesResponse['status'] ?? -1) === 0) {
            foreach ($queuesResponse['response']['queue'] ?? [] as $queue) {
                $queues[] = [
                    'extension' => $queue['extension'] ?? '',
                    'name' => $queue['queue_name'] ?? ''
                ];
            }
        }

        return response()->json([
            'success' => true,
            'extension' => $request->extension,
            'presence_status' => $currentPresence,
            'forwarding' => $currentConfig,
            'queues' => $queues
        ]);
    }

    /**
     * Actualizar configuración de desvíos de llamadas
     * IMPORTANTE: Se debe enviar cada desvío por separado con applyChanges después de cada uno
     * para que la PBX auto-detecte correctamente el tipo de destino.
     */
    public function updateCallForwarding(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canEditExtensions()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar anexos.'
            ], 403);
        }

        $request->validate([
            'extension' => 'required|string',
            'timetype' => 'required|in:0,1,2,3,4',
            'forwards' => 'required|array',
            'forwards.*.type' => 'required|in:cfb,cfn,cfu',
            'forwards.*.dest_type' => 'required|in:none,extension,queue,custom',
            'forwards.*.destination' => 'nullable|string'
        ]);

        // Verificar conexión
        if (!$this->testConnection()) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con la Central Telefónica.'
            ], 503);
        }

        $extension = $request->extension;
        $timetype = $request->timetype;
        $forwards = $request->forwards;

        $errors = [];
        $success = [];

        // Procesar cada desvío por separado (IMPORTANTE para auto-detección de tipo)
        foreach ($forwards as $forward) {
            $type = $forward['type']; // cfb, cfn, cfu
            $destType = $forward['dest_type']; // none, extension, queue, custom
            $destination = trim($forward['destination'] ?? '');

            // Si es "none", limpiar el desvío
            if ($destType === 'none') {
                $destination = '';
            }

            // Validar que si no es "none", tenga destino
            if ($destType !== 'none' && empty($destination)) {
                $errors[] = strtoupper($type) . ': Debes especificar un destino.';
                continue;
            }

            // Preparar datos para la API (sin destination_type - la PBX lo auto-detecta)
            $updateData = [
                'extension' => $extension,
                $type => $destination,
                $type . '_timetype' => $timetype
            ];

            // Enviar actualización
            $response = $this->connectApi('updateSIPAccount', $updateData);

            if (($response['status'] ?? -1) !== 0) {
                $errors[] = strtoupper($type) . ': Error al actualizar.';
                continue;
            }

            // Aplicar cambios después de cada desvío (IMPORTANTE)
            $this->connectApi('applyChanges', [], 30);
            usleep(500000); // 0.5 segundos de pausa

            $success[] = strtoupper($type);
        }

        if (!empty($errors) && empty($success)) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar desvíos: ' . implode(' | ', $errors)
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Desvíos actualizados: ' . implode(', ', $success),
            'warnings' => $errors
        ]);
    }
}