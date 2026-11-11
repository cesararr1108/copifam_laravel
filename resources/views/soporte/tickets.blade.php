<div x-data="ticketsModule({
         nombreUsuario: '{{ addslashes(trim(Session::get('ses_CopNombres','') . ' ' . Session::get('ses_CopApellidos',''))) }}',
         pdvArea:       '{{ addslashes(Session::get('ses_CopDpto') ?? \Illuminate\Support\Facades\DB::table('T_DPTO_SYS')->where('ID', Session::get('ses_CopDtoId'))->value('DESCRIPCION') ?? '') }}',
         bodegaNombre:  '{{ addslashes((\Illuminate\Support\Facades\DB::selectOne('SELECT B2.DESCRIPCION FROM T_BODEGAS B INNER JOIN COPIFAM_SIN_LOTE.DBO.BODEGAS B2 ON B2.BODEGA = B.BODEGA WHERE B.ID = ?', [Session::get('ses_CopBodegaId')]))?->DESCRIPCION ?? '') }}',
         telefonoCelular: '{{ addslashes(\Illuminate\Support\Facades\DB::table('T_USUARIOS')->where('ID', Session::get('ses_CopId'))->value('TELEFONOS') ?? '') }}',
         loginUsuario:  '{{ addslashes(Session::get('ses_CopLogin', '')) }}',
         esSistemas:    {{ \Illuminate\Support\Str::contains(strtolower(\Illuminate\Support\Facades\DB::table('T_ROLES')->where('ID', Session::get('ses_CopRol', 0))->value('TITULO') ?? ''), 'sistema') ? 'true' : 'false' }},
         esAdmin:       {{ \Illuminate\Support\Str::contains(strtolower(\Illuminate\Support\Facades\DB::table('T_ROLES')->where('ID', Session::get('ses_CopRol', 0))->value('TITULO') ?? ''), 'administrador') ? 'true' : 'false' }}
     })" x-init="init()" class="space-y-5">

    {{-- ── Título ──────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-800">Formulario de Soporte</h2>
        <p class="text-xs text-gray-400 mt-0.5">Gestione y consulte los tickets de soporte</p>
    </div>

    {{-- ── Tabs de navegación ──────────────────────────────────────── --}}
    <div class="flex items-center gap-1 border-b border-gray-200">
        <button @click="tabActivo = 'tickets'"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all duration-150"
                :class="tabActivo === 'tickets'
                    ? 'border-pink-500 text-pink-600'
                    : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300'">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>
            </svg>
            Tickets
            <span class="ml-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                  :class="tabActivo === 'tickets'"
                  ></span>
        </button>

        {{-- Tab Seguimiento: solo visible para admin y sistemas --}}
        <template x-if="esSistemas || esAdmin">
            <button @click="tabActivo = 'seguimiento'; $nextTick(() => { if(_dtSeguimiento) _dtSeguimiento.columns.adjust(); })"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all duration-150"
                    :class="tabActivo === 'seguimiento'
                        ? 'border-pink-500 text-pink-600'
                        : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300'">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
                Seguimiento
            </button>
        </template>

        {{-- Tab Análisis: solo visible para admin y sistemas --}}
        <template x-if="esSistemas || esAdmin">
            <button @click="tabActivo = 'analisis'; cargarAnalisis()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all duration-150"
                    :class="tabActivo === 'analisis'
                        ? 'border-pink-500 text-pink-600'
                        : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300'">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z"/>
                </svg>
                Análisis
            </button>
        </template>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         TAB: TICKETS (lista principal)
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="tabActivo === 'tickets'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

    {{-- ── Barra superior ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 space-y-3">

        {{-- Fila 1: buscador + botón buscar + botón limpiar + nuevo ticket --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text" x-model="filtroTexto"
                       @keydown.enter="buscarTickets()"
                       placeholder="Buscar por #, solicitante, asunto o encargado..."
                       class="tkt-search w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 transition-all">
            </div>

            <button @click="buscarTickets()" :disabled="cargando"
                    class="tkt-btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg disabled:opacity-60 disabled:cursor-not-allowed">
                <svg x-show="!cargando" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <svg x-show="cargando" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="cargando ? 'Buscando...' : 'Buscar'"></span>
            </button>

            <button x-show="filtroTexto || filtroEstado !== 'todos'"
                    @click="filtroTexto = ''; filtroEstado = 'todos'; buscarTickets()"
                    class="inline-flex items-center gap-1 px-2.5 py-2 text-[11px] text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50 border border-gray-200 hover:border-red-100 transition-all">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
                Limpiar
            </button>

            <span class="text-[11px] text-gray-400" x-text="tktTotal + ' ticket(s)'"></span>

            <button @click="abrirModalCrear()"
                    class="tkt-btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg ml-auto">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Nuevo ticket
            </button>
        </div>

        {{-- Fila 2: chips de estado --}}
        <div class="flex items-center gap-1 flex-wrap">
            <span class="text-[10px] text-gray-400 mr-1 font-medium">Estado:</span>
            <template x-for="opt in [
                {key:'todos',        label:'Todo'},
                {key:'pendiente',    label:'Pendiente'},
                {key:'en proceso',   label:'En Proceso'},
                {key:'en revisión',  label:'En Revisión'},
                {key:'devuelto',     label:'Devuelto'},
                {key:'solucionado',  label:'Solucionado'},
                {key:'no apto',      label:'No Apto'},
            ]" :key="opt.key">
                <button @click="setFiltroEstado(opt.key)"
                        class="px-3 py-1 text-xs rounded-full border transition-all"
                        :class="filtroEstado === opt.key
                            ? 'tkt-filter-active font-semibold'
                            : 'border-gray-200 text-gray-500 hover:border-pink-200 hover:text-pink-600'"
                        x-text="opt.label">
                </button>
            </template>
        </div>

    </div>

    {{-- ── Tabla de tickets (DataTables server-side) ──────────────── --}}

    {{-- Spinner de carga --}}
    <div x-show="tktCargandoDt" x-cloak class="tkt-modal-backdrop" style="z-index:9999;">
        <div class="tkt-loading-box"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="tkt-loading-header">
                <svg class="tkt-loading-icon" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span class="tkt-loading-title">Cargando tickets...</span>
            </div>
            <div class="tkt-loading-body">
                <p class="tkt-loading-sub">Por favor espera un momento</p>
            </div>
        </div>
    </div>

    <div class="tkt-tickets-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tbl-tickets" class="w-full text-xs" style="width:100%">
                <thead>
                    <tr>
                        <th class="tkt-th">Identificación</th>
                        <th class="tkt-th">Solicitado por</th>
                        <th class="tkt-th">Asunto</th>
                        <th class="tkt-th">Prioridad</th>
                        <th class="tkt-th">Encargado</th>
                        <th class="tkt-th">Fecha creación</th>
                        <th class="tkt-th">Estado</th>
                        <th class="tkt-th"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    </div>{{-- /tab tickets --}}

    {{-- ═══════════════════════════════════════════════════════════════
         TAB: SEGUIMIENTO — solo admin y sistemas
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="(esSistemas || esAdmin) && tabActivo === 'seguimiento'"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="space-y-4">

        {{-- Barra de búsqueda y filtros del seguimiento --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 space-y-3">

            {{-- Fila 1: buscador · fechas · PDV --}}
            <div class="flex flex-wrap items-center gap-3">

                {{-- Buscador de texto --}}
                <div class="relative" style="min-width:300px; max-width:330px; flex:1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    <input type="text" x-model="filtroSeguimiento"
                           @keydown.enter="buscarSeguimiento()"
                           placeholder="Buscar por #, encargado, solicitante..."
                           class="tkt-search w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 transition-all">
                </div>

                {{-- Rango de fechas --}}
                <div class="flex items-center gap-2" style="flex:2; min-width:420px">
                    <span class="text-xs text-gray-500 font-medium whitespace-nowrap">Desde:</span>
                    <div class="relative flex-1">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5"/>
                        </svg>
                        <input type="date" x-model="filtroSegDesde"
                               class="tkt-search w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2.5">
                    </div>
                    <span class="text-gray-300 text-sm">→</span>
                    <span class="text-xs text-gray-500 font-medium whitespace-nowrap">Hasta:</span>
                    <div class="relative flex-1">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5"/>
                        </svg>
                        <input type="date" x-model="filtroSegHasta"
                               class="tkt-search w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2.5">
                    </div>
                </div>

                {{-- Selector Punto de venta --}}
                <div class="relative" style="flex:1; min-width:180px" @click.outside="modalPdv = false">
                    <button @click="modalPdv = !modalPdv; busquedaPdv = ''"
                            class="w-full inline-flex items-center gap-2 px-4 py-2.5 text-xs rounded-lg border transition-all"
                            :class="filtroSegPdv
                                ? 'border-pink-400 text-pink-600 bg-pink-50 font-semibold'
                                : 'border-gray-200 text-gray-500 hover:border-pink-200 hover:text-pink-600'">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"/>
                        </svg>
                        <span class="flex-1 text-left truncate" x-text="filtroSegPdv || 'Punto de venta'"></span>
                        <span x-show="filtroSegPdv"
                              @click.stop="filtroSegPdv = ''"
                              class="w-3.5 h-3.5 rounded-full bg-pink-200 text-pink-700 flex items-center justify-center hover:bg-pink-300 transition-colors flex-shrink-0">
                            <svg class="w-2 h-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </span>
                        <svg class="w-3 h-3 text-gray-400 transition-transform duration-150 flex-shrink-0"
                             :class="modalPdv ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Dropdown PDV — lista viene del servidor (catalogs.puntosVenta), filtro visual con x-show --}}
                    <div x-show="modalPdv"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute left-0 top-full mt-1.5 z-50 w-72 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                        <div class="px-3 pt-3 pb-2 border-b border-gray-100">
                            <div class="relative">
                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-400"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <input type="text" x-model="busquedaPdv"
                                       placeholder="Buscar punto de venta..."
                                       class="w-full text-xs border border-gray-200 rounded-lg pl-7 pr-3 py-1.5 focus:outline-none focus:border-pink-400"
                                       @click.stop>
                            </div>
                        </div>
                        <button @click="filtroSegPdv = ''; modalPdv = false"
                                class="w-full flex items-center gap-2 px-4 py-2.5 text-xs transition-colors hover:bg-gray-50 text-left border-b border-gray-50"
                                :class="!filtroSegPdv ? 'font-semibold text-pink-600 bg-pink-50' : 'text-gray-600'">
                            Todos los puntos de venta
                        </button>
                        <div class="overflow-y-auto max-h-48">
                            <template x-for="pdv in catalogs.puntosVenta" :key="pdv.nombre">
                                <button x-show="!busquedaPdv || pdv.nombre.toLowerCase().includes(busquedaPdv.toLowerCase())"
                                        @click="filtroSegPdv = pdv.nombre; modalPdv = false"
                                        class="w-full flex items-center justify-between gap-2 px-4 py-2 text-xs transition-colors hover:bg-pink-50 text-left"
                                        :class="filtroSegPdv === pdv.nombre ? 'font-semibold text-pink-600 bg-pink-50' : 'text-gray-600'">
                                    <span x-text="pdv.nombre"></span>
                                    <svg x-show="filtroSegPdv === pdv.nombre" class="w-3.5 h-3.5 text-pink-500 flex-shrink-0"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </button>
                            </template>
                            <div x-show="catalogs.puntosVenta.filter(p => !busquedaPdv || p.nombre.toLowerCase().includes(busquedaPdv.toLowerCase())).length === 0"
                                 class="px-4 py-5 text-center text-xs text-gray-400">Sin resultados.</div>
                        </div>
                        <div class="px-4 py-2 border-t border-gray-100 text-[10px] text-gray-400"
                             x-text="catalogs.puntosVenta.length + ' puntos disponibles'"></div>
                    </div>
                </div>

            </div>

            {{-- Fila 2: chips de estado · Buscar · Limpiar · contador --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] text-gray-400 font-medium whitespace-nowrap">Estado:</span>

                <template x-for="opt in [
                    {key:'todos',       label:'Todo'},
                    {key:'pendiente',   label:'Pendiente'},
                    {key:'en proceso',  label:'En Proceso'},
                    {key:'en revisión', label:'En Revisión'},
                    {key:'devuelto',    label:'Devuelto'},
                    {key:'solucionado', label:'Solucionado'},
                    {key:'no apto',     label:'No Apto'},
                ]" :key="opt.key">
                    <button @click="filtroSegEstado = opt.key"
                            class="px-3 py-1 text-xs rounded-full border transition-all"
                            :class="filtroSegEstado === opt.key
                                ? 'tkt-filter-active font-semibold'
                                : 'border-gray-200 text-gray-500 hover:border-pink-200 hover:text-pink-600'"
                            x-text="opt.label">
                    </button>
                </template>

                {{-- Separador visual --}}
                <span class="w-px h-5 bg-gray-200 mx-1"></span>

                {{-- Botón Buscar --}}
                <button @click="buscarSeguimiento()"
                        class="tkt-btn-primary inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    Buscar
                </button>

                {{-- Limpiar --}}
                <button x-show="filtroSeguimiento || filtroSegDesde || filtroSegHasta || filtroSegEstado !== 'todos' || filtroSegPdv"
                        @click="filtroSeguimiento=''; filtroSegDesde=''; filtroSegHasta=''; filtroSegEstado='todos'; filtroSegPdv=''; buscarSeguimiento()"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50 border border-gray-200 hover:border-red-100 transition-all">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar
                </button>

                <span class="text-[11px] text-gray-400 ml-auto" x-text="segTotal + ' registro(s)'"></span>
            </div>

        </div>

        {{-- Spinner de carga (se muestra mientras DT hace el request) --}}
        <div x-show="segCargandoDt" x-cloak class="tkt-modal-backdrop" style="z-index:9999;">
            <div class="tkt-loading-box"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="tkt-loading-header">
                    <svg class="tkt-loading-icon" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.3)" stroke-width="3"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span class="tkt-loading-title">Cargando tickets...</span>
                </div>
                <div class="tkt-loading-body">
                    <p class="tkt-loading-sub">Por favor espera un momento</p>
                </div>
            </div>
        </div>

        {{-- Tabla de historial (DataTables server-side) --}}
        <div class="tkt-seg-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table id="tbl-seguimiento" class="w-full text-xs" style="width:100%">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="tkt-th">#</th>
                            <th class="tkt-th">Punto de venta</th>
                            <th class="tkt-th">Quién envió</th>
                            <th class="tkt-th">Encargado</th>
                            <th class="tkt-th">Fecha de inicio</th>
                            <th class="tkt-th">Estado</th>
                            <th class="tkt-th">Tiempo de respuesta</th>
                            <th class="tkt-th"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>{{-- /tab seguimiento --}}

    {{-- ═══════════════════════════════════════════════════════════════
         TAB: ANÁLISIS — solo admin y sistemas
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="(esSistemas || esAdmin) && tabActivo === 'analisis'"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Contenido --}}
        <div x-show="analisisCargando" class="flex items-center justify-center py-20">
            <div class="w-8 h-8 rounded-full border-2 border-pink-100 border-t-pink-500 animate-spin"></div>
        </div>

        <div x-show="!analisisCargando">

            {{-- Selector de mes + KPIs + botón Excel ───────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 mb-4
                        flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-gray-500">Mes:</label>
                    <select x-model="analisisMes"
                            @change="analisisAnio = analisisMes.split('-')[0]; analisisNumMes = analisisMes.split('-')[1]; cargarAnalisis()"
                            class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-pink-400">
                        <template x-for="op in analisisMeses" :key="op.value">
                            <option :value="op.value" x-text="op.label"></option>
                        </template>
                    </select>
                    {{-- Botón Excel --}}
                    <button @click="descargarExcel()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Exportar Excel
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <div class="text-xl font-bold" style="color:#ff1498" x-text="analisisDatos.total_mes ?? '—'"></div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Tickets totales</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-emerald-500"
                             x-text="analisisDatos.tiempo_promedio != null
                                ? (analisisDatos.tiempo_promedio < 60
                                    ? analisisDatos.tiempo_promedio + ' min'
                                    : Math.floor(analisisDatos.tiempo_promedio/60) + 'h ' + (analisisDatos.tiempo_promedio%60) + 'min')
                                : '—'">
                        </div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Tiempo prom. respuesta</div>
                    </div>
                </div>
            </div>

            {{-- Fila 1: Dona (estados) + Recuadro encargados ──────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                {{-- Dona: tickets por estado --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Tickets por estado
                    </p>
                    <div class="flex items-center justify-center" style="height:260px">
                        <canvas id="chart-estado"></canvas>
                    </div>
                </div>

                {{-- Recuadro: tickets resueltos por encargado --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Tickets resueltos por encargado
                    </p>
                    <template x-if="!analisisDatos.por_encargado || analisisDatos.por_encargado.length === 0">
                        <div class="flex flex-col items-center justify-center h-40 text-gray-300">
                            <svg class="w-8 h-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                            </svg>
                            <p class="text-xs">Sin tickets resueltos este mes</p>
                        </div>
                    </template>
                    <template x-if="analisisDatos.por_encargado && analisisDatos.por_encargado.length > 0">
                        <div class="space-y-2 overflow-y-auto" style="max-height:240px">
                            <template x-for="(enc, idx) in analisisDatos.por_encargado" :key="enc.encargado">
                                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 transition-colors">
                                    {{-- Posición --}}
                                    <span class="w-5 text-center text-[10px] font-bold text-gray-400" x-text="idx + 1"></span>
                                    {{-- Avatar inicial --}}
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                         :style="`background-color:${['#ff1498','#00AEEF','#a855f7','#f59e0b','#10b981'][idx % 5]}`"
                                         x-text="enc.encargado.charAt(0).toUpperCase()"></div>
                                    {{-- Nombre --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-700 truncate" x-text="enc.encargado"></p>
                                        <p class="text-[10px] text-gray-400"
                                           x-text="enc.tiempo_promedio_min != null
                                            ? 'Prom: ' + (enc.tiempo_promedio_min < 60
                                                ? Math.round(enc.tiempo_promedio_min) + ' min'
                                                : Math.floor(enc.tiempo_promedio_min/60) + 'h ' + (Math.round(enc.tiempo_promedio_min)%60) + 'min')
                                            : ''">
                                        </p>
                                    </div>
                                    {{-- Cantidad --}}
                                    <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 rounded-full text-xs font-bold text-white"
                                          style="background-color:#ff1498"
                                          x-text="enc.resueltos"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Fila 2: Línea (por mes) ────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                    Resueltos vs no resueltos — últimos 12 meses
                </p>
                <div style="height:240px">
                    <canvas id="chart-linea"></canvas>
                </div>
            </div>

            {{-- Fila 3: Barras (PDV) + Barras (categoría) ─────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Barras: PDV --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        PDV que más tickets genera
                    </p>
                    <div style="height:280px">
                        <canvas id="chart-pdv"></canvas>
                    </div>
                </div>

                {{-- Barras: categoría --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">
                        Tickets por categoría
                    </p>
                    <div style="height:280px">
                        <canvas id="chart-categoria"></canvas>
                    </div>
                </div>
            </div>

        </div>{{-- /!analisisCargando --}}

    </div>{{-- /tab analisis --}}

    {{-- ── Modal CREAR ticket ──────────────────────────────────────── --}}
    <div x-show="modalCrear"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="tkt-modal-backdrop"
         @keydown.window.escape.prevent="$event.stopPropagation()">

        <div x-show="modalCrear"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="tkt-modal-box">

            {{-- Cabecera --}}
            <div class="flex items-center justify-between px-6 py-4" style="background-color:#00AEEF">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-white">Nuevo ticket de soporte</p>
                        <p class="text-[11px]" style="color:#ffffff; font-medium">Complete los campos para registrar su solicitud</p>
                    </div>
                </div>
                <button @click="cerrarModalCrear()"
                        class="p-1.5 rounded-lg text-white hover:bg-white/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Formulario --}}
            <form @submit.prevent="guardarTicket()" class="p-6 space-y-5">

                {{-- Sección: Datos del solicitante (solo lectura) --}}
                <div>
                    <p class="tkt-section-title">Datos del solicitante</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Nombre solicitante</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                                <span class="text-xs text-gray-500" x-text="nuevoTicket.nombre_solicitante || 'Cargando...'"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">PDV / Área</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                                </svg>
                                <span class="text-xs text-gray-500" x-text="nuevoTicket.pdv_area || 'Cargando...'"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Bodega</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 21V10.75m0 0L12 4.5l9.75 6.25"/>
                                </svg>
                                <span class="text-xs text-gray-500" x-text="nuevoTicket.bodega_nombre || 'Sin bodega'"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Número de contacto</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                                </svg>
                                <span class="text-xs text-gray-500 font-mono" x-text="nuevoTicket.numero_contacto || 'Sin celular registrado'"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sección: Información del ticket --}}
                <div>
                    <p class="tkt-section-title">Información del ticket</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- Prioridad --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">
                                Prioridad <span class="text-red-400">*</span>
                            </label>
                            <select x-model="nuevoTicket.prioridad"
                                    class="tkt-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                                <option value="">Seleccione...</option>
                                <option value="Alto">Alto</option>
                                <option value="Medio">Medio</option>
                                <option value="Bajo">Bajo</option>
                            </select>
                        </div>

                        {{-- Categoría --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">
                                Categoría <span class="text-red-400">*</span>
                            </label>
                            <select x-model="nuevoTicket.categoria_id"
                                    @change="nuevoTicket.subcategoria_id = ''"
                                    class="tkt-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                                <option value="">Seleccione categoría...</option>
                                <template x-for="c in catalogs.categorias" :key="c.id">
                                    <option :value="c.id" x-text="c.nombre"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Subcategoría (dependiente) --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">
                                Subcategoría <span class="text-red-400">*</span>
                            </label>
                            <select x-model="nuevoTicket.subcategoria_id"
                                    :disabled="!nuevoTicket.categoria_id"
                                    :class="!nuevoTicket.categoria_id ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white'"
                                    class="tkt-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                                <option value="">
                                    <span x-text="nuevoTicket.categoria_id ? 'Seleccione subcategoría...' : 'Primero seleccione una categoría'"></span>
                                </option>
                                <template x-for="s in subcategoriasFiltradas" :key="s.id">
                                    <option :value="s.id" x-text="s.nombre"></option>
                                </template>
                            </select>
                            <p class="text-[10px] text-gray-400 mt-1" x-show="!nuevoTicket.categoria_id">
                                Seleccione primero una categoría para ver las subcategorías disponibles.
                            </p>
                        </div>

                    </div>
                </div>

                {{-- Título del ticket --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Título del ticket <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="nuevoTicket.titulo_ticket"
                           placeholder="Resuma brevemente el problema o solicitud" maxlength="150"
                           class="tkt-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Descripción detallada --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Descripción detallada <span class="text-red-400">*</span>
                    </label>
                    <textarea x-model="nuevoTicket.descripcion_detallada"
                              rows="4"
                              placeholder="Describa el problema con el mayor detalle posible: pasos para reproducirlo, mensajes de error, etc."
                              class="tkt-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none transition-all"></textarea>
                </div>

                {{-- Archivo adjunto (pegar con Ctrl+V) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Archivo adjunto</label>
                    <div @paste="capturarPegado($event)"
                         tabindex="0"
                         class="tkt-paste-zone relative border-2 border-dashed rounded-xl p-5 text-center cursor-default transition-all focus:outline-none"
                         :class="nuevoTicket.archivoNombre
                             ? 'border-pink-300 bg-pink-50'
                             : 'border-gray-200 bg-gray-50'">

                        {{-- Estado: sin archivo --}}
                        <div x-show="!nuevoTicket.archivoNombre" class="space-y-1.5">
                            <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                            </svg>
                            <p class="text-xs font-medium text-gray-500">Haga clic o pegue una imagen con <kbd class="px-1.5 py-0.5 text-[10px] bg-white border border-gray-200 rounded font-mono shadow-sm">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 text-[10px] bg-white border border-gray-200 rounded font-mono shadow-sm">V</kbd></p>
                            <p class="text-[10px] text-gray-400">Imágenes PNG, JPG, GIF — máx. 5 MB</p>
                        </div>

                        {{-- Estado: con archivo --}}
                        <div x-show="nuevoTicket.archivoNombre" class="flex items-center justify-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color:#ff149820">
                                <svg class="w-5 h-5" style="color:#ff1498" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                            </div>
                            <div class="text-left">
                                <p class="text-xs font-medium text-gray-700" x-text="nuevoTicket.archivoNombre"></p>
                                <p class="text-[10px] text-gray-400" x-text="nuevoTicket.archivoTamano"></p>
                            </div>
                            <button type="button"
                                    @click.stop="nuevoTicket.archivoNombre = ''; nuevoTicket.archivoTamano = ''; nuevoTicket.archivo = null"
                                    class="ml-auto p-1 rounded-md text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <input type="file" x-ref="fileInput"
                               @change="capturarArchivo($event)"
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                               class="hidden">
                    </div>

                    {{-- Aviso pegado --}}
                    <div x-show="mostrarAvisoPegado"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="mt-2 flex items-center gap-2 px-3 py-2 bg-green-50 border border-green-200 rounded-lg">
                        <svg class="w-3.5 h-3.5 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-[11px] text-green-700 font-medium">¡Imagen pegada correctamente!</span>
                    </div>
                </div>

                {{-- Botones --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                    <button type="button" @click="cerrarModalCrear()"
                            class="tkt-btn-secondary px-5 py-2 text-xs font-medium rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="guardandoTicket"
                            class="tkt-btn-primary inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold rounded-lg">
                        <svg x-show="guardandoTicket" class="tkt-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="guardandoTicket ? 'Enviando...' : 'Enviar ticket'"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>

    {{-- ── Modal detalle del ticket ─────────────────────────────────── --}}
    <div x-show="ticketActivo !== null"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="tkt-modal-backdrop"
         @keydown.window.escape.prevent="$event.stopPropagation()">

        <div x-show="ticketActivo !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="tkt-modal-box">

            {{-- Cabecera modal --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"
                 style="background-color:#00AEEF ">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-white"
                           x-text="'Ticket #' + (ticketActivo?.id_ticket ?? '')"></p>
                        <p class="text-[11px]" style="color:#ffffff; font-medium"
                           x-text="ticketActivo?.titulo_ticket ?? ''"></p>
                    </div>
                </div>
                <button @click="cerrarModal()"
                        class="p-1.5 rounded-lg text-white hover:bg-white/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Cuerpo modal --}}
            <div class="p-6 space-y-6">

                {{-- Badges rápidos --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold"
                          :class="claseEstado(ticketActivo?.estado)"
                          x-text="ticketActivo?.estado ?? '—'"></span>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold"
                          :class="clasePrioridad(ticketActivo?.prioridad)"
                          x-text="'Prioridad: ' + (ticketActivo?.prioridad ?? '—')"></span>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600"
                          x-text="'Bodega: ' + (ticketActivo?.bodega_nombre ?? '—')"></span>
                </div>

                {{-- Sección: Información general --}}
                <div>
                    <p class="tkt-section-title">Información general</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                        <div>
                            <p class="tkt-detail-label">Solicitante</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.nombre_solicitante ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Contacto</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.numero_contacto ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Bodega</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.bodega_nombre ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Usuario sistema</p>
                            <p class="tkt-detail-value font-mono" x-text="ticketActivo?.usuario ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Categoría</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.nombre_categoria ?? ticketActivo?.categoria_id ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Subcategoría</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.nombre_subcategoria ?? ticketActivo?.subcategoria_id ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Fecha creación</p>
                            <p class="tkt-detail-value" x-text="formatFecha(ticketActivo?.fecha_creacion)"></p>
                        </div>
                    </div>
                </div>

                {{-- Sección: Descripción --}}
                <div>
                    <p class="tkt-section-title">Descripción detallada</p>
                    <p class="tkt-detail-value bg-gray-50 rounded-lg p-3 leading-relaxed"
                       x-text="ticketActivo?.descripcion_detallada ?? 'Sin descripción.'"></p>
                </div>

                {{-- Sección: Gestión --}}
                <div>
                    <p class="tkt-section-title">Gestión del ticket</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                        <div>
                            <p class="tkt-detail-label">Persona encargada</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.persona_encargada ?? '—'"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Fecha inicio</p>
                            <p class="tkt-detail-value" x-text="formatFecha(ticketActivo?.fecha_inicio)"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Fecha estimada</p>
                            <p class="tkt-detail-value" x-text="formatFecha(ticketActivo?.fecha_estimada)"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Fecha solución</p>
                            <p class="tkt-detail-value" x-text="formatFecha(ticketActivo?.fecha_solucion)"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Tiempo atención</p>
                            <p class="tkt-detail-value" x-text="formatTiempo(ticketActivo?.tiempo_atencion)"></p>
                        </div>
                        <div>
                            <p class="tkt-detail-label">Validación usuario</p>
                            <p class="tkt-detail-value" x-text="ticketActivo?.validacion_usuario ?? '—'"></p>
                        </div>
                    </div>
                </div>

                {{-- Sección: Notas --}}
                <div>
                    <p class="tkt-section-title">Notas</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Nota del encargado (al aceptar/finalizar/rechazar) --}}
                        <div>
                            <p class="tkt-detail-label">Nota del encargado</p>
                            <div x-show="ticketActivo?.nota_encargado"
                                 class="tkt-detail-value bg-blue-50 border border-blue-100 rounded-lg p-3 space-y-1.5">
                                <div x-show="ticketActivo?.persona_encargado"
                                     class="flex items-center gap-1.5 text-[11px] font-semibold text-blue-700">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    <span x-text="ticketActivo?.persona_encargada"></span>
                                </div>
                                <p class="leading-relaxed text-gray-700" x-text="ticketActivo?.nota_encargado"></p>
                            </div>
                            <p x-show="!ticketActivo?.nota_encargado"
                               class="tkt-detail-value bg-blue-50 rounded-lg p-3 text-gray-400">Sin nota.</p>
                        </div>
                        {{-- Nota del usuario (cuando devuelve el ticket) --}}
                        <div>
                            <p class="tkt-detail-label">Nota del usuario</p>
                            <div x-show="ticketActivo?.nota_rechazo_usuario"
                                 class="tkt-detail-value bg-orange-50 border border-orange-100 rounded-lg p-3 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-orange-600">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    <span x-text="ticketActivo?.nombre_solicitante"></span>
                                </div>
                                <p class="leading-relaxed text-gray-700" x-text="ticketActivo?.nota_rechazo_usuario"></p>
                            </div>
                            <p x-show="!ticketActivo?.nota_rechazo_usuario"
                               class="tkt-detail-value bg-orange-50 rounded-lg p-3 text-gray-400">Sin nota.</p>
                        </div>
                    </div>
                </div>

                {{-- Archivo adjunto --}}
                <div x-show="ticketActivo?.archivo_adjunto">
                    <p class="tkt-section-title">Archivo adjunto</p>
                    <a :href="'{{ rtrim(config('app.url'), '/') }}/' + ticketActivo?.archivo_adjunto"
                       target="_blank"
                       class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-lg border border-pink-200 text-pink-600 hover:bg-pink-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                        </svg>
                        Ver archivo adjunto
                    </a>
                </div>

                {{-- ── Panel de acciones (solo Sistemas) ─────────────────── --}}
                <div x-show="esSistemas" class="border-t border-gray-100 pt-4">

                    {{-- Aceptar ticket (solo si está Pendiente) --}}
                    <div x-show="(ticketActivo?.estado ?? '').toLowerCase() === 'pendiente'">
                        <p class="tkt-section-title flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-9.75 0h9.75"/>
                            </svg>
                            Gestionar ticket
                        </p>
                        <div x-show="!mostrarFormAceptar && !mostrarFormRechazar" class="flex gap-2 flex-wrap">
                            <button @click="mostrarFormAceptar = true"
                                    class="tkt-btn-aceptar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Aceptar ticket
                            </button>
                            <button @click="mostrarFormRechazar = true"
                                    class="tkt-btn-rechazar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Rechazar ticket
                            </button>
                        </div>

                        {{-- Formulario Aceptar --}}
                        <div x-show="mostrarFormAceptar"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-3 mt-2">
                            <p class="text-xs font-bold text-green-800 flex items-center gap-2">
                                <span class="inline-flex w-5 h-5 rounded-full bg-green-600 items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </span>
                                Aceptar ticket — completar datos
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-1">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Encargado</label>
                                    <div class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg">
                                        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                        </svg>
                                        <span class="text-xs text-gray-600" x-text="_nombreUsuario"></span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 mt-1">Asignado automáticamente a su sesión.</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha estimada de solución <span class="text-red-400">*</span></label>
                                    <input type="date" x-model="accionDatos.fecha_estimada"
                                           :min="new Date().toISOString().split('T')[0]"
                                           class="tkt-input w-full text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white">
                                </div>
                            </div>
                            <div class="flex gap-2 justify-end pt-1">
                                <button type="button" @click="mostrarFormAceptar = false"
                                        class="tkt-btn-secondary px-4 py-2 text-xs font-medium rounded-lg">Cancelar</button>
                                <button type="button" @click="aceptarTicket()"
                                        :disabled="procesandoAccion"
                                        class="tkt-btn-aceptar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                    <svg x-show="procesandoAccion" class="tkt-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!procesandoAccion" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="procesandoAccion ? 'Procesando...' : 'Confirmar aceptación'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- Formulario Rechazar --}}
                        <div x-show="mostrarFormRechazar"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="bg-red-50 border border-red-200 rounded-xl p-4 space-y-3 mt-2">
                            <p class="text-xs font-bold text-red-800 flex items-center gap-2">
                                <span class="inline-flex w-5 h-5 rounded-full bg-red-500 items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </span>
                                Rechazar ticket — indique el motivo
                            </p>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Motivo de rechazo <span class="text-red-400">*</span></label>
                                <textarea x-model="accionDatos.nota_encargado" rows="3"
                                          placeholder="Explique el motivo por el cual rechaza este ticket..."
                                          class="tkt-input w-full text-xs border border-gray-200 rounded-lg px-3 py-2 resize-none bg-white"></textarea>
                            </div>
                            <div class="flex gap-2 justify-end pt-1">
                                <button type="button" @click="mostrarFormRechazar = false"
                                        class="tkt-btn-secondary px-4 py-2 text-xs font-medium rounded-lg">Cancelar</button>
                                <button type="button" @click="rechazarTicket()"
                                        :disabled="procesandoAccion"
                                        class="tkt-btn-rechazar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                    <svg x-show="procesandoAccion" class="tkt-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!procesandoAccion" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span x-text="procesandoAccion ? 'Procesando...' : 'Confirmar rechazo'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Finalizar ticket (En proceso o Devuelto) --}}
                    <div x-show="['en proceso','devuelto'].includes((ticketActivo?.estado ?? '').toLowerCase())">
                        <p class="tkt-section-title flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m-9.75 0h9.75"/>
                            </svg>
                            Gestionar ticket
                        </p>
                        <div x-show="!mostrarFormFinalizar">
                            <button @click="mostrarFormFinalizar = true"
                                    class="tkt-btn-finalizar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                                Finalizar ticket
                            </button>
                        </div>

                        {{-- Formulario Finalizar --}}
                        <div x-show="mostrarFormFinalizar"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="border border-blue-200 rounded-xl p-4 space-y-3 mt-2"
                             style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
                            <p class="text-xs font-bold text-blue-800 flex items-center gap-2">
                                <span class="inline-flex w-5 h-5 rounded-full bg-blue-500 items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </span>
                                <span x-text="(ticketActivo?.estado ?? '').toLowerCase() === 'devuelto'
                                    ? 'Re-finalizar ticket devuelto — nota de resolución'
                                    : 'Finalizar ticket — agregar nota de resolución'"></span>
                            </p>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nota del encargado <span class="text-red-400">*</span></label>
                                <textarea x-model="accionDatos.nota_encargado" rows="4"
                                          placeholder="Describa cómo fue resuelta la solicitud, pasos realizados, etc."
                                          class="tkt-input w-full text-xs border border-gray-200 rounded-lg px-3 py-2 resize-none bg-white"></textarea>
                            </div>
                            <div class="flex gap-2 justify-end pt-1">
                                <button type="button" @click="mostrarFormFinalizar = false"
                                        class="tkt-btn-secondary px-4 py-2 text-xs font-medium rounded-lg">Cancelar</button>
                                <button type="button" @click="finalizarTicket()"
                                        :disabled="procesandoAccion"
                                        class="tkt-btn-finalizar inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                    <svg x-show="procesandoAccion" class="tkt-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!procesandoAccion" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    <span x-text="procesandoAccion ? 'Procesando...' : 'Marcar como solucionado'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Panel de validación (solicitante o admin, estado "En revisión") ─── --}}
                <div x-show="(ticketActivo?.estado ?? '').toLowerCase() === 'en revisión'
                             && !esSistemas
                             && (ticketActivo?.usuario === _loginUsuario || esAdmin)"
                     class="border-t border-gray-100 pt-4">
                    <p class="tkt-section-title flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Confirmar resolución
                    </p>

                    {{-- Botones principales --}}
                    <div x-show="!mostrarFormValidar" class="flex gap-2 flex-wrap">
                        <button @click="validarTicket(true)"
                                :disabled="procesandoAccion"
                                class="tkt-btn-resolver inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="procesandoAccion ? 'Procesando...' : 'Sí, quedó solucionado'"></span>
                        </button>
                        <button @click="mostrarFormValidar = true"
                                class="tkt-btn-devolver inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                            </svg>
                            No, devolver al encargado
                        </button>
                    </div>

                    {{-- Formulario Devolver --}}
                    <div x-show="mostrarFormValidar"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="bg-orange-50 border border-orange-200 rounded-xl p-4 space-y-3 mt-2">
                        <p class="text-xs font-bold text-orange-800 flex items-center gap-2">
                            <span class="inline-flex w-5 h-5 rounded-full bg-orange-500 items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                </svg>
                            </span>
                            Devolver ticket — indique el motivo
                        </p>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Motivo de devolución <span class="text-red-400">*</span></label>
                            <textarea x-model="accionDatos.nota_rechazo_usuario" rows="3"
                                      placeholder="Explique por qué el problema no fue solucionado correctamente..."
                                      class="tkt-input w-full text-xs border border-gray-200 rounded-lg px-3 py-2 resize-none bg-white"></textarea>
                        </div>
                        <div class="flex gap-2 justify-end pt-1">
                            <button type="button" @click="mostrarFormValidar = false"
                                    class="tkt-btn-secondary px-4 py-2 text-xs font-medium rounded-lg">Cancelar</button>
                            <button type="button" @click="validarTicket(false)"
                                    :disabled="procesandoAccion"
                                    class="tkt-btn-devolver inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg">
                                <svg x-show="procesandoAccion" class="tkt-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <svg x-show="!procesandoAccion" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                </svg>
                                <span x-text="procesandoAccion ? 'Procesando...' : 'Confirmar devolución'"></span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer modal --}}
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                <button @click="cerrarModal()"
                        class="tkt-btn-secondary px-5 py-2 text-xs font-medium rounded-lg">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

</div>
