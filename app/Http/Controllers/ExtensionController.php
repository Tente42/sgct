<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Extension;

class ExtensionController extends Controller
{
    public function index(Request $request)
    {
        $anexo = $request->input('anexo');

        $extensions = Extension::query()
            ->when($anexo, fn ($q) => $q->where('extension', 'like', "%{$anexo}%"))
            ->orderBy('extension', 'asc')
            ->paginate(50)
            ->appends($request->only('anexo'));
        
        return view('configuracion', compact('extensions', 'anexo'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'extension' => 'required|string',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'permission' => 'required|in:Internal,Local,National,International',
            'max_contacts' => 'required|integer|min:1|max:10',
        ]);

        $extension = Extension::where('extension', $request->extension)->first();

        if (!$extension) {
            return back()->with('error', 'Anexo no encontrado');
        }

        $extension->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'permission' => $request->permission,
            'do_not_disturb' => $request->boolean('do_not_disturb'),
            'max_contacts' => $request->max_contacts,
        ]);

        return back()->with('success', "Anexo {$request->extension} actualizado correctamente");
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