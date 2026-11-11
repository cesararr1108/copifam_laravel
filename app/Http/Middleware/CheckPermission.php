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
        // Si no hay sesión activa
        if (!Session::has('ses_CopId')) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Sesión expirada. Por favor recargue la página.'], 401);
            }
            return redirect()->route('login');
        }

        $permisos = Session::get('ses_Permisos', []);

        // Normalizar a 4 dígitos para comparación robusta (por si la BD devuelve int)
        $codigoNorm   = str_pad($codigo, 4, '0', STR_PAD_LEFT);
        $permisosNorm = array_map(fn($p) => str_pad((string)(int)$p, 4, '0', STR_PAD_LEFT), $permisos);

        if (!in_array($codigoNorm, $permisosNorm, true)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'No tienes permiso para acceder a este recurso.'], 403);
            }
            return redirect()->route('dashboard')->with('error_permiso', 'No tienes permiso para acceder a ese módulo.');
        }

        return $next($request);
    }
}
