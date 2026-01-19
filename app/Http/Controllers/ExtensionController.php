<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;

class ExtensionController extends Controller
{
    public function index()
    {
        $extensions = Extension::orderBy('extension', 'asc')->paginate(50);
        
        return view('configuracion', compact('extensions'));
    }

    public function updateName(Request $request)
    {
        // Validamos
        $request->validate([
            'extension_id' => 'required',
            'fullname' => 'required|string|max:255'
        ]);

        // Guardamos en la base de datos local
        Extension::updateOrCreate(
            ['extension' => $request->extension_id],
            ['fullname' => $request->fullname]
        );

        return back()->with('success', 'Nombre actualizado correctamente');
    }
}