# 📧 Lectura de Correos IMAP con Laravel Jobs

## ✅ Implementación completada

Se ha configurado un **Sistema de Jobs** para procesar correos de Gmail sin bloquear requests HTTP.

---

## 📁 Archivos creados/modificados

1. **`app/Jobs/LeerCorreoFacturas.php`** - Job que contiene toda la lógica
2. **`app/Console/Commands/ProcesarFacturas.php`** - Comando Artisan
3. **`app/Console/Kernel.php`** - Configuración del scheduler
4. **`app/Http/Controllers/Api/FacturaElectronicaController.php`** - Controlador simplificado

---

## 🚀 Cómo usar

### **Opción 1: Ejecutar manualmente desde línea de comandos**

```bash
php artisan facturas:leer-correo
```

**Salida:**
```
▶️  Iniciando lectura de correos...
✅ Tarea completada correctamente
```

---

### **Opción 2: Ejecutar desde HTTP (despacha el Job)**

```http
GET /api/facturas-electronicas/leer-correo
```

**Respuesta:**
```json
{
  "ok": true,
  "mensaje": "Tarea de lectura de correos iniciada",
  "info": "Revisa los logs para ver el progreso: storage/logs/laravel.log"
}
```

---

### **Opción 3: Scheduler automático (RECOMENDADO)**

El scheduler ejecuta automáticamente cada **30 minutos** (configurable).

Para que funcione, agrega a tu `crontab` en el servidor:

```bash
* * * * * cd /ruta/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Este cron ejecutará **todos** los comandos programados.

---

## ⚙️ Configurar frecuencia del scheduler

Edita `app/Console/Kernel.php`, línea 15-16:

```php
// Cada 30 minutos (DEFAULT)
$schedule->command('facturas:leer-correo')->everyThirtyMinutes();

// O cada 5 minutos:
// $schedule->command('facturas:leer-correo')->everyFiveMinutes();

// O cada 1 hora:
// $schedule->command('facturas:leer-correo')->hourly();

// O a una hora específica (2 AM):
// $schedule->command('facturas:leer-correo')->dailyAt('02:00');
```

---

## 📊 Monitorear ejecuciones

### **Ver logs en tiempo real:**
```bash
tail -f storage/logs/laravel.log
```

### **Buscar solo logs del Job:**
```bash
grep "LeerCorreoFacturas" storage/logs/laravel.log
```

---

## ⚡ Colas (Queues) - Opcional

Si quieres procesar en **background sin bloquear**, configura colas:

### **1. En `.env`:**
```env
QUEUE_CONNECTION=database  # o redis, sync, etc.
```

### **2. Crear tabla de colas:**
```bash
php artisan queue:table
php artisan migrate
```

### **3. Iniciar worker en background:**
```bash
php artisan queue:work --timeout=300
```

---

## 🔧 Variables requeridas en `.env`

```env
IMAP_USER=tu-email@gmail.com
IMAP_PASS=tu-app-password  # Para Gmail con 2FA
```

⚠️ **IMPORTANTE**: Para Gmail con 2FA, genera un **App Password** en:
https://myaccount.google.com/apppasswords

---

## 📈 Flujo de ejecución

```
┌─────────────┐
│  Scheduler  │ (cada 30 min)
│ (o manual)  │
└──────┬──────┘
       │
       ▼
┌──────────────────────┐
│ ProcesarFacturas.php │ (Comando Artisan)
│ facturas:leer-correo │
└──────┬───────────────┘
       │
       ▼
┌────────────────────────┐
│ LeerCorreoFacturas.php │ (Job)
│  - Conecta IMAP        │
│  - Lee correos         │
│  - Extrae ZIPs         │
│  - Procesa XML         │
│  - Guarda en BD        │
└────────────────────────┘
```

---

## ✨ Ventajas

✅ No bloquea requests HTTP  
✅ Manejo automático cada 30 minutos  
✅ Logs detallados  
✅ Error handling robusto  
✅ Escalable con colas (queues)  

---

## 🐛 Troubleshooting

### **El scheduler no ejecuta:**
- Verificar que el cron esté corriendo: `crontab -l`
- Verificar permisos: `php artisan` debe ser ejecutable
- Test manual: `php artisan schedule:run`

### **El Job no procesa correos:**
- Verificar `IMAP_USER` y `IMAP_PASS` en `.env`
- Revisar logs: `tail -f storage/logs/laravel.log`
- Test manual: `php artisan facturas:leer-correo`

### **Problema con App Password en Gmail:**
- Generar nuevo App Password
- Usar 16 caracteres (sin espacios)
- Actualizar `.env`

---

## 📞 Soporte

Para más info sobre Laravel Queues:
https://laravel.com/docs/queues

Para más info sobre Task Scheduling:
https://laravel.com/docs/scheduling
