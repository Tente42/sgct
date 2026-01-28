<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPbxSelected
{
    /**
     * Rutas excluidas del middleware (no requieren central seleccionada)
     */
    protected array $excludedRoutes = [
        'login',
        'iniciar-sesion',
        'logout',
        'pbx.*',  // Todas las rutas de gestión de PBX
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si no hay usuario autenticado, dejar pasar (el middleware auth lo manejará)
        if (!auth()->check()) {
            return $next($request);
        }

        // Verificar si la ruta actual está excluida
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // Verificar si hay una central seleccionada en la sesión
        if (!session()->has('active_pbx_id')) {
            return redirect()
                ->route('pbx.index')
                ->with('warning', 'Debes seleccionar una central para continuar.');
        }

        return $next($request);
    }

    /**
     * Verificar si la ruta actual está excluida
     */
    protected function isExcludedRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        foreach ($this->excludedRoutes as $pattern) {
            // Patrón con wildcard (ej: pbx.*)
            if (str_contains($pattern, '*')) {
                $regex = str_replace('.', '\.', $pattern);
                $regex = str_replace('*', '.*', $regex);
                if (preg_match('/^' . $regex . '$/', $routeName)) {
                    return true;
                }
            } 
            // Coincidencia exacta
            elseif ($routeName === $pattern) {
                return true;
            }
        }

        return false;
    }
}
