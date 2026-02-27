<?php

use App\Http\Controllers\Auth\LoginController;
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

// Rutas protegidas — verificación de sesión directa
Route::get('/dashboard', function () {
    if (!Session::has('ses_CopId')) {
        return redirect()->route('login');
    }
    return view('dashboard');
})->name('dashboard');

