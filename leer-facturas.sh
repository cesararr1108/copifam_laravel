#!/bin/bash

# 📧 Script para ejecutar lectura de facturas cada minuto
# Coloca este script en /usr/local/bin/leer-facturas.sh
# Dale permisos: chmod +x /usr/local/bin/leer-facturas.sh

APP_URL="http://192.168.2.82/api/facturas/leer-correo"
LOG_FILE="/var/log/leer-facturas.log"

# Ejecutar curl con timeout de 45 segundos
curl -X POST "$APP_URL" \
  -H "Content-Type: application/json" \
  --max-time 45 \
  -s -w "\n[%{time_total}s]\n" \
  >> "$LOG_FILE" 2>&1

# Agregar timestamp
echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓" >> "$LOG_FILE"
