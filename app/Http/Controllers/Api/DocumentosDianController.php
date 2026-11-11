<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentosDianController extends Controller
{
    /**
     * Actualiza el campo PDF de los documentos DIAN
     * buscando los XMLs en las carpetas extraídas
     * 
     * @return JsonResponse
     */
    public function actualizarPDFsDesdeXML(): JsonResponse
    {
        try {
            $basePath = storage_path('app/facturas/');
            
            if (!is_dir($basePath)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Carpeta de facturas no existe: ' . $basePath
                ], 404);
            }

            $actualizados = 0;
            $errores = [];
            $noEncontrados = [];
            $yaActualizados = 0;

            // Iterar sobre las carpetas extraídas
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            $xmlsProcesados = [];

            foreach ($iterator as $file) {
                // Solo procesar archivos XML
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                    continue;
                }

                try {
                    $xmlPath = $file->getPathname();
                    $xmlContent = @file_get_contents($xmlPath);

                    if (!$xmlContent) {
                        $errores[] = "No se pudo leer: " . $xmlPath;
                        continue;
                    }

                    // Extraer UUID del XML
                    $uuid = $this->extraerUUID($xmlContent);

                    if (!$uuid) {
                        $errores[] = "UUID no encontrado en: " . $xmlPath;
                        continue;
                    }

                    // Evitar procesar el mismo XML dos veces (por carpeta)
                    if (in_array($uuid, $xmlsProcesados)) {
                        continue;
                    }
                    $xmlsProcesados[] = $uuid;

                    // Buscar el registro en BD
                    $documento = DB::table('t_documentos_dian_xml')
                        ->where('uuid', $uuid)
                        ->first();

                    if (!$documento) {
                        $noEncontrados[] = $uuid;
                        continue;
                    }

                    // Si ya tiene PDF, saltar
                    if (!empty($documento->pdf)) {
                        $yaActualizados++;
                        continue;
                    }

                    // Buscar el PDF en la misma carpeta padre del XML
                    $carpetaPadre = dirname($xmlPath);
                    $pdfPath = $this->buscarPDF($carpetaPadre);

                    if ($pdfPath) {
                        // Guardar ruta relativa desde basePath
                        $rutaPDFRelativa = str_replace($basePath, '', $pdfPath);
                        $rutaPDFRelativa = ltrim($rutaPDFRelativa, DIRECTORY_SEPARATOR);

                        DB::table('t_documentos_dian_xml')
                            ->where('uuid', $uuid)
                            ->update(['pdf' => $rutaPDFRelativa]);

                        $actualizados++;
                    } else {
                        $errores[] = "PDF no encontrado para UUID: " . $uuid;
                    }

                } catch (\Throwable $e) {
                    $errores[] = "Error al procesar XML: " . $e->getMessage();
                    Log::error("[DocumentosDian] Error: " . $e->getMessage());
                }
            }

            return response()->json([
                'ok' => true,
                'mensaje' => 'Actualización completada',
                'stats' => [
                    'actualizados' => $actualizados,
                    'ya_actualizados' => $yaActualizados,
                    'no_encontrados_en_bd' => count($noEncontrados),
                    'errores' => count($errores)
                ],
                'detalles' => [
                    'uuids_no_encontrados' => $noEncontrados,
                    'errores' => $errores
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error("[DocumentosDian] Error general: " . $e->getMessage());
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrae el UUID del contenido XML
     * 
     * @param string $xmlContent
     * @return string|null
     */
    private function extraerUUID(string $xmlContent): ?string
    {
        try {
            libxml_use_internal_errors(true);
            $xmlContent = trim($xmlContent);
            $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;

            if (!@$dom->loadXML($xmlContent)) {
                return null;
            }

            $xpath = new \DOMXPath($dom);

            // Buscar Attachment/Description
            $attachments = $xpath->query('//*[local-name()="Attachment"]');
            if ($attachments->length === 0) {
                return null;
            }

            $description = $xpath->query('.//*[local-name()="Description"]', $attachments->item(0));
            if ($description->length === 0) {
                return null;
            }

            // Parsear Description como XML
            $invoiceXml = $description->item(0)->textContent;
            $invoiceDom = new \DOMDocument();
            if (!@$invoiceDom->loadXML($invoiceXml)) {
                return null;
            }

            $invoiceXpath = new \DOMXPath($invoiceDom);
            $uuidNode = $invoiceXpath->query('//*[local-name()="UUID"]');

            if ($uuidNode && $uuidNode->length > 0) {
                return $uuidNode->item(0)->textContent;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("[DocumentosDian] Error al extraer UUID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca un archivo PDF en una carpeta de forma recursiva
     * 
     * @param string $carpeta
     * @return string|null
     */
    private function buscarPDF(string $carpeta): ?string
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($carpeta)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                    return $file->getPathname();
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("[DocumentosDian] Error al buscar PDF: " . $e->getMessage());
            return null;
        }
    }
}
