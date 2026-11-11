<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturaElectronicaController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LEER CORREO, PROCESAR XML Y GUARDAR EN BD
    |--------------------------------------------------------------------------
    */
    public function leerCorreo(): JsonResponse
    {
        $mailbox  = '{imap.gmail.com:993/imap/ssl}INBOX';
        $usuario  = config('services.imap.user');
        $password = config('services.imap.pass');

        if (empty($usuario) || empty($password)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Credenciales IMAP no configuradas. Agregue IMAP_USER e IMAP_PASS al .env del servidor.',
            ], 500);
        }

        try {
            $inbox = @imap_open($mailbox, $usuario, $password, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
        } catch (\Throwable $e) {
            $inbox = false;
        }

        if (!$inbox) {
            return response()->json(['ok' => false, 'error' => imap_last_error()], 500);
        }

        $fecha  = date('d-M-Y', strtotime('-25 days'));
        $emails = imap_search($inbox, 'SINCE "' . $fecha . '"');

        if (!$emails) {
            imap_close($inbox);
            return response()->json(['ok' => true, 'mensaje' => 'No hay correos nuevos', 'total_procesados' => 0]);
        }

        $savePath = storage_path('app/facturas/');
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $procesados = [];
        $errores    = [];

        foreach ($emails as $emailNumber) {

            $structure = imap_fetchstructure($inbox, $emailNumber);

            if (!isset($structure->parts)) continue;

            foreach ($structure->parts as $index => $part) {

                if (empty($part->dparameters)) continue;

                foreach ($part->dparameters as $object) {

                    if (strtolower($object->attribute) !== 'filename') continue;

                    $filename = $object->value;

                    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') continue;

                    Log::info("[FacturaElectronica] Procesando ZIP: {$filename}");

                    $body = imap_fetchbody($inbox, $emailNumber, $index + 1);
                    if ($part->encoding == 3) {
                        $body = base64_decode($body);
                    }

                    $zipPath = $savePath . $filename;
                    file_put_contents($zipPath, $body);

                    $zip = new \ZipArchive;
                    if ($zip->open($zipPath) !== true) {
                        $errores[] = "No se pudo abrir el ZIP: {$filename}";
                        Log::warning("[FacturaElectronica] No se pudo abrir ZIP: {$filename}");
                        continue;
                    }

                    $extractPath = $savePath . pathinfo($filename, PATHINFO_FILENAME) . '/';
                    if (!is_dir($extractPath)) {
                        mkdir($extractPath, 0777, true);
                    }

                    $zip->extractTo($extractPath);
                    $zip->close();
                    unlink($zipPath);

                    // Buscar XML dentro del ZIP extraído
                    $xmlPath    = null;
                    $pdfContent = null;

                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($extractPath)
                    );

                    foreach ($files as $file) {
                        if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                            $xmlPath    = $file->getPathname();
                            $xmlName    = $file->getFilename();
                            $pdfContent = $extractPath . pathinfo($xmlName, PATHINFO_FILENAME);
                            break;
                        }
                    }

                    if (!$xmlPath) {
                        $errores[] = "No se encontró XML en ZIP: {$filename}";
                        Log::warning("[FacturaElectronica] No se encontró XML en: {$filename}");
                        continue;
                    }

                    Log::info("[FacturaElectronica] XML encontrado: {$xmlPath}");

                    $xmlContent = file_get_contents($xmlPath);
                    $resultado  = $this->procesarFactura($xmlContent, $pdfContent);

                    if ($resultado['error']) {
                        $errores[] = "Error al parsear {$filename}: " . $resultado['mensaje'];
                        Log::error("[FacturaElectronica] Error parseando XML de {$filename}: " . $resultado['mensaje']);
                        continue;
                    }

                    Log::info("[FacturaElectronica] UUID extraído: " . $resultado['UUID']);

                    try {
                        // si el uuid esta vacio crea un valor para uuid aleatorio
                        if (empty($resultado['UUID'])) {
                            $resultado['UUID'] = uuid_create(UUID_TYPE_RANDOM);
                            Log::warning("[FacturaElectronica] UUID estaba vacío, se generó uno aleatorio: " . $resultado['UUID']);
                        }
                        $guardado = $this->guardarDocumento($resultado, $xmlContent);
                        $procesados[] = [
                            'uuid'   => $resultado['UUID'],
                            'estado' => $guardado ? 'Guardado' : 'Ya existía',
                        ];

                        Log::info("[FacturaElectronica] UUID {$resultado['UUID']} → " . ($guardado ? 'Guardado' : 'Ya existía'));
                    } catch (\Throwable $e) {
                        $errores[] = "Error BD al guardar {$resultado['UUID']}: " . $e->getMessage();
                        Log::error("[FacturaElectronica] Error BD: " . $e->getMessage());
                    }
                }
            }
        }

        imap_close($inbox);

        return response()->json([
            'ok'               => true,
            'mensaje'          => 'Proceso finalizado',
            'total_procesados' => count($procesados),
            'detalle'          => $procesados,
            'errores'          => $errores,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEER XML DIRECTO DESDE DIRECTORIO
    |--------------------------------------------------------------------------
    */
    public function leerXmlDirecto(Request $request): JsonResponse
    {
        $directory = $request->input('dir', storage_path('app/facturas_xml_directo'));

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo crear el directorio de XML: ' . $directory,
                ], 500);
            }
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.xml');

        if (empty($files)) {
            return response()->json([
                'ok'               => true,
                'mensaje'          => 'No hay archivos XML para procesar en el directorio',
                'directorio'       => $directory,
                'total_procesados' => 0,
            ]);
        }

        $procesados = [];
        $errores    = [];

        foreach ($files as $filePath) {
            $xmlContent = file_get_contents($filePath);
            if ($xmlContent === false) {
                $errores[] = "No se pudo leer el archivo: {$filePath}";
                Log::warning("[FacturaElectronica] No se pudo leer el archivo: {$filePath}");
                continue;
            }

            $resultado = $this->procesarFacturaDirecta($xmlContent);

            if ($resultado['error']) {
                $errores[] = "Error parseando XML {$filePath}: " . $resultado['mensaje'];
                Log::error("[FacturaElectronica] Error parseando XML {$filePath}: " . $resultado['mensaje']);
                continue;
            }

            try {
                                  // si el uuid esta vacio crea un valor para uuid aleatorio
                if (empty($resultado['UUID'])) {
                    $resultado['UUID'] = uuid_create(UUID_TYPE_RANDOM);
                    Log::warning("[FacturaElectronica] UUID estaba vacío, se generó uno aleatorio: " . $resultado['UUID']);
                }
                $guardado = $this->guardarDocumento($resultado, $xmlContent);
                $procesados[] = [
                    'archivo' => basename($filePath),
                    'uuid'    => $resultado['UUID'] ?? uuid_create(UUID_TYPE_RANDOM),
                    'estado'  => $guardado ? 'Guardado' : 'Ya existía',
                    'resultado'     => $resultado
                ];

                Log::info("[FacturaElectronica] Archivo {$filePath} -> " . ($guardado ? 'Guardado' : 'Ya existía'));
            } catch (\Throwable $e) {
                $errores[] = "Error BD al guardar {$filePath}: " . $e->getMessage();
                Log::error("[FacturaElectronica] Error BD al guardar {$filePath}: " . $e->getMessage());
            }
        }

        return response()->json([
            'ok'               => true,
            'mensaje'          => 'Proceso finalizado',
            'directorio'       => $directory,
            'total_procesados' => count($procesados),
            'detalle'          => $procesados,
            'errores'          => $errores,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR DOCUMENTOS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        try {
            $q    = trim($request->input('q', ''));
            $like = '%' . $q . '%';

            $docs = DB::select("
                SELECT TOP 100
                    id, uuid, parent_document_id, numero_factura, fecha_emision,
                    nit_proveedor, nombre_proveedor,
                    total_antes_descuentos, total_descuentos,
                    base_gravable, total_iva, total_factura,
                    fecha_creacion, revisada, pdf
                FROM t_documentos_dian_xml
                " . ($q !== '' ? "WHERE numero_factura LIKE ? OR nit_proveedor LIKE ? OR nombre_proveedor LIKE ? OR uuid LIKE ?" : '') . "
                ORDER BY fecha_creacion DESC
            ", $q !== '' ? [$like, $like, $like, $like] : []);

            return response()->json(['ok' => true, 'data' => $docs]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER ITEMS DE UN DOCUMENTO POR UUID
    |--------------------------------------------------------------------------
    */
    public function items(string $uuid): JsonResponse
    {
        try {
            // Sanitizar UUID para evitar problemas de caracteres especiales
            $uuid = trim($uuid);

            // Usar prepared statement con binding de parámetros
            $query = "SELECT TOP 1 [json_items] FROM [t_documentos_dian_xml] WHERE [uuid] = ?";
            
            $results = DB::select($query, [$uuid]);

            if (empty($results)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se encontraron ítems para ese UUID.'
                ], 404);
            }

            $data = $results[0]->json_items ?? null;

            if (!$data) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No hay datos de ítems para ese UUID.'
                ], 404);
            }

            $items = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('[FacturaElectronica] JSON inválido para UUID ' . $uuid . ': ' . json_last_error_msg());
                return response()->json([
                    'ok' => false,
                    'message' => 'El JSON está mal formado'
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'uuid' => $uuid,
                'items' => $items
            ]);

        } catch (\PDOException $e) {
            Log::error('[FacturaElectronica] PDO Error en items(): ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error de conexión a base de datos',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        } catch (\Throwable $e) {
            Log::error('[FacturaElectronica] Error en items(): ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function itemsh(string $uuid): JsonResponse
    {
        try {
            // Sanitizar UUID para evitar problemas de caracteres especiales
            $uuid = trim($uuid);

            // Usar prepared statement con binding de parámetros
            $query = "SELECT TOP 1 [json_items] FROM [t_documentos_dian_xml_his] WHERE [uuid] = ?";
            
            $results = DB::select($query, [$uuid]);

            if (empty($results)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se encontraron ítems para ese UUID.'
                ], 404);
            }

            $data = $results[0]->json_items ?? null;

            if (!$data) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No hay datos de ítems para ese UUID.'
                ], 404);
            }

            $items = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('[FacturaElectronica] JSON inválido para UUID ' . $uuid . ': ' . json_last_error_msg());
                return response()->json([
                    'ok' => false,
                    'message' => 'El JSON está mal formado'
                ], 500);
            }

            return response()->json([
                'ok' => true,
                'uuid' => $uuid,
                'items' => $items
            ]);

        } catch (\PDOException $e) {
            Log::error('[FacturaElectronica] PDO Error en items(): ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Error de conexión a base de datos',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        } catch (\Throwable $e) {
            Log::error('[FacturaElectronica] Error en items(): ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    


    /*
    |--------------------------------------------------------------------------
    | PARSEAR XML DIAN (AttachedDocument)
    |--------------------------------------------------------------------------
    */
    private function procesarFactura(string $xmlContent, ?string $xmlPathPdf): array
    {
        libxml_use_internal_errors(true);

        $xmlContent = trim($xmlContent);
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

        $attached = simplexml_load_string($xmlContent);

        if ($attached === false) {
            return ['error' => true, 'mensaje' => 'XML inválido (AttachedDocument)'];
        }

        // ParentDocumentID
        $parentNode       = $attached->xpath('//*[local-name()="ParentDocumentID"]');
        $parentDocumentID = isset($parentNode[0]) ? (string) $parentNode[0] : null;

        // XML interno embebido en Description
        $inner = $attached->xpath('//*[local-name()="Attachment"]//*[local-name()="Description"]');
        if (!isset($inner[0])) {
            return ['error' => true, 'mensaje' => 'No se encontró el XML interno'];
        }

        $invoice = simplexml_load_string((string) $inner[0]);
        if ($invoice === false) {
            return ['error' => true, 'mensaje' => 'XML interno inválido'];
        }

        return $this->procesarFacturaInterna($invoice, $parentDocumentID, $xmlPathPdf, $xmlContent);
    }

    private function procesarFacturaDirecta(string $xmlContent): array
    {
        libxml_use_internal_errors(true);

        $xmlContent = trim($xmlContent);
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

        $invoice = simplexml_load_string($xmlContent);
        if ($invoice === false) {
            return ['error' => true, 'mensaje' => 'XML inválido'];
        }

        return $this->procesarFacturaInterna($invoice, null, null, $xmlContent);
    }

    private function procesarFacturaInterna(\SimpleXMLElement $invoice, ?string $parentDocumentID, ?string $xmlPathPdf, string $xmlCompleto): array
    {
        $get = fn($node, $path) => ($r = $node->xpath($path)) && isset($r[0]) ? (string) $r[0] : null;

        $numeroFactura   = $get($invoice, './*[local-name()="ID"]');
        $fechaEmision    = $get($invoice, './*[local-name()="IssueDate"]');
        $UUID            = $get($invoice, './*[local-name()="UUID"]');
        $nitProveedor    = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="CompanyID"]');
        $nombreProveedor = $get($invoice, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="RegistrationName"]');

        $totalAntesDescuentos = 0;
        $totalDescuentos      = 0;
        $baseGravable         = 0;
        $totalIVA             = 0;
        $totalFactura         = 0;
        $items                = [];

        foreach ($invoice->xpath('//*[local-name()="InvoiceLine"]') as $line) {
            $cantidad      = (float) $get($line, '*[local-name()="InvoicedQuantity"]');
            $valorLinea    = (float) $get($line, '*[local-name()="LineExtensionAmount"]');
            $valorUnitario = (float) $get($line, './/*[local-name()="PriceAmount"]');

            $descuento    = 0;
            $pcjDescuento = 0;
            foreach ($line->xpath('.//*[local-name()="AllowanceCharge"]') as $allow) {
                if ($get($allow, './/*[local-name()="ChargeIndicator"]') === 'false') {
                    $descuento    = (float) $get($allow, './/*[local-name()="Amount"]');
                    $pcjDescuento = (float) $get($allow, './/*[local-name()="MultiplierFactorNumeric"]');
                    break;
                }
            }

            $ivaLinea      = 0;
            $porcentajeIVA = 0;
            foreach ($line->xpath('.//*[local-name()="TaxSubtotal"]') as $tax) {
                if ($get($tax, './/*[local-name()="TaxScheme"]/*[local-name()="ID"]') === '01') {
                    $ivaLinea      = (float) $get($tax, './/*[local-name()="TaxAmount"]');
                    $porcentajeIVA = (float) $get($tax, './/*[local-name()="Percent"]');
                    break;
                }
            }

            $totalAntesDescuentos += $valorUnitario * $cantidad;
            $totalDescuentos      += $descuento;
            $baseGravable         += $valorLinea;
            $totalIVA             += $ivaLinea;
            $totalFactura         += $valorLinea + $ivaLinea;

            $items[] = [
                'id'                  => $get($line, '*[local-name()="ID"]'),
                'cantidad'            => $cantidad,
                'valor_unitario'      => $valorUnitario,
                'valor_total_linea'   => $valorLinea,
                'descripcion'         => $get($line, './/*[local-name()="Description"]'),
                'codigo'              => $get($line, './/*[local-name()="StandardItemIdentification"]/*[local-name()="ID"]'),
                'porcentaje_iva'      => $porcentajeIVA,
                'valor_iva'           => $ivaLinea,
                'porcentaje_descuento'=> $pcjDescuento,
                'valor_descuento'     => $descuento,
            ];
        }

        return [
            'error'            => false,
            'ParentDocumentID' => $parentDocumentID,
            'numero_factura'   => $numeroFactura,
            'fecha_emision'    => $fechaEmision,
            'UUID'             => $UUID,
            'pdf'              => $xmlPathPdf,
            'proveedor'        => ['nit' => $nitProveedor, 'nombre' => $nombreProveedor],
            'items'            => $items,
            'totales'          => [
                'antes_descuentos' => round($totalAntesDescuentos, 2),
                'total_descuentos' => round($totalDescuentos, 2),
                'base_gravable'    => round($baseGravable, 2),
                'total_iva'        => round($totalIVA, 2),
                'total_factura'    => round($totalFactura, 2),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GUARDAR EN BD VALIDANDO DUPLICADOS
    |--------------------------------------------------------------------------
    */
    private function guardarDocumento(array $data, string $xmlCompleto): bool
    {
        if (empty($data['UUID'])) return false;

        $existe = DB::selectOne(
            "SELECT TOP 1 id FROM t_documentos_dian_xml WHERE uuid = ?",
            [$data['UUID']]
        );

        if ($existe) return false;

        DB::statement("
            INSERT INTO t_documentos_dian_xml (
                uuid, parent_document_id, numero_factura, fecha_emision,
                nit_proveedor, nombre_proveedor,
                total_antes_descuentos, total_descuentos,
                base_gravable, total_iva, total_factura,
                json_items, xml_completo, pdf
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ", [
            $data['UUID'],
            $data['ParentDocumentID'],
            $data['numero_factura'],
            $data['fecha_emision'],
            $data['proveedor']['nit'],
            $data['proveedor']['nombre'],
            $data['totales']['antes_descuentos'],
            $data['totales']['total_descuentos'],
            $data['totales']['base_gravable'],
            $data['totales']['total_iva'],
            $data['totales']['total_factura'],
            json_encode($data['items']),
            $xmlCompleto,
            $data['pdf'],
        ]);

        return true;
    }
}
