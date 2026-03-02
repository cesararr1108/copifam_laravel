<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud enviada — Copifam</title>
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

<div class="reset-card text-center">

    {{-- Logo --}}
    <div class="flex justify-center mb-6">
        <img src="{{ asset('storage/img/logo_copifam1.png') }}"
             alt="Logo Copifam"
             class="reset-logo">
    </div>

    {{-- Ícono de éxito --}}
    <div class="sent-icon-wrap">
        <svg class="w-8 h-8" style="color:#ff1498" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
        </svg>
    </div>

    {{-- Mensaje --}}
    <h1 class="text-xl font-semibold text-gray-800 mb-3">
        Solicitud enviada
    </h1>
    <p class="text-sm text-gray-500 px-4 leading-relaxed mb-8">
        Recibirás las instrucciones para restablecer tu contraseña.
        <br><br>
        Revisa tu correo electrónico y sigue los pasos indicados.
    </p>

    {{-- Volver al login --}}
    <a href="{{ route('login') }}"
       class="btn-primary" style="text-decoration:none; display:inline-flex; max-width:280px; margin:0 auto;">
        Volver al inicio de sesión
    </a>

    <p class="text-xs text-gray-400 mt-5">
        ¿No recibiste nada?
        <a href="{{ route('password.reset') }}" class="text-primary hover:underline">
            Intentar de nuevo
        </a>
    </p>

</div>

</body>
</html>
