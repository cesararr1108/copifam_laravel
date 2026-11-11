# API de Envío de Correos - COPIFAM

## Descripción
Controlador API para enviar correos HTML desde cualquier parte del proyecto Laravel. Soporta envío a múltiples destinatarios con validación de emails.

## Endpoint

**URL:** `POST http://192.168.2.82/api/enviar-correo`

## Parámetros (Body JSON)

| Parámetro | Tipo   | Requerido | Descripción |
|-----------|--------|-----------|-------------|
| `to`      | string | Sí        | Email(s) separados por punto y coma (`;`) |
| `subject` | string | Sí        | Asunto del correo |
| `message` | string | Sí        | Contenido HTML del correo |

## Ejemplos de Uso

### 1. Un solo correo
```php
$postData = [
    'to'      => 'cesararr1108@gmail.com',
    'subject' => 'COPIWEB: NUEVO PEDIDO #12345',
    'message' => '<h2>Tu pedido ha sido recibido</h2>'
];
```

### 2. Múltiples correos
```php
$postData = [
    'to'      => 'cesararr1108@gmail.com;bodega@cali.com;admin@empresa.com',
    'subject' => 'COPIWEB: NUEVO PEDIDO',
    'message' => '<h2>Nuevo Pedido Generado</h2>'
];
```

### 3. Con concatenación dinámica (Como en tu código)
```php
$para = "cesararr1108@gmail.com;";
$para .= BodegasEmail($bodega);  // Función que retorna email de la bodega

$postData = [
    'to'      => $para,
    'subject' => "COPIWEB:NUEVO PEDIDO #" . $numero,
    'message' => $msj  // HTML del mensaje
];
```

## Respuestas

### Exitosa (200)
```json
{
    "status": 200,
    "message": "Correos enviados exitosamente",
    "success": true,
    "emails_count": 2,
    "emails": ["cesararr1108@gmail.com", "bodega@cali.com"]
}
```

### Error de validación (422)
```json
{
    "status": 422,
    "message": "Error de validación",
    "success": false,
    "errors": {
        "to": ["El campo to es requerido"]
    }
}
```

### Email inválido (400)
```json
{
    "status": 400,
    "message": "Email inválido: email-invalido",
    "success": false
}
```

### Error de servidor (500)
```json
{
    "status": 500,
    "message": "Error al enviar correo: [detalles del error]",
    "success": false
}
```

## Requisitos de Configuración

Asegurate que el archivo `.env` tenga configurado el driver de correo:

```env
MAIL_MAILER=smtp
MAIL_HOST=tu_host
MAIL_PORT=tu_puerto
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_contraseña
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@copifam.com
MAIL_FROM_NAME="COPIFAM"
```

O usa `log` para pruebas (verifica en `storage/logs/`):
```env
MAIL_MAILER=log
```

## Archivos creados

- `app/Http/Controllers/Api/EmailController.php` - Controlador
- `routes/web.php` - Ruta POST /api/enviar-correo
- `EJEMPLO_ENVIAR_CORREO.php` - Ejemplos de uso
- `API_ENVIAR_CORREO.md` - Esta documentación

## Uso desde JavaScript/Fetch

```javascript
const postData = {
    to: 'cesararr1108@gmail.com;bodega@cali.com',
    subject: 'COPIWEB: NUEVO PEDIDO',
    message: '<h2>Detalles del pedido</h2>'
};

fetch('http://192.168.2.82/api/enviar-correo', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(postData)
})
.then(res => res.json())
.then(data => console.log(data));
```

## Notas Importantes

1. Los emails deben estar **separados por punto y coma** (`;`)
2. Los espacios se trimean automáticamente
3. Todos los emails se validan antes de enviar
4. Si hay un email inválido, retorna error 400 sin enviar nada
5. El HTML se envía como es, sin sanitizar (asegúrate de validar en origen)
6. Los emails se envían de uno en uno internamente

---
Creado para COPIFAM - Sistema de Gestión
