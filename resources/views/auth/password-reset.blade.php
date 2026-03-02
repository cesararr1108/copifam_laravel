<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($errors->has('error'))
        <meta name="form-error" content="{{ $errors->first('error') }}">
    @endif
    <title>Recuperar contraseña — Copifam</title>
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
    <script src="{{ asset('js/copifam-modal.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/password-reset.css') }}">
</head>
<body class="font-sans reset-bg">

<div class="reset-card">

    {{-- Logo --}}
    <div class="flex justify-center mb-6">
        <img src="{{ asset('storage/img/logo_copifam1.png') }}"
             alt="Logo Copifam"
             class="reset-logo">
    </div>

    {{-- Encabezado --}}
    <h1 class="text-xl font-semibold text-gray-800 text-center mb-2">
        ¿Olvidaste tu contraseña?
    </h1>
    <p class="text-sm text-amber-600 text-center mb-7 px-2">
        ¡No te preocupes! Simplemente escribe tu usuario
        y te enviaremos un link para restablecer tu contraseña.
    </p>

    {{-- Formulario --}}
    <form id="formReset" method="POST" action="{{ route('password.reset.post') }}">
        @csrf

        {{-- Campo usuario --}}
        <label class="block text-sm text-gray-600 mb-1.5 pl-1" for="usuario">
            Tu usuario
        </label>
        <div class="input-wrap {{ $errors->has('usuario') ? 'input-error' : '' }}">
            {{-- Ícono usuario --}}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" fill="#9CA3AF"/>
            </svg>
            <input
                type="text"
                id="usuario"
                name="usuario"
                value="{{ old('usuario') }}"
                placeholder="Ingresa tu usuario"
                autofocus
                autocomplete="username"
                required
            >
        </div>
        @error('usuario')
            <p class="text-red-500 text-xs mt-1 pl-2">{{ $message }}</p>
        @enderror

        {{-- Botón enviar --}}
        <button type="submit" id="btnReset" class="btn-primary mt-6">
            <span id="btnSpinner" class="spinner hidden"></span>
            <span id="btnText">Restablecer contraseña</span>
        </button>
    </form>

    {{-- Volver al login --}}
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
