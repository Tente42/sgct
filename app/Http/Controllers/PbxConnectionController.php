<?php

namespace App\Http\Controllers;

use App\Models\PbxConnection;
use App\Models\Call;
use App\Models\Extension;
use App\Jobs\SyncPbxDataJob;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class PbxConnectionController extends Controller
{
    public function index(): View
    {
        return view('pbx.index', [
            'connections' => PbxConnection::orderBy('name')->get()
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PbxConnection::create($this->validatePbx($request, true));

        return redirect()->route('pbx.index')
            ->with('success', 'Central PBX creada exitosamente.');
    }

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

    public function destroy(PbxConnection $pbx): RedirectResponse
    {
        if (session('active_pbx_id') === $pbx->id) {
            session()->forget(['active_pbx_id', 'active_pbx_name']);
        }

        $pbx->delete();

        return redirect()->route('pbx.index')
            ->with('success', 'Central PBX eliminada exitosamente.');
    }

    public function select(PbxConnection $pbx): RedirectResponse
    {
        $this->setActivePbx($pbx);

        $hasData = $this->pbxHasData($pbx);

        if (!$hasData) {
            return redirect()->route('pbx.setup', $pbx)
                ->with('info', "Central '{$pbx->name}' seleccionada. Se requiere sincronización inicial.");
        }

        return redirect()->route('dashboard')
            ->with('success', "Conectado a: {$pbx->name} ({$pbx->ip})");
    }

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

    public function checkSyncStatus(PbxConnection $pbx)
    {
        return response()->json([
            'syncing' => Cache::has("pbx_sync_lock_{$pbx->id}"),
            'progress' => Cache::get("pbx_sync_progress_{$pbx->id}")
        ]);
    }

    public function syncInitial(Request $request, PbxConnection $pbx): RedirectResponse
    {
        $lockKey = "pbx_sync_lock_{$pbx->id}";

        if (Cache::has($lockKey)) {
            return redirect()->back()
                ->with('warning', 'Ya hay una sincronización en progreso.');
        }

        $syncExtensions = $request->boolean('sync_extensions');
        $syncCalls = $request->boolean('sync_calls');
        $year = (int) $request->input('year', date('Y'));

        if (!$syncExtensions && !$syncCalls) {
            return redirect()->back()
                ->with('warning', 'No se seleccionó ninguna opción de sincronización.');
        }

        // Crear lock y despachar job
        Cache::put($lockKey, [
            'user' => auth()->user()->name ?? 'Sistema',
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        Cache::put("pbx_sync_progress_{$pbx->id}", 'Iniciando sincronización...', 3600);

        SyncPbxDataJob::dispatch($pbx->id, $syncExtensions, $syncCalls, $year, auth()->user()->name ?? 'Sistema');

        return redirect()->route('dashboard')
            ->with('info', 'Sincronización iniciada en segundo plano.');
    }

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

    private function pbxHasData(PbxConnection $pbx): bool
    {
        return Call::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->exists()
            || Extension::withoutGlobalScope('current_pbx')
                ->where('pbx_connection_id', $pbx->id)->exists();
    }
}
