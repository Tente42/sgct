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
    use \App\Http\Controllers\Concerns\ProcessesCdr;

    /**
     * Lista todas las centrales PBX
     * Admins ven todas. Usuarios normales ven solo las centrales "ready" que tienen asignadas.
     */
    public function index(): View
    {
        $user = auth()->user();
        
        $query = PbxConnection::orderBy('name');
        
        // Usuarios no-admin solo ven centrales listas y asignadas
        if (!$user->isAdmin()) {
            $allowedIds = $user->pbxConnections()->pluck('pbx_connection_id')->toArray();
            $query->where('status', PbxConnection::STATUS_READY)
                  ->whereIn('id', $allowedIds);
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

        // Si no es admin, verificar que tenga acceso a esta central
        if (!$user->isAdmin() && !$user->canAccessPbx($pbx->id)) {
            return redirect()->route('pbx.index')
                ->with('error', 'No tienes acceso a esta central.');
        }

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
        // Sin límite de tiempo para sincronización larga
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '512M');

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
     * Recibe un año y mes para sincronizar.
     * Usa ProcessesCdr trait (misma lógica que el comando calls:sync)
     * con paginación automática vía numRecords.
     */
    public function syncCalls(Request $request, PbxConnection $pbx): JsonResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1024M');

        try {
            $year = (int) $request->input('year', date('Y'));
            $month = (int) $request->input('month', 1);

            $pbx->update([
                'status' => PbxConnection::STATUS_SYNCING,
                'sync_message' => "Sincronizando llamadas de {$month}/{$year}..."
            ]);

            $this->setPbxConnection($pbx);

            if (!$this->testConnection()) {
                return response()->json(['success' => false, 'message' => 'Error de conexión'], 500);
            }

            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth()->min(Carbon::now());

            if ($startDate->greaterThan(Carbon::now())) {
                return response()->json([
                    'success' => true,
                    'message' => 'Mes futuro, saltando',
                    'count' => 0,
                    'hasMore' => $month < 12
                ]);
            }

            $pbx->update(['sync_message' => "Obteniendo CDRs de {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')}..."]);

            // Paginación automática
            $maxPerRequest = 5000;
            $pageStart = $startDate->copy();
            $totalCount = 0;
            $pagina = 1;

            do {
                $response = $this->connectApi('cdrapi', [
                    'format' => 'json',
                    'numRecords' => $maxPerRequest,
                    'startTime' => $pageStart->format('Y-m-d\TH:i:s'),
                    'endTime' => $endDate->format('Y-m-d\TH:i:s'),
                    'minDur' => 0
                ], 180);

                $cdrPackets = $response['cdr_root'] ?? [];
                $batchCount = count($cdrPackets);

                if ($batchCount === 0) break;

                $pbx->update(['sync_message' => "Procesando {$batchCount} paquetes CDR (página {$pagina})..."]);

                foreach ($cdrPackets as $cdrPacket) {
                    $segments = $this->collectCdrSegments($cdrPacket);
                    $segments = array_filter($segments, fn($s) => !empty($s['disposition']));
                    if (empty($segments)) continue;

                    $consolidated = $this->consolidateCdrSegments(array_values($segments));
                    if (empty($consolidated)) continue;

                    Call::withoutGlobalScope('current_pbx')->updateOrCreate(
                        [
                            'pbx_connection_id' => $pbx->id,
                            'unique_id' => $consolidated['unique_id']
                        ],
                        $consolidated
                    );
                    $totalCount++;
                }

                // Si recibimos el máximo, puede haber más → paginar
                if ($batchCount >= $maxPerRequest) {
                    $lastCall = end($cdrPackets);
                    $lastStart = $lastCall['start'] ?? ($lastCall['main_cdr']['start'] ?? null);
                    if ($lastStart) {
                        $newStart = Carbon::parse($lastStart);
                        if ($newStart->lessThanOrEqualTo($pageStart)) break; // evitar loop infinito
                        $pageStart = $newStart;
                        $pagina++;
                        continue;
                    }
                }

                break; // No hay más páginas
            } while (true);

            $pbx->update(['sync_message' => "Mes {$month}/{$year}: {$totalCount} llamadas"]);

            return response()->json([
                'success' => true,
                'message' => "{$totalCount} llamadas sincronizadas para {$month}/{$year}",
                'count' => $totalCount,
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
        // Guardar en sesión para el binding del service provider
        session(['active_pbx_id' => $pbx->id]);
        // Reconfigurar el servicio directamente para la request actual
        $this->getGrandstreamService()->setConnectionFromModel($pbx);
    }

}
