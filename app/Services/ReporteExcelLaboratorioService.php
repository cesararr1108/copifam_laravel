<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReporteExcelLaboratorioService
{
    /**
     * Consulta el stock del laboratorio para el mes y año dados
     */
    public function obtenerDatosStock(string $nit, int $mes, int $ano): array
    {
        $sql = "
            WITH OC_PENDIENTES_CTE AS (
                SELECT 
                    L.CODIGO AS SKU_ID,
                    SUM(L.CANTIDAD - ISNULL(L.CANTIDAD_DESPACHADA, 0)) AS STOCK_OC,
                    L.BODEGA AS PDV_ID
                FROM DOCUMENTOS_LIN_PED L WITH (NOLOCK)
                INNER JOIN DOCUMENTOS_PED P WITH (NOLOCK)
                    ON P.NUMERO = L.NUMERO 
                    AND P.SW = L.SW 
                    AND P.BODEGA = L.BODEGA 
                WHERE L.SW = 3 
                    AND L.CANTIDAD <> ISNULL(L.CANTIDAD_DESPACHADA, 0)
                GROUP BY L.CODIGO, L.BODEGA
            )
            SELECT
                CAST(V.BODEGA AS VARCHAR) + '_' + V.CODIGO AS LLAVE_STOCK,
                DY.DESCRIPCION AS DEPARTAMENTO,
                CY.DESCRIPCION AS CIUDAD,
                V.BODEGA AS PDV_ID,
                B.DESCRIPCION AS PDV,
                ISNULL((
                    SELECT TOP 1 ALTERNO
                    FROM REFERENCIAS_ALT WITH (NOLOCK)
                    WHERE CODIGO = V.CODIGO
                ), '') AS EAN_SKU,
                V.CODIGO AS SKU_ID,
                R.DESCRIPCION AS SKU,
                LAB.NOMBRES AS LABORATORIO,
                LIN.DESCRIPCION AS LINEA_LAB,
                CASE WHEN R.TAM_ANCHO > 0 THEN 'PREMIUM' ELSE 'NO PREMIUM' END AS LINEA_DE_VENTA,
                ISNULL(RV.CATEGORIA, '') AS ABC_VENTA,
                V.STOCK AS STOCK_TOTAL,
                CASE 
                    WHEN R.UND_COM = 1 THEN R.CAN_1 
                    WHEN R.UND_COM = 2 THEN R.CAN_2
                    WHEN R.UND_COM = 3 THEN R.CAN_3
                END AS STOCK_PACKS_PDV,
                ISNULL(CC.STOCK_OC, 0) AS STOCK_OC
            FROM V_REF_TOTAL V WITH (NOLOCK)
            INNER JOIN BODEGAS B WITH (NOLOCK)
                ON B.BODEGA = V.BODEGA 
            INNER JOIN REFERENCIAS R WITH (NOLOCK)
                ON R.CODIGO = V.CODIGO 
            LEFT JOIN REFERENCIAS_TIP6 LIN WITH (NOLOCK)
                ON LIN.TIPO_6 = R.TIPO_6
            LEFT JOIN TERCEROS LAB WITH (NOLOCK)
                ON LAB.NIT = R.NIT 
            LEFT JOIN Y_DEPARTAMENTOS DY WITH (NOLOCK)
                ON DY.DEPARTAMENTO = B.DEPARTAMENTO
            LEFT JOIN Y_CIUDADES CY WITH (NOLOCK)
                ON CY.DEPARTAMENTO = DY.DEPARTAMENTO
                AND CY.CIUDAD = B.CIUDAD
            LEFT JOIN COPIWEB.DBO.REF_CATEGORIAS RV WITH (NOLOCK)
                ON RV.CODIGO = V.CODIGO 
            LEFT JOIN OC_PENDIENTES_CTE CC 
                ON CC.SKU_ID = V.CODIGO 
                AND CC.PDV_ID = V.BODEGA 
            WHERE V.MES = :mes 
                AND V.ANO = :ano
                AND R.NIT = :nit
        ";

        return DB::select($sql, [
            'mes' => $mes,
            'ano' => $ano,
            'nit' => $nit,
        ]);
    }

    /**
     * Consulta las compras del laboratorio para el mes y año dados
     */
    public function obtenerDatosCompra(string $nit, int $mes, int $ano): array
    {
        $sql = "
            SELECT  
                CAST(L.BODEGA AS VARCHAR) + '_' + L.CODIGO AS LLAVE_COMPRA,
                D.TIPO + CAST(D.NUMERO AS VARCHAR) AS DOCUMENTO_INTERNO,
                D.DOCUMENTO AS FACTURA_PROVEEDOR,
                CAST(D.FECHA_HORA AS DATE) AS FECHA,
                DATEPART(HOUR, D.FECHA_HORA) AS HORA,
                'COMPRA ' + B.DESCRIPCION AS TIPO_TRANSACCION,
                L.BODEGA AS BODEGA_ID, 
                B.DESCRIPCION AS BODEGA,
                T.NIT AS PROVEEDOR_ID,
                T.NOMBRES AS PROVEEDOR,
                L.CODIGO AS SKU_ID,
                R.DESCRIPCION AS PRODUCTO,
                CASE 
                    WHEN R.UND_COM = 1 THEN R.UND_1 
                    WHEN R.UND_COM = 2 THEN R.UND_1 
                    WHEN R.UND_COM = 3 THEN R.UND_3 
                END AS UNIDAD_COMPLETA, 
                T.NOMBRES AS LABORATORIO, 
                R6.DESCRIPCION AS LINEA_LABORATORIO, 
                CASE WHEN ISNULL(R3.TIPO_3, '0') = '1' THEN 'PREMIUM' ELSE 'NO_PREMIUM' END AS LINEA_PREMIUM,
                L.CANTIDAD AS UM_COMPRADAS,
                L.CANTIDAD / NULLIF(L.CANTIDAD_UND, 0) AS PACKS_COMPRADAS,
                L.COSTO_UNITARIO AS COSTO_UNITARIO,
                L.PORCENTAJE_IVA AS PORCENTAJE_IVA,
                (L.COSTO_UNITARIO) * L.CANTIDAD AS COMPRA_NETA,
                (L.COSTO_UNITARIO * ((L.PORCENTAJE_IVA / 100))) * L.CANTIDAD AS VALOR_IVA,
                (L.COSTO_UNITARIO * ((L.PORCENTAJE_IVA / 100) + 1)) * L.CANTIDAD AS COMPRA_TOTAL,
                ISNULL(R.TAM_ANCHO, 0) AS ES_BONIFICACION
            FROM DOCUMENTOS_LIN L WITH (NOLOCK)
            INNER JOIN REFERENCIAS R WITH (NOLOCK)
                ON R.CODIGO = L.CODIGO 
            LEFT JOIN REFERENCIAS_TIP6 R6 WITH (NOLOCK)
                ON R6.TIPO_6 = R.TIPO_6 
            LEFT JOIN REFERENCIAS_TIP3 R3 WITH (NOLOCK)
                ON R3.TIPO_3 = R.TIPO_3 
            INNER JOIN DOCUMENTOS D WITH (NOLOCK)
                ON D.TIPO = L.TIPO AND D.NUMERO = L.NUMERO
            INNER JOIN TERCEROS T WITH (NOLOCK)
                ON T.NIT = D.NIT 
            INNER JOIN BODEGAS B WITH (NOLOCK)
                ON B.BODEGA = D.BODEGA  
            WHERE L.SW = 3 
                AND MONTH(L.FEC) = :mes 
                AND YEAR(L.FEC) = :ano 
                AND D.ANULADO = 0
                AND T.NIT = :nit
        ";

        return DB::select($sql, [
            'mes' => $mes,
            'ano' => $ano,
            'nit' => $nit,
        ]);
    }

    /**
     * Genera el archivo Excel de 2 pestañas y retorna la ruta completa del archivo
     */
    public function generarExcel(string $nit, string $laboratorio, int $mes, int $ano): string
    {
        $datosStock = $this->obtenerDatosStock($nit, $mes, $ano);
        $datosCompra = $this->obtenerDatosCompra($nit, $mes, $ano);

        $spreadsheet = new Spreadsheet();

        // ─────────────────────────────────────────────────────────────────────
        // HOJA 1: STOCK
        // ─────────────────────────────────────────────────────────────────────
        $sheetStock = $spreadsheet->getActiveSheet();
        $sheetStock->setTitle('STOCK');

        $headersStock = [
            'LLAVE_STOCK',
            'DEPARTAMENTO',
            'CIUDAD',
            'PDV_ID',
            'PDV',
            'EAN_SKU',
            'SKU_ID',
            'SKU',
            'LABORATORIO',
            'LINEA_LAB',
            'LINEA_DE_VENTA',
            'ABC_VENTA',
            'STOCK_TOTAL',
            'STOCK_PACKS_PDV',
            'STOCK_OC',
        ];

        $this->llenarHoja($sheetStock, $headersStock, $datosStock);

        // ─────────────────────────────────────────────────────────────────────
        // HOJA 2: COMPRA
        // ─────────────────────────────────────────────────────────────────────
        $sheetCompra = $spreadsheet->createSheet();
        $sheetCompra->setTitle('COMPRA');

        $headersCompra = [
            'LLAVE_COMPRA',
            'DOCUMENTO_INTERNO',
            'FACTURA_PROVEEDOR',
            'FECHA',
            'HORA',
            'TIPO_TRANSACCION',
            'BODEGA_ID',
            'BODEGA',
            'PROVEEDOR_ID',
            'PROVEEDOR',
            'SKU_ID',
            'PRODUCTO',
            'UNIDAD_COMPLETA',
            'LABORATORIO',
            'LINEA_LABORATORIO',
            'LINEA_PREMIUM',
            'UM_COMPRADAS',
            'PACKS_COMPRADAS',
            'COSTO_UNITARIO',
            'PORCENTAJE_IVA',
            'COMPRA_NETA',
            'VALOR_IVA',
            'COMPRA_TOTAL',
            'ES_BONIFICACION',
        ];

        $this->llenarHoja($sheetCompra, $headersCompra, $datosCompra);

        // Dejar activa la primera hoja al abrir
        $spreadsheet->setActiveSheetIndex(0);

        // Directorio de salida
        $dir = storage_path('app/temp/reportes_laboratorios');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $cleanLab = Str::slug($laboratorio, '_');
        $cleanLab = Str::limit($cleanLab, 30, '');
        $mesPadded = str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
        $fileName = "Reporte_{$nit}_{$cleanLab}_{$ano}_{$mesPadded}_" . date('Ymd_His') . ".xlsx";
        $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Escribe encabezados y datos con estilos en la hoja dada
     */
    protected function llenarHoja($sheet, array $headers, array $data): void
    {
        // Encabezados
        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($colIndex, 1);
            $cell->setValue($header);
            $colIndex++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

        // Estilo de encabezados
        $headerRange = "A1:{$lastColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'], // Azul institucional oscuro
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => false,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // Llenar filas de datos
        $rowNum = 2;
        foreach ($data as $item) {
            $colIndex = 1;
            $rowArray = (array)$item;

            foreach ($headers as $header) {
                $val = $rowArray[$header] ?? '';
                $cell = $sheet->getCellByColumnAndRow($colIndex, $rowNum);
                
                if (is_numeric($val) && !str_starts_with((string)$val, '0')) {
                    $cell->setValueExplicit($val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                } else {
                    $cell->setValueExplicit((string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $colIndex++;
            }
            $rowNum++;
        }

        // Borde fino a la tabla y autoajuste de ancho de columnas
        $totalRows = max($rowNum - 1, 1);
        $sheet->getStyle("A1:{$lastColLetter}{$totalRows}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D9D9D9'],
                ],
            ],
        ]);

        foreach (range(1, count($headers)) as $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Congelar encabezados
        $sheet->freezePane('A2');
    }
}
