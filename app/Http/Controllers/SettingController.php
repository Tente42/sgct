<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Mostrar la pagina de configuracion de tarifas
     */
    public function index()
    {
        // Verificar permiso para ver tarifas
        if (!auth()->user()->canViewRates()) {
            abort(403, 'No tienes permiso para ver las tarifas.');
        }

        $settings = Setting::all();
        return view('settings.index', compact('settings'));
    }

    /**
     * Actualizar las tarifas
     */
    public function update(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canEditRates()) {
            abort(403, 'No tienes permiso para editar tarifas.');
        }

        // Solo permitir claves de tarifa conocidas (seguridad: evitar manipulación de otros settings)
        $allowedKeys = ['price_mobile', 'price_national', 'price_international'];

        $request->validate(
            collect($allowedKeys)->mapWithKeys(fn($key) => [$key => 'nullable|integer|min:0'])->toArray()
        );

        foreach ($allowedKeys as $key) {
            if ($request->has($key)) {
                Setting::where('key', $key)->update(['value' => (int) $request->input($key)]);
            }
        }

        // Limpiar cache de tarifas en el modelo Call
        \App\Models\Call::clearPricesCache();

        return back()->with('success', 'Tarifas actualizadas correctamente.');
    }
}
