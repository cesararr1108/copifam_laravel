<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

// Raíz redirige al login
Route::get('/', function () {
    return redirect()->route('login');
});

// Formulario de login
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// Procesar login
Route::post('/login', [LoginController::class, 'login'])->name('login.post');

// Cerrar sesión
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ─── Recuperación de contraseña ───────────────────────────────────────────────
Route::get('/recuperar-contrasena',         [PasswordResetController::class, 'showForm'])->name('password.reset');
Route::post('/recuperar-contrasena',        [PasswordResetController::class, 'enviarSolicitud'])->name('password.reset.post');
Route::get('/recuperar-contrasena/enviado', [PasswordResetController::class, 'enviado'])->name('password.reset.sent');
Route::get('/nueva-contrasena',             [PasswordResetController::class, 'showNuevaForm'])->name('password.nueva');
Route::post('/nueva-contrasena',            [PasswordResetController::class, 'cambiarContrasena'])->name('password.nueva.post');

// ─── Rutas protegidas (requieren sesión activa) ───────────────────────────────
Route::middleware('session.check')->group(function () {

    // Dashboard principal
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'permisos' => session('ses_Permisos', []),
        ]);
    })->name('dashboard');

    // ── Inventarios ──────────────────────────────────────────────────────────
    Route::get('/inventarios/ingresos',             fn() => abort(501))->middleware('permission:0101')->name('inv.ingresos');
    Route::get('/inventarios/traslados',            fn() => abort(501))->middleware('permission:0103')->name('inv.traslados');
    Route::get('/inventarios/sugerido-compras',     fn() => abort(501))->middleware('permission:0104')->name('inv.sug-compras');
    Route::get('/inventarios/data-maestra',         fn() => abort(501))->middleware('permission:0107')->name('inv.data-maestra');
    Route::get('/inventarios/toma',                 fn() => abort(501))->middleware('permission:0108')->name('inv.toma');
    Route::get('/inventarios/actualizacion-precios',fn() => abort(501))->middleware('permission:0112')->name('inv.act-precios');
    Route::get('/inventarios/sugerido-pdv',         fn() => abort(501))->middleware('permission:0113')->name('inv.sug-pdv');
    Route::get('/inventarios/entradas',             fn() => abort(501))->middleware('permission:0114')->name('inv.entradas');
    Route::get('/inventarios/salidas',              fn() => abort(501))->middleware('permission:0115')->name('inv.salidas');
    Route::get('/inventarios/inventario',           fn() => abort(501))->middleware('permission:0116')->name('inv.inventario');
    Route::get('/inventarios/pqrs',                 fn() => abort(501))->middleware('permission:0117')->name('inv.pqrs');
    Route::get('/inventarios/ingresos-preliminares',fn() => abort(501))->middleware('permission:0118')->name('inv.ing-preliminares');

    // ── Ventas ───────────────────────────────────────────────────────────────
    Route::get('/ventas/ventas',        fn() => abort(501))->middleware('permission:0201')->name('ven.ventas');
    Route::get('/ventas/devoluciones',  fn() => abort(501))->middleware('permission:0202')->name('ven.devoluciones');
    Route::get('/ventas/ac-cajas',      fn() => abort(501))->middleware('permission:0203')->name('ven.ac-cajas');
    Route::get('/ventas/despachos',     fn() => abort(501))->middleware('permission:0204')->name('ven.despachos');
    Route::get('/ventas/dispensacion',  fn() => abort(501))->middleware('permission:0205')->name('ven.dispensacion');
    Route::get('/ventas/cuadre-cajasv', fn() => abort(501))->middleware('permission:0206')->name('ven.cuadre-cajasv');
    Route::get('/ventas/pedidos',       fn() => abort(501))->middleware('permission:0208')->name('ven.pedidos');
    Route::get('/ventas/cuadre-cajap',  fn() => abort(501))->middleware('permission:0209')->name('ven.cuadre-cajap');

    // ── Administración ───────────────────────────────────────────────────────
    Route::get('/administracion/usuarios',         fn() => abort(501))->middleware('permission:0301')->name('adm.usuarios');
    Route::get('/administracion/roles',            fn() => abort(501))->middleware('permission:0302')->name('adm.roles');
    Route::get('/administracion/permisos',         fn() => abort(501))->middleware('permission:0303')->name('adm.permisos');
    Route::get('/administracion/asignar-permisos', fn() => abort(501))->middleware('permission:0304')->name('adm.asignar-permisos');
    Route::get('/administracion/bodegas',          fn() => abort(501))->middleware('permission:0305')->name('adm.bodegas');
    Route::get('/administracion/modulos-permisos', fn() => abort(501))->middleware('permission:0306')->name('adm.modulos-permisos');
    Route::get('/administracion/reubicar-usuarios',fn() => abort(501))->middleware('permission:0307')->name('adm.reubicar');

    // ── Reportes ─────────────────────────────────────────────────────────────
    Route::get('/reportes/seg-pedidos',        fn() => abort(501))->middleware('permission:0401')->name('rep.seg-pedidos');
    Route::get('/reportes/inv-actual',         fn() => abort(501))->middleware('permission:0405')->name('rep.inv-actual');
    Route::get('/reportes/ventas-mensuales',   fn() => abort(501))->middleware('permission:0408')->name('rep.ventas-mensuales');
    Route::get('/reportes/contabilizacion',    fn() => abort(501))->middleware('permission:0413')->name('rep.contabilizacion');
    Route::get('/reportes/ingresos-ventas',    fn() => abort(501))->middleware('permission:0414')->name('rep.ingresos-ventas');
    Route::get('/reportes/listado-precios',    fn() => abort(501))->middleware('permission:0415')->name('rep.listado-precios');
    Route::get('/reportes/ventas-bodega',      fn() => abort(501))->middleware('permission:0416')->name('rep.ventas-bodega');
    Route::get('/reportes/reportes-adg',       fn() => abort(501))->middleware('permission:0418')->name('rep.adg');
    Route::get('/reportes/kardex',             fn() => abort(501))->middleware('permission:0420')->name('rep.kardex');
    Route::get('/reportes/arqueo-caja',        fn() => abort(501))->middleware('permission:0421')->name('rep.arqueo-caja');
    Route::get('/reportes/inventario-pdv',     fn() => abort(501))->middleware('permission:0422')->name('rep.inv-pdv');
    Route::get('/reportes/seg-pqrs',           fn() => abort(501))->middleware('permission:0423')->name('rep.seg-pqrs');
    Route::get('/reportes/ordenes-compra',     fn() => abort(501))->middleware('permission:0424')->name('rep.ordenes-compra');
    Route::get('/reportes/ajuste-inventario',  fn() => abort(501))->middleware('permission:0425')->name('rep.ajuste-inv');
    Route::get('/reportes/bensac',             fn() => abort(501))->middleware('permission:0426')->name('rep.bensac');
    Route::get('/reportes/traslados',          fn() => abort(501))->middleware('permission:0427')->name('rep.traslados');
    Route::get('/reportes/flotantes-sobrantes',fn() => abort(501))->middleware('permission:0428')->name('rep.flotantes');
    Route::get('/reportes/ordenes-detalladas', fn() => abort(501))->middleware('permission:0429')->name('rep.ordenes-det');

    // ── Tesorería ────────────────────────────────────────────────────────────
    Route::get('/tesoreria/gastos', fn() => abort(501))->middleware('permission:0501')->name('tes.gastos');

    // ── Contabilidad ─────────────────────────────────────────────────────────
    Route::get('/contabilidad/factura-electronica', fn() => abort(501))->middleware('permission:0601')->name('con.factura-electronica');

    // ── Mercadeo ─────────────────────────────────────────────────────────────
    Route::get('/mercadeo/teleferias', fn() => abort(501))->middleware('permission:0701')->name('mer.teleferias');

    // ── Cartera ──────────────────────────────────────────────────────────────
    Route::get('/cartera/validacion-disp',    fn() => abort(501))->middleware('permission:0801')->name('car.val-disp');
    Route::get('/cartera/info-fact-disp',     fn() => abort(501))->middleware('permission:0802')->name('car.info-fact');
    Route::get('/cartera/reimp-remisiones',   fn() => abort(501))->middleware('permission:0803')->name('car.reimp');
    Route::get('/cartera/comp-diario-venta',  fn() => abort(501))->middleware('permission:0806')->name('car.comp-diario');
    Route::get('/cartera/disp-facturadas',    fn() => abort(501))->middleware('permission:0807')->name('car.disp-fact');
    Route::get('/cartera/disp-pendientes',    fn() => abort(501))->middleware('permission:0808')->name('car.disp-pend');

    // ── Gestión Humana (grupo 11) ────────────────────────────────────────────
    Route::get('/gestion-humana/control-asistencia', fn() => abort(501))->middleware('permission:1101')->name('ges.asistencia');
    Route::get('/gestion-humana/control-es',         fn() => abort(501))->middleware('permission:1102')->name('ges.control-es');

    // ── Soporte ──────────────────────────────────────────────────────────────
    Route::get('/soporte/formulario', fn() => abort(501))->middleware('permission:1201')->name('sop.formulario');

});


