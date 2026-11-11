<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión — Copifam</title>
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
</head>
<body class="font-sans">

<div class="flex h-screen w-full">

    {{-- Lado izquierdo: imagen decorativa (oculta en móvil) --}}
    <div class="hidden md:block w-full overflow-hidden" style="background-color: #ff1498;">
        <img src="{{ asset('storage/img/logo_copifam5.png') }}" alt="Imagen copifam" >
    </div>

    {{-- Lado derecho: formulario --}}
    <div class="w-full flex flex-col items-center justify-center px-6">

        <form id="formLogin" class="w-full max-w-sm md:max-w-96 flex flex-col items-center"
              method="POST" action="{{ route('login.post') }}">
            @csrf

            {{-- Logo --}}
            <div class="flex items-center justify-center mb-5">
                <img src="{{ asset('storage/img/logo_copifam1.png') }}" alt="Logo copifam" class="h-30 w-auto object-contain">
            </div>

            
            <p class="text-sm text-gray-500/90 mt-3 text-center">
                Bienvenido de nuevo. Ingrese sus credenciales para continuar
            </p>

            {{-- Campo usuario --}}
            <div class="flex items-center w-full mt-8 bg-transparent border h-12 rounded-full overflow-hidden pl-6 gap-2 relative
                        {{ $errors->has('usuario') ? 'border-red-400' : 'border-gray-300/60' }}
                        focus-within:border-primary transition-colors"
                 id="usuarioContainer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" fill="#9CA3AF"/>
                </svg>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    value="{{ old('usuario') }}"
                    placeholder="Usuario"
                    autofocus
                    autocomplete="username"
                    class="bg-transparent text-gray-500/80 placeholder-gray-400 outline-none text-sm w-full h-full"
                    required
                >
                <div id="credencialesGuardadasIndicador" class="hidden absolute right-4 flex items-center gap-1 text-xs text-green-500 pr-2" title="Tienes credenciales guardadas">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                </div>
            </div>
            @error('usuario')
                <p class="w-full text-red-500 text-xs mt-1 pl-6">{{ $message }}</p>
            @enderror

            {{-- Campo contraseña --}}
            <div class="flex items-center w-full mt-4 bg-transparent border h-12 rounded-full overflow-hidden pl-6 gap-2
                        {{ $errors->has('clave') ? 'border-red-400' : 'border-gray-300/60' }}
                        focus-within:border-primary transition-colors">
                <svg width="13" height="17" viewBox="0 0 13 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 8.5c0-.938-.729-1.7-1.625-1.7h-.812V4.25C10.563 1.907 8.74 0 6.5 0S2.438 1.907 2.438 4.25V6.8h-.813C.729 6.8 0 7.562 0 8.5v6.8c0 .938.729 1.7 1.625 1.7h9.75c.896 0 1.625-.762 1.625-1.7zM4.063 4.25c0-1.406 1.093-2.55 2.437-2.55s2.438 1.144 2.438 2.55V6.8H4.061z" fill="#9CA3AF"/>
                </svg>
                <input
                    type="password"
                    id="clave"
                    name="clave"
                    placeholder="Contraseña"
                    autocomplete="current-password"
                    class="bg-transparent text-gray-500/80 placeholder-gray-400 outline-none text-sm w-full h-full"
                    required
                >
            </div>
            @error('clave')
                <p class="w-full text-red-500 text-xs mt-1 pl-6">{{ $message }}</p>
            @enderror

            {{-- Recordarme / Olvidé contraseña --}}
            <div class="w-full flex items-center justify-between mt-5 text-gray-500/80">
                <div class="flex items-center gap-2 group">
                    <input class="h-4 w-4 cursor-pointer accent-primary" type="checkbox" id="remember" name="remember">
                    <label class="text-sm cursor-pointer select-none" for="remember" title="Guarda tu usuario y contraseña en este navegador">Recordarme</label>
                    <svg class="w-3.5 h-3.5 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <a href="{{ route('password.reset') }}" class="text-sm underline underline-offset-2 hover:text-primary transition-colors">
                    ¿Olvidó su contraseña?
                </a>
            </div>

            {{-- Botón --}}
            <button type="submit" id="btnLogin"
                    class="mt-8 w-full h-11 rounded-full text-white font-medium text-sm transition-opacity hover:opacity-90"
                    style="background-color: #ff1498;">
                Ingresar
            </button>

        </form>

    </div>
</div>

{{-- Modal personalizado para errores de credenciales --}}
@if ($errors->has('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        CopifamModal.fire({
            title: 'Acceso denegado',
            text: '{{ $errors->first('error') }}',
            icon: 'error',
            confirmText: 'Entendido'
        });
    });
</script>
@endif

{{-- Modal personalizado para contraseña actualizada correctamente --}}
@if (session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        CopifamModal.fire({
            title: '¡Contraseña actualizada!',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmText: 'Continuar'
        });
    });
</script>
@endif

<script src="{{ asset('js/auth/login.js') }}"></script>

</body>
</html>
