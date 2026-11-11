<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FacturaElectronicaController;
use App\Http\Controllers\Api\CarritoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
|  FACTURAS ELECTRÓNICAS - LEER CORREOS DIAN
|--------------------------------------------------------------------------
*/

Route::post('/facturas/leer-correo', [FacturaElectronicaController::class, 'leerCorreo'])
    ->name('facturas.leer-correo');

/*
|--------------------------------------------------------------------------
|  CARRITO - ACTUALIZACIÓN DE PRECIOS Y STOCK WOOCOMMERCE
|--------------------------------------------------------------------------
*/

Route::match(['get', 'post'], '/carrito/actualizar-precios', [CarritoController::class, 'actualizarPrecios'])
    ->name('carrito.actualizar-precios');

