<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\UsuariosController;
use App\Http\Controllers\GestionHumana\AsistenciasController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

// Raíz redirige al login
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/test-cv', function (\Illuminate\Http\Request $request) {
    try {
        $ean = $request->input('ean', '7703186621412');
        $url = 'https://api.cruzverde.com.co/product-service/products/search-suggestions?limit=1&q=' . $ean;

        // Browsershot navega a la URL, espera la carga (JSON crudo en el documento) y retorna el HTML
        $html = \Spatie\Browsershot\Browsershot::url($url)
            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36')
            ->windowSize(1920, 1080)
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->bodyHtml();

        // El JSON dentro del HTML suele venir envuelto en "<pre>" o "<body>"
        $jsonStr = strip_tags($html);

        return response()->json([
            'status' => 200,
            'body' => json_decode($jsonStr, true) ?? $jsonStr
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 500,
            'error' => $e->getMessage()
        ]);
    }
});


// Formulario de login
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// Procesar login
Route::post('/login', [LoginController::class, 'login'])->name('login.post');

// Cerrar sesión
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ─── Recuperación de contraseña ───────────────────────────────────────────────
Route::get('/recuperar-contrasena', [PasswordResetController::class, 'showForm'])->name('password.reset');
Route::post('/recuperar-contrasena', [PasswordResetController::class, 'enviarSolicitud'])->name('password.reset.post');
Route::get('/recuperar-contrasena/enviado', [PasswordResetController::class, 'enviado'])->name('password.reset.sent');
Route::get('/nueva-contrasena/{token}', [PasswordResetController::class, 'showNuevaForm'])->name('password.nueva');
Route::post('/nueva-contrasena', [PasswordResetController::class, 'cambiarContrasena'])->name('password.nueva.post');

// ─── Rutas protegidas (requieren sesión activa) ───────────────────────────────
Route::middleware('session.check')->group(function () {

    // Dashboard principal
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'permisos' => session('ses_Permisos', []),
        ]);
    })->name('dashboard');

    // ── Inventarios ──────────────────────────────────────────────────────────
    Route::get('/inventarios/ingresos', fn() => abort(501))->middleware('permission:0101')->name('inv.ingresos');
    Route::get('/inventarios/traslados', fn() => abort(501))->middleware('permission:0103')->name('inv.traslados');
    Route::get('/inventarios/sugerido-compras', fn() => abort(501))->middleware('permission:0104')->name('inv.sug-compras');
    Route::get('/inventarios/data-maestra', fn() => abort(501))->middleware('permission:0107')->name('inv.data-maestra');
    Route::get('/inventarios/toma', fn() => abort(501))->middleware('permission:0108')->name('inv.toma');
    Route::get('/inventarios/actualizacion-precios', fn() => abort(501))->middleware('permission:0112')->name('inv.act-precios');
    Route::get('/inventarios/sugerido-pdv', fn() => abort(501))->middleware('permission:0113')->name('inv.sug-pdv');
    Route::get('/inventarios/entradas', fn() => abort(501))->middleware('permission:0114')->name('inv.entradas');
    Route::get('/inventarios/salidas', fn() => abort(501))->middleware('permission:0115')->name('inv.salidas');
    Route::get('/inventarios/inventario', fn() => abort(501))->middleware('permission:0116')->name('inv.inventario');
    Route::get('/inventarios/pqrs', fn() => abort(501))->middleware('permission:0117')->name('inv.pqrs');
    Route::get('/inventarios/ingresos-preliminares', fn() => abort(501))->middleware('permission:0118')->name('inv.ing-preliminares');

    // ── Ventas ───────────────────────────────────────────────────────────────
    Route::get('/ventas/ventas', fn() => abort(501))->middleware('permission:0201')->name('ven.ventas');
    Route::get('/ventas/devoluciones', fn() => abort(501))->middleware('permission:0202')->name('ven.devoluciones');
    Route::get('/ventas/ac-cajas', fn() => abort(501))->middleware('permission:0203')->name('ven.ac-cajas');
    Route::get('/ventas/despachos', fn() => abort(501))->middleware('permission:0204')->name('ven.despachos');
    Route::get('/ventas/dispensacion', fn() => abort(501))->middleware('permission:0205')->name('ven.dispensacion');
    Route::get('/ventas/cuadre-cajasv', fn() => abort(501))->middleware('permission:0206')->name('ven.cuadre-cajasv');
    Route::get('/ventas/pedidos', fn() => abort(501))->middleware('permission:0208')->name('ven.pedidos');
    Route::get('/ventas/cuadre-cajap', fn() => abort(501))->middleware('permission:0209')->name('ven.cuadre-cajap');

    // ── Administración ───────────────────────────────────────────────────────
    Route::get('/administracion/usuarios', [UsuariosController::class, 'index'])->middleware('permission:0301')->name('adm.usuarios');
    Route::get('/administracion/usuarios/catalogs', [UsuariosController::class, 'catalogs'])->middleware('permission:0301')->name('adm.usuarios.catalogs');
    Route::get('/administracion/usuarios/search', [UsuariosController::class, 'search'])->middleware('permission:0301')->name('adm.usuarios.search');
    Route::get('/administracion/usuarios/{usuario}', [UsuariosController::class, 'show'])->middleware('permission:0301')->name('adm.usuarios.show');
    Route::post('/administracion/usuarios', [UsuariosController::class, 'store'])->middleware('permission:0301')->name('adm.usuarios.store');
    Route::put('/administracion/usuarios/{usuario}', [UsuariosController::class, 'update'])->middleware('permission:0301')->name('adm.usuarios.update');
    Route::get('/administracion/roles', fn() => abort(501))->middleware('permission:0302')->name('adm.roles');
    Route::get('/administracion/permisos', fn() => abort(501))->middleware('permission:0303')->name('adm.permisos');
    Route::get('/administracion/asignar-permisos', fn() => abort(501))->middleware('permission:0304')->name('adm.asignar-permisos');
    Route::get('/administracion/bodegas', fn() => abort(501))->middleware('permission:0305')->name('adm.bodegas');
    Route::get('/administracion/modulos-permisos', fn() => abort(501))->middleware('permission:0306')->name('adm.modulos-permisos');
    Route::get('/administracion/reubicar-usuarios', fn() => abort(501))->middleware('permission:0307')->name('adm.reubicar');

    // ── Reportes ─────────────────────────────────────────────────────────────
    Route::get('/reportes/seg-pedidos', fn() => abort(501))->middleware('permission:0401')->name('rep.seg-pedidos');
    Route::get('/reportes/inv-actual', fn() => abort(501))->middleware('permission:0405')->name('rep.inv-actual');
    Route::get('/reportes/ventas-mensuales', fn() => abort(501))->middleware('permission:0408')->name('rep.ventas-mensuales');
    Route::get('/reportes/contabilizacion', fn() => abort(501))->middleware('permission:0413')->name('rep.contabilizacion');
    Route::get('/reportes/ingresos-ventas', fn() => abort(501))->middleware('permission:0414')->name('rep.ingresos-ventas');
    Route::get('/reportes/listado-precios', fn() => abort(501))->middleware('permission:0415')->name('rep.listado-precios');
    Route::get('/reportes/ventas-bodega', fn() => abort(501))->middleware('permission:0416')->name('rep.ventas-bodega');
    Route::get('/reportes/reportes-adg', fn() => abort(501))->middleware('permission:0418')->name('rep.adg');
    Route::get('/reportes/kardex', fn() => abort(501))->middleware('permission:0420')->name('rep.kardex');
    Route::get('/reportes/arqueo-caja', fn() => abort(501))->middleware('permission:0421')->name('rep.arqueo-caja');
    Route::get('/reportes/inventario-pdv', fn() => abort(501))->middleware('permission:0422')->name('rep.inv-pdv');
    Route::get('/reportes/seg-pqrs', fn() => abort(501))->middleware('permission:0423')->name('rep.seg-pqrs');
    Route::get('/reportes/ordenes-compra', fn() => abort(501))->middleware('permission:0424')->name('rep.ordenes-compra');
    Route::get('/reportes/ajuste-inventario', fn() => abort(501))->middleware('permission:0425')->name('rep.ajuste-inv');
    Route::get('/reportes/bensac', fn() => abort(501))->middleware('permission:0426')->name('rep.bensac');
    Route::get('/reportes/traslados', fn() => abort(501))->middleware('permission:0427')->name('rep.traslados');
    Route::get('/reportes/flotantes-sobrantes', fn() => abort(501))->middleware('permission:0428')->name('rep.flotantes');
    Route::get('/reportes/ordenes-detalladas', fn() => abort(501))->middleware('permission:0429')->name('rep.ordenes-det');

    // ── Tesorería ────────────────────────────────────────────────────────────
    Route::get('/tesoreria/gastos', fn() => abort(501))->middleware('permission:0501')->name('tes.gastos');

    // ── Contabilidad ─────────────────────────────────────────────────────────
    Route::get('/contabilidad/factura-electronica', fn() => abort(501))->middleware('permission:0601')->name('con.factura-electronica');

    // ── Mercadeo ─────────────────────────────────────────────────────────────
    Route::get('/mercadeo/teleferias', fn() => abort(501))->middleware('permission:0701')->name('mer.teleferias');
    Route::get('/mercadeo/shopping', [\App\Http\Controllers\Mercadeo\ShoppingController::class, 'index'])->middleware('permission:0702')->name('mer.shopping');
    Route::get('/mercadeo/shopping/search', [\App\Http\Controllers\Mercadeo\ShoppingController::class, 'search'])->middleware('permission:0702')->name('mer.shopping.search');

    // ── Cartera ──────────────────────────────────────────────────────────────
    Route::get('/cartera/validacion-disp', fn() => abort(501))->middleware('permission:0801')->name('car.val-disp');
    Route::get('/cartera/info-fact-disp', fn() => abort(501))->middleware('permission:0802')->name('car.info-fact');
    Route::get('/cartera/reimp-remisiones', fn() => abort(501))->middleware('permission:0803')->name('car.reimp');
    Route::get('/cartera/comp-diario-venta', fn() => abort(501))->middleware('permission:0806')->name('car.comp-diario');
    Route::get('/cartera/disp-facturadas', fn() => abort(501))->middleware('permission:0807')->name('car.disp-fact');
    Route::get('/cartera/disp-pendientes', fn() => abort(501))->middleware('permission:0808')->name('car.disp-pend');

    // ── Gestión Humana (grupo 11) ────────────────────────────────────────────
    Route::get('/gestion-humana/control-asistencia', [AsistenciasController::class, 'index'])->middleware('permission:1101')->name('ges.asistencia');
    Route::get('/gestion-humana/control-es', fn() => abort(501))->middleware('permission:1102')->name('ges.control-es');

    Route::prefix('gestion-humana')->middleware('permission:1101')->group(function () {
        Route::get('/asistencia/usuarios', [AsistenciasController::class, 'usuarios']);
        Route::get('/asistencia/areas', [AsistenciasController::class, 'obtenerAreas']);
        Route::get('/asistencia/tabla', [AsistenciasController::class, 'tabla']);
        Route::post('/asistencia/marcar', [AsistenciasController::class, 'marcar']);
        Route::get('/asistencia/detalle-f4', [AsistenciasController::class, 'detalleF4']);
        Route::get('/asistencia/historial', [AsistenciasController::class, 'historial']);
        Route::get('/asistencia/historial-datatable', [AsistenciasController::class, 'historialDataTable']);
    });

    // Ruta diagnóstico temporal — BORRAR DESPUÉS
    Route::get('/debug-asistencia-usuarios', function () {
        try {
            $r = \Illuminate\Support\Facades\DB::select("
                SELECT TOP 5 ID AS id, CONCAT(NOMBRES, ' ', APELLIDOS) AS nombre_completo
                FROM T_USUARIOS WHERE ESTADO = 'A' ORDER BY NOMBRES ASC
            ");
            return response()->json(['ok' => true, 'data' => $r, 'count' => count($r)]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    });


    // ── Soporte ──────────────────────────────────────────────────────────────
    Route::get('/soporte/formulario', fn() => abort(501))->middleware('permission:1201')->name('sop.formulario');

    Route::prefix('soporte')->middleware('permission:1201')->group(function () {
        Route::get('/catalogos', [\App\Http\Controllers\Soporte\TicketsController::class, 'catalogs']);
        Route::get('/tickets/seguimiento', [\App\Http\Controllers\Soporte\TicketsController::class, 'seguimiento']);
        Route::get('/tickets/analisis', [\App\Http\Controllers\Soporte\TicketsController::class, 'analisis']);
        Route::get('/tickets/analisis/excel', [\App\Http\Controllers\Soporte\TicketsController::class, 'analisisExcel']);
        Route::get('/tickets', [\App\Http\Controllers\Soporte\TicketsController::class, 'index']);
        Route::post('/tickets', [\App\Http\Controllers\Soporte\TicketsController::class, 'store']);
        Route::patch('/tickets/{id}/aceptar', [\App\Http\Controllers\Soporte\TicketsController::class, 'aceptar']);
        Route::patch('/tickets/{id}/rechazar', [\App\Http\Controllers\Soporte\TicketsController::class, 'rechazar']);
        Route::patch('/tickets/{id}/finalizar', [\App\Http\Controllers\Soporte\TicketsController::class, 'finalizar']);
        Route::patch('/tickets/{id}/validar', [\App\Http\Controllers\Soporte\TicketsController::class, 'validar']);
    });

});

// ─── API Factura Electrónica (sin middleware de sesión) ───────────────────────
Route::prefix('api/facturas-electronicas')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\FacturaElectronicaController::class, 'index']);
    Route::get('/{uuid}/items', [\App\Http\Controllers\Api\FacturaElectronicaController::class, 'items']);
    Route::get('/leer-correo', [\App\Http\Controllers\Api\FacturaElectronicaController::class, 'leerCorreo']);
});

// ─── API Descargar PDF ───────────────────────────────────────────────────────
// GET  /api/pdf/descargar?ruta=/ruta/absoluta/archivo.pdf&nombre=nombreOpcional
// POST /api/pdf/descargar  { "ruta": "/ruta/absoluta/archivo.pdf", "nombre": "opcional" }
Route::prefix('api/pdf')->group(function () {
    Route::get('/descargar', [\App\Http\Controllers\Api\PdfController::class, 'descargar']);
});

// ─── Scraper de Precios (La Rebaja) ──────────────────────────────────────────
// Route::get('/admin/iniciar-scraping', [\App\Http\Controllers\ScraperController::class, 'dispatchScraping']);


// ─── API Email - Enviar Correos (sin autenticación ni CSRF) ───────────────────
Route::prefix('api/email')->group(function () {
    Route::post('/enviar', [\App\Http\Controllers\Api\EmailController::class, 'enviarCorreo'])->withoutMiddleware('web');
    Route::get('/test', function () {
        return response()->json([
            'status' => 200,
            'message' => 'API Email disponible',
            'endpoint' => '/api/email/enviar'
        ]);
    })->withoutMiddleware('web');
});