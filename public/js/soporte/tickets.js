/**
 * soporte/tickets.js — Copifam
 * Vista de tickets de soporte
 */
function ticketsModule(config) {
    config = config || {};
    return {
        /* ── Estado general ───────────────────────────────── */
        _nombreUsuario: config.nombreUsuario || '',
        _pdvArea:       config.pdvArea       || '',
        _bodegaNombre:  config.bodegaNombre  || '',
        _telefonoCelular: config.telefonoCelular || '',
        _loginUsuario:  config.loginUsuario  || '',
        esSistemas:     config.esSistemas    || false,
        esAdmin:        config.esAdmin       || false,
        cargando:     false,
        tickets:      [],
        filtroEstado: 'todos',
        filtroTexto:  '',
        ticketActivo: null,

        /* ── Tabs ─────────────────────────────────────────── */
        tabActivo:       'tickets',

        /* ── Análisis ─────────────────────────────────────── */
        analisisCargando: false,
        analisisDatos:    {},
        analisisMes:      '',        // "YYYY-MM"
        analisisMeses:    [],        // opciones del select
        analisisAnio:     '',
        analisisNumMes:   '',
        _charts:          { estado: null, linea: null, pdv: null, categoria: null },
        /* ── Filtros del tab Seguimiento ──────────────────── */
        filtroSeguimiento: '',
        filtroSegEstado:   'todos',
        filtroSegDesde:    '',
        filtroSegHasta:    '',
        filtroSegPdv:      '',       // nombre exacto del PDV seleccionado
        modalPdv:          false,    // dropdown selector de PDV
        busquedaPdv:       '',       // texto de búsqueda dentro del selector

        /* ── Instancia DataTable ───────────────────────── */
        _dtSeguimiento: null,
        segTotal: 0,
        segCargandoDt: false,
        _dtTickets: null,
        tktTotal: 0,
        tktCargandoDt: false,

        /* ── Estado modal CREAR ───────────────────────────── */
        modalCrear:      false,
        guardandoTicket: false,
        mostrarAvisoPegado: false,

        /* ── Estado acciones (aceptar / rechazar / finalizar / validar) ── */
        mostrarFormAceptar:   false,
        mostrarFormRechazar:  false,
        mostrarFormFinalizar: false,
        mostrarFormValidar:   false,
        procesandoAccion:     false,
        accionDatos: {
            fecha_estimada:       '',
            nota_rechazo_usuario: '',
            nota_encargado:       '',
        },

        catalogs: {
            categorias:    [],
            subcategorias: [],
            puntosVenta:   [],
        },

        get subcategoriasFiltradas() {
            if (!this.nuevoTicket.categoria_id) return [];
            return this.catalogs.subcategorias.filter(
                s => String(s.categoria_id) === String(this.nuevoTicket.categoria_id)
            );
        },

        nuevoTicket: {
            nombre_solicitante: '',
            pdv_area:           '',
            bodega_nombre:      '',
            numero_contacto:    '',
            categoria_id:       '',
            subcategoria_id:    '',
            titulo_ticket:      '',
            descripcion_detallada: '',
            prioridad:          '',
            archivo:            null,
            archivoNombre:      '',
            archivoTamano:      '',
        },

        /* ── Init ─────────────────────────────────────────── */
        init() {
            this.tickets = [];
            // Garantizar que usuarios sin permiso nunca queden en el tab de seguimiento
            if (!(this.esSistemas || this.esAdmin)) {
                this.tabActivo = 'tickets';
            }
            this.cargarCatalogos();
            // Inicializar DataTable de tickets
            if (!this._dtTickets) {
                this.$nextTick(() => {
                    setTimeout(() => this._initDtTickets(), 50);
                });
            }
            // Inicializar DataTable de seguimiento para admin/sistemas (una sola vez)
            if ((this.esSistemas || this.esAdmin) && !this._dtSeguimiento) {
                this.$nextTick(() => {
                    setTimeout(() => this._initDtSeguimiento(), 50);
                });
            }
            // Poblar select de meses para análisis
            if (this.esSistemas || this.esAdmin) {
                this._initAnalisisMeses();
            }
        },

        /* ── Filtrar por estado ───────────────────────────── */
        setFiltroEstado(estado) {
            this.filtroEstado = estado;
        },

        buscarTickets() {
            if (this._dtTickets) this._dtTickets.ajax.reload(null, true);
        },

        cargarTickets() {
            this.buscarTickets();
        },

        /* ── Inicializar DataTable tickets ────────────────── */
        _initDtTickets() {
            const self = this;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            if ($.fn.DataTable.isDataTable('#tbl-tickets')) {
                $('#tbl-tickets').DataTable().destroy();
            }

            // Cache global para almacenar filas de tickets
            window._ticketsCache = {};

            self._dtTickets = $('#tbl-tickets').DataTable({
                serverSide:   true,
                processing:   false,
                searching:    false,
                lengthChange: false,
                pageLength:   25,
                language: {
                    zeroRecords: 'No hay tickets con el filtro seleccionado.',
                    info:        'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty:   'Sin registros',
                    infoFiltered:'(filtrado de _MAX_ total)',
                    paginate: { first:'«', last:'»', next:'›', previous:'‹' },
                },
                ajax: {
                    url:  '/soporte/tickets',
                    type: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
                    beforeSend() { self.tktCargandoDt = true;  },
                    complete()   { self.tktCargandoDt = false; },
                    data(d) {
                        d.estado = self.filtroEstado === 'todos' ? '' : self.filtroEstado;
                        d.search = { value: self.filtroTexto.trim(), regex: false };
                    },
                    error(xhr) {
                        self.tktCargandoDt = false;
                        let msg = 'Error al cargar los tickets.';
                        try { const r = JSON.parse(xhr.responseText); msg = r.message || msg; } catch(e) {}
                        if (xhr.status === 401) msg = 'Tu sesión expiró. Por favor recarga la página.';
                        if (xhr.status === 403) msg = 'No tienes permiso para acceder a los tickets.';
                        copifamModal('error', 'Error', msg);
                    },
                    dataSrc: 'data',
                },
                order: [[0, 'desc']],
                drawCallback(settings) {
                    self.tktTotal = settings.json?.recordsFiltered ?? 0;
                    const w = document.getElementById('tbl-tickets_wrapper');
                    if (!w) return;
                    // Info "Página X de Y"
                    const api      = new $.fn.dataTable.Api(settings);
                    const pageInfo = api.page.info();
                    const infoEl   = w.querySelector('.dt-info');
                    if (infoEl) infoEl.textContent = `Página ${pageInfo.page + 1} de ${pageInfo.pages || 1}`;
                    // Centrado completo: cada nivel del layout
                    w.querySelectorAll('.dt-layout-row').forEach(r => {
                        r.style.cssText = 'float:none!important;clear:both!important;display:flex!important;flex-direction:column!important;align-items:center!important;width:100%!important;';
                    });
                    w.querySelectorAll('.dt-layout-cell').forEach(c => {
                        c.style.cssText = 'float:none!important;display:flex!important;justify-content:center!important;width:100%!important;';
                    });
                    const pg = w.querySelector('.dt-paging');
                    if (pg) pg.style.cssText = 'display:flex!important;justify-content:center!important;align-items:center!important;float:none!important;gap:0.2rem!important;flex-wrap:wrap!important;';
                    
                    // Guardar en cache los datos de las filas actuales
                    const rows = api.rows({page: 'current'}).data().toArray();
                    rows.forEach(row => {
                        window._ticketsCache[row.id_ticket] = row;
                    });
                },
                initComplete() {
                    const card = document.querySelector('.tkt-tickets-card');
                    const wrapper = document.getElementById('tbl-tickets_wrapper');
                    if (card && wrapper && !card.contains(wrapper)) card.appendChild(wrapper);
                },
                columns: [
                    /* 0 — # */
                    { data: 'id_ticket', render: v =>
                        `<span class="font-mono font-semibold text-gray-700">#${v}</span>` },
                    /* 1 — Solicitado por */
                    { data: 'nombre_solicitante', render: (v) => {
                        const ini = self._esc((v ?? '?').charAt(0).toUpperCase());
                        return `<div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0" style="background-color:#ff1498">${ini}</div>
                            <span class="text-gray-700">${self._esc(v ?? '—')}</span>
                        </div>`;
                    }},
                    /* 2 — Asunto */
                    { data: 'titulo_ticket', render: (v, _, row) =>
                        `<div class="max-w-[220px]">
                            <div class="text-gray-700 truncate">${self._esc(v ?? '—')}</div>
                            <div class="text-gray-400 text-[10px]">${self._esc(row.bodega_nombre ?? '')}</div>
                        </div>` },
                    /* 3 — Prioridad */
                    { data: 'prioridad', render: v => {
                        const vl  = (v ?? '').toLowerCase();
                        const cls = vl === 'alto' ? 'tkt-badge-alto' : vl === 'medio' ? 'tkt-badge-medio' : 'tkt-badge-bajo';
                        return `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${cls}">${self._esc(v ?? '—')}</span>`;
                    }},
                    /* 4 — Encargado */
                    { data: 'persona_encargada', render: v => {
                        if (!v) return '<span class="text-gray-300">—</span>';
                        const ini = self._esc((v ?? '?').charAt(0).toUpperCase());
                        return `<div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0" style="background-color:#8b5cf6">${ini}</div>
                            <span class="text-gray-600">${self._esc(v)}</span>
                        </div>`;
                    }},
                    /* 5 — Fecha creación */
                    { data: 'fecha_creacion', render: v =>
                        `<span class="text-gray-500 whitespace-nowrap">${self._esc(self.formatFecha(v))}</span>` },
                    /* 6 — Estado */
                    { data: 'estado', render: v => {
                        const cls = self.claseEstado(v);
                        return `<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ${cls}">${self._esc(v ?? '—')}</span>`;
                    }},
                    /* 7 — Ver */
                    { data: null, orderable: false, className: 'text-right',
                      render: (_, __, row) => `<button onclick="window._dtVerTicket(${row.id_ticket})"
                        class="tkt-btn-view inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium rounded-md">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>Ver</button>` },
                ],
            });

            window._dtVerTicket = (id) => {
                const row = window._ticketsCache[id];
                if (row) self.verDetalle(row);
                else console.warn('Ticket no encontrado en cache:', id);
            };
        },

        /* ── Inicializar DataTable server-side ────────────── */
        _initDtSeguimiento() {
            const self = this;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            // Destruir instancia previa si existe (evita "Cannot reinitialise DataTable")
            if ($.fn.DataTable.isDataTable('#tbl-seguimiento')) {
                $('#tbl-seguimiento').DataTable().destroy();
            }

            // Cache global para almacenar filas de seguimiento
            window._detallesCache = {};

            self._dtSeguimiento = $('#tbl-seguimiento').DataTable({
                serverSide:   true,
                processing:   false,   // manejamos el spinner manualmente
                searching:    false,
                lengthChange: false,
                pageLength:   25,
                language: {
                    zeroRecords: 'No se encontraron registros con los filtros aplicados.',
                    info:        'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty:   'Sin registros',
                    infoFiltered:'(filtrado de _MAX_ total)',
                    paginate: {
                        first:    '«',
                        last:     '»',
                        next:     '›',
                        previous: '‹',
                    },
                },
                ajax: {
                    url:  '/soporte/tickets/seguimiento',
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':     csrfToken,
                    },
                    beforeSend() { self.segCargandoDt = true;  },
                    complete()   { self.segCargandoDt = false; },
                    data(d) {
                        d.estado = self.filtroSegEstado === 'todos' ? '' : self.filtroSegEstado;
                        d.desde  = self.filtroSegDesde;
                        d.hasta  = self.filtroSegHasta;
                        d.pdv    = self.filtroSegPdv;
                        d.search = { value: self.filtroSeguimiento.trim(), regex: false };
                    },
                    dataSrc: 'data',
                },
                order: [[0, 'desc']],
                drawCallback(settings) {
                    self.segTotal = settings.json?.recordsFiltered ?? 0;
                    const wrapper = document.getElementById('tbl-seguimiento_wrapper');
                    if (!wrapper) return;
                    // Info "Página X de Y"
                    const api      = new $.fn.dataTable.Api(settings);
                    const pageInfo = api.page.info();
                    const infoEl   = wrapper.querySelector('.dt-info');
                    if (infoEl) infoEl.textContent = `Página ${pageInfo.page + 1} de ${pageInfo.pages || 1}`;
                    // Centrado completo: cada nivel del layout
                    wrapper.querySelectorAll('.dt-layout-row').forEach(row => {
                        row.style.cssText = 'float:none!important;clear:both!important;display:flex!important;flex-direction:column!important;align-items:center!important;width:100%!important;';
                    });
                    wrapper.querySelectorAll('.dt-layout-cell').forEach(c => {
                        c.style.cssText = 'float:none!important;display:flex!important;justify-content:center!important;width:100%!important;';
                    });
                    const pagingEl = wrapper.querySelector('.dt-paging');
                    if (pagingEl) pagingEl.style.cssText = 'display:flex!important;justify-content:center!important;align-items:center!important;float:none!important;gap:0.2rem!important;flex-wrap:wrap!important;';
                    
                    // Guardar en cache los datos de las filas actuales
                    const rows = api.rows({page: 'current'}).data().toArray();
                    rows.forEach(row => {
                        window._detallesCache[row.id_ticket] = row;
                    });
                },
                initComplete() {
                    // Mover el wrapper de DT dentro del card (bg-white rounded-xl...)
                    const card = document.querySelector('.tkt-seg-card');
                    const wrapper = document.getElementById('tbl-seguimiento_wrapper');
                    if (card && wrapper && !card.contains(wrapper)) {
                        card.appendChild(wrapper);
                    }
                },
                columns: [
                    /* 0 — # */
                    {
                        data: 'id_ticket',
                        render(v) {
                            return `<span class="font-mono font-semibold text-gray-700">#${v}</span>`;
                        },
                    },
                    /* 1 — Punto de venta */
                    {
                        data: 'bodega_nombre',
                        render(v, _, row) {
                            const nombre = v || '—';
                            const area   = row.pdv_area || '';
                            return `<div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color:#00AEEF15">
                                    <svg class="w-3.5 h-3.5" style="color:#00AEEF" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-700">${self._esc(nombre)}</div>
                                    <div class="text-[10px] text-gray-400">${self._esc(area)}</div>
                                </div>
                            </div>`;
                        },
                    },
                    /* 2 — Quién envió */
                    {
                        data: 'nombre_solicitante',
                        render(v, _, row) {
                            const inicial = (v || '?').charAt(0).toUpperCase();
                            return `<div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0" style="background-color:#ff1498">${inicial}</div>
                                <div>
                                    <div class="text-gray-700">${self._esc(v || '—')}</div>
                                    <div class="text-[10px] text-gray-400">${self._esc(row.titulo_ticket || '')}</div>
                                </div>
                            </div>`;
                        },
                    },
                    /* 3 — Encargado */
                    {
                        data: 'persona_encargada',
                        render(v) {
                            if (!v) return '<span class="text-gray-300 text-xs">Sin asignar</span>';
                            const inicial = v.charAt(0).toUpperCase();
                            return `<div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0" style="background-color:#8b5cf6">${inicial}</div>
                                <span class="text-gray-600">${self._esc(v)}</span>
                            </div>`;
                        },
                    },
                    /* 4 — Fecha de inicio */
                    {
                        data: 'fecha_inicio',
                        render(v) {
                            if (!v) return '<span class="text-gray-300">—</span>';
                            return `<div class="flex items-center gap-1.5 text-gray-500 whitespace-nowrap">
                                <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                                </svg>
                                ${self.formatFecha(v)}
                            </div>`;
                        },
                    },
                    /* 5 — Estado */
                    {
                        data: 'estado',
                        render(v) {
                            const cls = self.claseEstado(v);
                            return `<span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-semibold ${cls}">${self._esc(v || '—')}</span>`;
                        },
                    },
                    /* 6 — Tiempo de respuesta */
                    {
                        data: 'tiempo_atencion',
                        render(v, _, row) {
                            const estado = (row.estado || '').toLowerCase();
                            const tieneT = v !== null && v !== undefined && v !== '';
                            if (tieneT) {
                                const mins = parseInt(v, 10);
                                const color = mins > 1440 ? 'text-red-500' : mins > 480 ? 'text-amber-500' : 'text-emerald-600';
                                const iconColor = mins > 1440 ? 'text-red-400' : mins > 480 ? 'text-amber-400' : 'text-emerald-400';
                                return `<div class="flex items-center gap-1.5">
                                    <svg class="w-3 h-3 flex-shrink-0 ${iconColor}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="font-medium ${color}">${self.formatTiempo(v)}</span>
                                </div>`;
                            }
                            if (['no apto', 'solucionado'].includes(estado)) {
                                return `<span class="inline-flex items-center gap-1 text-gray-400 text-xs">
                                    <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    No registrado
                                </span>`;
                            }
                            return `<span class="inline-flex items-center gap-1 text-blue-400 text-xs font-medium">
                                <svg class="w-3 h-3" style="animation:tkt-spin 1.5s linear infinite" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                En curso...
                            </span>`;
                        },
                    },
                    /* 7 — Ver detalle */
                    {
                        data:       null,
                        orderable:  false,
                        className:  'text-right',
                        render(_, __, row) {
                            const id = row.id_ticket;
                            return `<button onclick="window._dtVerDetalle(${id})"
                                        class="tkt-btn-view inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium rounded-md">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Ver
                            </button>`;
                        },
                    },
                ],
            });

            // Puente global para el botón "Ver" generado por DT
            window._dtVerDetalle = (id) => {
                const row = window._detallesCache[id];
                if (row) self.verDetalle(row);
                else console.warn('Detalle no encontrado en cache:', id);
            };
        },

        /* ── Buscar / recargar DataTable (botón Buscar) ───── */
        buscarSeguimiento() {
            if (this._dtSeguimiento) {
                this._dtSeguimiento.ajax.reload(null, true); // true = volver a pág 1
            }
        },

        /* ── Helper: escapar HTML ─────────────────────────── */
        _esc(str) {
            return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },

        /* ── Modal detalle ────────────────────────────────── */
        verDetalle(ticket) {
            this.ticketActivo         = ticket;
            this.mostrarFormAceptar   = false;
            this.mostrarFormRechazar  = false;
            this.mostrarFormFinalizar = false;
            this.mostrarFormValidar   = false;
            this.accionDatos = { fecha_estimada: '', nota_rechazo_usuario: '', nota_encargado: '' };
        },

        cerrarModal() {
            this.ticketActivo         = null;
            this.mostrarFormAceptar   = false;
            this.mostrarFormRechazar  = false;
            this.mostrarFormFinalizar = false;
            this.mostrarFormValidar   = false;
        },

        /* ── Modal CREAR: abrir / cerrar ──────────────────── */
        abrirModalCrear() {
            this.nuevoTicket = {
                nombre_solicitante:    this._nombreUsuario,
                pdv_area:              this._pdvArea,
                bodega_nombre:         this._bodegaNombre,
                numero_contacto:       this._telefonoCelular,
                categoria_id:          '',
                subcategoria_id:       '',
                titulo_ticket:         '',
                descripcion_detallada: '',
                prioridad:             '',
                archivo:               null,
                archivoNombre:         '',
                archivoTamano:         '',
            };
            this.mostrarAvisoPegado = false;
            this.modalCrear = true;
        },

        cerrarModalCrear() {
            this.modalCrear = false;
        },

        /* ── Acción: Aceptar ticket ───────────────────────── */
        async aceptarTicket() {
            if (!this.accionDatos.fecha_estimada) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe indicar la fecha estimada de solución.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            const hoy        = new Date(); hoy.setHours(0, 0, 0, 0);
            const [y, m, d]  = this.accionDatos.fecha_estimada.split('-').map(Number);
            const fechaEleg  = new Date(y, m - 1, d);
            if (fechaEleg < hoy) {
                CopifamModal.fire({ title: 'Fecha inválida', text: 'La fecha estimada no puede ser anterior a hoy.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.procesandoAccion = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const res  = await fetch(`/soporte/tickets/${this.ticketActivo.id_ticket}/aceptar`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:    JSON.stringify({ fecha_estimada: this.accionDatos.fecha_estimada }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Error al aceptar el ticket.');
                this._actualizarTicketEnLista(data.ticket);
                this.ticketActivo     = data.ticket;
                this.mostrarFormAceptar = false;
                CopifamModal.fire({ title: '¡Ticket aceptado!', text: 'El ticket ha sido puesto En proceso.', icon: 'success', confirmText: 'Aceptar' });
            } catch (e) {
                CopifamModal.fire({ title: 'Error', text: e.message, icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.procesandoAccion = false;
            }
        },

        /* ── Acción: Rechazar ticket ──────────────────────── */
        async rechazarTicket() {
            if (!this.accionDatos.nota_encargado.trim()) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe indicar el motivo del rechazo.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.procesandoAccion = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const res  = await fetch(`/soporte/tickets/${this.ticketActivo.id_ticket}/rechazar`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:    JSON.stringify({ nota_encargado: this.accionDatos.nota_encargado }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Error al rechazar el ticket.');
                this._actualizarTicketEnLista(data.ticket);
                this.ticketActivo      = data.ticket;
                this.mostrarFormRechazar = false;
                CopifamModal.fire({ title: 'Ticket no apto', text: 'El ticket fue marcado como no apto.', icon: 'info', confirmText: 'Aceptar' });
            } catch (e) {
                CopifamModal.fire({ title: 'Error', text: e.message, icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.procesandoAccion = false;
            }
        },

        /* ── Acción: Finalizar ticket (→ En revisión) ─────── */
        async finalizarTicket() {
            if (!this.accionDatos.nota_encargado.trim()) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe agregar una nota de resolución.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.procesandoAccion = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const res  = await fetch(`/soporte/tickets/${this.ticketActivo.id_ticket}/finalizar`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:    JSON.stringify({ nota_encargado: this.accionDatos.nota_encargado }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Error al finalizar el ticket.');
                this._actualizarTicketEnLista(data.ticket);
                this.ticketActivo       = data.ticket;
                this.mostrarFormFinalizar = false;
                CopifamModal.fire({ title: 'Enviado a revisión', text: 'El ticket quedó en estado "En revisión". El solicitante debe confirmar si fue resuelto.', icon: 'success', confirmText: 'Entendido' });
            } catch (e) {
                CopifamModal.fire({ title: 'Error', text: e.message, icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.procesandoAccion = false;
            }
        },

        /* ── Acción: Validar ticket (solicitante confirma) ── */
        async validarTicket(aprobado) {
            if (!aprobado && !this.accionDatos.nota_rechazo_usuario.trim()) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe indicar por qué el ticket no quedó resuelto.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.procesandoAccion = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const res  = await fetch(`/soporte/tickets/${this.ticketActivo.id_ticket}/validar`, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:    JSON.stringify({
                        aprobado:                 aprobado ? 1 : 0,
                        nota_rechazo_usuario:  aprobado ? null : this.accionDatos.nota_rechazo_usuario,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Error al validar el ticket.');
                this._actualizarTicketEnLista(data.ticket);
                this.ticketActivo     = data.ticket;
                this.mostrarFormValidar = false;
                if (aprobado) {
                    CopifamModal.fire({ title: '¡Ticket resuelto!', text: 'Gracias por confirmar. El ticket ha sido marcado como Resuelto.', icon: 'success', confirmText: 'Aceptar' });
                } else {
                    CopifamModal.fire({ title: 'Ticket devuelto', text: 'El ticket fue devuelto al encargado para que lo revise nuevamente.', icon: 'info', confirmText: 'Entendido' });
                }
            } catch (e) {
                CopifamModal.fire({ title: 'Error', text: e.message, icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.procesandoAccion = false;
            }
        },

        /* ── Actualiza el ticket en el arreglo local ──────── */
        _actualizarTicketEnLista(ticketActualizado) {
            if (!ticketActualizado) return;
            const idx = this.tickets.findIndex(t => t.id_ticket === ticketActualizado.id_ticket);
            if (idx !== -1) this.tickets.splice(idx, 1, ticketActualizado);
        },

        /* ── Cargar tickets existentes ────────────────────── */
        async cargarTickets() {
            this.cargando = true;
            try {
                const res = await fetch('/soporte/tickets', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Error ' + res.status);
                const data = await res.json();
                this.tickets = data.tickets ?? [];
            } catch (e) {
                console.warn('[tickets] No se pudieron cargar los tickets:', e.message);
            } finally {
                this.cargando = false;
            }
        },

        /* ── Cargar catálogos (categorías / subcategorías) ── */
        async cargarCatalogos() {
            try {
                const res = await fetch('/soporte/catalogos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('Error ' + res.status);
                const data = await res.json();
                this.catalogs.categorias    = data.categorias    ?? [];
                this.catalogs.subcategorias = data.subcategorias ?? [];
                this.catalogs.puntosVenta   = data.puntos_venta  ?? [];
            } catch (e) {
                console.warn('[tickets] No se pudieron cargar los catálogos:', e.message);
            }
        },

        /* ── Capturar pegado Ctrl+V ───────────────────────── */
        capturarPegado(event) {
            const items = event.clipboardData?.items ?? [];
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        this._asignarArchivo(file);
                        this.mostrarAvisoPegado = true;
                        setTimeout(() => { this.mostrarAvisoPegado = false; }, 3000);
                    }
                    event.preventDefault();
                    break;
                }
            }
        },

        /* ── Capturar archivo desde input ─────────────────── */
        capturarArchivo(event) {
            const file = event.target.files?.[0];
            if (file) this._asignarArchivo(file);
        },

        _asignarArchivo(file) {
            const maxMB = 5;
            if (file.size > maxMB * 1024 * 1024) {
                CopifamModal.fire({ title: 'Archivo demasiado grande', text: 'El archivo supera el tamaño máximo permitido (' + maxMB + ' MB).', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.nuevoTicket.archivo      = file;
            this.nuevoTicket.archivoNombre = file.name;
            this.nuevoTicket.archivoTamano = (file.size / 1024).toFixed(1) + ' KB';
        },

        /* ── Guardar ticket ───────────────────────────────── */
        async guardarTicket() {
            const t = this.nuevoTicket;

            // Validación campo por campo con CopifamModal
            if (!t.numero_contacto) {
                CopifamModal.fire({ title: 'Sin celular registrado', text: 'No tiene un número de celular registrado en su perfil. Por favor, contacte al administrador para actualizar su información.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!/^\d{7,15}$/.test(t.numero_contacto)) {
                CopifamModal.fire({ title: 'Número inválido', text: 'El número de celular registrado en su perfil no es válido. Por favor, contacte al administrador.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!t.prioridad) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe seleccionar una prioridad.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!t.categoria_id) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe seleccionar una categoría.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!t.subcategoria_id) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe seleccionar una subcategoría.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!t.titulo_ticket || !t.titulo_ticket.trim()) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe ingresar el título del ticket.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            if (!t.descripcion_detallada || !t.descripcion_detallada.trim()) {
                CopifamModal.fire({ title: 'Campo requerido', text: 'Debe ingresar la descripción detallada.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }

            this.guardandoTicket = true;
            try {
                const formData = new FormData();
                formData.append('nombre_solicitante',    t.nombre_solicitante);
                formData.append('pdv_area',              t.pdv_area);
                formData.append('numero_contacto',       t.numero_contacto);
                formData.append('categoria_id',          t.categoria_id);
                formData.append('subcategoria_id',       t.subcategoria_id);
                formData.append('titulo_ticket',         t.titulo_ticket);
                formData.append('descripcion_detallada', t.descripcion_detallada);
                formData.append('prioridad',             t.prioridad);
                if (t.archivo) {
                    formData.append('archivo_adjunto', t.archivo, t.archivoNombre);
                }

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const res  = await fetch('/soporte/tickets', {
                    method:  'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body:    formData,
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.message ?? 'Error al crear el ticket.');

                this.cerrarModalCrear();
                CopifamModal.fire({ title: '¡Ticket creado!', text: 'Ticket #' + (data.ticket?.id_ticket ?? '') + ' registrado correctamente.', icon: 'success', confirmText: 'Aceptar' });
                if (this._dtTickets) this._dtTickets.ajax.reload(null, true);
            } catch (e) {
                CopifamModal.fire({ title: 'Error', text: e.message, icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.guardandoTicket = false;
            }
        },

        /* ── Helpers de badge ─────────────────────────────── */
        clasePrioridad(p) {
            const m = { 'alto': 'tkt-badge-alto', 'medio': 'tkt-badge-medio', 'bajo': 'tkt-badge-bajo' };
            return m[(p ?? '').toLowerCase()] ?? 'tkt-badge-medio';
        },

        claseEstado(e) {
            const m = {
                'pendiente':    'tkt-estado-pendiente',
                'en proceso':   'tkt-estado-proceso',
                'en revisión':  'tkt-estado-revision',
                'devuelto':     'tkt-estado-devuelto',
                'solucionado':  'tkt-estado-solucionado',
                'no apto':      'tkt-estado-no-apto',
            };
            return m[(e ?? '').toLowerCase()] ?? 'tkt-estado-pendiente';
        },

        formatFecha(f) {
            if (!f) return '—';
            // Si es solo fecha (YYYY-MM-DD), parsear sin zona horaria para evitar
            // que UTC midnight se desplace al día anterior en Colombia (UTC-5)
            const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(String(f).trim());
            let d;
            if (soloFecha) {
                const [y, mo, da] = String(f).split('-').map(Number);
                d = new Date(y, mo - 1, da);
            } else {
                d = new Date(f);
            }
            if (isNaN(d)) return f;
            return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' });
        },

        // Convierte minutos (int desde BD) a texto legible: "2 días, 3 horas, 15 min"
        formatTiempo(min) {
            if (min === null || min === undefined || min === '' || isNaN(min)) return '—';
            const m = parseInt(min, 10);
            if (m <= 0) return 'Menos de 1 minuto';
            const dias  = Math.floor(m / 1440);
            const horas = Math.floor((m % 1440) / 60);
            const mins  = m % 60;
            const partes = [];
            if (dias  > 0) partes.push(dias  + (dias  === 1 ? ' día'  : ' días'));
            if (horas > 0) partes.push(horas + (horas === 1 ? ' hora' : ' horas'));
            if (mins  > 0) partes.push(mins  + ' min');
            return partes.join(', ') || 'Menos de 1 minuto';
        },

        /* ═══════════════════════════════════════════════════
           ANÁLISIS — helpers y carga de datos
        ═══════════════════════════════════════════════════ */

        /** Genera las opciones del selector de mes (últimos 24 meses). */
        _initAnalisisMeses() {
            const meses = [
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ];
            const now  = new Date();
            const opts = [];
            for (let i = 0; i < 24; i++) {
                const d    = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const anio = d.getFullYear();
                const mes  = d.getMonth() + 1;
                const val  = anio + '-' + String(mes).padStart(2, '0');
                opts.push({ value: val, label: meses[d.getMonth()] + ' ' + anio });
            }
            this.analisisMeses = opts;
            this.analisisMes   = opts[0].value;
            this.analisisAnio  = String(now.getFullYear());
            this.analisisNumMes= String(now.getMonth() + 1);
        },

        /** Descarga el Excel del análisis del mes seleccionado. */
        descargarExcel() {
            const parts   = this.analisisMes.split('-');
            const anio    = parts[0];
            const numMes  = parseInt(parts[1], 10);
            window.location.href = `/soporte/tickets/analisis/excel?anio=${anio}&mes=${numMes}`;
        },

        /** Destruye las 4 instancias de Chart.js para poder recrearlas. */
        _destruirCharts() {
            ['estado','linea','pdv','categoria'].forEach(k => {
                if (this._charts[k]) {
                    this._charts[k].destroy();
                    this._charts[k] = null;
                }
            });
        },

        /** Llama al endpoint y reconstruye los gráficos. */
        async cargarAnalisis() {
            if (!(this.esSistemas || this.esAdmin)) return;
            this.analisisCargando = true;
            this._destruirCharts();

            const parts   = this.analisisMes.split('-');
            const anio    = parts[0];
            const numMes  = parseInt(parts[1], 10);
            const csrf    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            try {
                const resp = await fetch(
                    `/soporte/tickets/analisis?anio=${anio}&mes=${numMes}`,
                    { headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }
                );
                const data = await resp.json();
                if (!data.ok) throw new Error(data.message || 'Error al cargar análisis');

                this.analisisDatos = data;

                // Esperar a que Alpine renderice los canvas
                await this.$nextTick();
                setTimeout(() => this._buildCharts(data), 50);
            } catch (e) {
                console.error('cargarAnalisis:', e);
            } finally {
                this.analisisCargando = false;
            }
        },

        /** Construye los 4 gráficos Chart.js. */
        _buildCharts(data) {
            const ROSA  = '#ff1498';
            const CYAN  = '#00AEEF';
            const LIME  = '#84cc16';
            const AMBER = '#f59e0b';
            const SLATE = '#94a3b8';

            // Paleta para barras múltiples
            const PALETTE = [
                '#ff1498','#00AEEF','#a855f7','#f59e0b',
                '#10b981','#ef4444','#3b82f6','#ec4899',
                '#14b8a6','#f97316',
            ];

            const defaults = {
                font: { family: "'Inter','system-ui',sans-serif", size: 11 },
                color: '#6b7280',
            };

            /* ── 1. DONA: tickets por estado ─────────────────── */
            const ctxE = document.getElementById('chart-estado');
            if (ctxE && data.por_estado?.length) {
                const labels = data.por_estado.map(r => r.estado ?? 'Sin estado');
                const values = data.por_estado.map(r => Number(r.total));
                const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);
                const totalDona = values.reduce((a, b) => a + b, 0);

                this._charts.estado = new Chart(ctxE, {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: values, backgroundColor: colors,
                            borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '55%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    font: defaults.font, color: defaults.color,
                                    padding: 12, boxWidth: 12, boxHeight: 12,
                                    generateLabels(chart) {
                                        const ds  = chart.data.datasets[0];
                                        const tot = ds.data.reduce((a, b) => a + b, 0);
                                        return chart.data.labels.map((lbl, i) => ({
                                            text: `${lbl}: ${ds.data[i]} (${tot ? Math.round(ds.data[i]/tot*100) : 0}%)`,
                                            fillStyle: ds.backgroundColor[i],
                                            hidden: false, index: i,
                                            strokeStyle: '#fff', lineWidth: 2,
                                        }));
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => {
                                        const pct = totalDona ? Math.round(ctx.parsed / totalDona * 100) : 0;
                                        return `  ${ctx.label}: ${ctx.parsed} tickets (${pct}%)`;
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'donutCenter',
                        afterDraw(chart) {
                            const { ctx, chartArea: { top, bottom, left, right } } = chart;
                            const cx = (left + right) / 2;
                            const cy = (top + bottom) / 2;
                            ctx.save();
                            ctx.textAlign    = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.font         = `bold 22px Inter,system-ui,sans-serif`;
                            ctx.fillStyle    = '#374151';
                            ctx.fillText(totalDona, cx, cy - 8);
                            ctx.font      = `11px Inter,system-ui,sans-serif`;
                            ctx.fillStyle = '#9ca3af';
                            ctx.fillText('tickets', cx, cy + 12);
                            ctx.restore();
                        }
                    }]
                });
            }

            /* ── 2. LÍNEA: resueltos vs no resueltos por mes ─── */
            const ctxL = document.getElementById('chart-linea');
            if (ctxL && data.resueltos_por_mes?.length) {
                const nombresMes = [
                    '','Ene','Feb','Mar','Abr','May','Jun',
                    'Jul','Ago','Sep','Oct','Nov','Dic'
                ];
                const labels   = data.resueltos_por_mes.map(r => nombresMes[r.mes] + ' ' + String(r.anio).slice(2));
                const resueltos    = data.resueltos_por_mes.map(r => r.resueltos);
                const noResueltos  = data.resueltos_por_mes.map(r => r.no_resueltos);

                this._charts.linea = new Chart(ctxL, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            { label: 'Resueltos',     data: resueltos,
                              borderColor: LIME,  backgroundColor: LIME  + '20',
                              tension: 0.35, fill: true, pointRadius: 4, pointBackgroundColor: LIME },
                            { label: 'No resueltos',  data: noResueltos,
                              borderColor: ROSA,  backgroundColor: ROSA  + '20',
                              tension: 0.35, fill: true, pointRadius: 4, pointBackgroundColor: ROSA },
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { grid: { display: false },
                                 ticks: { ...defaults.font, color: defaults.color } },
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' },
                                 ticks: { ...defaults.font, color: defaults.color, stepSize: 1 } }
                        },
                        plugins: {
                            legend: { labels: { ...defaults.font, color: defaults.color,
                                boxWidth: 12, boxHeight: 12 } }
                        }
                    }
                });
            }

            /* ── 3. BARRAS horizontales: PDV ─────────────────── */
            const ctxP = document.getElementById('chart-pdv');
            if (ctxP && data.por_pdv?.length) {
                const labels = data.por_pdv.map(r => r.pdv ?? 'Sin PDV');
                const values = data.por_pdv.map(r => r.total);
                const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);

                this._charts.pdv = new Chart(ctxP, {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Tickets', data: values,
                            backgroundColor: colors, borderRadius: 6, borderSkipped: false }] },
                    options: {
                        indexAxis: 'y',
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { beginAtZero: true, grid: { color: '#f1f5f9' },
                                 ticks: { ...defaults.font, color: defaults.color, stepSize: 1 } },
                            y: { grid: { display: false },
                                 ticks: { ...defaults.font, color: defaults.color } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            }

            /* ── 4. BARRAS verticales: categoría ─────────────── */
            const ctxC = document.getElementById('chart-categoria');
            if (ctxC && data.por_categoria?.length) {
                const labels = data.por_categoria.map(r => r.categoria ?? 'Sin categoría');
                const values = data.por_categoria.map(r => r.total);
                const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);

                this._charts.categoria = new Chart(ctxC, {
                    type: 'bar',
                    data: { labels, datasets: [{ label: 'Tickets', data: values,
                            backgroundColor: colors, borderRadius: 6, borderSkipped: false }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { grid: { display: false },
                                 ticks: { ...defaults.font, color: defaults.color,
                                          maxRotation: 30, minRotation: 0 } },
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' },
                                 ticks: { ...defaults.font, color: defaults.color, stepSize: 1 } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        },
    };
}
