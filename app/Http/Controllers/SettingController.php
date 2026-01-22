<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Mostrar la página de configuración de tarifas
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
        // Recorrer todos los inputs excepto el token CSRF
        foreach ($request->except('_token') as $key => $value) {
            Setting::where('key', $key)->update(['value' => (int) $value]);
        }

        return back()->with('success', 'Tarifas actualizadas correctamente.');
    }
}
