{{-- ══════════════════════════════════════════════════════════════════════
MÓDULO: Control de Asistencia — Gestión Humana
Código: 1101
Alpine component: asistenciaModule() → public/js/gestion_humana/asistencia.js
══════════════════════════════════════════════════════════════════════ --}}
<div x-data="asistenciaModule()" x-init="init()" class="space-y-5">

    {{-- ── Título ──────────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-800">Control de Asistencia</h2>
        <p class="text-xs text-gray-400 mt-0.5">Registro de entradas y salidas del personal — Gestión Humana</p>
    </div>

    {{-- ── Tabs ────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-1 border-b border-gray-200">
        <button @click="tabActivo = 'registro'"
            class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all duration-150"
            :class="tabActivo === 'registro'
                    ? 'border-pink-500 text-pink-600'
                    : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300'">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Registro de Asistencia
        </button>
        @if(in_array((int) Session::get('ses_CopRol'), [1, 9]))
            <button @click="tabActivo = 'historial'; cargarHistorial()"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-all duration-150"
                :class="tabActivo === 'historial'
                        ? 'border-pink-500 text-pink-600'
                        : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300'">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                </svg>
                Historial
            </button>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
    TAB: REGISTRO DE ASISTENCIA
    ════════════════════════════════════════════════════════════════════ --}}
    <div x-show="tabActivo === 'registro'" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

        {{-- ── Recuadro único: selector + botones + tabla ──────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

            {{-- ── Sección 1: Selector de persona ──────────────────────────── --}}
            <div class="px-5 py-4 border-b border-gray-100">
                <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-widest">
                    Seleccione su nombre
                </label>
                <select x-model.number="usuarioSeleccionado" class="asis-select w-full text-sm border border-gray-200 rounded-lg px-4 py-2.5 text-gray-700
                               focus:outline-none focus:ring-2 focus:ring-pink-400 transition-all">
                    <option value="">— Seleccione su nombre —</option>
                    <template x-for="u in usuarios" :key="u.id ? String(u.id) : Math.random()">
                        <option :value="u.id" x-text="u.nombre_completo"></option>
                    </template>
                </select>
            </div>

            {{-- ── Sección 2: Botones de marcación ─────────────────────────── --}}
            <div class="px-5 py-5 border-b border-gray-100 bg-gray-50/40">

                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tipo de marcación</p>
                    <span x-show="!usuarioSeleccionado" class="text-xs text-gray-400 italic">Seleccione un empleado
                        arriba</span>
                </div>

                <div class="flex flex-wrap justify-center gap-6">

                    {{-- Primer Turno --}}
                    <div class="flex flex-col items-center gap-3">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest
                                     px-3 py-1 bg-white rounded-full border border-gray-200 shadow-sm">Primer
                            Turno</span>
                        <div class="flex gap-3">
                            <button @click="marcar('entrada_t1')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                @mouseup="$el.classList.remove('asis-btn--pressed')"
                                @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                class="asis-btn asis-btn-cyan">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                </svg>
                                Entrada T 1
                            </button>
                            <button @click="marcar('salida_t1')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                @mouseup="$el.classList.remove('asis-btn--pressed')"
                                @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                class="asis-btn asis-btn-cyan">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                </svg>
                                Salida T 1
                            </button>
                        </div>
                    </div>

                    @if((int) Session::get('ses_CopRol') !== 5)
                        <div class="w-px bg-gray-200 self-stretch hidden sm:block"></div>

                        {{-- Segundo Turno --}}
                        <div class="flex flex-col items-center gap-3">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest
                                         px-3 py-1 bg-white rounded-full border border-gray-200 shadow-sm">Segundo
                                Turno</span>
                            <div class="flex gap-3">
                                <button @click="marcar('entrada_t2')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                    @mouseup="$el.classList.remove('asis-btn--pressed')"
                                    @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                    class="asis-btn asis-btn-cyan">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    Entrada T 2
                                </button>
                                <button @click="marcar('salida_t2')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                    @mouseup="$el.classList.remove('asis-btn--pressed')"
                                    @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                    class="asis-btn asis-btn-cyan">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    </svg>
                                    Salida T 2
                                </button>
                            </div>
                        </div>
                    @endif

                    @if((int) Session::get('ses_CopRol') !== 5)
                        <div class="w-px bg-gray-200 self-stretch hidden sm:block"></div>

                        {{-- F4 --}}
                        <div class="flex flex-col items-center gap-3">
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest
                                         px-3 py-1 bg-white rounded-full border border-gray-200 shadow-sm">F4</span>
                            <div class="flex gap-3">
                                <button @click="abrirF4('salida_f4')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                    @mouseup="$el.classList.remove('asis-btn--pressed')"
                                    @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                    class="asis-btn asis-btn-cyan">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    </svg>
                                    Salida F4
                                </button>
                                <button @click="abrirF4('entrada_f4')" @mousedown="$el.classList.add('asis-btn--pressed')"
                                    @mouseup="$el.classList.remove('asis-btn--pressed')"
                                    @mouseleave="$el.classList.remove('asis-btn--pressed')" :disabled="marcando"
                                    class="asis-btn asis-btn-cyan">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    Entrada F4
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- ── Sección 3: Cabecera de tabla ─────────────────────────────── --}}
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-700">Registros de Entradas y Salidas para hoy</p>
                    <p class="text-[10px] text-gray-400 mt-0.5" x-text="fechaHoyLabel"></p>
                </div>
                <button @click="cargarTabla()" :disabled="tablaCargando" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg
                               border border-gray-200 text-gray-500 hover:border-pink-300 hover:text-pink-600 transition-all
                               disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="tablaCargando ? 'animate-spin' : ''" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Actualizar
                </button>
            </div>

            {{-- Spinner --}}
            <div x-show="tablaCargando" class="flex items-center justify-center py-10">
                <div class="w-6 h-6 rounded-full border-2 border-pink-100 border-t-pink-500 animate-spin"></div>
            </div>

            {{-- ── Sección 4: Tabla ──────────────────────────────────────────── --}}
            <div x-show="!tablaCargando" class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="asis-th w-8">#</th>
                            <th class="asis-th">Nombres y Apellidos</th>
                            <th class="asis-th">H. Entrada Turno 1</th>
                            <th class="asis-th">H. Salida Turno 1</th>
                            <th class="asis-th">H. Entrada Turno 2</th>
                            <th class="asis-th">H. Salida Turno 2</th>
                            <th class="asis-th text-center">F4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="registros.length === 0">
                            <tr>
                                <td colspan="7" class="text-center py-12 text-gray-300">
                                    <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                                    </svg>
                                    <p class="text-xs">Sin registros para hoy</p>
                                </td>
                            </tr>
                        </template>
                        <template x-for="(row, idx) in registros" :key="row.id ? String(row.id) : idx">
                            <tr class="asis-tr">
                                <td class="asis-td text-center text-gray-400 font-medium" x-text="idx + 1"></td>
                                <td class="asis-td font-semibold text-gray-800" x-text="row.nombre_completo"></td>
                                <td class="asis-td">
                                    <span x-show="row.entrada_t1" class="asis-badge asis-badge-cyan"
                                        x-text="row.entrada_t1"></span>
                                </td>
                                <td class="asis-td">
                                    <span x-show="row.salida_t1" class="asis-badge asis-badge-pink"
                                        x-text="row.salida_t1"></span>
                                </td>
                                <td class="asis-td">
                                    <span x-show="row.entrada_t2" class="asis-badge asis-badge-cyan"
                                        x-text="row.entrada_t2"></span>
                                </td>
                                <td class="asis-td">
                                    <span x-show="row.salida_t2" x-text="row.salida_t2"></span>
                                </td>
                                <td class="asis-td text-center">
                                    <button x-show="row.cant_f4 > 0"
                                        @click="verF4(row.usuarioAsistencia_id, row.nombre_completo)"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold
                                                   bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 transition-colors">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span x-text="row.cant_f4"></span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Footer total --}}
            <div x-show="registros.length > 0 && !tablaCargando"
                class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/40">
                <span class="text-[11px] text-gray-400"
                    x-text="registros.length + ' persona(s) registrada(s) hoy'"></span>
            </div>

        </div>{{-- /recuadro único --}}

    </div>{{-- /tab registro --}}

    {{-- ════════════════════════════════════════════════════════════════════
    TAB: HISTORIAL
    ════════════════════════════════════════════════════════════════════ --}}
    @if(in_array((int) Session::get('ses_CopRol'), [1, 9]))
        <div x-show="tabActivo === 'historial'" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="space-y-4">


            {{-- Filtros historial --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">

                    {{-- Rango de fechas --}}
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500 font-medium whitespace-nowrap">Desde:</label>
                        <input type="date" x-model="histDesde"
                            class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-pink-400">
                        <span class="text-gray-300">→</span>
                        <label class="text-xs text-gray-500 font-medium whitespace-nowrap">Hasta:</label>
                        <input type="date" x-model="histHasta"
                            class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>

                    {{-- Buscar por nombre --}}
                    <div class="relative flex-1 min-w-[200px]">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input type="text" x-model="histNombre" @keydown.enter="cargarHistorial()"
                            placeholder="Buscar por nombre..."
                            class="w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>

                    <button @click="cargarHistorial()" :disabled="histCargando"
                        class="asis-btn-primary inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg disabled:opacity-60">
                        <svg class="w-3.5 h-3.5" :class="histCargando ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        Buscar
                    </button>

                    <button x-show="histDesde || histHasta || histNombre"
                        @click="histDesde=''; histHasta=''; histNombre=''; cargarHistorial()" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] text-gray-400 hover:text-red-500
                                   rounded-lg hover:bg-red-50 border border-gray-200 hover:border-red-100 transition-all">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Limpiar
                    </button>
                </div>
            </div>

            {{-- Sub-tabs: Asistencias / F4 --}}
            <div class="flex gap-2">
                <button @click="cambiarTabHistorial('asistencias')"
                    class="px-3 py-1.5 text-xs rounded-full border font-semibold transition-all" :class="histSubTab === 'asistencias'
                            ? 'border-pink-400 text-pink-600 bg-pink-50'
                            : 'border-gray-200 text-gray-500 hover:border-pink-200 hover:text-pink-500'">
                    Asistencias
                </button>
                <button @click="cambiarTabHistorial('f4')"
                    class="px-3 py-1.5 text-xs rounded-full border font-semibold transition-all" :class="histSubTab === 'f4'
                            ? 'border-amber-400 text-amber-600 bg-amber-50'
                            : 'border-gray-200 text-gray-500 hover:border-amber-200 hover:text-amber-500'">
                    Registros F4
                </button>
            </div>

            {{-- Spinner historial --}}
            <div x-show="histCargando" x-cloak class="asis-modal-backdrop" style="z-index:9999;">
                <div class="asis-loading-box" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="asis-loading-header">
                        <svg class="asis-loading-icon" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span class="asis-loading-title">Cargando asistencias...</span>
                    </div>
                    <div class="asis-loading-body">
                        <p class="asis-loading-sub">Por favor espera un momento</p>
                    </div>
                </div>
            </div>

            {{-- Tabla asistencias con DataTables --}}
            <div x-show="!histCargando && histSubTab === 'asistencias'"
                class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden dt-table-wrapper">
                <div class="overflow-x-auto">
                    <table id="histAsistenciasTable" class="w-full text-xs dt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombres y Apellidos</th>
                                <th>Fecha</th>
                                <th>Entrada T1</th>
                                <th>Salida T1</th>
                                <th>Entrada T2</th>
                                <th>Salida T2</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- Tabla F4 con DataTables --}}
            <div x-show="!histCargando && histSubTab === 'f4'"
                class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden dt-table-wrapper">
                <div class="overflow-x-auto">
                    <table id="histF4Table" class="w-full text-xs dt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Nota</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>{{-- /tab historial --}}
    @endif


    {{-- ── Modal F4 (nota) ─────────────────────────────────────────────── --}}
    <div x-show="modalF4.open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="asis-modal-backdrop"
        @keydown.window.escape.prevent="$event.stopPropagation()">

        <div x-show="modalF4.open" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="asis-modal-box">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 rounded-t-2xl"
                style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white"
                            x-text="modalF4.tipo === 'salida_f4' ? 'Salida F4' : 'Entrada F4'"></p>
                        <p class="text-[11px] text-white/80" x-text="usuarioNombre"></p>
                    </div>
                </div>
                <button @click="modalF4.open = false"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-white/70 hover:text-white hover:bg-white/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1.5">
                        Motivo / Nota
                        <span class="text-gray-400 font-normal">(opcional)</span>
                    </label>
                    <textarea x-model="modalF4.nota" rows="3" placeholder="Indique el motivo de la salida/entrada F4..."
                        class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2.5
                                     focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none transition-all"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button @click="modalF4.open = false" class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-200 text-gray-500
                                   hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="confirmarF4()" :disabled="marcando" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg
                                   text-white transition-colors disabled:opacity-60" style="background-color:#f59e0b">
                        <svg x-show="marcando" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <span x-text="marcando ? 'Registrando...' : 'Confirmar'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Modal Detalle F4 ─────────────────────────────────────────────── --}}
    <div x-show="modalDetalleF4.open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="asis-modal-backdrop"
        @keydown.window.escape.prevent="$event.stopPropagation()">

        <div x-show="modalDetalleF4.open" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="asis-modal-box">

            <div class="flex items-center justify-between px-6 py-4 rounded-t-2xl"
                style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">Detalle F4</p>
                        <p class="text-[11px] text-white/80" x-text="modalDetalleF4.nombre"></p>
                    </div>
                </div>
                <button @click="modalDetalleF4.open = false"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-white/70 hover:text-white hover:bg-white/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <template x-if="modalDetalleF4.registros.length === 0">
                    <p class="text-center text-xs text-gray-400 py-6">Sin registros F4 para esta persona hoy.</p>
                </template>
                <div class="space-y-2">
                    <template x-for="(r, i) in modalDetalleF4.registros" :key="r.id ? String(r.id) : i">
                        <div class="p-3 rounded-xl border border-gray-100 bg-gray-50 space-y-1.5">
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold shrink-0" :class="r.tipo === 'Salida'
                                          ? 'bg-pink-100 text-pink-600'
                                          : 'bg-emerald-100 text-emerald-600'" x-text="r.tipo"></span>
                                <span class="text-xs font-semibold text-gray-700" x-text="r.fecha_hora"></span>
                            </div>
                            <p x-show="r.nota" class="text-xs text-gray-500 italic leading-relaxed break-words"
                                x-text="r.nota"></p>
                        </div>
                    </template>
                </div>
                <div class="flex justify-end mt-4">
                    <button @click="modalDetalleF4.open = false"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>{{-- /tab historial --}}

</div>