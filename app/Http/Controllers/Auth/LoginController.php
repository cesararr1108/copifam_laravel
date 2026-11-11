<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $clave   = trim($request->input('clave'));

        try {
            $resultado = DB::select("
                SELECT TOP 1
                    U.*,
                    X.BODEGA AS DES_BOG_DF,
                    X.BODEGA AS DES_BOG,
                    X.ID     AS ID_BODEGA,
                    1        AS cant_bog,
                    D.DESCRIPCION AS DES_DPTO
                FROM T_USUARIOS U
                LEFT JOIN dbo.T_BODEGAS X  ON X.BODEGA = U.BODEGA
                LEFT JOIN dbo.T_DPTO_SYS D ON D.ID     = U.DPTO_ID
                WHERE U.USUARIO = ?
                  AND U.ESTADO  = 'A'
            ", [$usuario]);
        } catch (\Exception $e) {
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

        // Verificar contraseña: soporta Hash de Laravel
        $claveValida = Hash::check($clave, $u->CLAVE);


        if (!$claveValida) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['error' => 'El usuario o la contraseña son incorrectos.']);
        }

        // Guardar sesión — reemplaza los $_SESSION del sistema anterior
        Session::put([
            'ses_CopId'        => $u->ID,
            'ses_CopLogin'     => $u->USUARIO,
            'ses_CopNombres'   => $u->NOMBRES,
            'ses_CopApellidos' => $u->APELLIDOS,
            'ses_CopRol'       => $u->ROLES_ID,
            'ses_CopDtoId'     => $u->DPTO_ID,
            'ses_CopDpto'      => $u->DES_DPTO,   // Descripción del departamento
            'ses_TipodDoc'     => 0,
            'ses_BogDefault'   => $u->BODEGA,
            'ses_CopBodegaId'  => $u->BODEGA,
            'ses_CopBodega'    => $u->DES_BOG_DF,
            'ses_DptoBodega'   => null,
            'ses_CiudadBodega' => null,
        ]);

        $request->session()->regenerate();

        // Cargar permisos del rol en sesión
        $this->cargarPermisos((int) $u->ROLES_ID);

        return redirect()->route('dashboard');
    }

    /**
     * Consulta los códigos de submódulos permitidos para el rol
     * y los guarda en sesión como array plano de strings.
     */
    private function cargarPermisos(int $rolId): void
    {
        try {
            $permisos = DB::select("
                SELECT Y.NUMERO AS CODIGO
                FROM T_PERMISOS_ROLES          M
                INNER JOIN T_MODULOS_PERMISOS  X ON X.ID       = M.MODULOS_PERMISOS_ID
                INNER JOIN T_MODULOS           Y ON Y.ID       = X.ID_MODULO
                WHERE M.ID_ROLES  = ?
                  AND X.ID_PERMISO = 1
                ORDER BY Y.GRUPO
            ", [$rolId]);

            // Extraer solo los códigos como array plano, zero-padded a 4 dígitos: ["0101","0301",...]
            $codigos = array_map(fn($row) => str_pad((string)(int)$row->CODIGO, 4, '0', STR_PAD_LEFT), $permisos);

            Session::put('ses_Permisos', $codigos);
        } catch (\Exception $e) {
            // Si falla la carga de permisos, sesión vacía (sin acceso a módulos)
            Session::put('ses_Permisos', []);
        }
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
