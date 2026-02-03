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

            // Obtener lista de extensiones
            $listResponse = $this->connectApi('listAccount', [
                'options' => 'extension,user_id',
                'item_num' => 1000,
                'sidx' => 'extension',
                'sord' => 'asc'
            ]);

            $accounts = $listResponse['response']['account'] ?? 
                        $listResponse['response']['body']['account'] ?? [];

            if (empty($accounts)) {
                $pbx->update(['sync_message' => 'No se encontraron extensiones en la central.']);
                return response()->json(['success' => true, 'message' => 'Sin extensiones', 'count' => 0]);
            }

            $total = count($accounts);
            $synced = 0;

            foreach ($accounts as $account) {
                $extensionNumber = $account['extension'] ?? null;
                if (!$extensionNumber) continue;

                $pbx->update(['sync_message' => "Sincronizando extensión {$extensionNumber} ({$synced}/{$total})..."]);

                // Obtener datos completos de la extensión
                $userInfo = $this->connectApi('getUser', ['user_name' => $extensionNumber]);
                
                $userData = $userInfo['response']['user_name'] 
                         ?? $userInfo['response'][$extensionNumber] 
                         ?? $userInfo['response'] 
                         ?? [];

                // Obtener IP actual
                $liveData = $this->connectApi('listAccount', [
                    'options' => 'extension,addr',
                    'item_num' => 1,
                    'sidx' => 'extension',
                    'sord' => 'asc'
                ]);

                $ip = null;
                $liveAccounts = $liveData['response']['account'] ?? [];
                foreach ($liveAccounts as $live) {
                    if (($live['extension'] ?? '') === $extensionNumber) {
                        $ip = ($live['addr'] ?? null) !== '-' ? ($live['addr'] ?? null) : null;
                        break;
                    }
                }

                // Mapear permisos
                $permission = 'Internal';
                $permRaw = $userData['permission'] ?? '';
                if (str_contains($permRaw, 'international')) $permission = 'International';
                elseif (str_contains($permRaw, 'national')) $permission = 'National';
                elseif (str_contains($permRaw, 'local')) $permission = 'Local';

                // Guardar o actualizar extensión
                Extension::withoutGlobalScope('current_pbx')->updateOrCreate(
                    [
                        'pbx_connection_id' => $pbx->id,
                        'extension' => $extensionNumber
                    ],
                    [
                        'first_name' => $userData['first_name'] ?? null,
                        'last_name' => $userData['last_name'] ?? null,
                        'fullname' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')) ?: null,
                        'email' => $userData['email'] ?? null,
                        'phone' => $userData['phone_number'] ?? null,
                        'ip' => $ip,
                        'permission' => $permission,
                        'do_not_disturb' => ($userData['dnd'] ?? 'no') === 'yes',
                        'max_contacts' => (int)($userData['max_contacts'] ?? 1),
                        'secret' => $userData['secret'] ?? null,
                    ]
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

            $cdrs = $response['cdr_root'] ?? [];
            $count = 0;

            if (!empty($cdrs)) {
                $pbx->update(['sync_message' => "Procesando " . count($cdrs) . " registros..."]);

                foreach ($cdrs as $cdr) {
                    $uniqueId = $cdr['AcctSessionId'] ?? ($cdr['src'] . '_' . $cdr['StartTime']);

                    Call::withoutGlobalScope('current_pbx')->updateOrCreate(
                        [
                            'pbx_connection_id' => $pbx->id,
                            'unique_id' => $uniqueId
                        ],
                        [
                            'source' => $cdr['src'] ?? 'unknown',
                            'destination' => $cdr['dst'] ?? 'unknown',
                            'caller_name' => $cdr['CallerIDName'] ?? null,
                            'start_time' => $cdr['StartTime'] ?? now(),
                            'duration' => (int)($cdr['Duration'] ?? 0),
                            'billsec' => (int)($cdr['Billsec'] ?? 0),
                            'disposition' => $cdr['Disposition'] ?? 'UNKNOWN',
                            'call_type' => $this->determineCallType($cdr['dst'] ?? ''),
                            // Nuevos campos detallados del CDR
                            'action_type' => $cdr['action_type'] ?? null,
                            'answer_time' => $cdr['AnswerTime'] ?? null,
                            'dstanswer' => $cdr['dstanswer'] ?? null,
                            'lastapp' => $cdr['lastapp'] ?? null,
                            'channel' => $cdr['channel'] ?? null,
                            'dst_channel' => $cdr['dstchannel'] ?? null,
                            'src_trunk_name' => $cdr['src_trunk_name'] ?? null,
                        ]
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
