# 📧 Configuración de Crontab para Lectura de Facturas DIAN

## 🔧 Opción 1: Ejecutar vía CURL cada minuto

### Paso 1: Crear un script bash

```bash
cat > /usr/local/bin/leer-facturas-cron.sh << 'EOF'
#!/bin/bash

# Script para ejecutar API de lectura de facturas cada minuto
APP_URL="http://192.168.2.82/api/facturas/leer-correo"

curl -X POST "$APP_URL" \
  -H "Content-Type: application/json" \
  -w "\nStatus: %{http_code}\n" \
  2>&1 >> /var/log/leer-facturas-cron.log

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Ejecución completada" >> /var/log/leer-facturas-cron.log
EOF

chmod +x /usr/local/bin/leer-facturas-cron.sh
```

### Paso 2: Añadir al crontab

```bash
crontab -e
```

Agregar esta línea para ejecutar cada minuto:

```
* * * * * /usr/local/bin/leer-facturas-cron.sh
```

O para ejecutar cada 5 minutos:

```
*/5 * * * * /usr/local/bin/leer-facturas-cron.sh
```

O para ejecutar cada 10 minutos:

```
*/10 * * * * /usr/local/bin/leer-facturas-cron.sh
```

---

## 🔧 Opción 2: Ejecutar vía Laravel Scheduler (Recomendado)

### Paso 1: Crear un comando artisan

```bash
php artisan make:command LeerFacturasCorreo
```

### Paso 2: Editar `app/Console/Commands/LeerFacturasCorreo.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LeerFacturasCorreo extends Command
{
    protected $signature = 'facturas:leer-correo';
    protected $description = 'Lee correos y procesa facturas DIAN';

    public function handle()
    {
        try {
            $this->info('📧 Iniciando lectura de facturas...');

            $response = Http::post(url('/api/facturas/leer-correo'));

            $data = $response->json();

            $this->info('✅ Procesados: ' . $data['procesados']);
            $this->info('❌ Errores: ' . $data['errores']);

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
```

### Paso 3: Registrar en `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // Ejecutar cada minuto
    $schedule->command('facturas:leer-correo')
        ->everyMinute()
        ->withoutOverlapping();

    // O cada 5 minutos
    // $schedule->command('facturas:leer-correo')
    //     ->everyFiveMinutes()
    //     ->withoutOverlapping();

    // O cada 10 minutos
    // $schedule->command('facturas:leer-correo')
    //     ->everyTenMinutes()
    //     ->withoutOverlapping();
}
```

### Paso 4: Agregar crontab para Laravel

```bash
crontab -e
```

Agregar:

```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔧 Opción 3: Ejecutar vía PHP CLI (Más simple)

Crear un script PHP en `public/cron-facturas.php`:

```php
<?php

$appUrl = 'http://192.168.2.82/api/facturas/leer-correo';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $appUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($opt, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$log = "/var/log/leer-facturas.log";
file_put_contents($log, "[" . date('Y-m-d H:i:s') . "] HTTP {$http_code}: {$response}\n", FILE_APPEND);
```

Agregar al crontab:

```
* * * * * php /ruta/al/proyecto/public/cron-facturas.php
```

---

## 📊 Monitorear los logs

```bash
# Ver logs en tiempo real
tail -f /var/log/leer-facturas-cron.log

# Ver logs de Laravel
tail -f /ruta/al/proyecto/storage/logs/laravel.log
```

---

## ⚙️ Verificar que crontab está funcionando

```bash
# Listar crontab actual
crontab -l

# Ver historial de ejecuciones (si está configurado)
grep CRON /var/log/syslog
```

---

## 🔔 Mejores prácticas

✅ **Ejecutar cada 5-10 minutos** en lugar de cada minuto  
✅ **Usar `withoutOverlapping()`** para evitar ejecuciones simultáneas  
✅ **Monitorear los logs** periódicamente  
✅ **Probar manualmente primero** antes de automatizar  
✅ **Usar HTTPS en producción** para la URL de la API  

---

## 🧪 Testing manual

Ejecutar manualmente para verificar que funciona:

```bash
curl -X POST http://192.168.2.82/api/facturas/leer-correo
```

Debería retornar:

```json
{
  "ok": true,
  "mensaje": "Lectura de correos completada",
  "procesados": 0,
  "errores": 0,
  "facturas": [],
  "detalles_errores": []
}
```
