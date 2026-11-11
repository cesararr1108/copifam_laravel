<div x-data="shoppingModule()" x-init="setup()" class="space-y-5">

    {{-- ── Título ─────────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-800">0702 — Shopping de Precios</h2>
        <p class="text-xs text-gray-400 mt-0.5">Comparativa de precios entre Copifam y farmacias externas (Matching por EAN)</p>
    </div>

    {{-- ── Tabla de resultados ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">

        {{-- Cabecera con contador + búsqueda --}}
        <div class="px-5 py-3 border-b border-gray-50 flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[250px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text" id="sh-search-input"
                       placeholder="Buscar por código, EAN o nombre de producto..."
                       class="w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all outline-none">
            </div>

            {{-- Botón Buscar --}}
            <button type="button"
                    @click="buscar()"
                    :disabled="cargandoDt"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg text-white transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    style="background-color:#00AEEF">
                <svg x-show="!cargandoDt" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <svg x-show="cargandoDt" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="cargandoDt ? 'Buscando...' : 'Buscar'"></span>
            </button>

            <span class="text-xs text-gray-400 whitespace-nowrap"
                  x-text="totalRegistros + ' producto(s)'"></span>
        </div>

        {{-- Tabla DataTables --}}
        <div id="sh-tabla-container" class="overflow-x-auto">
            <table id="tbl-shopping" class="w-full text-xs" style="width:100%">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>EAN</th>
                        <th>Producto</th>
                        <th>Copifam</th>
                        <th>La Rebaja</th>
                        <th>Pasteur</th>
                        <th>Alemana</th>
                        <th>Colsubsidio</th>
                        <th>Mejor P.</th>
                        <th>Promedio</th>
                        <th>Vs Mejor</th>
                        <th>Vs Prom.</th>
                        <th>Margen %</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Spinner de carga (Estilo idéntico a Usuarios/Asistencia) --}}
    <div x-show="cargandoDt" x-cloak class="sh-modal-backdrop">
        <div class="sh-loading-box">
            <div class="sh-loading-header">
                <svg class="sh-loading-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                </svg>
                <span class="sh-loading-title">Cargando Shopping</span>
            </div>
            <div class="sh-loading-body">
                <p class="sh-loading-sub">Obteniendo precios actualizados de las droguerías externas...</p>
            </div>
        </div>
    </div>

</div>
