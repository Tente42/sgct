<?php

namespace App\Http\Controllers;

use App\Models\PbxConnection;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PbxConnectionController extends Controller
{
    /**
     * Mostrar lista de centrales PBX
     */
    public function index(): View
    {
        $connections = PbxConnection::orderBy('name')->get();
        
        return view('pbx.index', compact('connections'));
    }

    /**
     * Almacenar una nueva central PBX
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'ip' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'verify_ssl' => 'boolean',
        ]);

        // Establecer valor por defecto para verify_ssl
        $validated['verify_ssl'] = $request->boolean('verify_ssl');

        PbxConnection::create($validated);

        return redirect()
            ->route('pbx.index')
            ->with('success', 'Central PBX creada exitosamente.');
    }

    /**
     * Actualizar una central PBX existente
     */
    public function update(Request $request, PbxConnection $pbx): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'ip' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'verify_ssl' => 'boolean',
        ]);

        // Si no se proporciona password, mantener el actual
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['verify_ssl'] = $request->boolean('verify_ssl');

        $pbx->update($validated);

        return redirect()
            ->route('pbx.index')
            ->with('success', 'Central PBX actualizada exitosamente.');
    }

    /**
     * Eliminar una central PBX
     */
    public function destroy(PbxConnection $pbx): RedirectResponse
    {
        // Verificar si es la central activa
        if (session('active_pbx_id') === $pbx->id) {
            session()->forget(['active_pbx_id', 'active_pbx_name']);
        }

        $pbx->delete();

        return redirect()
            ->route('pbx.index')
            ->with('success', 'Central PBX eliminada exitosamente.');
    }

    /**
     * Seleccionar una central PBX como activa
     */
    public function select(PbxConnection $pbx): RedirectResponse
    {
        // Guardar en sesión la central seleccionada
        session([
            'active_pbx_id' => $pbx->id,
            'active_pbx_name' => $pbx->name ?? $pbx->ip,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', "Conectado a: {$pbx->name} ({$pbx->ip})");
    }

    /**
     * Desconectar de la central activa
     */
    public function disconnect(): RedirectResponse
    {
        session()->forget(['active_pbx_id', 'active_pbx_name']);

        return redirect()
            ->route('pbx.index')
            ->with('info', 'Desconectado de la central.');
    }
}
