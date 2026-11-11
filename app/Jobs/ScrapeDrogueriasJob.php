<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ScrapeDrogueriasJob
 *
 * Job unificado que busca precios en las tres droguerías externas:
 *   - La Rebaja (VTEX Intelligent Search)
 *   - Farmacias Pasteur (VTEX Intelligent Search)
 *   - Cruz Verde (Salesforce Commerce Cloud / SFCC)
 *
 * Estrategia de búsqueda (en orden de prioridad):
 *   1. Buscar por cada EAN disponible en el campo `ean`
 *   2. Si ningún EAN retorna resultado → precio = NULL (no se usa nombre)
 *
 * Resultado guardado en tabla `shopping`:
 *   precio_rebaja, precio_pasteur, precio_cruz
 */
class ScrapeDrogueriasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $codigo;
    protected string $ean;
    protected string $nombre_producto;

    public $tries = 3;
    public $backoff = 45;
    public $timeout = 180; // Tiempo límite del Job para que no lo mate Laravel

    // ─────────────────────────────────────────────────────────────────────────
    // CONFIGURACIÓN DE FARMACIAS
    // ─────────────────────────────────────────────────────────────────────────
    private const FARMACIAS = [
        'rebaja' => [
            'nombre' => 'La Rebaja',
            'url' => 'https://www.larebajavirtual.com/api/intelligent-search/product_search/trade-policy/1',
            'referer' => 'https://www.larebajavirtual.com/',
            'tipo' => 'vtex',
            'campo_bd' => 'precio_rebaja',
            'campo_desc' => 'descuento_rebaja',
        ],
        'pasteur' => [
            'nombre' => 'Pasteur',
            'url' => 'https://www.farmaciaspasteur.com.co/api/io/_v/api/intelligent-search/product_search/trade-policy/1',
            'referer' => 'https://www.farmaciaspasteur.com.co/',
            'tipo' => 'vtex',
            'campo_bd' => 'precio_pasteur',
            'campo_desc' => 'descuento_pasteur',
        ],
        /* 'cruzverde' => [
            'nombre' => 'Cruz Verde',
            // API BFF Node.js (Requiere sesión activa, obtenida vía Browsershot)
            'url' => 'https://api.cruzverde.com.co/product-service/products/search-suggestions',
            'referer' => 'https://www.cruzverde.com.co/',
            'tipo' => 'cruzverde',
            'campo_bd' => 'precio_cruz',
        ], */
        'alemana' => [
            'nombre' => 'Tu Droguería Virtual',
            'url' => 'https://www.tudrogueriavirtual.com/api/catalog_system/pub/products/search',
            'referer' => 'https://www.tudrogueriavirtual.com/',
            'tipo' => 'vtex_classic',
            'campo_bd' => 'precio_alemana',
            'campo_desc' => 'descuento_alemana',
        ],
        'colsubsidio' => [
            'nombre' => 'Colsubsidio',
            'url' => 'https://www.drogueriascolsubsidio.com/api/catalog_system/pub/products/search',
            'referer' => 'https://www.drogueriascolsubsidio.com/',
            'tipo' => 'vtex_classic',
            'campo_bd' => 'precio_colsubsidio',
            'campo_desc' => 'descuento_colsubsidio',
        ],
    ];

    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
    ];

    public function __construct(string $codigo, string $ean, string $nombre_producto)
    {
        $this->codigo = $codigo;
        $this->ean = trim($ean);
        $this->nombre_producto = $nombre_producto;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HANDLE: Recorre las tres farmacias secuencialmente
    // ─────────────────────────────────────────────────────────────────────────
    public function handle(): void
    {
        if (empty($this->ean)) {
            Log::notice("[Droguerías] Producto sin EAN, omitido: [{$this->nombre_producto}]");
            return;
        }

        Log::debug("[Droguerías] Procesando [{$this->nombre_producto}] EAN: [{$this->ean}]");

        $actualizaciones = [];
        $eans = preg_split('/[\s,]+/', $this->ean, -1, PREG_SPLIT_NO_EMPTY);

        foreach (self::FARMACIAS as $key => $config) {
            $cacheKey = "{$key}_" . md5($this->codigo);

            // Bypass temporal de caché para depuración
            /*
            if (Cache::has($cacheKey)) {
                Log::debug("[{$config['nombre']}] Caché hit → [{$this->codigo}]");
                continue;
            }
            */

            // Delay entre farmacia y farmacia
            sleep(rand(2, 5));

            $resultado = match ($config['tipo']) {
                'vtex' => $this->buscarVtex($eans, $config),
                'vtex_classic' => $this->buscarVtexClassic($eans, $config),
                default => null,
            };

            if ($resultado !== null) {
                $actualizaciones[$config['campo_bd']] = $resultado['precio'];
                if ($resultado['descuento']) {
                    $actualizaciones[$config['campo_desc']] = $resultado['descuento'];
                    Log::info("[{$config['nombre']}] ✔ [{$this->nombre_producto}] → Normal: $ {$resultado['precio']} | Oferta: $ {$resultado['descuento']}");
                } else {
                    $actualizaciones[$config['campo_desc']] = null;
                    Log::info("[{$config['nombre']}] ✔ [{$this->nombre_producto}] → $ {$resultado['precio']}");
                }
                Cache::put($cacheKey, $resultado, now()->addHours(6));
            } else {
                Log::notice("[{$config['nombre']}] Sin precio → [{$this->nombre_producto}]");
                Cache::put($cacheKey, 'not_found', now()->addHours(2));
            }
        }

        if (!empty($actualizaciones)) {
            DB::table('shopping')
                ->where('codigo', $this->codigo)
                ->update($actualizaciones);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VTEX Intelligent Search (La Rebaja y Pasteur)
    // ─────────────────────────────────────────────────────────────────────────
    private function buscarVtex(array $eans, array $config): ?array
    {
        $mejorCandidato = null;
        $maxPuntaje = 0;

        foreach ($eans as $ean) {
            \Log::debug("[{$config['nombre']}] Buscando EAN: [{$ean}]");
            $data = $this->peticion($config['url'], ['query' => $ean, 'count' => 10], $config['referer']);

            foreach ($data['products'] ?? [] as $product) {
                $nombreProductoWeb = $product['productName'] ?? '';
                
                foreach ($product['items'] ?? [] as $sku) {
                    $nombreSkuWeb = $sku['nameComplete'] ?? $sku['name'] ?? '';
                    $specs = [];
                    foreach ($product['properties'] ?? [] as $prop) {
                        if (in_array($prop['name'], ['Presentacionunidadmedida', 'Presentación', 'Unidades', 'Contenido'])) {
                            foreach ($prop['values'] ?? [] as $val) $specs[] = $val;
                        }
                    }
                    $nombreCompletoWeb = $nombreProductoWeb . ' ' . $nombreSkuWeb . ' ' . implode(' ', $specs);

                    $offer = $sku['sellers'][0]['commertialOffer'] ?? null;
                    if ($offer && (($offer['Price'] ?? $offer['price'] ?? 0) > 0)) {
                        $pNormal = $offer['ListPrice'] ?? $offer['listPrice'] ?? $offer['Price'] ?? $offer['price'];
                        $pOferta = $offer['Price'] ?? $offer['price'];
                        
                        $precio    = $pNormal;
                        $descuento = ($pNormal > $pOferta) ? $pOferta : null;

                        $puntaje = $this->calcularPuntaje($this->nombre_producto, $nombreCompletoWeb);
                        
                        if ($puntaje > $maxPuntaje) {
                            $maxPuntaje = $puntaje;
                            $mejorCandidato = [
                                'precio' => (float) $precio,
                                'descuento' => $descuento ? (float) $descuento : null,
                                'nombre' => $nombreCompletoWeb,
                                'ean_encontrado' => $ean
                            ];
                        }
                    }
                }
            }
        }

        if ($mejorCandidato) {
            \Log::debug("[Scraper] Puntaje final para '{$mejorCandidato['nombre']}': {$maxPuntaje} " . ($maxPuntaje < 12 ? "(RECHAZADO por bajo puntaje)" : "(ACEPTADO)"));
        }

        if ($mejorCandidato && $maxPuntaje >= 12) { 
            \Log::info("[Scraper] Mejor coincidencia para '{$this->nombre_producto}': '{$mejorCandidato['nombre']}' con puntaje {$maxPuntaje} (EAN: {$mejorCandidato['ean_encontrado']})");
            return ['precio' => $mejorCandidato['precio'], 'descuento' => $mejorCandidato['descuento']];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VTEX Classic API (Tu Droguería Virtual / Alemana)
    // ─────────────────────────────────────────────────────────────────────────
    private function buscarVtexClassic(array $eans, array $config): ?array
    {
        $mejorCandidato = null;
        $maxPuntaje = 0;

        foreach ($eans as $ean) {
            \Log::debug("[{$config['nombre']}] Buscando EAN (Classic): [{$ean}]");
            $data = $this->peticion($config['url'], ['fq' => "alternateIds_Ean:{$ean}"], $config['referer']);

            if (!is_array($data) || empty($data)) {
                \Log::debug("[{$config['nombre']}] No se encontraron resultados para EAN: [{$ean}]");
                continue;
            }

            foreach ($data as $product) {
                $nombreProductoWeb = $product['productName'] ?? '';

                foreach ($product['items'] ?? [] as $sku) {
                    $nombreSkuWeb = $sku['nameComplete'] ?? $sku['name'] ?? '';
                    // Extraer especificaciones de forma dinámica basado en lo que la tienda reporte
                    $specs = [];
                    if (isset($product['allSpecifications']) && is_array($product['allSpecifications'])) {
                        foreach ($product['allSpecifications'] as $sField) {
                            if (isset($product[$sField])) {
                                if (is_array($product[$sField])) foreach ($product[$sField] as $v) $specs[] = $v;
                                else $specs[] = $product[$sField];
                            }
                        }
                    } else {
                        // Fallback por si no viene la lista de specs
                        foreach (['Presentación', 'Unidades', 'Contenido', 'Presentacionunidadmedida', 'Concentración'] as $sField) {
                            if (isset($product[$sField])) {
                                if (is_array($product[$sField])) foreach ($product[$sField] as $v) $specs[] = $v;
                                else $specs[] = $product[$sField];
                            }
                        }
                    }
                    $nombreCompletoWeb = $nombreProductoWeb . ' ' . $nombreSkuWeb . ' ' . implode(' ', $specs);

                    $offer = $sku['sellers'][0]['commertialOffer'] ?? null;
                    if ($offer && (($offer['Price'] ?? $offer['price'] ?? 0) > 0)) {
                        $pNormal = $offer['ListPrice'] ?? $offer['listPrice'] ?? $offer['Price'] ?? $offer['price'];
                        $pOferta = $offer['Price'] ?? $offer['price'];

                        $precio    = $pNormal;
                        $descuento = ($pNormal > $pOferta) ? $pOferta : null;

                        $puntaje = $this->calcularPuntaje($this->nombre_producto, $nombreCompletoWeb);

                        if ($puntaje > $maxPuntaje) {
                            $maxPuntaje = $puntaje;
                            $mejorCandidato = [
                                'precio' => (float) $precio,
                                'descuento' => $descuento ? (float) $descuento : null,
                                'nombre' => $nombreCompletoWeb,
                                'ean_encontrado' => $ean
                            ];
                        }
                    }
                }
            }
        }

        if ($mejorCandidato) {
            // Nota: En vtex_classic usamos fq=alternateIds_Ean, lo cual ya es un filtro exacto.
            // El puntaje es solo para verificar que no sea algo totalmente loco, pero bajamos el umbral a 0 si hay EAN match.
            \Log::debug("[{$config['nombre']}] Mejor candidato: '{$mejorCandidato['nombre']}' con puntaje {$maxPuntaje} (EAN: {$mejorCandidato['ean_encontrado']})");
            
            if ($maxPuntaje > -50) { // Umbral muy bajo dado que ya filtramos por EAN
                return ['precio' => $mejorCandidato['precio'], 'descuento' => $mejorCandidato['descuento']];
            } else {
                \Log::warning("[{$config['nombre']}] Match RECHAZADO por puntaje extremadamente bajo ({$maxPuntaje}) para '{$mejorCandidato['nombre']}'");
            }
        }

        return null;
    }

    /*
    // ─────────────────────────────────────────────────────────────────────────
    // Cruz Verde (API BFF + Browsershot Invisible)
    // Elude los bloqueos de CloudFront simulando un navegador Chrome real.
    // ─────────────────────────────────────────────────────────────────────────
    private function buscarCruzVerde(array $eans, array $config): ?float
    {
        foreach ($eans as $ean) {
            $apiUrl = $config['url'] . '?limit=1&q=' . $ean;
            $ua = $this->userAgents[array_rand($this->userAgents)];

            try {
                // Forzamos el HOME a /tmp para que Chrome no intente crear carpetas en /var/www/.local
                putenv('HOME=/tmp');

                // 1. Navegamos a la HOME para tener cookies de CloudFront vivas
                // 2. Ejecutamos un fetch() nativo dentro del DOM para obtener el JSON real
                $result = \Spatie\Browsershot\Browsershot::url('https://www.cruzverde.com.co/')
                    ->userAgent($ua)
                    ->windowSize(1920, 1080)
                    ->timeout(120) // Aumentamos la paciencia a 120 segundos
                    ->setNodeModulePath(base_path('node_modules'))
                    ->setChromePath('/usr/bin/google-chrome') // Ruta oficial de Google Chrome (.deb)
                    ->noSandbox()
                    ->addChromiumArguments([
                        'user-data-dir=/tmp/chromium-profile',
                        'disable-gpu',
                        'disable-dev-shm-usage',
                        'no-zygote',
                        'no-first-run',
                        'no-default-browser-check',
                        'disable-extensions',
                        'disable-component-update',
                        'disable-features=IsolateOrigins,site-per-process',
                    ])
                    ->setEnvVars(['HOME' => '/tmp'])
                    ->blockUrls(['*.png', '*.jpg', '*.jpeg', '*.gif', '*.svg', '*.woff*']) // Mantenemos bloqueo de imágenes/fuentes
                    ->waitUntilDomContentLoaded() // Solo esperamos el HTML base
                    ->evaluate(<<<JS
                        (function() {
                            return fetch("{$apiUrl}")
                                .then(function(response) { return response.json(); })
                                .then(function(data) { return { success: true, data: data }; })
                                .catch(function(error) { return { success: false, error: error.toString() }; });
                        })()
                    JS);

                if (!($result['success'] ?? false)) {
                    Log::warning("[Cruz Verde] Fetch falló para {$ean}: " . ($result['error'] ?? 'Desconocido'));
                    continue;
                }

                $data = $result['data'] ?? [];
                $products = $data['productSuggestions']['products'] ?? [];

                if (!empty($products) && is_array($products)) {
                    $product = $products[0];
                    $nombreWeb = $product['productName'] ?? '';

                    // Extraer precio: buscamos en la estructura del BFF de Cruz Verde
                    // sellers[0].commertialOffer.Price es el estándar de VTEX que Cruz Verde copió
                    $price = $product['items'][0]['sellers'][0]['commertialOffer']['Price'] ?? null;

                    if ($price > 0 && $this->esSimilar($this->nombre_producto, $nombreWeb)) {
                        Log::debug("[Cruz Verde] EAN {$ean} → $ {$price} (Match: {$nombreWeb})");
                        return (float) $price;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[Cruz Verde] Error Browsershot para EAN {$ean}: " . $e->getMessage());
            }

            // Pequeña pausa para no saturar el servidor entre arranques de Chrome
            sleep(2);
        }

        return null;
    }
    */


    // ─────────────────────────────────────────────────────────────────────────
    // HTTP Helper genérico con reintentos
    // ─────────────────────────────────────────────────────────────────────────
    private function peticion(string $url, array $params, string $referer, array $customHeaders = []): array
    {
        $ua = $this->userAgents[array_rand($this->userAgents)];

        for ($i = 1; $i <= 3; $i++) {
            try {
                $headers = array_merge([
                    'User-Agent' => $ua,
                    'Accept' => 'application/json',
                    'Accept-Language' => 'es-CO,es;q=0.9',
                    'Referer' => $referer,
                    'X-Requested-With' => 'XMLHttpRequest',
                ], $customHeaders);

                // Importante: No mezclar llaves duplicadas si ya vienen en $customHeaders
                $response = Http::timeout(15)
                    ->withHeaders($headers)
                    ->get($url, $params);

                if ($response->successful()) {
                    $json = $response->json() ?? [];
                    \Log::debug("[Droguerías] OK {$url} - " . (count($json['products'] ?? $json) . " items encontrados"));
                    return $json;
                }

                \Log::warning("[Droguerías] ERROR {$response->status()} en {$url}");

                if (in_array($response->status(), [429, 403])) {
                    Log::warning("[Droguerías] HTTP {$response->status()} en {$url} – esperando 20s");
                    sleep(20);
                }
            } catch (\Throwable $e) {
                Log::debug("[Droguerías] Intento {$i}/3 fallido en {$url}: " . $e->getMessage());
                sleep(5 * $i);
            }
        }

        return [];
    }

    private function calcularPuntaje(string $local, string $web): int
    {
        $limpiar = function($str) {
            $str = strtolower($str);
            $buscar     = ['á','é','í','ó','ú','ñ'];
            $reemplazar = ['a','e','i','o','u','n'];
            $str = str_replace($buscar, $reemplazar, $str);
            // Separar números de letras (ej: 50ml -> 50 ml)
            $str = preg_replace('/([0-9]+)([a-z]+)/i', '$1 $2', $str);
            $str = preg_replace('/([a-z]+)([0-9]+)/i', '$1 $2', $str);
            $str = preg_replace('/[^a-z0-9 ]/', ' ', $str); 
            return array_filter(explode(' ', $str), fn($w) => strlen($w) > 0); // Permitimos palabras de 1 char (ej. '1')
        };

        $palabrasLocal = $limpiar($local);
        $palabrasWeb   = $limpiar($web);
        
        $score = 0;
        $matchClave = 0;
        $mismatchNumerico = false;
        
        $stopwords = ['caja', 'por', 'con', 'und', 'unidad', 'unidades', 'gramos', 'mililitros', 'tabletas', 'capsulas', 'frasco', 'ampolla', 'sobre', 'sobres', 'yardas', 'yarda'];

        // 1. Premiar coincidencias
        foreach ($palabrasLocal as $pL) {
            $encontrada = false;
            foreach ($palabrasWeb as $pW) {
                if ($pL === $pW) {
                    if (is_numeric($pL)) {
                        $score += 40; // Los números son CRÍTICOS (ej. '24' vs '1')
                    } elseif (in_array($pL, $stopwords)) {
                        $score += 5;
                    } else {
                        $score += 20; 
                        $matchClave++;
                    }
                    $encontrada = true;
                    break;
                }
            }
            // Si hay un número en local que NO está en web, penalización fuerte
            if (!$encontrada && is_numeric($pL) && intval($pL) > 1) {
                $score -= 50; 
            }
        }

        // 2. Penalizar palabras en web que NO están en local (evita que nombres más largos ganen por error)
        foreach ($palabrasWeb as $pW) {
            if (!in_array($pW, $palabrasLocal)) {
                if (is_numeric($pW)) {
                    $score -= 60; // Web tiene un número que local no tiene (ej. web dice '1' y local '24')
                } else {
                    $score -= 2; // Penalización leve por ruido
                }
            }
        }

        // 3. Validación mínima: al menos una palabra clave o coincidencia numérica fuerte
        if ($matchClave === 0 && (!preg_grep('/[0-9]/', $palabrasLocal) || $score < 40)) {
            return 0;
        }

        return $score;
    }
}

