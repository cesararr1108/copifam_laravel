# ⏰ CONFIGURAR EJECUCIÓN CADA MINUTO

## Paso 1: Copiar script bash a /usr/local/bin

```bash
sudo cp leer-facturas.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/leer-facturas.sh
```

## Paso 2: Crear directorio de logs y permisos

```bash
sudo mkdir -p /var/log/facturas
sudo touch /var/log/leer-facturas.log
sudo chmod 666 /var/log/leer-facturas.log
```

## Paso 3: Agregar crontab para ejecutar CADA MINUTO

```bash
# Abrir editor de crontab
crontab -e

# Agregar esta línea al final (ejecuta cada minuto, todos los días):
* * * * * /usr/local/bin/leer-facturas.sh
```

**Explicación:**
```
* * * * * /comando/a/ejecutar
│ │ │ │ │
│ │ │ │ └─── Día de la semana (0-6) (0=domingo)
│ │ │ └───── Mes (1-12)
│ │ └─────── Día del mes (1-31)
│ └───────── Hora (0-23)
└─────────── Minuto (0-59)

* * * * * = Cada minuto de cada hora, cada día
```

## Verificar que está funcionando

```bash
# Ver logs en tiempo real
tail -f /var/log/leer-facturas.log

# Ver si está en crontab
crontab -l

# Ver ejecuciones del cron
grep CRON /var/log/syslog | tail -20
```

## Ejemplo de log esperado

```
[2026-03-19 14:30:15] ✓ [0.234s]
[2026-03-19 14:30:00] Procesados: 0, Errores: 0
[2026-03-19 14:31:15] ✓ [0.187s]
[2026-03-19 14:31:00] Procesados: 1, Errores: 0
[2026-03-19 14:32:15] ✓ [0.156s]
[2026-03-19 14:32:00] Procesados: 0, Errores: 0
```

## Si necesitas ver más detalles

```bash
# Tail con más contexto
tail -100 /var/log/leer-facturas.log

# Filtrar por email del día
grep "$(date +%Y-%m-%d)" /var/log/leer-facturas.log

# Ver logs de Laravel también
tail -f storage/logs/laravel.log
```

## Detener temporalmente

```bash
# Comentar en crontab (agregar # al inicio)
# * * * * * /usr/local/bin/leer-facturas.sh

# O simplemente
crontab -e
# Comentar o borrar la línea
```

## Diferencias con otras frecuencias

```bash
# Cada minuto
* * * * * /comando

# Cada 5 minutos
*/5 * * * * /comando

# Cada 10 minutos
*/10 * * * * /comando

# Cada hora (minuto 0)
0 * * * * /comando

# Cada 2 horas
0 */2 * * * /comando

# A las 3:30 AM
30 3 * * * /comando

# Lunes a viernes cada minuto
* * * * 1-5 /comando
```

---

## ✅ Ventajas de esta setup:

- ⚡ Ejecuta **exactamente cada minuto**
- 📦 Procesa **máximo 5 correos por ejecución** (rápido)
- ✓ Marca correos como **SEEN** después de procesar
- 📊 **Sin duplicados** (valida UUID en BD)
- 💪 **Sin timeout** (timeout de 45s en curl)
- 📋 **Logs detallados** en `/var/log/leer-facturas.log`
- ⏸️ **Fácil de pausar** si lo necesitas

---

## 🔧 Troubleshooting

### Los logs no se crean
```bash
sudo chown $USER:$USER /var/log/leer-facturas.log
sudo chmod 666 /var/log/leer-facturas.log
```

### El script no se ejecuta
```bash
# Verificar permisos
ls -l /usr/local/bin/leer-facturas.sh

# Debe mostrar: -rwxr-xr-x
# Si no, ejecutar:
sudo chmod +x /usr/local/bin/leer-facturas.sh
```

### Ver errores de cron
```bash
# En Ubuntu/Debian
sudo tail -f /var/log/syslog | grep CRON

# En CentOS/RHEL
sudo tail -f /var/log/cron
```

### Probar manualmente
```bash
/usr/local/bin/leer-facturas.sh

# Debe retornar algo como:
# {"ok":true,"mensaje":"Lectura de correos completada"...
```

---

## 📞 Soporte

Si tienes problemas:

```bash
# Mostrar info del sistema
uname -a
php -v
php -m | grep imap

# Ver variables de cron
env

# Debuggear en tiempo real
watch -n 1 'tail -5 /var/log/leer-facturas.log'
```
