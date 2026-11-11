<?php

namespace App\Http\Controllers\GestionHumana;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class AsistenciasController extends Controller
{
    // Mapeo de meses en español
    private static function formatearFechaEstatica($fecha, $formato = 'd M Y')
    {
        if (!$fecha) return '';
        
        $mesesES = [
            'Jan' => 'Enero', 'Feb' => 'Febrero', 'Mar' => 'Marzo', 'Apr' => 'Abril',
            'May' => 'Mayo', 'Jun' => 'Junio', 'Jul' => 'Julio', 'Aug' => 'Agosto',
            'Sep' => 'Septiembre', 'Oct' => 'Octubre', 'Nov' => 'Noviembre', 'Dec' => 'Diciembre'
        ];
        
        $parsed = Carbon::parse($fecha);
        $formateada = $parsed->format($formato);
        
        foreach ($mesesES as $en => $es) {
            $formateada = str_replace($en, $es, $formateada);
        }
        
        return $formateada;
    }

    // Mapeo de meses en español
    private function formatearFecha($fecha, $formato = 'd M Y')
    {
        return self::formatearFechaEstatica($fecha, $formato);
    }

    public function index()
    {
        return view('gestion_humana.asistencia');
    }
    public function usuarios()
    {
        try {
            $rol = Session::get('ses_CopRol');
            $userId = Session::get('ses_CopId');

            $sql = "SELECT TOP 1000 ID AS id, CONCAT(NOMBRES, ' ', APELLIDOS) AS nombre_completo
                    FROM T_USUARIOS
                    WHERE ROLES_ID NOT IN (2, 10) AND ESTADO = 'A'";
            $params = [];

            if ($rol == 5) {
                $sql .= " AND ID = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY NOMBRES ASC";

            $usuarios = DB::select($sql, $params);

            // Normalizar a array simple para garantizar claves en minúscula
            $datos = array_map(function ($u) {
                $arr = (array) $u;
                // SQL Server puede devolver 'ID' o 'id' según el driver; buscamos ambos
                $id = isset($arr['id']) ? $arr['id']
                    : (isset($arr['ID']) ? $arr['ID'] : null);
                return [
                    'id'              => $id,
                    'nombre_completo' => $arr['nombre_completo'] ?? '',
                ];
            }, $usuarios);

            return response()->json($datos);
        } catch (\Exception $e) {
            \Log::error('Error en usuarios(): ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerAreas()
    {
        try {
            $areas = DB::select("
                SELECT TOP 100 id, descripcion
                FROM ASISTENCIAS_AREAS
                ORDER BY descripcion ASC
            ");
            return response()->json($areas);
        } catch (\Exception $e) {
            \Log::error('Error en obtenerAreas(): ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
 public function tabla(Request $request)
    {
        try {
            $fecha = $request->input('fecha', Carbon::today()->toDateString());
            $rol = Session::get('ses_CopRol');
            $userId = Session::get('ses_CopId');

            $sql = "SELECT TOP 1000
                        ra.id,
                        ra.id_usuario,
                        CONCAT(tu.NOMBRES, ' ', tu.APELLIDOS) AS nombre_completo,
                        ra.entrada_t1, ra.salida_t1, ra.entrada_t2, ra.salida_t2,
                        (SELECT COUNT(*) FROM Reg_f4
                         WHERE id_usuario = ra.id_usuario
                           AND CAST(fecha AS DATE) = ?) AS cant_f4
                    FROM Reg_Asistencias ra
                    INNER JOIN T_USUARIOS tu ON ra.id_usuario = tu.ID
                    WHERE CAST(ra.fecha AS DATE) = ?";

            $params = [$fecha, $fecha];

            if ($rol == 5) {
                $sql .= " AND ra.id_usuario = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY ra.entrada_t1 DESC";

            $registros = DB::select($sql, $params);

            $datos = array_map(function ($row) {
                return [
                    'id'                   => $row->id,
                    'id_usuario'           => $row->id_usuario,
                    'nombre_completo'      => $row->nombre_completo,
                    'entrada_t1'           => self::formatearFechaEstatica($row->entrada_t1, 'd M Y h:i A'),
                    'salida_t1'            => self::formatearFechaEstatica($row->salida_t1, 'd M Y h:i A'),
                    'entrada_t2'           => self::formatearFechaEstatica($row->entrada_t2, 'd M Y h:i A'),
                    'salida_t2'            => self::formatearFechaEstatica($row->salida_t2, 'd M Y h:i A'),
                    'cant_f4'              => (int) $row->cant_f4,
                ];
            }, $registros);

            return response()->json($datos);
        } catch (\Exception $e) {
            \Log::error('Error en tabla(): ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function obtenerRegistro(int $id, string $fecha)
    {
        return DB::selectOne("
            SELECT * FROM Reg_Asistencias
            WHERE id_usuario = ?
              AND CAST(fecha AS DATE) = ?
        ", [$id, $fecha]);
    }

    public function marcar(Request $request)
    {
        try {
            $request->validate([
                'id'   => 'required',
                'tipo' => 'required|string|in:entrada_t1,salida_t1,entrada_t2,salida_t2,salida_f4,entrada_f4',
                'nota' => 'nullable|string|max:500',
            ]);

            $id         = (int) $request->input('id');
            $now        = Carbon::now();
            $fecha      = $now->toDateString();
            $fecha_hora = $now->format('Y-m-d H:i:s');
            $tipo       = $request->tipo;
            $nota       = $request->nota ?? '';

            $reg = $this->obtenerRegistro($id, $fecha);

            switch ($tipo) {
                case 'entrada_t1':
                    if ($reg) {
                        if (!empty($reg->entrada_t1)) return response()->json(['ok' => false, 'message' => 'Ya se registró la entrada del primer turno.'], 422);
                        DB::statement("UPDATE Reg_Asistencias SET entrada_t1=CONVERT(DATETIME,'{$fecha_hora}',121) WHERE id_usuario={$id} AND CAST(fecha AS DATE)='{$fecha}'");
                    } else {
                        DB::statement("INSERT INTO Reg_Asistencias (id_usuario,entrada_t1,fecha) VALUES ({$id},CONVERT(DATETIME,'{$fecha_hora}',121),'{$fecha}')");
                    }
                    return response()->json(['ok' => true, 'message' => 'Entrada primer turno registrada.']);

                case 'salida_t1':
                    if (!$reg || empty($reg->entrada_t1)) return response()->json(['ok' => false, 'message' => 'Debe registrar la entrada del primer turno primero.'], 422);
                    if (!empty($reg->salida_t1)) return response()->json(['ok' => false, 'message' => 'La salida del primer turno ya fue registrada.'], 422);
                    DB::statement("UPDATE Reg_Asistencias SET salida_t1=CONVERT(DATETIME,'{$fecha_hora}',121) WHERE id_usuario={$id} AND CAST(fecha AS DATE)='{$fecha}'");
                    return response()->json(['ok' => true, 'message' => 'Salida primer turno registrada.']);

                case 'entrada_t2':
                    if (!$reg || empty($reg->entrada_t1) || empty($reg->salida_t1)) return response()->json(['ok' => false, 'message' => 'Debe completar el primer turno (entrada y salida) antes del segundo turno.'], 422);
                    if (!empty($reg->entrada_t2)) return response()->json(['ok' => false, 'message' => 'Ya se registró la entrada del segundo turno.'], 422);
                    DB::statement("UPDATE Reg_Asistencias SET entrada_t2=CONVERT(DATETIME,'{$fecha_hora}',121) WHERE id_usuario={$id} AND CAST(fecha AS DATE)='{$fecha}'");
                    return response()->json(['ok' => true, 'message' => 'Entrada segundo turno registrada.']);

                case 'salida_t2':
                    if (!$reg || empty($reg->entrada_t1) || empty($reg->salida_t1)) return response()->json(['ok' => false, 'message' => 'Debe completar el primer turno antes del segundo turno.'], 422);
                    if (empty($reg->entrada_t2)) return response()->json(['ok' => false, 'message' => 'Debe registrar la entrada del segundo turno primero.'], 422);
                    if (!empty($reg->salida_t2)) return response()->json(['ok' => false, 'message' => 'La salida del segundo turno ya fue registrada.'], 422);
                    DB::statement("UPDATE Reg_Asistencias SET salida_t2=CONVERT(DATETIME,'{$fecha_hora}',121) WHERE id_usuario={$id} AND CAST(fecha AS DATE)='{$fecha}'");
                    return response()->json(['ok' => true, 'message' => 'Salida segundo turno registrada.']);

                case 'salida_f4':
                    if (!$reg) return response()->json(['ok' => false, 'message' => 'Debe realizar la entrada del primer turno primero.'], 422);
                    $notaEscapada = str_replace("'", "''", $nota);
                    DB::statement("INSERT INTO Reg_f4 (id_usuario,fecha_hora,tipo,nota,fecha) VALUES ({$id},CONVERT(DATETIME,'{$fecha_hora}',121),'Salida','{$notaEscapada}','{$fecha}')");
                    return response()->json(['ok' => true, 'message' => 'Salida F4 registrada.']);

                case 'entrada_f4':
                    if (!$reg) return response()->json(['ok' => false, 'message' => 'Debe realizar la entrada del primer turno primero.'], 422);
                    $notaEscapada = str_replace("'", "''", $nota);
                    DB::statement("INSERT INTO Reg_f4 (id_usuario,fecha_hora,tipo,nota,fecha) VALUES ({$id},CONVERT(DATETIME,'{$fecha_hora}',121),'Entrada','{$notaEscapada}','{$fecha}')");
                    return response()->json(['ok' => true, 'message' => 'Entrada F4 registrada.']);

                default:
                    return response()->json(['ok' => false, 'message' => 'Tipo de marcación inválido.'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function detalleF4(Request $request)
    {
        try {
            $id    = (int) $request->input('id');
            $fecha = $request->input('fecha', Carbon::today()->toDateString());

            $registros = DB::select("
                SELECT CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) AS nombre_completo,
                       f4.fecha_hora, f4.tipo, f4.nota
                FROM Reg_f4 f4
                INNER JOIN T_USUARIOS tu ON f4.id_usuario = tu.ID
                WHERE f4.id_usuario=? AND CAST(f4.fecha AS DATE)=?
                ORDER BY f4.fecha_hora ASC
            ", [$id, $fecha]);

            $datos = array_map(function ($row) {
                return [
                    'nombre_completo' => $row->nombre_completo,
                    'fecha_hora'      => self::formatearFechaEstatica($row->fecha_hora, 'd M Y h:i A'),
                    'tipo'            => $row->tipo ?? '',
                    'nota'            => $row->nota ?? '',
                ];
            }, $registros);

            return response()->json($datos);
        } catch (\Exception $e) {
            \Log::error('Error en detalleF4(): ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function historial(Request $request)
    {
        $desde  = $request->input('desde');
        $hasta  = $request->input('hasta');
        $nombre = trim($request->input('nombre', ''));

        $params = [];
        $where  = [];
        $whereF4 = [];

        $rol = (int) Session::get('ses_CopRol');
        if (!in_array($rol, [1, 9])) {
            return response()->json(['error' => 'No autorizado para ver el historial'], 403);
        }

        if ($desde) { $where[] = "CAST(ra.fecha AS DATE)>=?"; $params[] = $desde; $whereF4[] = "CAST(f4.fecha AS DATE)>=?"; }
        if ($hasta)  { $where[] = "CAST(ra.fecha AS DATE)<=?"; $params[] = $hasta;  $whereF4[] = "CAST(f4.fecha AS DATE)<=?"; }
        if ($nombre !== '') { $where[] = "CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) LIKE ?"; $params[] = "%{$nombre}%"; }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $registros = DB::select("
            SELECT ra.id_usuario, CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) AS nombre_completo,
                   ra.fecha, ra.entrada_t1, ra.salida_t1, ra.entrada_t2, ra.salida_t2
            FROM Reg_Asistencias ra
            INNER JOIN T_USUARIOS tu ON ra.id_usuario=tu.ID
            {$whereClause}
            ORDER BY ra.fecha DESC, ra.entrada_t1 DESC
        ", $params);

        $paramsF4 = [];
        if ($desde) $paramsF4[] = $desde;
        if ($hasta)  $paramsF4[] = $hasta;
        $whereF4Clause = $whereF4 ? 'WHERE ' . implode(' AND ', $whereF4) : '';

        $f4 = DB::select("
            SELECT CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) AS nombre_completo,
                   f4.fecha_hora, f4.tipo, f4.nota, f4.fecha
            FROM Reg_f4 f4
            INNER JOIN T_USUARIOS tu ON f4.id_usuario=tu.ID
            {$whereF4Clause}
            ORDER BY f4.fecha DESC, f4.fecha_hora ASC
        ", $paramsF4);

        $asistencias = array_map(function ($r) {
            return [
                'id'              => $r->id_usuario,
                'nombre_completo' => $r->nombre_completo,
                'fecha'           => self::formatearFechaEstatica($r->fecha, 'd M Y'),
                'entrada_t1'      => $r->entrada_t1 ? Carbon::parse($r->entrada_t1)->format('h:i A') : '',
                'salida_t1'       => $r->salida_t1  ? Carbon::parse($r->salida_t1)->format('h:i A')  : '',
                'entrada_t2'      => $r->entrada_t2 ? Carbon::parse($r->entrada_t2)->format('h:i A') : '',
                'salida_t2'       => $r->salida_t2  ? Carbon::parse($r->salida_t2)->format('h:i A')  : '',
            ];
        }, $registros);

        $regF4 = array_map(function ($r) {
            return [
                'nombre_completo' => $r->nombre_completo,
                'fecha'           => self::formatearFechaEstatica($r->fecha, 'd M Y'),
                'fecha_hora'      => self::formatearFechaEstatica($r->fecha_hora, 'd M Y h:i A'),
                'tipo'            => $r->tipo ?? '',
                'nota'            => $r->nota ?? '',
            ];
        }, $f4);

        return response()->json(['asistencias' => $asistencias, 'reg_f4' => $regF4]);
    }

    public function historialDataTable(Request $request)
    {
        try {
            $rol = (int) Session::get('ses_CopRol');
            if (!in_array($rol, [1, 9])) {
                return response()->json(['error' => 'No autorizado para ver el historial'], 403);
            }

            $draw = (int) $request->input('draw', 1);
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $searchValue = trim($request->input('search.value', ''));
            $orderColumn = (int) $request->input('order.0.column', 0);
            $orderDir = strtoupper($request->input('order.0.dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            
            // Filtros del formulario (no de búsqueda global)
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');
            $nombre = trim($request->input('nombre', ''));
            $tipoRegistro = $request->input('tipo', 'asistencias'); // 'asistencias' o 'f4'

            // Columnas ordenables para cada tabla
            $columnasAsistencias = ['id', 'nombre_completo', 'fecha', 'entrada_t1', 'salida_t1', 'entrada_t2', 'salida_t2'];
            $columnasF4 = ['id', 'nombre_completo', 'fecha', 'fecha_hora', 'tipo', 'nota'];

            if ($tipoRegistro === 'asistencias') {
                // ─── TABLA: ASISTENCIAS ───
                $columnas = $columnasAsistencias;
                $baseQuery = "
                    SELECT ra.id, ra.id_usuario, CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) AS nombre_completo,
                           ra.fecha, ra.entrada_t1, ra.salida_t1, ra.entrada_t2, ra.salida_t2
                    FROM Reg_Asistencias ra
                    INNER JOIN T_USUARIOS tu ON ra.id_usuario=tu.ID
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Filtros SQL
                if ($desde) {
                    $baseQuery .= " AND CAST(ra.fecha AS DATE) >= ?";
                    $params[] = $desde;
                }
                if ($hasta) {
                    $baseQuery .= " AND CAST(ra.fecha AS DATE) <= ?";
                    $params[] = $hasta;
                }
                if ($nombre !== '') {
                    $baseQuery .= " AND CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) LIKE ?";
                    $params[] = "%{$nombre}%";
                }
                if ($searchValue !== '') {
                    $baseQuery .= " AND (CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) LIKE ? OR CAST(ra.fecha AS DATE) = ?)";
                    $params[] = "%{$searchValue}%";
                    $params[] = $searchValue;
                }

                // Contar total con filtros
                $countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as temp";
                $totalFiltered = DB::selectOne($countQuery, $params)->total;

                // Ordenamiento
                $colName = $columnas[$orderColumn] ?? 'ra.fecha';
                $baseQuery .= " ORDER BY {$colName} {$orderDir}";
                $baseQuery .= " OFFSET {$start} ROWS FETCH NEXT {$length} ROWS ONLY";

                // Ejecutar query
                $datos = DB::select($baseQuery, $params);

                // Formatear datos
                $datos = array_map(function ($r) {
                    return [
                        'id'                   => $r->id,
                        'id_usuario'           => $r->id_usuario,
                        'nombre_completo'      => $r->nombre_completo,
                        'fecha'                => self::formatearFechaEstatica($r->fecha, 'd M Y'),
                        'entrada_t1'           => $r->entrada_t1 ? Carbon::parse($r->entrada_t1)->format('h:i A') : '',
                        'salida_t1'            => $r->salida_t1  ? Carbon::parse($r->salida_t1)->format('h:i A')  : '',
                        'entrada_t2'           => $r->entrada_t2 ? Carbon::parse($r->entrada_t2)->format('h:i A') : '',
                        'salida_t2'            => $r->salida_t2  ? Carbon::parse($r->salida_t2)->format('h:i A')  : '',
                    ];
                }, $datos);

            } else {
                // ─── TABLA: REGISTROS F4 ───
                $columnas = $columnasF4;
                
                $baseQuery = "
                    SELECT f4.id, f4.id_usuario, CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) AS nombre_completo,
                           f4.fecha_hora, f4.tipo, f4.nota, f4.fecha
                    FROM Reg_f4 f4
                    INNER JOIN T_USUARIOS tu ON f4.id_usuario=tu.ID
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Filtros SQL
                if ($desde) {
                    $baseQuery .= " AND CAST(f4.fecha AS DATE) >= ?";
                    $params[] = $desde;
                }
                if ($hasta) {
                    $baseQuery .= " AND CAST(f4.fecha AS DATE) <= ?";
                    $params[] = $hasta;
                }
                if ($nombre !== '') {
                    $baseQuery .= " AND CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) LIKE ?";
                    $params[] = "%{$nombre}%";
                }
                if ($searchValue !== '') {
                    $baseQuery .= " AND (CONCAT(tu.NOMBRES,' ',tu.APELLIDOS) LIKE ? OR f4.tipo LIKE ?)";
                    $params[] = "%{$searchValue}%";
                    $params[] = "%{$searchValue}%";
                }

                // Contar total con filtros
                $countQuery = "SELECT COUNT(*) as total FROM (" . $baseQuery . ") as temp";
                $totalFiltered = DB::selectOne($countQuery, $params)->total;

                // Ordenamiento
                $colName = $columnas[$orderColumn] ?? 'f4.fecha';
                // Agregar prefijo f4. si no lo tiene
                if (strpos($colName, '.') === false) {
                    $colName = 'f4.' . $colName;
                }
                // Para columnas de texto largo, usar CAST para evitar problemas con tipos de datos grandes
                if ($colName === 'f4.nota' || $colName === 'f4.tipo') {
                    $colName = "CAST({$colName} AS VARCHAR(MAX))";
                }
                $baseQuery .= " ORDER BY {$colName} {$orderDir}";
                $baseQuery .= " OFFSET {$start} ROWS FETCH NEXT {$length} ROWS ONLY";

                // Ejecutar query
                $datos = DB::select($baseQuery, $params);

                // Formatear datos
                $datos = array_map(function ($r) {
                    return [
                        'id'                   => $r->id,
                        'id_usuario'           => $r->id_usuario,
                        'nombre_completo'      => $r->nombre_completo,
                        'fecha'                => self::formatearFechaEstatica($r->fecha, 'd M Y'),
                        'fecha_hora'           => self::formatearFechaEstatica($r->fecha_hora, 'h:i A'),
                        'tipo'                 => $r->tipo ?? '',
                        'nota'                 => $r->nota ?? '',
                    ];
                }, $datos);
            }

            // Contar total sin filtros (para DataTables)
            $totalQuery = $tipoRegistro === 'asistencias' 
                ? "SELECT COUNT(*) as total FROM Reg_Asistencias"
                : "SELECT COUNT(*) as total FROM Reg_f4";
            $recordsTotal = DB::selectOne($totalQuery)->total;

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $totalFiltered,
                'data'            => $datos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'draw'            => (int) $request->input('draw', 1),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => $e->getMessage()
            ], 500);
        }
    }
}