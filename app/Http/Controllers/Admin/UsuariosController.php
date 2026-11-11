<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('admin.usuarios');
    }

    /**
     * Catálogos para los selects.
     * T_DPTO_SYS  → ID, DESCRIPCION
     * T_ROLES     → ID, TITULO
     * T_BODEGAS   → ID, DESCRIPCION
     */
    public function catalogs(): JsonResponse
    {
        try {
            $departamentos = DB::select("SELECT ID, DESCRIPCION FROM T_DPTO_SYS ORDER BY DESCRIPCION");
            $roles         = DB::select("SELECT ID, TITULO FROM T_ROLES WHERE ESTADO = 'A' ORDER BY TITULO");
            $bodegas       = DB::select("
                SELECT B.ID, B2.DESCRIPCION
                FROM T_BODEGAS B
                INNER JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
                WHERE B.ESTADO = 'A'
                ORDER BY B2.DESCRIPCION
            ");

            return response()->json([
                'ok'            => true,
                'departamentos' => array_map(fn($d) => [
                    'id'     => $d->ID,
                    'nombre' => $d->DESCRIPCION,
                ], $departamentos),
                'roles'         => array_map(fn($r) => [
                    'id'     => $r->ID,
                    'nombre' => $r->TITULO,
                ], $roles),
                'bodegas'       => array_map(fn($b) => [
                    'id'    => $b->ID,
                    'label' => $b->DESCRIPCION,
                ], $bodegas),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error al cargar catálogos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DataTables server-side para la tabla de usuarios.
     */
    public function search(Request $request): JsonResponse
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = min(100, max(5, (int) $request->input('length', 25)));
        $search = strtoupper(trim($request->input('search.value', '') ?? ''));

        try {
            // Verificamos si la columna CORREO existe para evitar el error 500 en ciertos entornos
            $checkCorreo = DB::selectOne("SELECT COL_LENGTH('T_USUARIOS', 'CORREO') as has_correo");
            $colCorreo = ($checkCorreo && $checkCorreo->has_correo) ? "U.CORREO" : "NULL AS CORREO";

            $like     = "%{$search}%";
            $where    = $search !== ''
                ? "WHERE U.USUARIO LIKE ? OR U.NOMBRES LIKE ? OR U.APELLIDOS LIKE ?"
                : '';
            $bindings = $search !== '' ? [$like, $like, $like] : [];

            /* Total sin filtro */
            $total = DB::selectOne("SELECT COUNT(*) AS CNT FROM T_USUARIOS U")->CNT;

            /* Total con filtro */
            $filtered = $search !== ''
                ? DB::selectOne(
                    "SELECT COUNT(*) AS CNT FROM T_USUARIOS U {$where}",
                    $bindings
                  )->CNT
                : $total;

            /* Página */
            $colMap = [
                0 => 'U.USUARIO',
                1 => 'U.NOMBRES',
                2 => 'U.APELLIDOS',
                3 => 'CORREO',
                4 => 'U.TELEFONOS',
                5 => 'D.DESCRIPCION',
                6 => 'R.TITULO',
                7 => 'B2.DESCRIPCION',
                8 => 'U.ESTADO',
            ];
            $orderColIdx = (int) $request->input('order.0.column', 0);
            $orderDir    = strtoupper($request->input('order.0.dir', 'asc')) === 'DESC' ? 'DESC' : 'ASC';
            $orderCol    = $colMap[$orderColIdx] ?? 'U.USUARIO';
            $orderClause = "{$orderCol} {$orderDir}";

            $data = DB::select("
                SELECT
                    U.USUARIO, U.NOMBRES, U.APELLIDOS, {$colCorreo}, U.TELEFONOS,
                    U.ESTADO,
                    U.ROLES_ID, U.DPTO_ID, U.BODEGA AS BODEGA_ID,
                    R.TITULO          AS NOM_ROL,
                    D.DESCRIPCION     AS NOM_DPTO,
                    B2.DESCRIPCION    AS NOM_BODEGA
                FROM T_USUARIOS U
                LEFT JOIN T_ROLES    R  ON R.ID   = U.ROLES_ID
                LEFT JOIN T_DPTO_SYS D  ON D.ID   = U.DPTO_ID
                LEFT JOIN T_BODEGAS  B  ON B.ID   = U.BODEGA
                LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
                {$where}
                ORDER BY {$orderClause}
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ", array_merge($bindings, [$start, $length]));

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => (int) $total,
                'recordsFiltered' => (int) $filtered,
                'data'            => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Error al buscar usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Devuelve un usuario por su login.
     */
    public function show(string $usuario): JsonResponse
    {
        try {
            $user = DB::selectOne("
                SELECT USUARIO, NOMBRES, APELLIDOS, CORREO, TELEFONOS,
                       BODEGA AS BODEGA_ID, ESTADO, ROLES_ID, DPTO_ID
                FROM T_USUARIOS WHERE USUARIO = ?
            ", [strtoupper($usuario)]);

            if (!$user) {
                return response()->json(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);
            }

            return response()->json(['ok' => true, 'data' => $user]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error al obtener el usuario.'], 500);
        }
    }

    /**
     * Crea un usuario nuevo.
     * BODEGA en T_USUARIOS es int = ID de T_BODEGAS.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'usuario'   => ['required', 'string', 'min:3', 'max:50'],
            'clave'     => ['required', 'string', 'min:6'],
            'nombres'   => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo'    => ['nullable', 'email', 'max:150'],
            'telefonos' => ['nullable', 'string', 'max:20'],
            'dpto_id'   => ['required', 'integer'],
            'roles_id'  => ['required', 'integer'],
            'estado'    => ['required', 'in:A,I'],
        ], [
            'usuario.required'   => 'El usuario es obligatorio.',
            'usuario.min'        => 'El usuario debe tener al menos 3 caracteres.',
            'clave.required'     => 'La clave es obligatoria.',
            'clave.min'          => 'La clave debe tener al menos 6 caracteres.',
            'nombres.required'   => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'correo.email'       => 'Ingresa un correo electrónico válido.',
            'dpto_id.required'   => 'El departamento es obligatorio.',
            'roles_id.required'  => 'El rol es obligatorio.',
        ]);

        $usuario = strtoupper(trim($request->usuario));

        $existe = DB::selectOne("SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE USUARIO = ?", [$usuario]);
        if ($existe) {
            return response()->json(['ok' => false, 'message' => "El usuario '{$usuario}' ya existe en el sistema."], 422);
        }

        // Validar correo duplicado
        if ($request->correo) {
            $correoEnUso = DB::selectOne(
                "SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE LOWER(CORREO) = ?",
                [strtolower(trim($request->correo))]
            );
            if ($correoEnUso) {
                return response()->json(['ok' => false, 'message' => "El correo '{$request->correo}' ya está registrado en otro usuario."], 422);
            }
        }

        // Validar teléfono duplicado
        if ($request->telefonos) {
            $telEnUso = DB::selectOne(
                "SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE TELEFONOS = ?",
                [trim($request->telefonos)]
            );
            if ($telEnUso) {
                return response()->json(['ok' => false, 'message' => "El número '{$request->telefonos}' ya está registrado en otro usuario."], 422);
            }
        }

        try {
            $maxId = DB::selectOne("SELECT ISNULL(MAX(ID), 0) + 1 AS NEXT_ID FROM T_USUARIOS");
            $nextId = $maxId->NEXT_ID;

            DB::insert("
                INSERT INTO T_USUARIOS
                    (ID, USUARIO, CLAVE, NOMBRES, APELLIDOS, CORREO, TELEFONOS, DPTO_ID, ROLES_ID, BODEGA, ESTADO, FECHA_CREACION)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())
            ", [
                $nextId,
                $usuario,
                Hash::make($request->clave),
                strtoupper(trim($request->nombres)),
                strtoupper(trim($request->apellidos)),
                $request->correo    ? strtolower(trim($request->correo))    : null,
                $request->telefonos ? trim($request->telefonos)             : null,
                (int) $request->dpto_id,
                (int) $request->roles_id,
                (int) $request->bodega,
                $request->estado,
            ]);

            return response()->json(['ok' => true, 'message' => "Usuario '{$usuario}' creado correctamente."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error al guardar el usuario: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza un usuario.
     * Si viene solo_clave=1 solo actualiza la contraseña.
     */
    public function update(Request $request, string $usuario): JsonResponse
    {
        $usuario = strtoupper($usuario);

        /* ── Solo cambio de clave ──────────────────────────── */
        if ($request->input('solo_clave') === '1') {
            $request->validate([
                'clave' => ['required', 'string', 'min:6'],
            ], [
                'clave.required' => 'Debe ingresar la nueva clave.',
                'clave.min'      => 'La clave debe tener al menos 6 caracteres.',
            ]);

            try {
                $existe = DB::selectOne("SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE USUARIO = ?", [$usuario]);
                if (!$existe) {
                    return response()->json(['ok' => false, 'message' => 'No se encontró el usuario.'], 404);
                }

                DB::statement(
                    "UPDATE T_USUARIOS SET CLAVE = ? WHERE USUARIO = ?",
                    [Hash::make($request->clave), $usuario]
                );

                return response()->json(['ok' => true, 'message' => 'Clave actualizada correctamente.']);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => 'Error al cambiar la clave: ' . $e->getMessage()], 500);
            }
        }

        /* ── Actualización general ─────────────────────────── */
        $request->validate([
            'nombres'   => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo'    => ['nullable', 'email', 'max:150'],
            'telefonos' => ['nullable', 'string', 'max:20'],
            'dpto_id'   => ['required', 'integer'],
            'roles_id'  => ['required', 'integer'],
            'bodega'    => ['required', 'integer'],
            'estado'    => ['required', 'in:A,I'],
        ], [
            'nombres.required'   => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'correo.email'       => 'Ingresa un correo electrónico válido.',
            'dpto_id.required'   => 'El departamento es obligatorio.',
            'roles_id.required'  => 'El rol es obligatorio.',
            'bodega.required'    => 'La bodega es obligatoria.',
        ]);

        // Validar correo duplicado (excluyendo el usuario actual)
        if ($request->correo) {
            $correoEnUso = DB::selectOne(
                "SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE LOWER(CORREO) = ? AND USUARIO <> ?",
                [strtolower(trim($request->correo)), $usuario]
            );
            if ($correoEnUso) {
                return response()->json(['ok' => false, 'message' => "El correo '{$request->correo}' ya está registrado en otro usuario."], 422);
            }
        }

        // Validar teléfono duplicado (excluyendo el usuario actual)
        if ($request->telefonos) {
            $telEnUso = DB::selectOne(
                "SELECT TOP 1 USUARIO FROM T_USUARIOS WHERE TELEFONOS = ? AND USUARIO <> ?",
                [trim($request->telefonos), $usuario]
            );
            if ($telEnUso) {
                return response()->json(['ok' => false, 'message' => "El número '{$request->telefonos}' ya está registrado en otro usuario."], 422);
            }
        }

        try {
            DB::update("
                UPDATE T_USUARIOS
                SET NOMBRES = ?, APELLIDOS = ?, CORREO = ?, TELEFONOS = ?,
                    DPTO_ID = ?, ROLES_ID = ?, BODEGA = ?, ESTADO = ?
                WHERE USUARIO = ?
            ", [
                strtoupper(trim($request->nombres)),
                strtoupper(trim($request->apellidos)),
                $request->correo    ? strtolower(trim($request->correo))    : null,
                $request->telefonos ? trim($request->telefonos)             : null,
                (int) $request->dpto_id,
                (int) $request->roles_id,
                (int) $request->bodega,
                $request->estado,
                $usuario,
            ]);

            return response()->json(['ok' => true, 'message' => "Usuario '{$usuario}' actualizado correctamente."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error al actualizar el usuario.'], 500);
        }
    }
}
