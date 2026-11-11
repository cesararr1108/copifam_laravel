// @ts-nocheck
/**
 * admin/usuarios.js — Copifam
 * T_DPTO_SYS (ID, DESCRIPCION) · T_ROLES (ID, TITULO) · T_BODEGAS (ID, BODEGA int, CIUDAD)
 * T_USUARIOS.BODEGA = int → ID de T_BODEGAS
 * DataTables server-side
 */
function usuariosModule() {
    return {
        guardando:        false,
        editando:         false,
        guardandoClave:   false,
        modoEditarClave:  false,
        claveOriginal:    '',
        verClave:         false,

        /* DataTables */
        _dtUsuarios:      null,
        usTotalRegistros: 0,
        usCargandoDt:     false,

        form: {
            usuario:   '',
            clave:     '',
            nombres:   '',
            apellidos: '',
            correo:    '',
            telefonos: '',
            dpto_id:   '',
            roles_id:  '',
            bodega:    '',
            estado:    'A',
        },

        catalogs: {
            departamentos: [],
            roles:         [],
            bodegas:       [],
        },

        get csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },

        get jsonHeaders() {
            return {
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        baseUrl: '/administracion/usuarios',

        async init() {
            await this.cargarCatalogs();
            this.$nextTick(() => setTimeout(() => this._initDtUsuarios(), 50));
        },

        /* ── Catalogos ─────────────────────────────────────────── */
        async cargarCatalogs() {
            try {
                const res  = await fetch(this.baseUrl + '/catalogs', { headers: this.jsonHeaders });
                const data = await res.json();
                if (data.ok) {
                    this.catalogs.departamentos = data.departamentos;
                    this.catalogs.roles         = data.roles;
                    this.catalogs.bodegas       = data.bodegas;
                } else {
                    console.warn('Catalogos:', data.message);
                }
            } catch (e) {
                console.error('Error catalogos:', e);
            }
        },

        /* ── DataTable ─────────────────────────────────────────── */
        _initDtUsuarios() {
            if (this._dtUsuarios) {
                if ($.fn.DataTable.isDataTable('#tbl-usuarios')) {
                    $('#tbl-usuarios').DataTable().destroy();
                }
                this._dtUsuarios = null;
            }

            const self = this;

            this._dtUsuarios = $('#tbl-usuarios').DataTable({
                processing:   false,
                serverSide:   true,
                pageLength:   25,
                lengthChange: false,
                language: {
                    search:      '',
                    info:        'Pagina _PAGE_ de _PAGES_',
                    infoEmpty:   'Sin resultados',
                    paginate:    { previous: '‹', next: '›' },
                    emptyTable:  'No se encontraron usuarios.',
                    zeroRecords: 'No se encontraron usuarios con ese criterio.',
                },
                dom: 'tip',
                ajax: {
                    url:  self.baseUrl + '/search',
                    type: 'GET',
                    headers: {
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'application/json',
                    },
                    beforeSend() { self.usCargandoDt = true; },
                    complete()   { self.usCargandoDt = false; },
                    error(xhr) {
                        self.usCargandoDt = false;
                        console.error('DT usuarios error:', xhr);
                    },
                },
                columns: [
                    { data: 'USUARIO',    name: 'USUARIO',    className: 'px-4 py-2.5 text-gray-700' },
                    { data: 'NOMBRES',    name: 'NOMBRES',    className: 'px-4 py-2.5 text-gray-600' },
                    { data: 'APELLIDOS',  name: 'APELLIDOS',  className: 'px-4 py-2.5 text-gray-600' },
                    { data: 'CORREO',     name: 'CORREO',     className: 'px-4 py-2.5 text-gray-500',
                      render: v => v ?? '-' },
                    { data: 'TELEFONOS',  name: 'TELEFONOS',  className: 'px-4 py-2.5 text-gray-500',
                      render: v => v ?? '-' },
                    { data: 'NOM_DPTO',   name: 'NOM_DPTO',   className: 'px-4 py-2.5 text-gray-500',
                      render: v => v ?? '-' },
                    { data: 'NOM_ROL',    name: 'NOM_ROL',    className: 'px-4 py-2.5 text-gray-500',
                      render: v => v ?? '-' },
                    { data: 'NOM_BODEGA', name: 'NOM_BODEGA', className: 'px-4 py-2.5 text-gray-500',
                      render: v => v ?? '-' },
                    {
                        data: 'ESTADO', name: 'ESTADO', className: 'px-4 py-2.5',
                        render(v) {
                            const cls = v === 'A' ? 'usu-badge-activo' : 'usu-badge-inactivo';
                            const lbl = v === 'A' ? 'Activo' : 'Inactivo';
                            return '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold ' + cls + '">' + lbl + '</span>';
                        },
                    },
                    {
                        data: null, orderable: false, className: 'px-4 py-2.5 text-right',
                        render(data, type, row) {
                            return '<button type="button" onclick="window._dtVerUsuario(\'' + row.USUARIO + '\')" class="usu-btn-edit inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium rounded-md"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg> Editar</button>';
                        },
                    },
                ],
                drawCallback(settings) {
                    self.usTotalRegistros = settings.json?.recordsFiltered ?? 0;
                    const w = document.getElementById('tbl-usuarios_wrapper');
                    if (!w) return;
                    const api      = new $.fn.dataTable.Api(settings);
                    const pageInfo = api.page.info();
                    const infoEl   = w.querySelector('.dt-info');
                    if (infoEl) infoEl.textContent = 'Pagina ' + (pageInfo.page + 1) + ' de ' + (pageInfo.pages || 1);
                    w.querySelectorAll('.dt-layout-row').forEach(r => {
                        r.style.cssText = 'float:none!important;clear:both!important;display:flex!important;flex-direction:column!important;align-items:center!important;width:100%!important;';
                    });
                    w.querySelectorAll('.dt-layout-cell').forEach(c => {
                        c.style.cssText = 'float:none!important;display:flex!important;justify-content:center!important;width:100%!important;';
                    });
                    const pg = w.querySelector('.dt-paging');
                    if (pg) pg.style.cssText = 'display:flex!important;justify-content:center!important;align-items:center!important;float:none!important;gap:0.2rem!important;flex-wrap:wrap!important;';
                },
                initComplete() {
                    const card = document.getElementById('us-tabla-card');
                    const wrap = document.getElementById('tbl-usuarios_wrapper');
                    if (card && wrap) card.appendChild(wrap);
                    /* Búsqueda solo al pulsar Enter o el botón Buscar */
                    const searchInput = document.getElementById('us-search-input');
                    if (searchInput && self._dtUsuarios) {
                        searchInput.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                self._dtUsuarios.search(this.value).draw();
                            }
                        });
                    }
                },
            });

            window._dtVerUsuario = async (usuario) => {
                try {
                    const res  = await fetch(this.baseUrl + '/' + encodeURIComponent(usuario), { headers: this.jsonHeaders });
                    const data = await res.json();
                    if (data.ok && data.data) this._cargarDatos(data.data);
                } catch (e) { console.error('_dtVerUsuario:', e); }
            };
        },

        /* ── Verificar usuario al perder foco ── */
        async verificarUsuario() {
            const usr = (this.form.usuario ?? '').trim().toUpperCase();
            if (!usr || this.editando) return;
            try {
                const res  = await fetch(this.baseUrl + '/' + encodeURIComponent(usr), { headers: this.jsonHeaders });
                if (!res.ok) return;
                const data = await res.json();
                if (data.ok && data.data) this._cargarDatos(data.data);
            } catch (e) { console.error('verificarUsuario:', e); }
        },

        /* ── Cargar datos en formulario ── */
        _cargarDatos(u) {
            this.editando        = true;
            this.verClave        = false;
            this.modoEditarClave = false;
            this.form.usuario    = u.USUARIO;
            this.form.clave      = '------';
            this.claveOriginal   = '------';
            this.form.nombres    = u.NOMBRES    ?? '';
            this.form.apellidos  = u.APELLIDOS  ?? '';
            this.form.correo     = u.CORREO     ?? '';
            this.form.telefonos  = u.TELEFONOS  ?? '';
            this.form.dpto_id    = u.DPTO_ID    ? String(u.DPTO_ID)   : '';
            this.form.roles_id   = u.ROLES_ID   ? String(u.ROLES_ID)  : '';
            this.form.bodega     = u.BODEGA_ID  ? String(u.BODEGA_ID) : (u.BODEGA ? String(u.BODEGA) : '');
            this.form.estado     = u.ESTADO     ?? 'A';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        cargarEdicion(u) { this._cargarDatos(u); },

        /* ── Toggle editar/guardar clave ── */
        async toggleEditarClave() {
            if (!this.modoEditarClave) {
                this.claveOriginal   = this.form.clave;
                this.form.clave      = '';
                this.modoEditarClave = true;
                this.$nextTick(() => document.getElementById('input-clave')?.focus());
                return;
            }
            const nuevaClave = (this.form.clave ?? '').trim();
            if (!nuevaClave) {
                this.form.clave      = this.claveOriginal;
                this.modoEditarClave = false;
                return;
            }
            this.guardandoClave = true;
            try {
                const body = new URLSearchParams({ _token: this.csrf, _method: 'PUT', solo_clave: '1', clave: nuevaClave });
                const res  = await fetch(this.baseUrl + '/' + encodeURIComponent(this.form.usuario), { method: 'POST', headers: this.jsonHeaders, body });
                const data = await res.json();
                if (data.ok) {
                    CopifamModal.fire({ title: 'Clave actualizada', text: data.message, icon: 'success', confirmText: 'Aceptar' });
                    this.form.clave = this.claveOriginal = '------';
                } else {
                    CopifamModal.fire({ title: 'Error', text: data.message ?? 'No se pudo cambiar la clave.', icon: 'error', confirmText: 'Entendido' });
                    this.form.clave = this.claveOriginal;
                }
            } catch {
                CopifamModal.fire({ title: 'Sin conexion', text: 'No se pudo contactar al servidor.', icon: 'error', confirmText: 'Entendido' });
                this.form.clave = this.claveOriginal;
            } finally {
                this.guardandoClave  = false;
                this.modoEditarClave = false;
            }
        },

        /* ── Guardar ── */
        async guardar() {
            if (this.guardando) return;
            if (!(this.form.usuario   ?? '').trim()) return CopifamModal.fire({ title: 'Campo requerido', text: 'Debe digitar el usuario.',    icon: 'warning', confirmText: 'OK' });
            if (!this.editando && !(this.form.clave ?? '').trim()) return CopifamModal.fire({ title: 'Campo requerido', text: 'Debe digitar la clave.',     icon: 'warning', confirmText: 'OK' });
            if (!(this.form.nombres   ?? '').trim()) return CopifamModal.fire({ title: 'Campo requerido', text: 'Debe digitar los nombres.',   icon: 'warning', confirmText: 'OK' });
            if (!(this.form.apellidos ?? '').trim()) return CopifamModal.fire({ title: 'Campo requerido', text: 'Debe digitar los apellidos.', icon: 'warning', confirmText: 'OK' });
            this.guardando = true;
            const url  = this.editando ? this.baseUrl + '/' + encodeURIComponent(this.form.usuario) : this.baseUrl;
            const body = new URLSearchParams({
                _token:    this.csrf,
                usuario:   this.form.usuario,
                nombres:   this.form.nombres,
                apellidos: this.form.apellidos,
                correo:    this.form.correo    ?? '',
                telefonos: this.form.telefonos ?? '',
                dpto_id:   this.form.dpto_id,
                roles_id:  this.form.roles_id,
                bodega:    this.form.bodega,
                estado:    this.form.estado,
            });
            if (!this.editando) body.append('clave', this.form.clave);
            else                body.append('_method', 'PUT');
            try {
                const res  = await fetch(url, { method: 'POST', headers: this.jsonHeaders, body });
                const data = await res.json();
                if (data.ok) {
                    CopifamModal.fire({ title: 'Listo', text: data.message, icon: 'success', confirmText: 'Aceptar' });
                    this.resetForm();
                    if (this._dtUsuarios) this._dtUsuarios.ajax.reload(null, false);
                } else {
                    const msg = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message ?? 'Error al guardar.');
                    CopifamModal.fire({ title: 'Error', text: msg, icon: 'error', confirmText: 'Entendido' });
                }
            } catch {
                CopifamModal.fire({ title: 'Sin conexion', text: 'No se pudo contactar al servidor.', icon: 'error', confirmText: 'Entendido' });
            } finally {
                this.guardando = false;
            }
        },

        cancelarEdicion() { this.resetForm(); },

        resetForm() {
            this.editando = this.verClave = this.modoEditarClave = false;
            this.claveOriginal = '';
            this.form = { usuario: '', clave: '', nombres: '', apellidos: '', correo: '', telefonos: '', dpto_id: '', roles_id: '', bodega: '', estado: 'A' };
        },
    };
}