<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Muestra el formulario
    public function showLogin() {
        return view('login');
    }

    // Procesa los datos
    public function login(Request $request) {
        // 1. Validamos que llegue un 'name' (Usuario) y 'password'
        $credentials = $request->validate([
            'name' => ['required', 'string'], 
            'password' => ['required'],
        ]);

        // Easter Egg: Si el usuario y contraseña son "doom", redirige al juego
        if ($request->input('name') === 'doom' && $request->input('password') === 'doom') {
            return redirect()->route('doom');
        }

        // 2. Intentamos loguear
        // Laravel buscara automaticamente en la columna 'name' de la BD
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            return redirect()->route('dashboard');
        }

        // 3. Si falla, devolvemos error
        return back()->withErrors([
            'name' => 'Usuario o contraseña incorrectos.', 
        ]);
    }

    // Cerrar sesión
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}