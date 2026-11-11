<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Muestra el formulario de recuperación de contraseña.
     */
    public function showForm(): View
    {
        return view('auth.password-reset');
    }

    /**
     * Procesa la solicitud de recuperación.
     * Verifica que el usuario exista y esté activo en T_USUARIOS.
     */
    public function enviarSolicitud(Request $request): RedirectResponse
    {
        $request->validate([
            'usuario' => ['required', 'string', 'min:3', 'max:50'],
        ], [
            'usuario.required' => 'El usuario es obligatorio.',
            'usuario.min'      => 'El usuario debe tener al menos 3 caracteres.',
        ]);

        $usuario = strtoupper(trim($request->input('usuario')));

        try {
            $user = DB::selectOne("
                SELECT TOP 1 USUARIO, CORREO, ESTADO
                FROM T_USUARIOS
                WHERE USUARIO = ? 
            ", [$usuario]);
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Error de conexión con la base de datos. Intente nuevamente.']);
        }

        //  Validación 1: Usuario no existe
        if (!$user) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'El usuario ingresado no existe en el sistema.']);
        }

        //  Validación 2: Usuario inactivo
        if ($user->ESTADO !== 'A') {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Este usuario se encuentra inactivo. Contacte al administrador.']);
        }

        //  Validación 3: Sin correo registrado
        if (empty($user->CORREO)) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Este usuario no tiene un correo registrado. Contacte al administrador.']);
        }

        //  Generar token único y guardarlo en BD
        $token = Str::random(64);

        try {
            // Eliminar tokens anteriores del mismo usuario
            DB::delete("DELETE FROM T_PASSWORD_RESETS WHERE USUARIO = ?", [$usuario]);

            // Insertar nuevo token — EXPIRA_EN calculado por SQL Server
            DB::insert("
                INSERT INTO T_PASSWORD_RESETS (USUARIO, TOKEN, EXPIRA_EN, USADO)
                VALUES (?, ?, DATEADD(MINUTE, 60, GETDATE()), 0)
            ", [$usuario, $token]);
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Error al generar el enlace de recuperación. Intente nuevamente.']);
        }

        //  Enviar correo con el enlace
        $enlace = route('password.nueva', ['token' => $token]);
        $correo = $user->CORREO;

        try {
            Mail::send('emails.password-reset', [
                'usuario' => $usuario,
                'enlace'  => $enlace,
            ], function ($message) use ($correo, $usuario) {
                $message->to($correo)
                        ->subject('Restablecer contraseña — Copifam');
            });
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('usuario'))
                ->withErrors(['usuario' => 'Error SMTP: ' . $e->getMessage()]);
        }

        //  Redirigir a pantalla de confirmación
        return redirect()->route('password.reset.sent');
    }

    /**
     * Muestra la pantalla de confirmación de envío.
     */
    public function enviado(): View
    {
        return view('auth.password-reset-sent');
    }

    /**
     * Muestra el formulario para ingresar la nueva contraseña.
     */
    public function showNuevaForm(Request $request, string $token): View|RedirectResponse
    {
        $reset = null;
        try {
            $reset = DB::selectOne("
                SELECT USUARIO, TOKEN, EXPIRA_EN, USADO
                FROM T_PASSWORD_RESETS
                WHERE TOKEN = ? AND USADO = 0 AND EXPIRA_EN > GETDATE()
            ", [$token]);
        } catch (\Throwable $e) {
            return redirect()->route('password.reset')
                ->withErrors(['usuario' => 'Error de conexión. No se pudo verificar el enlace. Intente nuevamente.']);
        }

        if (!$reset) {
            return redirect()->route('password.reset')
                ->withErrors(['usuario' => 'El enlace de recuperación no es válido, ya fue utilizado o ha expirado. Solicita uno nuevo.']);
        }

        return view('auth.password-nueva', ['token' => $token]);
    }

    /**
     * Procesa el cambio de contraseña.
     */
    public function cambiarContrasena(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'clave'    => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'clave.required'  => 'La contraseña es obligatoria.',
            'clave.min'       => 'La contraseña debe tener al menos 6 caracteres.',
            'clave.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $token = $request->input('token');

        $reset = DB::selectOne("
            SELECT USUARIO, EXPIRA_EN, USADO
            FROM T_PASSWORD_RESETS
            WHERE TOKEN = ? AND USADO = 0 AND EXPIRA_EN > GETDATE()
        ", [$token]);

        if (!$reset) {
            return redirect()->route('password.reset')
                ->withErrors(['usuario' => 'El enlace de recuperación no es válido, ya fue utilizado o ha expirado. Solicita uno nuevo.']);
        }

        $nuevaClave = Hash::make($request->input('clave'));

        try {
            // Actualizar contraseña
            DB::update("
                UPDATE T_USUARIOS SET CLAVE = ? WHERE USUARIO = ?
            ", [$nuevaClave, $reset->USUARIO]);

            // Marcar token como usado
            DB::update("
                UPDATE T_PASSWORD_RESETS SET USADO = 1 WHERE TOKEN = ?
            ", [$token]);
        } catch (\Throwable $e) {
            return back()->withErrors(['clave' => 'Error al actualizar la contraseña. Intente nuevamente.']);
        }

        return redirect()->route('login')
            ->with('success', '¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.');
    }
}
