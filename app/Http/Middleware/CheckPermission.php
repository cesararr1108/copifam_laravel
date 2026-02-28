<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Valida que el usuario tenga el código de permiso requerido.
     *
     * Uso en rutas:
     *   ->middleware('permission:0101')
     */
    public function handle(Request $request, Closure $next, string $codigo): Response
    {
        // Si no hay sesión activa, redirigir al login
        if (!Session::has('ses_CopId')) {
            return redirect()->route('login');
        }

        $permisos = Session::get('ses_Permisos', []);

        if (!in_array($codigo, $permisos, true)) {
            // Sin permiso: redirigir al dashboard con mensaje de acceso denegado
            return redirect()->route('dashboard')->with('error_permiso', 'No tienes permiso para acceder a ese módulo.');
        }

        return $next($request);
    }
}
