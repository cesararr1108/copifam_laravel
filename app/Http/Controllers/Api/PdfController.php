<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdfController extends Controller
{
    /**
     * Directorios base permitidos para la descarga de PDFs.
     * Se aceptan rutas absolutas o relativas a cualquiera de estas bases.
     *
     * Añade aquí cualquier directorio raíz donde vivan los PDFs del servidor.
     */
    private array $basesPermitidas = [
        '/var/www/apicart/writable/facturas',          // API Cart (PHP puro)
        '/var/www/copifam_laravel/storage/app/facturas', // Storage Laravel
    ];

    /**
     * Descargar un PDF del servidor.
     *
     * Acepta GET o POST con los parámetros:
     *
     *   ruta   (string, requerido)
     *          Puede ser:
     *          a) Ruta absoluta:  /var/www/adg/facturas/z0811.../ad0811....pdf
     *          b) Ruta relativa:  z0811.../ad0811....pdf
     *             → se busca en cada base permitida hasta encontrar el archivo
     *
     *   nombre (string, opcional) — nombre de descarga (sin extensión)
     *
     * Ejemplos:
     *   GET /api/pdf/descargar?ruta=z081.../ad081....pdf
     *   GET /api/pdf/descargar?ruta=/var/www/adg/facturas/doc.pdf&nombre=Factura-001
     */
    public function descargar(Request $request): BinaryFileResponse
    {
        $rutaSolicitada = trim($request->input('ruta', ''));
        $nombreDescarga = trim($request->input('nombre', ''));

        // ── 1. Parámetro requerido ────────────────────────────────────────────
        if ($rutaSolicitada === '') {
            abort(400, 'El parámetro "ruta" es requerido.');
        }

        // ── 2. Protección contra path traversal ──────────────────────────────
        if (str_contains($rutaSolicitada, '..')) {
            abort(403, 'Ruta no permitida.');
        }

        // ── 3. Resolver ruta real ─────────────────────────────────────────────
        $rutaReal = $this->resolverRuta($rutaSolicitada);

        if ($rutaReal === null) {
            abort(404, 'Archivo no encontrado. Ruta recibida: "' . $rutaSolicitada . '"');
        }

        // ── 4. Validar que está dentro de una base permitida ─────────────────
        if (! $this->estaEnBasePermitida($rutaReal)) {
            abort(403, 'No tiene permiso para acceder a esta ruta.');
        }

        // ── 5. Validar que es un archivo PDF ──────────────────────────────────
        if (! is_file($rutaReal)) {
            abort(400, 'La ruta indicada no corresponde a un archivo.');
        }

        if (strtolower(pathinfo($rutaReal, PATHINFO_EXTENSION)) !== 'pdf') {
            abort(415, 'Solo se permite la descarga de archivos PDF.');
        }

        // ── 6. Nombre final del archivo descargado ────────────────────────────
        $nombreFinal = $nombreDescarga !== ''
            ? $nombreDescarga . '.pdf'
            : basename($rutaReal);

        // ── 7. Enviar archivo ─────────────────────────────────────────────────
        return response()->download($rutaReal, $nombreFinal, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreFinal . '"',
        ]);
    }

    /**
     * Resuelve la ruta real del archivo.
     *
     * Si la ruta es absoluta y existe → la devuelve directamente.
     * Si es relativa → la busca dentro de cada base permitida.
     * Retorna null si no se encuentra en ningún lugar.
     */
    private function resolverRuta(string $ruta): ?string
    {
        // Ruta absoluta
        if (str_starts_with($ruta, '/')) {
            $real = realpath($ruta);
            return $real !== false ? $real : null;
        }

        // Ruta relativa: buscar en cada base permitida
        foreach ($this->basesPermitidas as $base) {
            $candidato = realpath($base . DIRECTORY_SEPARATOR . $ruta);
            if ($candidato !== false && is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * Verifica que la ruta resuelta esté dentro de alguna base permitida.
     */
    private function estaEnBasePermitida(string $rutaReal): bool
    {
        foreach ($this->basesPermitidas as $base) {
            $baseReal = realpath($base);
            if ($baseReal !== false && str_starts_with($rutaReal, $baseReal . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }
}
