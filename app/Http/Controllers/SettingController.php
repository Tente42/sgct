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

        // Recorrer todos los inputs excepto el token CSRF
        foreach ($request->except('_token') as $key => $value) {
            Setting::where('key', $key)->update(['value' => (int) $value]);
        }

        return back()->with('success', 'Tarifas actualizadas correctamente.');
    }
}
