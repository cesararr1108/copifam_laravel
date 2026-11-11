// @ts-nocheck
/* ══════════════════════════════════════════════════════════════════════
   MÓDULO: Control de Asistencia — asistenciaModule()
   Alpine.js component
════════════════════════════════════════════════════════════════════════ */
function asistenciaModule() {
    return {

        // ── Estado general ────────────────────────────────────────────────────
        tabActivo: 'registro',

        // ── Registro ──────────────────────────────────────────────────────────
        usuarios: [],
        areasSelect: [],
        usuarioSeleccionado: '',
        marcando: false,

        // Tabla del día
        registros: [],
        tablaCargando: false,
        fechaHoy: '',
        fechaHoyLabel: '',

        // Modal F4
        modalF4: { open: false, tipo: '', nota: '' },

        // Modal detalle F4
        modalDetalleF4: { open: false, nombre: '', registros: [] },

        // ── Historial ─────────────────────────────────────────────────────────
        histDesde: '',
        histHasta: '',
        histNombre: '',
        histCargando: false,
        histSubTab: 'asistencias',
        histDataTableAsistencias: null,
        histDataTableF4: null,
        histDataTableInitialized: false,

        // ─────────────────────────────────────────────────────────────────────
        // Init
        // ─────────────────────────────────────────────────────────────────────
        init() {
            const hoy = new Date();
            const pad = n => String(n).padStart(2, '0');
            this.fechaHoy = `${hoy.getFullYear()}-${pad(hoy.getMonth()+1)}-${pad(hoy.getDate())}`;
            this.fechaHoyLabel = hoy.toLocaleDateString('es-CO', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });

            // Valores por defecto historial: mes actual
            const y = hoy.getFullYear();
            const m = String(hoy.getMonth() + 1).padStart(2, '0');
            this.histDesde = `${y}-${m}-01`;
            this.histHasta = this.fechaHoy;

            // Guardar referencia global del módulo para funciones de DataTables
            window.__asistenciaModuleInstance = this;

            this.cargarUsuarios();
            this.cargarTabla();
        },

        // ─────────────────────────────────────────────────────────────────────
        // Usuario seleccionado → nombre legible
        // ─────────────────────────────────────────────────────────────────────
        get usuarioNombre() {
            const u = this.usuarios.find(u => String(u.id) === String(this.usuarioSeleccionado));
            return u ? u.nombre_completo : '';
        },

        // ─────────────────────────────────────────────────────────────────────
        // Catálogo usuarios
        // ─────────────────────────────────────────────────────────────────────
        cargarUsuarios() {
            fetch('/gestion-humana/asistencia/usuarios', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(data => { this.usuarios = Array.isArray(data) ? data : []; })
                .catch(err => {
                    console.error('Error al cargar usuarios:', err);
                    this.usuarios = [];
                    CopifamModal.fire({ title: 'Error', text: 'No se pudo cargar el listado de empleados. (' + err.message + ')', icon: 'error', confirmText: 'Aceptar' });
                });
        },



        // ─────────────────────────────────────────────────────────────────────
        // Tabla del día
        // ─────────────────────────────────────────────────────────────────────
        cargarTabla() {
            this.tablaCargando = true;
            fetch(`/gestion-humana/asistencia/tabla?fecha=${this.fechaHoy}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => {
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(data => { this.registros = Array.isArray(data) ? data : []; })
                .catch(err => {
                    console.error('Error al cargar tabla:', err);
                    this.registros = [];
                    CopifamModal.fire({ title: 'Error', text: 'No se pudo cargar la tabla de asistencias. (' + err.message + ')', icon: 'error', confirmText: 'Aceptar' });
                })
                .finally(() => { this.tablaCargando = false; });
        },

        // ─────────────────────────────────────────────────────────────────────
        // Marcar entrada / salida directa (sin modal)
        // ─────────────────────────────────────────────────────────────────────
        marcar(tipo) {
            if (!this.usuarioSeleccionado) {
                CopifamModal.fire({ title: 'Atención', text: 'Seleccione un empleado primero.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this._enviarMarcacion({ tipo, nota: '' });
        },

        // ─────────────────────────────────────────────────────────────────────
        // Abrir modal F4
        // ─────────────────────────────────────────────────────────────────────
        abrirF4(tipo) {
            if (!this.usuarioSeleccionado) {
                CopifamModal.fire({ title: 'Atención', text: 'Seleccione un empleado primero.', icon: 'warning', confirmText: 'Entendido' });
                return;
            }
            this.modalF4 = { open: true, tipo, nota: '' };
        },

        // ─────────────────────────────────────────────────────────────────────
        // Confirmar desde modal F4
        // ─────────────────────────────────────────────────────────────────────
        confirmarF4() {
            this._enviarMarcacion({ tipo: this.modalF4.tipo, nota: this.modalF4.nota });
        },

        // ─────────────────────────────────────────────────────────────────────
        // Envío al servidor (común para todos los tipos)
        // ─────────────────────────────────────────────────────────────────────
        _enviarMarcacion({ tipo, nota }) {
            this.marcando = true;

            const ahora = new Date();
            const pad = n => String(n).padStart(2, '0');
            const fecha_hora = `${ahora.getFullYear()}-${pad(ahora.getMonth()+1)}-${pad(ahora.getDate())} `
                             + `${pad(ahora.getHours())}:${pad(ahora.getMinutes())}:${pad(ahora.getSeconds())}`;

            const body = new FormData();
            body.append('id',        this.usuarioSeleccionado);
            body.append('fecha',     this.fechaHoy);
            body.append('fecha_hora',fecha_hora);
            body.append('tipo',      tipo);
            body.append('nota',      nota ?? '');

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            fetch('/gestion-humana/asistencia/marcar', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body,
            })
                .then(async r => {
                    const data = await r.json();
                    if (!r.ok) {
                        throw new Error(data.message ?? 'Error al registrar.');
                    }
                    return data;
                })
                .then(data => {
                    this.modalF4.open = false;
                    CopifamModal.fire({
                        title: '¡Registrado!',
                        text: data.message,
                        icon: 'success',
                        confirmText: 'Aceptar'
                    });
                    this.cargarTabla();
                })
                .catch(err => {
                    CopifamModal.fire({
                        title: 'No se pudo registrar',
                        text: err.message,
                        icon: 'error',
                        confirmText: 'Aceptar'
                    });
                })
                .finally(() => { this.marcando = false; });
        },

        // ─────────────────────────────────────────────────────────────────────
        // Ver detalle F4 de una fila de la tabla
        // ─────────────────────────────────────────────────────────────────────
        verF4(id, nombre) {
            fetch(`/gestion-humana/asistencia/detalle-f4?id=${id}&fecha=${this.fechaHoy}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => {
                    this.modalDetalleF4 = { open: true, nombre, registros: data };
                })
                .catch(() => {
                    CopifamModal.fire({ title: 'Error', text: 'No se pudo cargar el detalle F4.', icon: 'error', confirmText: 'Aceptar' });
                });
        },

        // ─────────────────────────────────────────────────────────────────────
        // Historial con DataTables (server-side)
        // ─────────────────────────────────────────────────────────────────────
        cargarHistorial() {
            if (this.histCargando) return;
            this.histCargando = true;

            // Destroys anteriores
            if (this.histDataTableAsistencias) {
                this.histDataTableAsistencias.destroy();
                this.histDataTableAsistencias = null;
            }
            if (this.histDataTableF4) {
                this.histDataTableF4.destroy();
                this.histDataTableF4 = null;
            }

            // Reinitialize based on current tab
            this.$nextTick(() => {
                if (this.histSubTab === 'asistencias') {
                    this.initDataTableAsistencias();
                } else {
                    this.initDataTableF4();
                }
            });
        },

        initDataTableAsistencias() {
            const tableId = '#histAsistenciasTable';
            const self = this;
            
            this.histDataTableAsistencias = $(tableId).DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                ordering: true,
                searching: true,
                destroy: true,
                ajax: {
                    url: '/gestion-humana/asistencia/historial-datatable',
                    type: 'GET',
                    data: (d) => {
                        d.desde = this.histDesde;
                        d.hasta = this.histHasta;
                        d.nombre = this.histNombre;
                        d.tipo = 'asistencias';
                    },
                    beforeSend() { self.histCargando = true; },
                    complete()   { self.histCargando = false; },
                    error(xhr) {
                        self.histCargando = false;
                        console.error('DT asistencias error:', xhr);
                    },
                },
                columns: [
                    { data: 'id', orderable: true, searchable: false },
                    { data: 'nombre_completo', orderable: true, searchable: true },
                    { data: 'fecha', orderable: true, searchable: false },
                    { data: 'entrada_t1', orderable: true, searchable: false },
                    { data: 'salida_t1', orderable: true, searchable: false },
                    { data: 'entrada_t2', orderable: true, searchable: false },
                    { data: 'salida_t2', orderable: true, searchable: false },
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/es-MX.json',
                    infoEmpty: 'Sin resultados',
                    paginate: { first: '«', last: '»', previous: '‹', next: '›' },
                },
                dom: 'tip',
                paging: true,
                info: true,
                drawCallback(settings) {
                    self.histCargando = false;
                    const w = document.getElementById('histAsistenciasTable_wrapper');
                    if (!w) return;
                    const api = new $.fn.dataTable.Api(settings);
                    const pageInfo = api.page.info();
                    const infoEl = w.querySelector('.dt-info');
                    if (infoEl) {
                        if (pageInfo.recordsTotal === 0) {
                            infoEl.textContent = 'Sin resultados';
                        } else {
                            infoEl.textContent = 'Pagina ' + (pageInfo.page + 1) + ' de ' + Math.max(pageInfo.pages, 1);
                        }
                    }
                    w.querySelectorAll('.dt-layout-row').forEach(r => {
                        r.style.cssText = 'float:none!important;clear:both!important;display:flex!important;flex-direction:column!important;align-items:center!important;width:100%!important;';
                    });
                    w.querySelectorAll('.dt-layout-cell').forEach(c => {
                        c.style.cssText = 'float:none!important;display:flex!important;justify-content:center!important;width:100%!important;';
                    });
                    const pg = w.querySelector('.dt-paging');
                    if (pg) pg.style.cssText = 'display:flex!important;justify-content:center!important;align-items:center!important;float:none!important;gap:0.2rem!important;flex-wrap:wrap!important;';
                }
            });
        },

        initDataTableF4() {
            const tableId = '#histF4Table';
            const self = this;
            
            this.histDataTableF4 = $(tableId).DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                ordering: true,
                searching: true,
                destroy: true,
                ajax: {
                    url: '/gestion-humana/asistencia/historial-datatable',
                    type: 'GET',
                    data: (d) => {
                        d.desde = this.histDesde;
                        d.hasta = this.histHasta;
                        d.nombre = this.histNombre;
                        d.tipo = 'f4';
                    },
                    beforeSend() { self.histCargando = true; },
                    complete()   { self.histCargando = false; },
                    error(xhr) {
                        self.histCargando = false;
                        console.error('DT f4 error:', xhr);
                    },
                },
                columns: [
                    { data: 'id', orderable: true, searchable: false },
                    { data: 'nombre_completo', orderable: true, searchable: true },
                    { data: 'fecha', orderable: true, searchable: false },
                    { data: 'fecha_hora', orderable: true, searchable: false },
                    { data: 'tipo', orderable: true, searchable: true },
                    { data: 'nota', orderable: true, searchable: true },
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.2.2/i18n/es-MX.json',
                    infoEmpty: 'Sin resultados',
                    paginate: { first: '«', last: '»', previous: '‹', next: '›' },
                },
                dom: 'tip',
                paging: true,
                info: true,
                drawCallback(settings) {
                    self.histCargando = false;
                    const w = document.getElementById('histF4Table_wrapper');
                    if (!w) return;
                    const api = new $.fn.dataTable.Api(settings);
                    const pageInfo = api.page.info();
                    const infoEl = w.querySelector('.dt-info');
                    if (infoEl) {
                        if (pageInfo.recordsTotal === 0) {
                            infoEl.textContent = 'Sin resultados';
                        } else {
                            infoEl.textContent = 'Pagina ' + (pageInfo.page + 1) + ' de ' + Math.max(pageInfo.pages, 1);
                        }
                    }
                    w.querySelectorAll('.dt-layout-row').forEach(r => {
                        r.style.cssText = 'float:none!important;clear:both!important;display:flex!important;flex-direction:column!important;align-items:center!important;width:100%!important;';
                    });
                    w.querySelectorAll('.dt-layout-cell').forEach(c => {
                        c.style.cssText = 'float:none!important;display:flex!important;justify-content:center!important;width:100%!important;';
                    });
                    const pg = w.querySelector('.dt-paging');
                    if (pg) pg.style.cssText = 'display:flex!important;justify-content:center!important;align-items:center!important;float:none!important;gap:0.2rem!important;flex-wrap:wrap!important;';
                }
            });
        },

        cambiarTabHistorial(nuevoTab) {
            if (this.histSubTab === nuevoTab) return;
            this.histSubTab = nuevoTab;
            this.cargarHistorial();
        },
    };
}

// Fin del módulo
