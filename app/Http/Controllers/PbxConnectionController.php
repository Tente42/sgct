<?php

namespace App\Http\Controllers;

use App\Models\PbxConnection;
use App\Models\Call;
use App\Models\Extension;
use App\Traits\GrandstreamTrait;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class PbxConnectionController extends Controller
{
    use GrandstreamTrait;

    /**
     * Lista todas las centrales PBX
     * Los usuarios normales solo ven las centrales con estado "ready"
     */
    public function index(): View
    {
        $user = auth()->user();
        
        $query = PbxConnection::orderBy('name');
        
        // Usuarios no-admin solo ven centrales listas
        if (!$user->isAdmin()) {
            $query->where('status', PbxConnection::STATUS_READY);
        }

        return view('pbx.index', [
            'connections' => $query->get()
        ]);
    }

    /**
     * Crear nueva central PBX (Solo Admin)
     * La central se crea con estado "pending" y redirige a setup
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePbx($request, true);
        $data['status'] = PbxConnection::STATUS_PENDING;
        
        $pbx = PbxConnection::create($data);

        return redirect()->route('pbx.setup', $pbx)
            ->with('info', 'Central creada. Ahora debes sincronizar los datos iniciales.');
    }

    /**
     * Actualizar central PBX existente (Solo Admin)
     */
    public function update(Request $request, PbxConnection $pbx): RedirectResponse
    {
        $data = $this->validatePbx($request, false);
        
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $pbx->update($data);

        return redirect()->route('pbx.index')
            ->with('success', 'Central PBX actualizada exitosamente.');
    }

    /**
     * Eliminar central PBX (Solo Admin)
     */
    public function destroy(PbxConnection $pbx): RedirectResponse
    {
        if (session('active_pbx_id') === $pbx->id) {
            session()->forget(['active_pbx_id', 'active_pbx_name']);
        }

        // Eliminar datos relacionados
        Call::withoutGlobalScope('current_pbx')
            ->where('pbx_connection_id', $pbx->id)->delete();
        Extension::withoutGlobalScope('current_pbx')
            ->where('pbx_connection_id', $pbx->id)->delete();

        $pbx->delete();

        return redirect()->route('pbx.index')
            ->with('success', 'Central PBX eliminada exitosamente.');
    }

    /**
     * Seleccionar una central para trabajar
     */
    public function select(PbxConnection $pbx): RedirectResponse
    {
        $user = auth()->user();

        // Si no está lista y el usuario no es admin, denegar acceso
        if (!$pbx->isReady() && !$user->isAdmin()) {
            return redirect()->route('pbx.index')
                ->with('error', 'Esta central aún no está disponible.');
        }

        // Si está sincronizando, redirigir al setup (solo admin puede ver)
        if ($pbx->isSyncing() && $user->isAdmin()) {
            $this->setActivePbx($pbx);
            return redirect()->route('pbx.setup', $pbx)
                ->with('info', 'La sincronización está en progreso.');
        }

        // Si está pendiente y es admin, ir a setup
        if ($pbx->isPending() && $user->isAdmin()) {
            $this->setActivePbx($pbx);
            return redirect()->route('pbx.setup', $pbx)
                ->with('info', 'Esta central necesita sincronización inicial.');
        }

        $this->setActivePbx($pbx);

        return redirect()->route('dashboard')
            ->with('success', "Conectado a: {$pbx->name} ({$pbx->ip})");
    }

    /**
     * Página de configuración/sincronización inicial (Solo Admin)
     */
    public function setup(PbxConnection $pbx): View
    {
        $this->setActivePbx($pbx);

        return view('pbx.setup', [
            'pbx' => $pbx,
            'callCount' => Call::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->count(),
            'extensionCount' => Extension::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->count(),
        ]);
    }

    /**
     * Verificar estado de sincronización (AJAX)
     */
    public function checkSyncStatus(PbxConnection $pbx): JsonResponse
    {
        $pbx->refresh();
        
        return response()->json([
            'status' => $pbx->status,
            'message' => $pbx->sync_message,
            'isReady' => $pbx->isReady(),
            'isSyncing' => $pbx->isSyncing(),
            'extensionCount' => Extension::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->count(),
            'callCount' => Call::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->count(),
        ]);
    }

    /**
     * Iniciar sincronización de extensiones (AJAX - Solo Admin)
     * Este método sincroniza TODAS las extensiones con TODOS sus datos
     */
    public function syncExtensions(PbxConnection $pbx): JsonResponse
    {
        try {
            // Marcar como sincronizando
            $pbx->update([
                'status' => PbxConnection::STATUS_SYNCING,
                'sync_message' => 'Conectando con la central...'
            ]);

            // Configurar la conexión para este PBX
            $this->setPbxConnection($pbx);

            // Verificar conexión
            if (!$this->testConnection()) {
                $pbx->update([
                    'status' => PbxConnection::STATUS_ERROR,
                    'sync_message' => 'No se pudo conectar con la central. Verifica IP, puerto y credenciales.'
                ]);
                return response()->json(['success' => false, 'message' => 'Error de conexión'], 500);
            }

            $pbx->update(['sync_message' => 'Obteniendo lista de extensiones...']);

            // Usar listUser igual que ImportarExtensiones command
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
                $pbx->update(['sync_message' => 'No se encontraron extensiones en la central.']);
                return response()->json(['success' => true, 'message' => 'Sin extensiones', 'count' => 0]);
            }

            $total = count($users);
            $synced = 0;

            foreach ($users as $userData) {
                $extensionNumber = $userData['user_name'] ?? null;
                if (!$extensionNumber) continue;

                $pbx->update(['sync_message' => "Sincronizando extensión {$extensionNumber} ({$synced}/{$total})..."]);

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

                // Obtener detalles SIP adicionales
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
                        $data['permission'] = $this->parseExtensionPermission($details['permission'] ?? 'internal');
                    }
                } catch (\Exception $e) {
                    // Ignorar errores de SIP, continuar con datos básicos
                }

                // Guardar o actualizar extensión
                Extension::withoutGlobalScope('current_pbx')->updateOrCreate(
                    [
                        'pbx_connection_id' => $pbx->id,
                        'extension' => $extensionNumber
                    ],
                    $data
                );

                $synced++;
            }

            $pbx->update(['sync_message' => "Extensiones sincronizadas: {$synced}"]);

            return response()->json([
                'success' => true,
                'message' => "Se sincronizaron {$synced} extensiones",
                'count' => $synced
            ]);

        } catch (\Exception $e) {
            $pbx->update([
                'status' => PbxConnection::STATUS_ERROR,
                'sync_message' => 'Error: ' . $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parsear permiso de extensión al formato local
     */
    private function parseExtensionPermission(string $permission): string
    {
        $permission = strtolower($permission);
        if (str_contains($permission, 'international')) return 'International';
        if (str_contains($permission, 'national')) return 'National';
        if (str_contains($permission, 'local')) return 'Local';
        return 'Internal';
    }

    /**
     * Sincronizar llamadas por chunks (AJAX - Solo Admin)
     * Recibe un año y mes para sincronizar
     */
    public function syncCalls(Request $request, PbxConnection $pbx): JsonResponse
    {
        try {
            $year = (int) $request->input('year', date('Y'));
            $month = (int) $request->input('month', 1);

            $pbx->update([
                'status' => PbxConnection::STATUS_SYNCING,
                'sync_message' => "Sincronizando llamadas de {$month}/{$year}..."
            ]);

            // Configurar la conexión para este PBX
            $this->setPbxConnection($pbx);

            // Verificar conexión
            if (!$this->testConnection()) {
                return response()->json(['success' => false, 'message' => 'Error de conexión'], 500);
            }

            // Calcular rango del mes
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // No sincronizar meses futuros
            if ($startDate->isFuture()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mes futuro, saltando',
                    'count' => 0,
                    'hasMore' => $month < 12
                ]);
            }

            $pbx->update(['sync_message' => "Obteniendo CDRs de {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')}..."]);

            $response = $this->connectApi('cdrapi', [
                'format' => 'json',
                'startTime' => $startDate->format('Y-m-d\TH:i:s'),
                'endTime' => $endDate->format('Y-m-d\TH:i:s'),
                'minDur' => 0
            ], 180);

            $cdrPackets = $response['cdr_root'] ?? [];
            $count = 0;

            if (!empty($cdrPackets)) {
                $pbx->update(['sync_message' => "Procesando " . count($cdrPackets) . " paquetes CDR..."]);

                foreach ($cdrPackets as $cdrPacket) {
                    // Recolectar todos los segmentos del paquete (igual que SyncCalls command)
                    $segments = $this->collectCdrSegments($cdrPacket);
                    $segments = array_filter($segments, fn($s) => !empty($s['disposition']));

                    if (empty($segments)) continue;

                    // Consolidar segmentos en un registro de llamada
                    $consolidated = $this->consolidateCallData(array_values($segments));
                    if (empty($consolidated)) continue;

                    Call::withoutGlobalScope('current_pbx')->updateOrCreate(
                        [
                            'pbx_connection_id' => $pbx->id,
                            'unique_id' => $consolidated['unique_id']
                        ],
                        $consolidated
                    );
                    $count++;
                }
            }

            $pbx->update(['sync_message' => "Mes {$month}/{$year}: {$count} llamadas"]);

            return response()->json([
                'success' => true,
                'message' => "{$count} llamadas sincronizadas para {$month}/{$year}",
                'count' => $count,
                'hasMore' => $month < 12
            ]);

        } catch (\Exception $e) {
            $pbx->update(['sync_message' => 'Error: ' . $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Recolectar todos los segmentos de un paquete CDR recursivamente
     */
    private function collectCdrSegments(array $node): array
    {
        $collected = [];

        // Si este nodo tiene 'start', es un segmento válido
        if (isset($node['start']) && !empty($node['start'])) {
            $collected[] = $node;
        }

        // Buscar sub_cdr anidados
        foreach ($node as $key => $value) {
            if (is_array($value) && (str_starts_with($key, 'sub_cdr') || $key === 'main_cdr')) {
                $collected = array_merge($collected, $this->collectCdrSegments($value));
            }
        }

        return $collected;
    }

    /**
     * Consolidar segmentos en un solo registro de llamada
     */
    private function consolidateCallData(array $segments): array
    {
        if (empty($segments)) return [];

        $first = $segments[0];
        $firstSrc = $first['src'] ?? '';
        $firstDst = $first['dst'] ?? '';

        // Determinar tipo de llamada
        $esEntrante = $this->esExterno($firstSrc) && $this->esAnexo($firstDst);

        $data = [
            'unique_id' => null,
            'start_time' => null,
            'answer_time' => null,
            'source' => null,
            'destination' => null,
            'dstanswer' => null,
            'duration' => 0,
            'billsec' => 0,
            'disposition' => 'NO ANSWER',
            'action_type' => null,
            'lastapp' => null,
            'channel' => null,
            'dst_channel' => null,
            'src_trunk_name' => null,
            'caller_name' => null,
            'call_type' => $esEntrante ? 'inbound' : 'outbound',
        ];

        foreach ($segments as $seg) {
            $src = $seg['src'] ?? '';
            $dst = $seg['dst'] ?? '';

            // Capturar datos más tempranos/relevantes
            if (!$data['start_time'] || ($seg['start'] ?? '') < $data['start_time']) {
                $data['start_time'] = $seg['start'] ?? null;
            }
            $data['unique_id'] ??= $seg['acctid'] ?? $seg['uniqueid'] ?? null;
            $data['caller_name'] ??= $seg['caller_name'] ?? null;

            // Campos detallados
            $data['action_type'] ??= $seg['action_type'] ?? null;
            $data['lastapp'] ??= $seg['lastapp'] ?? null;
            $data['channel'] ??= $seg['channel'] ?? null;
            $data['dst_channel'] ??= $seg['dstchannel'] ?? null;
            $data['src_trunk_name'] ??= $seg['src_trunk_name'] ?? null;
            
            // Capturar answer_time si existe
            if (!empty($seg['answer']) && $seg['answer'] !== '0000-00-00 00:00:00') {
                $data['answer_time'] ??= $seg['answer'];
            }
            
            // Capturar dstanswer
            if (!empty($seg['dstanswer'])) {
                $data['dstanswer'] ??= $seg['dstanswer'];
            }

            // Sumar tiempos
            $data['duration'] += (int)($seg['duration'] ?? 0);
            $data['billsec'] += (int)($seg['billsec'] ?? 0);

            // Determinar origen/destino según tipo
            if ($esEntrante) {
                $data['source'] ??= $this->esAnexo($dst) ? $dst : null;
                $data['destination'] ??= $this->esExterno($src) ? $src : null;
            } else {
                $data['source'] ??= $this->esAnexo($src) ? $src : null;
                $data['destination'] ??= $dst ?: null;
            }

            // Si hay billsec > 0, fue contestada
            if ((int)($seg['billsec'] ?? 0) > 0) {
                $data['disposition'] = 'ANSWERED';
            }
        }

        // Valores por defecto
        $data['source'] ??= $firstSrc ?: 'Desconocido';
        $data['destination'] ??= $firstDst ?: 'Desconocido';
        $data['unique_id'] ??= md5($data['start_time'] . $data['source'] . $data['destination']);

        // Determinar disposition si no fue ANSWERED
        if ($data['disposition'] !== 'ANSWERED') {
            foreach ($segments as $seg) {
                $disp = strtoupper($seg['disposition'] ?? '');
                if (str_contains($disp, 'BUSY')) {
                    $data['disposition'] = 'BUSY';
                    break;
                } elseif (str_contains($disp, 'FAILED')) {
                    $data['disposition'] = 'FAILED';
                }
            }
        }

        return $data;
    }

    /**
     * Verificar si es un anexo/extensión interno (3-4 dígitos)
     */
    private function esAnexo(string $num): bool
    {
        return preg_match('/^\d{3,4}$/', $num) === 1;
    }

    /**
     * Verificar si es número externo
     */
    private function esExterno(string $num): bool
    {
        return strlen($num) > 4 || str_starts_with($num, '+') || str_starts_with($num, '9');
    }

    /**
     * Finalizar sincronización (AJAX - Solo Admin)
     */
    public function finishSync(PbxConnection $pbx): JsonResponse
    {
        $extensionCount = Extension::withoutGlobalScope('current_pbx')
            ->where('pbx_connection_id', $pbx->id)->count();
        $callCount = Call::withoutGlobalScope('current_pbx')
            ->where('pbx_connection_id', $pbx->id)->count();

        $pbx->update([
            'status' => PbxConnection::STATUS_READY,
            'sync_message' => "Sincronización completada. {$extensionCount} extensiones, {$callCount} llamadas.",
            'last_sync_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Central lista para usar'
        ]);
    }

    /**
     * Desconectar de la central activa
     */
    public function disconnect(): RedirectResponse
    {
        session()->forget(['active_pbx_id', 'active_pbx_name']);

        return redirect()->route('pbx.index')
            ->with('info', 'Desconectado de la central.');
    }

    // ========== MÉTODOS PRIVADOS ==========

    private function validatePbx(Request $request, bool $requirePassword): array
    {
        $rules = [
            'name' => 'nullable|string|max:255',
            'ip' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => $requirePassword ? 'required|string|max:255' : 'nullable|string|max:255',
            'verify_ssl' => 'boolean',
        ];

        $validated = $request->validate($rules);
        $validated['verify_ssl'] = $request->boolean('verify_ssl');

        return $validated;
    }

    private function setActivePbx(PbxConnection $pbx): void
    {
        session([
            'active_pbx_id' => $pbx->id,
            'active_pbx_name' => $pbx->name ?? $pbx->ip,
        ]);
    }

    /**
     * Configurar la conexión del trait para un PBX específico
     */
    private function setPbxConnection(PbxConnection $pbx): void
    {
        // El trait usa la sesión para obtener la conexión
        session(['active_pbx_id' => $pbx->id]);
    }

    /**
     * Determinar el tipo de llamada basado en el destino
     */
    private function determineCallType(string $destination): string
    {
        if (strlen($destination) <= 4) {
            return 'Interna';
        }
        if (preg_match('/^9/', $destination)) {
            return 'Celular';
        }
        if (preg_match('/^00/', $destination)) {
            return 'Internacional';
        }
        return 'Nacional';
    }
}
