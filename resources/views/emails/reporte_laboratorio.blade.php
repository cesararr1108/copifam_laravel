<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Mensual de Stock y Compras</title>
    <style>
        body {
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 650px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            border: 1px solid #e1e7ec;
        }
        .header {
            background-color: #1F4E79;
            color: #ffffff;
            padding: 24px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            background: #e8f1f8;
            color: #1F4E79;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
        }
        .info-card {
            background: #f8fafc;
            border-left: 4px solid #1F4E79;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .info-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-card td {
            padding: 4px 0;
            font-size: 14px;
        }
        .info-card td.label {
            color: #64748b;
            width: 130px;
            font-weight: bold;
        }
        .excel-box {
            background: #f0fdf4;
            border: 1px dashed #22c55e;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
            color: #166534;
        }
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>COPIFAM S.A.S</h1>
            <p>Reporte Oficial de Movimientos Mensuales</p>
        </div>
        <div class="content">
            <p>Estimados señores de <strong>{{ $laboratorio }}</strong>,</p>

            <p>Reciban un cordial saludo. Adjunto a este correo compartimos el informe consolidado correspondiente al <strong>mes vencido</strong> de sus productos comercializados con Copifam.</p>

            <div class="info-card">
                <table>
                    <tr>
                        <td class="label">Laboratorio:</td>
                        <td><strong>{{ $laboratorio }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">NIT:</td>
                        <td>{{ $nit }}</td>
                    </tr>
                    <tr>
                        <td class="label">Período:</td>
                        <td><span class="badge">{{ $nombreMes }} de {{ $ano }}</span></td>
                    </tr>
                </table>
            </div>

            <p>El archivo adjunto de Excel contiene dos hojas de trabajo detalladas:</p>
            <ul>
                <li><strong>STOCK:</strong> Existencias por punto de venta, línea de venta, categoría ABC y órdenes de compra pendientes.</li>
                <li><strong>COMPRA:</strong> Registro de facturas del proveedor, transacciones de compras, cantidades y costos del período.</li>
            </ul>

            <div class="excel-box">
                📎 <strong>Archivo adjunto:</strong> {{ $nombreArchivo }}
            </div>

            <p style="font-size: 13px; color: #64748b; margin-top: 25px;">
                Este es un mensaje generado automáticamente por el sistema institucional de Copifam. Si tiene alguna inquietud respecto a la información presentada, por favor comuníquese con el departamento de compras.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Droguerías Copifam. Todos los derechos reservados.<br>
            Este correo y sus adjuntos son para uso exclusivo del destinatario previsto.
        </div>
    </div>
</body>
</html>
