<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckSession
{
    /**
     * Verifica que exista una sesión activa.
     * Redirige al login si no hay sesión.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::has('ses_CopId')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
