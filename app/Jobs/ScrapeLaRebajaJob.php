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

class ScrapeLaRebajaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $codigo;
    protected $descripcion;

    /**
     * Reintentos permitidos si ocurre una falla HTTP (p. ej. Rate Limit)
     */
    public $tries = 3;

    /**
     * Espera antes de volver a intentar en caso de error (en segundos)
     */
    public $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct($codigo, $descripcion)
    {
        $this->codigo = $codigo;
        $this->descripcion = $descripcion;
    }

    /**
     * Pool de User-Agents para rotación anti-detección
     */
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
    ];

    /**
     * Execute the job.
     */
    public function handle()
    {
        // ── Buenas prácticas: delay aleatorio 2–5s para no saturar el servidor ──
        $delay = rand(2, 5);
        Log::debug("Scraper: esperando {$delay}s antes de consultar [{$this->descripcion}]");
        sleep($delay);

        try {
            // 1. Verificar caché para evitar peticiones redundantes
            $cacheKey = "precio_rebaja_" . md5($this->descripcion);
            if (Cache::has($cacheKey)) {
                $cachedValue = Cache::get($cacheKey);
                if ($cachedValue !== 'not_found') {
                    $this->upsertPrecioBd((float) $cachedValue);
                    return;
                }
                // Si está en caché como 'not_found', igual salimos sin golpear la API
                Log::debug("Caché hit (not_found) para: [{$this->descripcion}]");
                return;
            }

            // 2. Limpiar y expandir el nombre antes de enviarlo a la API
            $queryBusqueda = $this->prepararQueryBusqueda($this->descripcion);
            Log::debug("Query API: [{$queryBusqueda}] (original: [{$this->descripcion}])");

            // URL del endpoint Intelligent Search (no bloqueado por WAF como catalog_system)
            $searchUrl = "https://www.larebajavirtual.com/api/intelligent-search/product_search/trade-policy/1"
                . "?query=" . urlencode($queryBusqueda)
                . "&count=10&locale=es-CO";

            // 3. Rotación de User-Agent + headers completos de navegador real
            $ua = $this->userAgents[array_rand($this->userAgents)];

            $response = Http::withHeaders([
                'User-Agent'         => $ua,
                'Accept'             => 'application/json, text/plain, */*',
                'Accept-Language'    => 'es-CO,es;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding'    => 'gzip, deflate, br',
                'Referer'            => 'https://www.larebajavirtual.com/farmacia?map=productClusterIds',
                'Origin'             => 'https://www.larebajavirtual.com',
                'Connection'         => 'keep-alive',
                'sec-ch-ua'          => '"Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"',
                'sec-ch-ua-mobile'   => '?0',
                'sec-ch-ua-platform' => '"Windows"',
                'Sec-Fetch-Dest'     => 'empty',
                'Sec-Fetch-Mode'     => 'cors',
                'Sec-Fetch-Site'     => 'same-origin',
                'DNT'                => '1',
            ])->timeout(25)->get($searchUrl);

            $status = $response->status();

            // 4. Manejo diferenciado de errores HTTP
            if ($status === 429) {
                // Rate limit: relanzar para que el sistema de reintentos (backoff) lo reintente
                Log::warning("RATE LIMIT (429) para [{$this->descripcion}]. Se reintentará con backoff.");
                throw new \Exception("Rate limit HTTP 429 – reintento programado");
            }

            if ($status === 403) {
                // Forbidden: IP bloqueada temporalmente, no tiene sentido reintentar ahora
                Log::warning("ACCESO DENEGADO (403) para [{$this->descripcion}]. Sin reintento.");
                $this->upsertPrecioBd(null);
                return; // No lanzamos excepción → no consume reintentos
            }

            if (!$response->successful()) {
                Log::warning("Error HTTP {$status} para [{$this->descripcion}] | URL: {$searchUrl}");
                $this->upsertPrecioBd(null);
                return;
            }

            $body = $response->json();

            // 5. Intelligent Search devuelve { products: [...], recordsFiltered: N, ... }
            $productosApi = $body['products'] ?? [];

            if (empty($productosApi)) {
                Log::notice("Sin resultados API para: [{$this->descripcion}]");
                $this->upsertPrecioBd(null);
                Cache::put($cacheKey, 'not_found', now()->addHours(2));
                return;
            }

            $mejoresResultados = [];

            // 6. Recorrer resultados y calcular score de similitud
            foreach ($productosApi as $p) {
                $nombreWeb = $p['productName'] ?? '';
                $items     = $p['items'] ?? [];

                if (empty($nombreWeb) || empty($items)) continue;

                Log::debug("Candidato en API: [{$nombreWeb}]");

                $seller = $items[0]['sellers'][0] ?? null;
                $precio = null;

                if ($seller && isset($seller['commertialOffer']['Price'])) {
                    $precio = (float) $seller['commertialOffer']['Price'];
                    $stock  = (int) ($seller['commertialOffer']['AvailableQuantity'] ?? 0);

                    if ($precio <= 0) {
                        $precio = null;
                    } elseif ($stock <= 0) {
                        // Guardar el precio igual aunque no haya stock, según petición del usuario
                        Log::notice("Producto agotado en web, pero guardando precio: [{$nombreWeb}] (Precio: {$precio})");
                    }
                }

                if ($precio !== null) {
                    $score = $this->matchProductoScore($this->descripcion, $nombreWeb);
                    Log::debug("Score {$score}% | BD:[{$this->descripcion}] vs WEB:[{$nombreWeb}]");

                    if ($score >= 70) {
                        $mejoresResultados[] = [
                            'score'  => $score,
                            'nombre' => $nombreWeb,
                            'precio' => $precio,
                        ];
                    }
                }
            }

            // 7. Elegir el mejor match y guardar
            if (!empty($mejoresResultados)) {
                usort($mejoresResultados, fn($a, $b) => $b['score'] <=> $a['score']);
                $ganador = $mejoresResultados[0];

                $this->upsertPrecioBd($ganador['precio']);
                Cache::put($cacheKey, $ganador['precio'], now()->addDay());

                Log::info("✔ MATCH: BD [{$this->descripcion}] → WEB [{$ganador['nombre']}] | Precio: $" . number_format($ganador['precio'], 0) . " | Score: {$ganador['score']}%");
            } else {
                Log::notice("Sin coincidencia ≥70% para: [{$this->descripcion}]");
                $this->upsertPrecioBd(null);
                Cache::put($cacheKey, 'not_found', now()->addHours(2));
            }

        } catch (\Exception $e) {
            $msg = $e->getMessage();

            // Si es rate limit, relanzamos para que el Job se reintente automáticamente
            if (str_contains($msg, '429')) {
                throw $e;
            }

            // Cualquier otro error: loguear y continuar sin detener el scraping global
            Log::error("Error en Job VTEX [{$this->descripcion}]: {$msg}");
            $this->upsertPrecioBd(null);
        }
    }

    /**
     * SISTEMA DE COMPARACIÓN INTELIGENTE (SCORING)
     * Retorna un puntaje de 0 a 100 basado en coincidencias semánticas.
     */
    private function matchProductoScore(string $productoBD, string $productoWeb): int
    {
        // 1. Extraer Atributos CRÍTICOS (Antes de que la limpieza los destruya)
        // Ejemplo: '60 sob', '15 ml', '20 tabletas'
        $safeBD = mb_strtolower($productoBD, 'UTF-8');
        $safeWeb = mb_strtolower($productoWeb, 'UTF-8');
        $attrBD = $this->extraerAtributos($safeBD);
        $attrWeb = $this->extraerAtributos($safeWeb);

        // 2. Limpieza y Normalización
        $normBD = $this->limpiarIrrelevantes($this->aplicarEquivalencias($this->normalizarTexto($productoBD)));
        $normWeb = $this->limpiarIrrelevantes($this->aplicarEquivalencias($this->normalizarTexto($productoWeb)));

        // --- SISTEMA DE PUNTUACIÓN (Base: similar_text) ---
        similar_text($normBD, $normWeb, $perc);
        $score = (int) $perc;

        // Penalty Fatal: Diferencia en concentración
        if (!empty($attrBD['concentracion']) && !empty($attrWeb['concentracion'])) {
            if ($attrBD['concentracion'] !== $attrWeb['concentracion']) {
                return 0; 
            } else {
                $score += 20;
            }
        }

        // Penalty Fatal: Diferencia en CANTIDAD (Ej. Caja 60 sobres vs 1 sobre)
        if (!empty($attrBD['cantidad']) && !empty($attrWeb['cantidad'])) {
            if ($attrBD['cantidad'] !== $attrWeb['cantidad']) {
                return 0; // Rechazado por tamaño de caja distinto
            } else {
                $score += 15;
            }
        }

        // Penalty Fatal: Tallas diferentes
        if (!empty($attrBD['talla']) && !empty($attrWeb['talla'])) {
            if ($attrBD['talla'] !== $attrWeb['talla']) {
                return 0;
            } else {
                $score += 15;
            }
        }

        // Bono de Marca Conservador
        if (!empty($attrBD['marca'])) {
            if (strpos($normWeb, $attrBD['marca']) !== false) {
                $score += 10;
            }
        }

        // Bono de Primer término (activo)
        $activo = explode(' ', trim($normBD))[0] ?? '';
        if (!empty($activo) && mb_strlen($activo) > 2) {
            if ($activo !== ($attrBD['marca'] ?? '') && strpos($normWeb, $activo) !== false) {
                $score += 10;
            }
        }

        // Penalidad por Palabras Clave Faltantes (Evita mezclar Vainilla con Chocolate)
        $bdWords = array_filter(explode(' ', trim($normBD)), fn($w) => mb_strlen($w) > 3);
        $webWords = array_filter(explode(' ', trim($normWeb)), fn($w) => mb_strlen($w) > 3);
        $faltanEnWeb = array_diff($bdWords, $webWords);
        
        // Cada palabra de la BD > 3 letras ausente en la web resta 12 puntos
        $score -= (count($faltanEnWeb) * 12);

        $finalScore = max(0, min($score, 100));
        Log::debug("Score {$finalScore}% | BD:[{$productoBD}] vs WEB:[{$productoWeb}]");

        return $finalScore;
    }
    /**
     * Prepara el texto del producto para enviarlo como query a la API.
     * Expande abreviaciones, elimina caracteres especiales y términos de relleno.
     * Se usa SOLO para la búsqueda; el scoring sigue usando el nombre original.
     */
    private function prepararQueryBusqueda(string $nombre): string
    {
        $texto = mb_strtolower(trim($nombre), 'UTF-8');

        // 1. Tildes y caracteres especiales
        $texto = str_replace(
            ['á','é','í','ó','ú','ü','ñ'],
            ['a','e','i','o','u','u','n'],
            $texto
        );

        // 2. Eliminar laboratorios en paréntesis (ruido directo)
        $texto = preg_replace('/\((inactivo|p|ag|mk|ecar|genfar|coaspharma|drogam)\)/i', '', $texto);

        // 3. Separadores no alfanuméricos → espacio
        // Esto cambia "PAN.TENA" a "PAN TENA" e impide reemplazos locos como "PAnumeroTENA"
        $texto = preg_replace('/[\\/\\-+.,;:()\'\"\\[\\]{}|@#$%^&*_=!?]/u', ' ', $texto);

        // 4. Eliminar patrones de referencia/embalaje/promoción
        $texto = preg_replace(
            '/\b(r\s*\d+|\d+\s*r|embal|x\s*\d+|nula|cortesia|cono|promo|bd|pague\s*\d+\s*lleve\s*\d+|pag\d+llev\d+|lata|caja|blister|display|rollo|bolsa|doy|sachets?|pouch|ins\s*\d+\s*[gG]?\s*x\s*\d+\s*[mM]?[mM]?)\b/i',
            '',
            $texto
        );

        // 5. Diccionario palabra a palabra
        $d = [
            // ── FORMAS FARMACÉUTICAS (eliminar: no ayudan a buscar) ──
            'tab'        => '', 'tabs'      => '', 'tableta'    => '', 'tabletas'   => '',
            'cap'        => '', 'caps'      => '', 'capsula'    => '', 'capsulas'   => '',
            'gragea'     => '', 'grageas'   => '', 'pastilla'   => '', 'pastillas'  => '',
            'comp'       => 'compacto', 'comprimido'=> '', 'comprimidos'=> '',
            'sobre'      => '', 'sobres'    => '',
            
            // Expandir a término completo O ELIMINAR si diluyen la búsqueda
            // VTEX Intelligent Search falla si hay muchas palabras genéricas (como solucion, inyectable)
            'susp'       => '',             'suspension' => '',
            'sol'        => '',             'solucion' => '',
            'iny'        => '',             'inyec'  => '',     'inyectable' => '',
            'got'        => '',             'gts'    => '',     'gotas' => '',
            'jbe'        => '',             'jrb'    => '',     'jarabe' => '',
            'crm'        => 'crema',        'cre'    => 'crema',
            'ung'        => 'unguento',     'pom'    => 'pomada',
            'lot'        => 'locion',       'loc'    => 'locion',
            'aer'        => 'aerosol',      'spr'    => 'spray',
            'champ'      => 'shampoo',      'chp'    => 'shampoo',    'shp'   => 'shampoo', 'cha' => 'shampoo',
            'cond'       => 'acondicionador', 'acond' => 'acondicionador',
            'emul'       => 'emulsion',
            'amp'        => '',             'amps'   => '', 'ampolla' => '', 'ampollas' => '',
            'vag'        => 'vaginal',      'rect'   => 'rectal',
            'oftal'      => 'oftalmico',    'of'     => 'oftalmica',
            'nas'        => 'nasal',        'otic'   => 'otico',     'oi'     => 'oido',
            'top'        => 'topico',       'derm'   => 'dermico',
            'ora'        => 'oral',         'pluma'  => '',
            
            // ── UNIDADES DE MEDIDA (eliminar) ────────────────────────
            'gr'  => '', 'grs' => '', 'ml'  => '', 'mls' => '',
            'mg'  => '', 'mcg' => '', 'ug'  => '', 'ui'  => '', 'iu' => '',
            'oz'  => '', 'fl'  => '', 'lt'  => '', 'lts' => '',
            'kg'  => '', 'kgs' => '', 'cc'  => '', 'yds' => 'yardas',
            // Cantidades/conteo
            'und' => '', 'un'  => '', 'uds' => '', 'unid'=> '', 'unidades' => '', 'unds' => '',

            // ── INSUMOS MÉDICOS ───────────────────────────────────────
            'jer'        => 'jeringa',      'jeringa'=> 'jeringa',
            'ag'         => 'aguja',        'aguja'  => 'aguja',
            'cat'        => 'cateter',
            'gua'        => 'guante',       'guan'   => 'guante',
            'vend'       => 'venda',
            'gasa'       => 'gasa',
            'term'       => 'termometro',   'ter'    => 'termometro',
            'oxim'       => 'oximetro',
            'gluc'       => 'glucometro',
            'neb'        => 'nebulizador',
            'ins'        => 'insulina',
            'dren'       => 'drenaje',
            'alg'        => 'algodon',
            'recol'      => 'recolector',   'orina'  => 'orina',
            'silic'      => 'silicona',

            // ── HIGIENE Y CUIDADO PERSONAL ────────────────────────────
            'toa'        => 'toallas',
            'hum'        => 'humedas',
            'prot'       => 'protector',
            'diar'       => 'diarios',
            'noc'        => 'noche',
            'mul'        => 'multiple',
            'pan'        => 'panal',        'pañ'    => 'panal',
            'masc'       => 'mascarilla',
            'des'        => 'desodorante',  'deo'    => 'desodorante',
            'blo'        => 'bloqueador',   'fps'    => '',
            'hid'        => 'hidratante',   'hidra'  => 'hidratante', 'hidrol' => 'hidrolizado',
            'jab'        => 'jabon',        'jbr'    => 'jabon',
            'enjuag'     => 'enjuague',
            'csp'        => 'crema pelo',
            'ant'        => 'anticonceptivo',
            'buff'       => 'esponja',
            'perf'       => 'perfume',
            'col'        => 'colonia',
            'repel'      => 'repelente',
            'desmaq'     => 'desmaquillante',
            'est'        => 'estuche',

            // ── MAQUILLAJE / DERMOCOSM. ───────────────────────────────
            'maq'        => 'maquillaje',
            'ld'         => 'larga duracion',
            'pol'        => 'polvo',
            'bron'       => 'bronceador',
            'esmalt'     => 'esmalte',
            'delin'      => 'delineador',
            'somb'       => 'sombra',
            'cor'        => 'corrector',
            'labial'     => 'labial',

            // ── ALIMENTACIÓN / NUTRICIÓN / OTROS ──────────────────────────────
            'vit'        => 'vitamina',     'vita'   => 'vitamina',  'vitamin' => 'vitamina',
            'zing'       => 'zinc',
            'supl'       => 'suplemento',   'collagen' => 'colageno',
            'inst'       => 'instantaneo',  'pediat' => 'pediatrico',
            'vain'       => 'vainilla',     'cocacola' => 'coca cola',
            'choc'       => 'chocolate',
            'fres'       => 'fresa',
            'ban'        => 'banano',
            'adv'        => 'avanzado',     
            'fco'        => 'frasco',       'inh' => 'inhalador',
            'liq'        => 'liquido',      'cardonatada' => 'carbonatada',
            'fantast'    => '',             'fantastic' => '',
            'fs'         => 'filtro solar', 'no' => 'numero',
            'urocondon'  => 'recolector de orina',
            'condon'     => 'preservativo',

            // ── LABORATORIOS Y MARCAS GENÉRICAS COMO RUIDO ─────────────────
            'mk' => '', 'gf' => '', 'icom' => '', 'drofarma' => '', 'caribe' => '',
            'fam' => '', 'ei' => '', 'pc' => '', 'rc' => '', 'hp' => '', 'vt' => '',
            'mpromed' => 'promedical',

            // ── TÉRMINOS DE ENVASE (eliminar) ─────────────────────────
            'lata'       => '', 'botella' => '', 'estuche'  => '',
            'sachet'     => '', 'tira'    => '', 'rollo'    => '',
            'pet'        => '',   
            'embal'      => '', 'display'  => '',
            'extract'    => 'extracto', 'ac' => 'acido',
            'pvp'        => '', 'dto' => '', 'iva' => '',
            'lote'       => '', 'ven' => '', 'venc' => '', 'reg' => '', 'san' => '',

            // ── TALLAS ────────────────────────────────────────────────
            'peq'        => 'pequeno',  'pequena'  => 'pequeno',
            'med'        => 'mediano',  'medium'   => 'mediano',
            'gde'        => 'grande',
            // ── GÉNERO ────────────────────────────────────────────────
            'women'      => 'mujer',    'woman'    => 'mujer',
            'men'        => 'hombre',   'man'      => 'hombre',
            'inf'        => 'infantil', 'infan'    => 'infantil',
            'adu'        => 'adulto',   'nino'     => 'nino',
            // ── ACCIÓN FARMACOLÓGICA (eliminar → ruido) ───────────────
            'lp'         => '', 'lr'   => '', 'sr'   => '',
            'er'         => '', 'ds'   => '',
            // ── GENÉRICOS DE RELLENO (eliminar) ──────────────────────
            'clasico'    => '', 'classic'   => '',
            'original'   => '', 'tradicional'=> '',
            'plus'       => '', 'max'       => '',
            'ultra'      => '', 'forte'     => '',
            'extra'      => '', 'super'     => '',
            'nuevo'      => '', 'new'       => '',
            'old'        => '', 'pro'       => '',
            'total'      => '', 'complete'  => '', 'completo' => '',
            'advanced'   => '', 'advance'   => '',
            'premium'    => '', 'gold'      => '',
            'sensual'    => '', 'fresh'     => '',
            'sport'      => '', 'active'    => '', 'activo' => '',
            'special'    => '', 'especial'  => '',
            'ultraforte' => '', 'pus'       => 'plus',
            // Referencias sueltas → eliminar
            'r'          => '',
        ];

        $palabras = explode(' ', $texto);
        
        $salida = [];
        foreach ($palabras as $p) {
            $pTrim = trim($p);
            if ($pTrim === '') continue;

            $palabraFinal = array_key_exists($pTrim, $d) ? $d[$pTrim] : $pTrim;
            
            // Si el diccionario lo elimina, saltar
            if ($palabraFinal === '') continue;

            // Filtramos las partes del diccionario (podrian ser compuestas si expandimos algo a "filtro solar")
            $subWords = explode(' ', $palabraFinal);
            
            foreach ($subWords as $sw) {
                // Mantener si tiene longitud > 1 O ES NUMERO O ES VITAMINA
                if (mb_strlen($sw) > 1 || is_numeric($sw) || in_array($sw, ['a','b','c','d','e','k','v'])) {
                    $salida[] = $sw;
                }
            }
        }

        return trim(implode(' ', $salida));
    }

    /**
     * DICCIONARIO DE EQUIVALENCIAS SEMÁNTICAS (usado para el scoring de similitud)
     */
    private function aplicarEquivalencias(string $texto): string
    {
        $texto = preg_replace('/[\\/\\-+.,;:()\'\"\\[\\]{}|@#$%^&*_=!?]/u', ' ', strtolower($texto));

        $diccionario = [
            // Tallas
            'women'  => 'mujer',   'woman'   => 'mujer',
            'men'    => 'hombre',  'man'     => 'hombre',
            'med'    => 'm',       'medium'  => 'm',
            'gde'    => 'l',       'grande'  => 'l',
            'peq'    => 's',       'pequeno' => 's',
            'xg'     => 'xl',      'xxg'     => 'xxl',
            'extra grande'=> 'xl', 'extragrande'=> 'xl',
            // Formas farmacéuticas (se eliminan para no afectar scoring)
            'sob'       => '', 'sobres'   => '', 'sobre'    => '',
            'tab'       => '', 'tabletas' => '', 'tableta'  => '',
            'cap'       => '', 'capsulas' => '', 'capsula'  => '',
            'grageas'   => '', 'ampolla'  => '', 'jarabe'   => '',
            'suspension'=> '', 'solucion' => '', 'gotas'    => '', 'got' => '', 'of' => '',
            // Unidades
            'gr' => '', 'grs' => '', 'ml' => '', 'mg' => '',
            'und' => '', 'unidades' => '', 'oz' => '', 'ui' => '', 'iu' => '',
            // Relleno
            'locion'  => '', 'clasico' => '', 'classic' => '',
            'max'     => '', 'original'=> '', 'plus'    => '',
            'extra'   => '', 'nuevo'   => '',
            // BD específicos / Abreviaturas
            'pan'     => 'panal',
            'inst'    => 'instantaneo',
            'champ'   => 'shampoo',     'cha' => 'shampoo',     'shampoo' => 'shampoo',
            'comp'    => 'compacto',
            'jab'     => 'jabon',
            'est'     => 'estuche',
            'vit'     => 'vitamina',
            'condon'  => 'preservativo',
            'urocondon' => 'recolector orina',
            'desmaq'  => 'desmaquillante',
            'recol'   => 'recolector',
            'acond'   => 'acondicionador',
            'bot'     => 'botella',
            'nr'      => 'no retornable',
            'dte'     => 'desodorante',
            'deop'    => 'deopies',
            'prot'    => 'protector',
        ];

        $palabras = explode(' ', $texto);
        foreach ($palabras as &$p) {
            $pTrim = trim($p);
            if (array_key_exists($pTrim, $diccionario)) {
                $p = $diccionario[$pTrim];
            } else {
                $p = $pTrim;
            }
        }
        return trim(implode(' ', array_filter($palabras, fn($v) => $v !== '')));
    }

    /**
     * Extrae marcas y tallas comunes.
     */
    private function extraerAtributos(string $texto): array
    {
        $attr = ['marca' => '', 'concentracion' => '', 'cantidad' => '', 'talla' => ''];

        // Concentración (incluyendo %, mg, ml, etc)
        if (preg_match('/(\d+(?:\.\d+)?)\s*(mg|g|ml|mcg|ui|%)/i', $texto, $m)) {
            $attr['concentracion'] = $m[1] . $m[2];
        }

        // Cantidad de unidades (Ej: 'x 60', '60 tabs', '1 sobre', 'pack x 3')
        if (preg_match('/(?:x\s*)?(\d+)\s*(und|unid|unidades|tab|tabletas|cap|capsula|capsulas|sob|sobres|paq)\b/i', $texto, $m)) {
            $attr['cantidad'] = (int) $m[1];
        } elseif (preg_match('/\b[x\-\*]\s*(\d+)\b/i', $texto, $m)) {
            $attr['cantidad'] = (int) $m[1];
        }

        // Marcas Familiares
        $marcas = [
            // Farmacéuticas
            'genfar','mk','bayer','pfizer','sanofi','novartis','lafrancol','tecnoquimicas',
            'tecnofar','coaspharma','ropsohn','procaps','chalver','laprofff',
            // Analgésicos / antigripales
            'advil','dolex','dolanex','buscapina','espasmo','flanax','naproxeno','noraver',
            // Vitaminas / suplementos
            'centrum','supradyn','berocca','ensure','pediasure','sustagen',
            // Cuidado personal
            'nivea','eucerin','neutrogena','dove','rexona','axe','gillette',
            'pantene','head','sedal','elvive','tresemme','koleston','nescafe',
            // Bebidas / alimentos
            'colcafe','nescafe','milo','quaker','doria','zenú','festival',
            // Pañales / higiene íntima
            'huggies','winny','pequeñin','tena','nosotras','stayfree','carefree',
            // Varios
            'mottas','iris','nopikex','fred','bocatto','vogue','salome','old parr',
        ];
        foreach ($marcas as $m) {
            if (strpos($texto, $m) !== false) {
                $attr['marca'] = $m;
                break;
            }
        }

        // Tallas
        if (preg_match('/\b(s|m|l|xl|xxl)\b/i', $texto, $m)) {
            $attr['talla'] = $m[1];
        }

        return $attr;
    }

    /**
     * Limpieza final del texto
     */
    private function limpiarIrrelevantes(string $texto): string
    {
        // Limpiamos contenido genérico en parentesis que no ayuda
        $texto = preg_replace('/\([^)]+\)/', '', $texto);
        
        // Eliminamos unidades y palabras de relleno que suelen ser variables
        $eliminar = ['/(\d+(?:\.\d+)?)\s*(mg|g|ml|mcg|ui|sobres|capsulas|tabletas|und|unidades|gr|grs|%|vol)/i', '/\b(x\s*\d+|pack)\b/i', '/[^a-z0-9\s]/'];
        $texto = preg_replace($eliminar, ' ', $texto);
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Normaliza a minúsculas y quita tildes
     */
    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = str_replace(
            ['á','é','í','ó','ú','ñ','/','-','(',')','.',','],
            ['a','e','i','o','u','n',' ',' ',' ',' ',' ',' '],
            $texto
        );
        return trim(preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Inserta o actualiza el registro en la BD usando lógica Upsert
     */
    private function upsertPrecioBd(?float $precio)
    {
        $table = DB::connection('sqlsrv')->table('copiweb_sin_lote.dbo.shopping');
        $existe = $table->where('id_referencia', $this->codigo)->exists();

        if ($existe) {
            $table->where('id_referencia', $this->codigo)->update([
                'nombre_producto' => $this->descripcion,
                'precio_rebaja'   => $precio,
            ]);
        } else {
            $table->insert([
                'id_referencia'   => $this->codigo,
                'nombre_producto' => $this->descripcion,
                'precio_rebaja'   => $precio,
            ]);
        }
    }
}
