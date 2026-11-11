<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class TicketsController extends Controller
{
    /**
     * GET /soporte/catalogos
     * Retorna categorías y subcategorías para los selects del formulario.
     */
    public function catalogs(): JsonResponse
    {
        try {
            $categorias    = DB::select("SELECT categoria_id AS id, nombre_categoria AS nombre FROM categorias ORDER BY nombre_categoria");
            $subcategorias = DB::select("SELECT subcategoria_id AS id, nombre_subcategoria AS nombre, categoria_id FROM subcategorias ORDER BY nombre_subcategoria");

            // Puntos de venta disponibles (con al menos 1 ticket registrado)
            $puntosVenta = DB::select("
                SELECT DISTINCT B2.DESCRIPCION AS nombre
                FROM tickets t
                INNER JOIN T_BODEGAS B  ON B.ID     = t.bodega
                INNER JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
                WHERE B2.DESCRIPCION IS NOT NULL AND B2.DESCRIPCION <> ''
                ORDER BY B2.DESCRIPCION
            ");

            return response()->json([
                'ok'           => true,
                'categorias'   => $categorias,
                'subcategorias'=> $subcategorias,
                'puntos_venta' => $puntosVenta,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())], 500);
        }
    }

    /* ─────────────────── helpers de rol ─────────────────────────── */

    /** IDs de roles que pueden ver TODOS los tickets */
    private function rolId(): int
    {
        return (int) Session::get('ses_CopRol', 0);
    }

    private function tituloRol(): string
    {
        $rol = DB::selectOne("SELECT TITULO FROM T_ROLES WHERE ID = ?", [$this->rolId()]);
        return strtolower($rol?->TITULO ?? '');
    }

    private function esAdminOSistemas(): bool
    {
        $t = $this->tituloRol();
        return str_contains($t, 'administrador') || str_contains($t, 'sistema');
    }

    private function esSistemas(): bool
    {
        return str_contains($this->tituloRol(), 'sistema');
    }

    /* ─────────────────── query base ─────────────────────────────── */

    private function queryTickets(string $whereExtra = '', array $bindings = []): array
    {
        return DB::select("
            SELECT
                t.id_ticket,
                CONVERT(varchar(19), t.fecha_creacion, 120) AS fecha_creacion,
                t.nombre_solicitante,
                t.numero_contacto,
                t.pdv_area,
                t.bodega,
                B2.DESCRIPCION        AS bodega_nombre,
                t.titulo_ticket,
                t.descripcion_detallada,
                t.archivo_adjunto,
                t.prioridad,
                t.estado,
                t.usuario,
                t.persona_encargada,
                CONVERT(varchar(19), t.fecha_inicio,    120) AS fecha_inicio,
                CONVERT(varchar(10), t.fecha_estimada,  23)  AS fecha_estimada,
                CONVERT(varchar(19), t.fecha_solucion,  120) AS fecha_solucion,
                t.tiempo_atencion,
                t.validacion_usuario,
                t.nota_encargado,
                t.nota_rechazo_usuario,
                t.categoria_id,
                t.subcategoria_id,
                c.nombre_categoria    AS nombre_categoria,
                s.nombre_subcategoria AS nombre_subcategoria
            FROM tickets t
            LEFT JOIN categorias    c  ON c.categoria_id    = t.categoria_id
            LEFT JOIN subcategorias s  ON s.subcategoria_id = t.subcategoria_id
            LEFT JOIN T_BODEGAS     B  ON B.ID              = t.bodega
            LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
            {$whereExtra}
            ORDER BY t.id_ticket DESC
        ", $bindings);
    }

    /**
     * GET /soporte/tickets
     * Lista paginada con protocolo DataTables server-side.
     * Acepta: draw, start, length, search[value], estado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $draw   = (int) $request->input('draw', 1);
            $start  = max(0, (int) $request->input('start', 0));
            $length = min(100, max(5, (int) $request->input('length', 25)));
            $search = trim($request->input('search.value', '') ?? '');
            $estado = trim($request->input('estado', ''));

            $where    = [];
            $bindings = [];

            // Filtro por usuario (no admin/sistemas solo ven los suyos)
            if (!$this->esAdminOSistemas()) {
                $where[]    = "t.usuario = ?";
                $bindings[] = Session::get('ses_CopLogin', '');
            }

            // Búsqueda libre
            if ($search !== '') {
                $like     = '%' . $search . '%';
                $where[]  = "(CAST(t.id_ticket AS VARCHAR) LIKE ? OR t.nombre_solicitante LIKE ? OR t.titulo_ticket LIKE ? OR t.persona_encargada LIKE ?)";
                $bindings = array_merge($bindings, [$like, $like, $like, $like]);
            }

            // Filtro estado
            if ($estado !== '' && $estado !== 'todos') {
                $where[]    = "LOWER(t.estado) = LOWER(?)";
                $bindings[] = $estado;
            }

            $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $baseJoins = "
                FROM tickets t
                LEFT JOIN categorias    c  ON c.categoria_id    = t.categoria_id
                LEFT JOIN subcategorias s  ON s.subcategoria_id = t.subcategoria_id
                LEFT JOIN T_BODEGAS     B  ON B.ID              = t.bodega
                LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
            ";

            // Total sin filtros de usuario/búsqueda
            $baseWhereTotal = [];
            $bindingsTotal  = [];
            if (!$this->esAdminOSistemas()) {
                $baseWhereTotal[] = "t.usuario = ?";
                $bindingsTotal[]  = Session::get('ses_CopLogin', '');
            }
            $whereTotal   = count($baseWhereTotal) > 0 ? 'WHERE ' . implode(' AND ', $baseWhereTotal) : '';
            $recordsTotal = DB::selectOne("SELECT COUNT(*) AS total {$baseJoins} {$whereTotal}", $bindingsTotal)?->total ?? 0;

            // Total con filtros
            $recordsFiltered = DB::selectOne("SELECT COUNT(*) AS total {$baseJoins} {$whereClause}", $bindings)?->total ?? 0;

            // ── Ordenamiento dinámico enviado por DataTables ───────────────
            $colMap = [
                0 => 't.id_ticket',
                1 => 't.nombre_solicitante',
                2 => 't.titulo_ticket',
                3 => 't.prioridad',
                4 => 't.persona_encargada',
                5 => 't.fecha_creacion',
                6 => 't.estado',
            ];
            $orderColIdx = (int) $request->input('order.0.column', 0);
            $orderDir    = strtoupper($request->input('order.0.dir', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
            $orderCol    = $colMap[$orderColIdx] ?? 't.id_ticket';
            $orderClause = "{$orderCol} {$orderDir}";

            // Datos paginados
            $tickets = DB::select("
                SELECT
                    t.id_ticket,
                    CONVERT(varchar(19), t.fecha_creacion, 120) AS fecha_creacion,
                    t.nombre_solicitante,
                    t.numero_contacto,
                    t.pdv_area,
                    t.bodega,
                    B2.DESCRIPCION        AS bodega_nombre,
                    t.titulo_ticket,
                    t.descripcion_detallada,
                    t.archivo_adjunto,
                    t.prioridad,
                    t.estado,
                    t.usuario,
                    t.persona_encargada,
                    CONVERT(varchar(19), t.fecha_inicio,    120) AS fecha_inicio,
                    CONVERT(varchar(10), t.fecha_estimada,  23)  AS fecha_estimada,
                    CONVERT(varchar(19), t.fecha_solucion,  120) AS fecha_solucion,
                    t.tiempo_atencion,
                    t.validacion_usuario,
                    t.nota_encargado,
                    t.nota_rechazo_usuario,
                    t.categoria_id,
                    t.subcategoria_id,
                    c.nombre_categoria,
                    s.nombre_subcategoria
                {$baseJoins}
                {$whereClause}
                ORDER BY {$orderClause}
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ", array_merge($bindings, [$start, $length]));

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => (int) $recordsTotal,
                'recordsFiltered' => (int) $recordsFiltered,
                'data'            => $tickets,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * GET /soporte/tickets/seguimiento
     * Búsqueda paginada para el tab de Seguimiento (solo Admin/Sistemas).
     * Acepta: q, estado, desde, hasta, page, per_page
     */
    public function seguimiento(Request $request): JsonResponse
    {
        if (!$this->esAdminOSistemas()) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        try {
            // ── Parámetros DataTables ──────────────────────────────────────
            $draw    = (int) $request->input('draw', 1);
            $start   = max(0, (int) $request->input('start', 0));
            $length  = min(100, max(5, (int) $request->input('length', 25)));
            $search  = trim($request->input('search.value', '') ?? '');

            // ── Filtros propios (enviados junto con los parámetros DT) ─────
            $estado  = trim($request->input('estado', ''));
            $desde   = trim($request->input('desde', ''));
            $hasta   = trim($request->input('hasta', ''));
            $pdv     = trim($request->input('pdv', ''));

            $where    = [];
            $bindings = [];

            // Filtro texto libre (búsqueda DT o barra propia)
            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[]  = "(B2.DESCRIPCION LIKE ? OR t.persona_encargada LIKE ? OR t.nombre_solicitante LIKE ? OR CAST(t.id_ticket AS VARCHAR) LIKE ? OR t.pdv_area LIKE ?)";
                $bindings = array_merge($bindings, [$like, $like, $like, $like, $like]);
            }

            // Filtro por punto de venta exacto
            if ($pdv !== '') {
                $where[]    = "B2.DESCRIPCION = ?";
                $bindings[] = $pdv;
            }

            // Filtro estado
            if ($estado !== '' && $estado !== 'todos') {
                $where[]    = "LOWER(t.estado) = LOWER(?)";
                $bindings[] = $estado;
            }

            // Filtro fecha desde
            if ($desde !== '') {
                $where[]    = "CAST(t.fecha_creacion AS DATE) >= CONVERT(date, ?, 23)";
                $bindings[] = $desde;
            }

            // Filtro fecha hasta
            if ($hasta !== '') {
                $where[]    = "CAST(t.fecha_creacion AS DATE) <= CONVERT(date, ?, 23)";
                $bindings[] = $hasta;
            }

            $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

            $baseJoins = "
                FROM tickets t
                LEFT JOIN categorias    c  ON c.categoria_id    = t.categoria_id
                LEFT JOIN subcategorias s  ON s.subcategoria_id = t.subcategoria_id
                LEFT JOIN T_BODEGAS     B  ON B.ID              = t.bodega
                LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
            ";

            // Total sin filtros (recordsTotal)
            $recordsTotal = DB::selectOne(
                "SELECT COUNT(*) AS total {$baseJoins}"
            )?->total ?? 0;

            // Total con filtros (recordsFiltered)
            $recordsFiltered = DB::selectOne(
                "SELECT COUNT(*) AS total {$baseJoins} {$whereClause}",
                $bindings
            )?->total ?? 0;

            // ── Ordenamiento dinámico enviado por DataTables ───────────────
            $colMapSeg = [
                0 => 't.id_ticket',
                1 => 'B2.DESCRIPCION',
                2 => 't.nombre_solicitante',
                3 => 't.persona_encargada',
                4 => 't.fecha_inicio',
                5 => 't.estado',
                6 => 't.tiempo_atencion',
            ];
            $orderColIdx = (int) $request->input('order.0.column', 0);
            $orderDir    = strtoupper($request->input('order.0.dir', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
            $orderCol    = $colMapSeg[$orderColIdx] ?? 't.id_ticket';
            $orderClause = "{$orderCol} {$orderDir}";

            // Datos paginados (OFFSET / FETCH — T-SQL)
            $tickets = DB::select("
                SELECT
                    t.id_ticket,
                    CONVERT(varchar(19), t.fecha_creacion, 120) AS fecha_creacion,
                    t.nombre_solicitante,
                    t.pdv_area,
                    t.bodega,
                    B2.DESCRIPCION        AS bodega_nombre,
                    t.titulo_ticket,
                    t.prioridad,
                    t.estado,
                    t.persona_encargada,
                    CONVERT(varchar(19), t.fecha_inicio,   120) AS fecha_inicio,
                    CONVERT(varchar(10), t.fecha_estimada,  23) AS fecha_estimada,
                    CONVERT(varchar(19), t.fecha_solucion, 120) AS fecha_solucion,
                    t.tiempo_atencion,
                    t.nota_encargado,
                    c.nombre_categoria,
                    s.nombre_subcategoria
                {$baseJoins}
                {$whereClause}
                ORDER BY {$orderClause}
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ", array_merge($bindings, [$start, $length]));

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => (int) $recordsTotal,
                'recordsFiltered' => (int) $recordsFiltered,
                'data'            => $tickets,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * PATCH /soporte/tickets/{id}/aceptar
     * Solo Sistemas. Cambia estado a "En proceso", guarda fecha_inicio, fecha_estimada
     * y persona_encargada (tomado de la sesión del usuario que acepta).
     */
    public function aceptar(Request $request, int $id): JsonResponse
    {
        if (!$this->esSistemas()) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_estimada' => 'required|date|after_or_equal:today',
        ], [
            'fecha_estimada.required'          => 'Debe indicar la fecha estimada de solución.',
            'fecha_estimada.date'              => 'La fecha estimada no tiene un formato válido.',
            'fecha_estimada.after_or_equal'    => 'La fecha estimada no puede ser anterior a hoy.',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $existe = DB::selectOne("SELECT id_ticket FROM tickets WHERE id_ticket = ?", [$id]);
            if (!$existe) return response()->json(['ok' => false, 'message' => 'Ticket no encontrado.'], 404);

            // Persona encargada = nombre completo del usuario que acepta (desde sesión)
            $encargado = trim(Session::get('ses_CopNombres', '') . ' ' . Session::get('ses_CopApellidos', ''));

            $now           = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            $fechaEstimada = \Carbon\Carbon::createFromFormat('Y-m-d', $request->fecha_estimada)->format('Y-m-d');

            DB::statement("
                UPDATE tickets
                SET estado            = 'En proceso',
                    fecha_inicio      = CONVERT(datetime, ?, 120),
                    fecha_estimada    = CONVERT(date, ?, 23),
                    persona_encargada = ?
                WHERE id_ticket = ?
            ", [
                $now,
                $fechaEstimada,
                $encargado,
                $id,
            ]);

            $ticket = $this->queryTickets('WHERE t.id_ticket = ?', [$id])[0] ?? null;
            return response()->json(['ok' => true, 'ticket' => $ticket]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /soporte/tickets/{id}/rechazar
     * Solo Sistemas. Cambia estado a "No apto" y guarda nota_rechazo_usuario.
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        if (!$this->esSistemas()) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nota_encargado' => 'required|string|max:500',
        ], [
            'nota_encargado.required' => 'Debe indicar el motivo del rechazo.',
            'nota_encargado.max'      => 'El motivo no puede superar los 500 caracteres.',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $existe = DB::selectOne("SELECT id_ticket FROM tickets WHERE id_ticket = ?", [$id]);
            if (!$existe) return response()->json(['ok' => false, 'message' => 'Ticket no encontrado.'], 404);

            $encargado = trim(Session::get('ses_CopNombres', '') . ' ' . Session::get('ses_CopApellidos', ''));

            DB::statement("
                UPDATE tickets
                SET estado            = 'No apto',
                    nota_encargado    = ?,
                    persona_encargada = ?
                WHERE id_ticket = ?
            ", [$request->nota_encargado, $encargado, $id]);

            $ticket = $this->queryTickets('WHERE t.id_ticket = ?', [$id])[0] ?? null;
            return response()->json(['ok' => true, 'ticket' => $ticket]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /soporte/tickets/{id}/finalizar
     * Solo Sistemas. Cambia estado a "En revisión" (pendiente de validación del usuario),
     * guarda nota_encargado y calcula tiempo_atencion desde fecha_inicio hasta ahora.
     * Aplica tanto a "En proceso" como a "Devuelto".
     */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        if (!$this->esSistemas()) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nota_encargado' => 'required|string|max:1000',
        ], [
            'nota_encargado.required' => 'Debe agregar una nota de resolución.',
            'nota_encargado.max'      => 'La nota no puede superar los 1000 caracteres.',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $existe = DB::selectOne("SELECT id_ticket, estado, fecha_inicio FROM tickets WHERE id_ticket = ?", [$id]);
            if (!$existe) return response()->json(['ok' => false, 'message' => 'Ticket no encontrado.'], 404);

            $estadoActual = strtolower($existe->estado ?? '');
            if (!in_array($estadoActual, ['en proceso', 'devuelto'])) {
                return response()->json(['ok' => false, 'message' => 'Solo se pueden finalizar tickets en proceso o devueltos.'], 422);
            }

            // Calcular tiempo de atención en minutos (columna int en BD)
            $tiempoAtencionMin = null;
            if (!empty($existe->fecha_inicio)) {
                try {
                    $inicio = new \DateTime($existe->fecha_inicio);
                    $fin    = new \DateTime();
                    $tiempoAtencionMin = (int) round(($fin->getTimestamp() - $inicio->getTimestamp()) / 60);
                    if ($tiempoAtencionMin < 0) $tiempoAtencionMin = 0;
                } catch (\Throwable) {
                    $tiempoAtencionMin = null;
                }
            }

            $now = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
            DB::statement("
                UPDATE tickets
                SET estado           = 'En revisión',
                    nota_encargado   = ?,
                    fecha_solucion   = CONVERT(datetime, ?, 120),
                    tiempo_atencion  = ?
                WHERE id_ticket = ?
            ", [$request->nota_encargado, $now, $tiempoAtencionMin, $id]);

            $ticket = $this->queryTickets('WHERE t.id_ticket = ?', [$id])[0] ?? null;
            return response()->json(['ok' => true, 'ticket' => $ticket]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /soporte/tickets/{id}/validar
     * El usuario solicitante valida si su ticket quedó solucionado o lo devuelve.
     * - aprobado = true  → estado = "Solucionado"
     * - aprobado = false → estado = "Devuelto", nota_rechazo_usuario = motivo
     */
    public function validar(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'aprobado'            => 'required|boolean',
            'nota_rechazo_usuario' => 'required_if:aprobado,false|nullable|string|max:500',
        ], [
            'aprobado.required'                => 'Debe indicar si aprueba o devuelve el ticket.',
            'nota_rechazo_usuario.required_if' => 'Debe indicar el motivo por el que devuelve el ticket.',
            'nota_rechazo_usuario.max'         => 'El motivo no puede superar los 500 caracteres.',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $existe = DB::selectOne(
                "SELECT id_ticket, estado, usuario FROM tickets WHERE id_ticket = ?", [$id]
            );
            if (!$existe) return response()->json(['ok' => false, 'message' => 'Ticket no encontrado.'], 404);

            // Solo el solicitante original o admin pueden validar (Sistemas NO valida)
            $loginActual = Session::get('ses_CopLogin', '');
            $puedeValidar = ($existe->usuario === $loginActual)
                         || (str_contains($this->tituloRol(), 'administrador'));
            if (!$puedeValidar) {
                return response()->json(['ok' => false, 'message' => 'No autorizado para validar este ticket.'], 403);
            }

            if (strtolower($existe->estado ?? '') !== 'en revisión') {
                return response()->json(['ok' => false, 'message' => 'Solo se pueden validar tickets en estado "En revisión".'], 422);
            }

            if ($request->boolean('aprobado')) {
                DB::statement("
                    UPDATE tickets
                    SET estado             = 'Solucionado',
                        validacion_usuario = 'Aprobado'
                    WHERE id_ticket = ?
                ", [$id]);
            } else {
                DB::statement("
                    UPDATE tickets
                    SET estado                = 'Devuelto',
                        nota_rechazo_usuario  = ?,
                        validacion_usuario    = 'Devuelto'
                    WHERE id_ticket = ?
                ", [$request->nota_rechazo_usuario, $id]);
            }

            $ticket = $this->queryTickets('WHERE t.id_ticket = ?', [$id])[0] ?? null;
            return response()->json(['ok' => true, 'ticket' => $ticket]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /soporte/tickets
     * Crea un nuevo ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'numero_contacto'       => 'required|string|min:7|max:20',
            'categoria_id'          => 'required',
            'subcategoria_id'       => 'required',
            'titulo_ticket'         => 'required|string|max:150',
            'descripcion_detallada' => 'required|string',
            'prioridad'             => 'required|in:Alto,Medio,Bajo',
            'archivo_adjunto'       => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx|max:5120',
        ], [
            'numero_contacto.required' => 'El número de contacto es obligatorio.',
            'numero_contacto.min'      => 'El número de contacto no es válido.',
            'numero_contacto.max'      => 'El número de contacto no es válido.',
            'categoria_id.required'    => 'Debe seleccionar una categoría.',
            'subcategoria_id.required' => 'Debe seleccionar una subcategoría.',
            'titulo_ticket.required'   => 'El título del ticket es obligatorio.',
            'titulo_ticket.max'        => 'El título no puede superar los 150 caracteres.',
            'descripcion_detallada.required' => 'La descripción detallada es obligatoria.',
            'prioridad.required'       => 'Debe seleccionar una prioridad.',
            'prioridad.in'             => 'La prioridad seleccionada no es válida.',
            'archivo_adjunto.file'     => 'El archivo adjunto no es válido.',
            'archivo_adjunto.mimes'    => 'El archivo debe ser de tipo: jpg, png, gif, pdf, doc, docx, xls o xlsx.',
            'archivo_adjunto.max'      => 'El archivo no puede superar los 5 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            /* ── Datos del solicitante desde sesión ──────── */
            $usuario           = Session::get('ses_CopLogin', 'SISTEMA');
            $nombreSolicitante = trim(Session::get('ses_CopNombres', '') . ' ' . Session::get('ses_CopApellidos', ''));
            $pdvArea           = Session::get('ses_CopDpto', '');
            $bodegaNum         = Session::get('ses_CopBodegaId'); // número de bodega (T_USUARIOS.BODEGA = T_BODEGAS.BODEGA)

            // Si ses_CopDpto no está en sesión aún, lo consulta directo
            if (empty($pdvArea) && Session::has('ses_CopDtoId')) {
                $dpto    = DB::selectOne("SELECT DESCRIPCION FROM T_DPTO_SYS WHERE ID = ?", [Session::get('ses_CopDtoId')]);
                $pdvArea = $dpto?->DESCRIPCION ?? '';
            }

            /* ── Archivo adjunto ─────────────────────────── */
            $rutaArchivo = null;
            if ($request->hasFile('archivo_adjunto')) {
                $file     = $request->file('archivo_adjunto');
                $nombre   = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                // Guardado directo en public/uploads/tickets/ (sin symlink, funciona en nginx/Windows)
                $file->move(public_path('uploads/tickets'), $nombre);
                $rutaArchivo = 'uploads/tickets/' . $nombre;
            }

            /* ── Insertar en BD (id_ticket es IDENTITY, no se inserta) ── */
            $now = \Carbon\Carbon::now()->format('Y-m-d H:i:s');

            DB::statement("
                INSERT INTO tickets (
                    fecha_creacion, nombre_solicitante, numero_contacto,
                    pdv_area, bodega, titulo_ticket, descripcion_detallada, archivo_adjunto,
                    categoria_id, subcategoria_id, prioridad, estado, usuario
                ) VALUES (
                    CONVERT(datetime, ?, 120), ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, 'Pendiente', ?
                )
            ", [
                $now,
                $nombreSolicitante,
                $request->numero_contacto,
                $pdvArea,
                $bodegaNum ? (int) $bodegaNum : null,
                $request->titulo_ticket,
                $request->descripcion_detallada,
                $rutaArchivo,
                $request->categoria_id,
                $request->subcategoria_id,
                $request->prioridad,
                $usuario,
            ]);

            /* ── Devolver el ticket recién creado ────────── */
            $ticket = DB::selectOne("
                SELECT
                    t.*,
                    c.nombre_categoria    AS nombre_categoria,
                    s.nombre_subcategoria AS nombre_subcategoria,
                    B2.DESCRIPCION        AS bodega_nombre
                FROM tickets t
                LEFT JOIN categorias    c  ON c.categoria_id    = t.categoria_id
                LEFT JOIN subcategorias s  ON s.subcategoria_id = t.subcategoria_id
                LEFT JOIN T_BODEGAS     B  ON B.ID              = t.bodega
                LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
                WHERE t.id_ticket = (SELECT MAX(id_ticket) FROM tickets)
            ");

            return response()->json([
                'ok'      => true,
                'message' => 'Ticket creado correctamente.',
                'ticket'  => $ticket,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /soporte/tickets/analisis?anio=2026&mes=3
     * Retorna las 5 métricas para el tab de Análisis.
     * Solo accesible por admin / sistemas.
     */
    public function analisis(Request $request): JsonResponse
    {
        if (!$this->esAdminOSistemas()) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        try {
            $anio = (int) $request->input('anio', date('Y'));
            $mes  = (int) $request->input('mes',  date('n'));

            // ── 1. Tickets por estado (mes seleccionado) ───────────────────
            $porEstado = DB::select("
                SELECT t.estado, COUNT(*) AS total
                FROM tickets t
                WHERE YEAR(t.fecha_creacion) = ? AND MONTH(t.fecha_creacion) = ?
                GROUP BY t.estado
                ORDER BY total DESC
            ", [$anio, $mes]);

            // ── 2. Tiempo promedio de respuesta (mes seleccionado, solo resueltos) ──
            $tiempoPromedio = DB::selectOne("
                SELECT AVG(CAST(t.tiempo_atencion AS FLOAT)) AS promedio_minutos
                FROM tickets t
                WHERE YEAR(t.fecha_creacion) = ?
                  AND MONTH(t.fecha_creacion) = ?
                  AND t.tiempo_atencion IS NOT NULL
                  AND LOWER(t.estado) IN ('solucionado', 'no apto')
            ", [$anio, $mes]);

            // ── 3. Resueltos vs No resueltos por mes (últimos 12 meses) ────
            $resueltosPorMes = DB::select("
                SELECT
                    YEAR(t.fecha_creacion)  AS anio,
                    MONTH(t.fecha_creacion) AS mes,
                    SUM(CASE WHEN LOWER(t.estado) = 'solucionado' THEN 1 ELSE 0 END) AS resueltos,
                    SUM(CASE WHEN LOWER(t.estado) <> 'solucionado' THEN 1 ELSE 0 END) AS no_resueltos
                FROM tickets t
                WHERE t.fecha_creacion >= DATEADD(MONTH, -11,
                      DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1))
                GROUP BY YEAR(t.fecha_creacion), MONTH(t.fecha_creacion)
                ORDER BY anio, mes
            ");

            // ── 4. PDV que más tickets genera (mes seleccionado) ───────────
            $porPdv = DB::select("
                SELECT TOP 10
                    B2.DESCRIPCION AS pdv,
                    COUNT(*) AS total
                FROM tickets t
                LEFT JOIN T_BODEGAS B ON B.ID = t.bodega
                LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA
                WHERE YEAR(t.fecha_creacion) = ? AND MONTH(t.fecha_creacion) = ?
                  AND B2.DESCRIPCION IS NOT NULL
                GROUP BY B2.DESCRIPCION
                ORDER BY total DESC
            ", [$anio, $mes]);

            // ── 5. Tickets por categoría (mes seleccionado) ────────────────
            $porCategoria = DB::select("
                SELECT
                    ISNULL(c.nombre_categoria, 'Sin categoría') AS categoria,
                    COUNT(*) AS total
                FROM tickets t
                LEFT JOIN categorias c ON c.categoria_id = t.categoria_id
                WHERE YEAR(t.fecha_creacion) = ? AND MONTH(t.fecha_creacion) = ?
                GROUP BY c.nombre_categoria
                ORDER BY total DESC
            ", [$anio, $mes]);

            // ── Total de tickets del mes ───────────────────────────────────
            $totalMes = DB::selectOne("
                SELECT COUNT(*) AS total FROM tickets
                WHERE YEAR(fecha_creacion) = ? AND MONTH(fecha_creacion) = ?
            ", [$anio, $mes])?->total ?? 0;

            // ── 6. Tickets resueltos por encargado (mes seleccionado) ──────
            $porEncargado = DB::select("
                SELECT
                    t.persona_encargada AS encargado,
                    COUNT(*) AS resueltos,
                    AVG(CAST(t.tiempo_atencion AS FLOAT)) AS tiempo_promedio_min
                FROM tickets t
                WHERE YEAR(t.fecha_creacion) = ? AND MONTH(t.fecha_creacion) = ?
                  AND LOWER(t.estado) = 'solucionado'
                  AND t.persona_encargada IS NOT NULL
                  AND t.persona_encargada <> ''
                GROUP BY t.persona_encargada
                ORDER BY resueltos DESC
            ", [$anio, $mes]);

            return response()->json([
                'ok'               => true,
                'total_mes'        => (int) $totalMes,
                'por_estado'       => $porEstado,
                'tiempo_promedio'  => round((float)($tiempoPromedio?->promedio_minutos ?? 0)),
                'resueltos_por_mes'=> $resueltosPorMes,
                'por_pdv'          => $porPdv,
                'por_categoria'    => $porCategoria,
                'por_encargado'    => $porEncargado,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * GET /soporte/tickets/analisis/excel?anio=2026&mes=3
     * Descarga un archivo Excel (CSV con BOM UTF-8) con el análisis del mes.
     */
    public function analisisExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$this->esAdminOSistemas()) {
            abort(403, 'No autorizado.');
        }

        $anio = (int) $request->input('anio', date('Y'));
        $mes  = (int) $request->input('mes',  date('n'));

        $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $etiquetaMes = ($meses[$mes] ?? $mes) . ' ' . $anio;
        $filename    = "analisis_tickets_{$anio}_{$mes}.csv";

        // ── Datos ──────────────────────────────────────────────────────────
        $porEstado = DB::select("
            SELECT t.estado, COUNT(*) AS total FROM tickets t
            WHERE YEAR(t.fecha_creacion)=? AND MONTH(t.fecha_creacion)=?
            GROUP BY t.estado ORDER BY total DESC", [$anio, $mes]);

        $porEncargado = DB::select("
            SELECT t.persona_encargada AS encargado, COUNT(*) AS resueltos,
                   AVG(CAST(t.tiempo_atencion AS FLOAT)) AS tiempo_prom
            FROM tickets t
            WHERE YEAR(t.fecha_creacion)=? AND MONTH(t.fecha_creacion)=?
              AND LOWER(t.estado)='solucionado'
              AND t.persona_encargada IS NOT NULL AND t.persona_encargada<>''
            GROUP BY t.persona_encargada ORDER BY resueltos DESC", [$anio, $mes]);

        $porPdv = DB::select("
            SELECT TOP 10 B2.DESCRIPCION AS pdv, COUNT(*) AS total
            FROM tickets t
            LEFT JOIN T_BODEGAS B ON B.ID=t.bodega
            LEFT JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA=B.BODEGA
            WHERE YEAR(t.fecha_creacion)=? AND MONTH(t.fecha_creacion)=?
              AND B2.DESCRIPCION IS NOT NULL
            GROUP BY B2.DESCRIPCION ORDER BY total DESC", [$anio, $mes]);

        $porCategoria = DB::select("
            SELECT ISNULL(c.nombre_categoria,'Sin categoría') AS categoria, COUNT(*) AS total
            FROM tickets t
            LEFT JOIN categorias c ON c.categoria_id=t.categoria_id
            WHERE YEAR(t.fecha_creacion)=? AND MONTH(t.fecha_creacion)=?
            GROUP BY c.nombre_categoria ORDER BY total DESC", [$anio, $mes]);

        $totalMes = DB::selectOne("
            SELECT COUNT(*) AS total FROM tickets
            WHERE YEAR(fecha_creacion)=? AND MONTH(fecha_creacion)=?", [$anio, $mes])?->total ?? 0;

        $tiempoPromedio = DB::selectOne("
            SELECT AVG(CAST(t.tiempo_atencion AS FLOAT)) AS prom FROM tickets t
            WHERE YEAR(t.fecha_creacion)=? AND MONTH(t.fecha_creacion)=?
              AND t.tiempo_atencion IS NOT NULL AND LOWER(t.estado) IN ('solucionado','no apto')",
            [$anio, $mes])?->prom ?? 0;

        // ── Generar CSV ────────────────────────────────────────────────────
        $rows = [];

        $rows[] = ["ANÁLISIS DE TICKETS — {$etiquetaMes}"];
        $rows[] = [];
        $rows[] = ["Total de tickets del mes", $totalMes];
        $rows[] = ["Tiempo promedio de respuesta (min)", round((float)$tiempoPromedio)];
        $rows[] = [];

        $rows[] = ["TICKETS POR ESTADO"];
        $rows[] = ["Estado", "Cantidad"];
        foreach ($porEstado as $r) { $rows[] = [$r->estado ?? 'Sin estado', $r->total]; }
        $rows[] = [];

        $rows[] = ["TICKETS RESUELTOS POR ENCARGADO"];
        $rows[] = ["Encargado", "Tickets resueltos", "Tiempo promedio (min)"];
        foreach ($porEncargado as $r) { $rows[] = [$r->encargado, $r->resueltos, round((float)($r->tiempo_prom ?? 0))]; }
        $rows[] = [];

        $rows[] = ["PDV QUE MÁS TICKETS GENERA (TOP 10)"];
        $rows[] = ["PDV", "Cantidad"];
        foreach ($porPdv as $r) { $rows[] = [$r->pdv ?? 'Sin PDV', $r->total]; }
        $rows[] = [];

        $rows[] = ["TICKETS POR CATEGORÍA"];
        $rows[] = ["Categoría", "Cantidad"];
        foreach ($porCategoria as $r) { $rows[] = [$r->categoria ?? 'Sin categoría', $r->total]; }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
