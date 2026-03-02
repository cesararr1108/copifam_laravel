<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nueva contraseña — Copifam</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#ff1498' },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/password-reset.css') }}">
</head>
<body class="font-sans reset-bg">

<div class="reset-card rounded-2xl">

    {{-- Logo --}}
    <div class="flex justify-center mb-6">
        <img src="{{ asset('storage/img/logo_copifam1.png') }}"
             alt="Logo Copifam"
             class="reset-logo">
    </div>

    {{-- Ícono --}}
    <div class="reset-icon-wrap">
        <svg class="w-7 h-7" style="color:#ff1498" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
        </svg>
    </div>

    {{-- Título --}}
    <h1 class="text-xl font-semibold text-gray-800 mb-2">Crear nueva contraseña</h1>
    <p class="text-sm text-gray-500 mb-6">
        Ingresa y confirma tu nueva contraseña. Debe tener al menos 6 caracteres.
    </p>

    {{-- Formulario --}}
    <form method="POST" action="{{ route('password.nueva.post') }}" id="formNueva">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Nueva contraseña --}}
        <div class="mb-4">
            <label class="block text-sm text-gray-600 mb-1.5 pl-1" for="clave">
                Nueva contraseña
            </label>
            <div class="input-wrap {{ $errors->has('clave') ? 'input-error' : '' }}">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                     stroke="#9CA3AF" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                <input type="password" id="clave" name="clave"
                       placeholder="Mínimo 6 caracteres"
                       autocomplete="new-password" required>
            </div>
            @error('clave')
                <p class="text-red-500 text-xs mt-1 pl-2 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Confirmar contraseña --}}
        <div class="mb-6">
            <label class="block text-sm text-gray-600 mb-1.5 pl-1" for="clave_confirmation">
                Confirmar contraseña
            </label>
            <div class="input-wrap">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                     stroke="#9CA3AF" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <input type="password" id="clave_confirmation" name="clave_confirmation"
                       placeholder="Repite tu contraseña"
                       autocomplete="new-password" required>
            </div>
        </div>

        {{-- Botón --}}
        <button type="submit" id="btnGuardar" class="btn-primary w-full">
            <span id="btnText">Guardar nueva contraseña</span>
            <span id="btnSpinner" class="hidden">
                <span class="spinner"></span>
                Guardando...
            </span>
        </button>
    </form>

    {{-- Volver --}}
    <div class="flex justify-center">
        <a href="{{ route('login') }}" class="back-link">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
            Volver al inicio de sesión
        </a>
    </div>

</div>

<script src="{{ asset('js/auth/password-reset.js') }}"></script>
</body>
</html>
