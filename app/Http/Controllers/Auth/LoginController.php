<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Muestra el formulario de login.
     * Si ya hay sesión activa, redirige al dashboard.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Session::has('ses_CopId')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Procesa el login ejecutando el SP P_LOGIN en SQL Server.
     */
    public function login(Request $request): RedirectResponse
    {
        // Validación
        $request->validate([
            'usuario' => ['required', 'string', 'min:3', 'max:50'],
            'clave'   => ['required', 'string', 'min:3', 'max:100'],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'usuario.min'      => 'El usuario debe tener al menos 3 caracteres.',
            'clave.required'   => 'La contraseña es obligatoria.',
            'clave.min'        => 'La contraseña debe tener al menos 3 caracteres.',
        ]);

        $usuario = strtoupper(trim($request->input('usuario')));
        $clave   = md5(trim($request->input('clave')));

        try {
            $resultado = DB::select('SELECT * FROM t_usuarios WHERE USUARIO = ? AND CLAVE = ?', [
                $usuario,
                $clave,
            ]);
        } catch (\Exception $e) {
            // Mostrar error real temporalmente para diagnóstico (remover en producción)
            $mensajeError = config('app.debug')
                ? 'Error BD: ' . $e->getMessage()
                : 'Error de conexión con la base de datos. Intente nuevamente.';

            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['error' => $mensajeError]);
        }

        if (empty($resultado)) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['error' => 'El usuario o la contraseña son incorrectos.']);
        }

        $u = $resultado[0];

        // Guardar sesión — reemplaza los $_SESSION del sistema anterior
        Session::put([
            'ses_CopId'        => $u->ID,
            'ses_CopLogin'     => $u->USUARIO,
            'ses_CopNombres'   => $u->NOMBRES,
            'ses_CopApellidos' => $u->APELLIDOS,
            'ses_CopRol'       => $u->ROLES_ID,
            'ses_CopDtoId'     => $u->DPTO_ID,
            'ses_TipodDoc'     => 0,
            'ses_BogDefault'   => $u->BODEGA,
            'ses_CopBodegaId'  => $u->BODEGA,
            'ses_CopBodega'    => $u->DES_BOG_DF,
            'ses_DptoBodega'   => $u->DEPARTAMENTO,
            'ses_CiudadBodega' => $u->CIUDAD,
        ]);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Verifica que haya sesión activa.
     * Usar en rutas protegidas: LoginController::checkSession()
     */
    public static function checkSession(): bool
    {
        return Session::has('ses_CopId');
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(Request $request): RedirectResponse
    {
        Session::flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
