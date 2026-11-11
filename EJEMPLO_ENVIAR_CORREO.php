<?php

/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * EJEMPLO DE USO: Enviar Correo via API
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Ruta: POST http://192.168.2.82/api/enviar-correo
 * 
 * El controlador acepta:
 * - to:      Email(s) concatenados con punto y coma (;)
 * - subject: Asunto del correo
 * - message: HTML del correo
 */

// ── EJEMPLO 1: Un solo correo ────────────────────────────────────────────────
$para = "cesararr1108@gmail.com";
$asunto = "COPIWEB: NUEVO PEDIDO #12345";
$msj = "<h2>Tu pedido ha sido recibido</h2><p>Detalles del pedido...</p>";

$postData = [
    'to'      => $para,
    'subject' => $asunto,
    'message' => $msj
];

// Enviar request
$response = enviarCorreo($postData);
echo json_encode($response);


// ── EJEMPLO 2: Múltiples correos (El usado en tu sistema) ───────────────────
$bodega = "CALI"; // Ejemplo

$para = "cesararr1108@gmail.com;";
// Función que devuelve un email por bodega
$para .= BodegasEmail($bodega);

$numero = 12345;
$asunto = "COPIWEB:NUEVO PEDIDO #" . $numero;

// HTML del mensaje
$msj = "<h1>Nuevo Pedido</h1>";
$msj .= "<p>Número: " . $numero . "</p>";
$msj .= "<p>Bodega: " . $bodega . "</p>";

$postData = [
    'to'      => $para,
    'subject' => $asunto,
    'message' => $msj
];

$response = enviarCorreo($postData);
echo json_encode($response);


// ── EJEMPLO 3: Múltiples correos directamente ────────────────────────────────
$para = "cesararr1108@gmail.com;bodega@cali.com;supervisor@empresa.com";
$asunto = "COPIWEB: NUEVO PEDIDO";
$msj = "<h2>Nuevo Pedido Generado</h2>";

$postData = [
    'to'      => $para,
    'subject' => $asunto,
    'message' => $msj
];

$response = enviarCorreo($postData);
echo json_encode($response);


/**
 * Función helper para enviar correos
 * Puedes incluir esto en cualquier controlador del proyecto
 */
function enviarCorreo($postData)
{
    $url = "http://192.168.2.82/api/enviar-correo";
    
    // Inicializar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Ejecutar request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Procesar respuesta
    return [
        'http_code' => $httpCode,
        'response'  => json_decode($response, true),
    ];
}


/**
 * Respuesta exitosa:
 * {
 *     "status": 200,
 *     "message": "Correos enviados exitosamente",
 *     "success": true,
 *     "emails_count": 2,
 *     "emails": ["cesararr1108@gmail.com", "bodega@cali.com"]
 * }
 * 
 * Respuesta con error:
 * {
 *     "status": 400,
 *     "message": "Email inválido: email-invalido",
 *     "success": false
 * }
 */
