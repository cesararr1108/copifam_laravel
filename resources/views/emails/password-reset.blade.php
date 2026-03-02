<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña — Copifam</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Inter', Arial, sans-serif; }
        .wrapper { max-width: 520px; margin: 40px auto; padding: 0 16px; }
        .card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background-color: #ff1498; padding: 32px 40px; text-align: center; }
        .header img { height: 48px; width: auto; }
        .body { padding: 36px 40px; }
        .body h1 { margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #111827; }
        .body p { margin: 0 0 16px; font-size: 14px; color: #6b7280; line-height: 1.6; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background-color: #ff1498; color: #ffffff !important;
               text-decoration: none; padding: 13px 32px; border-radius: 9999px;
               font-size: 14px; font-weight: 600; letter-spacing: 0.01em; }
        .divider { border: none; border-top: 1px solid #f3f4f6; margin: 24px 0; }
        .link-text { font-size: 12px; color: #9ca3af; word-break: break-all; }
        .footer { background-color: #f9fafb; padding: 20px 40px; text-align: center; }
        .footer p { margin: 0; font-size: 11px; color: #9ca3af; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">

        {{-- Header --}}
        <div class="header">
            <img src="{{ config('app.url') }}/storage/img/logo_copifam5.png" alt="Copifam">
        </div>

        {{-- Body --}}
        <div class="body">
            <h1>Restablecer contraseña</h1>
            <p>Hola <strong>{{ $usuario }}</strong>,</p>
            <p>
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en el sistema
                <strong>Copifam</strong>. Haz clic en el botón para crear una nueva contraseña:
            </p>

            <div class="btn-wrap">
                <a href="{{ $enlace }}" class="btn">Restablecer mi contraseña</a>
            </div>

            <p>
                Este enlace es válido por <strong>60 minutos</strong>. Si no solicitaste este cambio,
                puedes ignorar este correo — tu contraseña actual seguirá siendo la misma.
            </p>

            <hr class="divider">

            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p class="link-text">{{ $enlace }}</p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                Este correo fue generado automáticamente por el sistema Copifam.<br>
                Por favor no respondas a este mensaje.
            </p>
        </div>

    </div>
</div>
</body>
</html>
