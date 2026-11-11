<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * El modelo NO usa la tabla users de Laravel.
     * La autenticación se realiza mediante el SP P_LOGIN en SQL Server.
     * Este modelo actúa como contenedor de los datos de sesión del usuario.
     */

    // No usamos auto-incremento estándar de Laravel
    public $incrementing = false;
    public $timestamps   = false;

    // Clave primaria según el campo ID que devuelve P_LOGIN
    protected $primaryKey = 'ID';
    protected $keyType    = 'int';

    /**
     * Campos que devuelve el SP P_LOGIN mapeados al modelo.
     * Equivalente a los $_SESSION del sistema anterior.
     */
    protected $fillable = [
        'ID',
        'USUARIO',
        'NOMBRES',
        'APELLIDOS',
        'ROLES_ID',
        'DPTO_ID',
        'BODEGA',
        'DES_BOG_DF',
        'DEPARTAMENTO',
        'CIUDAD',
    ];

    /**
     * Campos que NO se serializan en respuestas JSON.
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * Equivalencias entre los campos del SP y los nombres de sesión originales.
     * Documentado para referencia durante la migración.
     *
     * SP campo         => $_SESSION original
     * ─────────────────────────────────────────
     * ID               => ses_CopId
     * USUARIO          => ses_CopLogin
     * NOMBRES          => ses_CopNombres
     * APELLIDOS        => ses_CopApellidos
     * ROLES_ID         => ses_CopRol
     * DPTO_ID          => ses_CopDtoId
     * BODEGA           => ses_CopBodegaId / ses_BogDefault
     * DES_BOG_DF       => ses_CopBodega
     * DEPARTAMENTO     => ses_DptoBodega
     * CIUDAD           => ses_CiudadBodega
     */
}
