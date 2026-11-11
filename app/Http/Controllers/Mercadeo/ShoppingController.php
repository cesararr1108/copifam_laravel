<?php

namespace App\Http\Controllers\Mercadeo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShoppingController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('mercadeo.shopping');
    }

    /**
     * DataTables server-side para la tabla de shopping.
     */
    public function search(Request $request): JsonResponse
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = min(100, max(5, (int) $request->input('length', 25)));
        $search = strtoupper(trim($request->input('search.value', '') ?? ''));

        try {
            $like     = "%{$search}%";
            $where    = $search !== ''
                ? "WHERE codigo LIKE ? OR ean LIKE ? OR nombre_producto LIKE ?"
                : '';
            $bindings = $search !== '' ? [$like, $like, $like] : [];

            /* Total sin filtro */
            $total = DB::selectOne("SELECT COUNT(*) AS CNT FROM shopping")->CNT;

            /* Total con filtro */
            $filtered = $search !== ''
                ? DB::selectOne("SELECT COUNT(*) AS CNT FROM shopping {$where}", $bindings)->CNT
                : $total;

            /* Mapeo de columnas para ordenamiento */
            $colMap = [
                0 => 'codigo',
                1 => 'ean',
                2 => 'nombre_producto',
                3 => 'precio_copifam',
                4 => 'precio_rebaja',
                5 => 'precio_pasteur',
                6 => 'precio_alemana',
                7 => 'precio_colsubsidio',
            ];
            
            $orderColIdx = (int) $request->input('order.0.column', 0);
            $orderDir    = strtoupper($request->input('order.0.dir', 'asc')) === 'DESC' ? 'DESC' : 'ASC';
            $orderCol    = $colMap[$orderColIdx] ?? 'nombre_producto';
            $orderClause = "{$orderCol} {$orderDir}";

            $data = DB::select("
                SELECT 
                    codigo, ean, nombre_producto, precio_copifam,
                    precio_rebaja, descuento_rebaja,
                    precio_pasteur, descuento_pasteur,
                    precio_alemana, descuento_alemana,
                    precio_colsubsidio, descuento_colsubsidio
                FROM shopping
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
                'error'           => 'Error al buscar productos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
