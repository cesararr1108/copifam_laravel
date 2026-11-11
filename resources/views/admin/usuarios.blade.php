<div x-data="usuariosModule()" x-init="init()" class="space-y-5">

    {{-- ── Título ─────────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-800">Creación de usuarios</h2>
        <p class="text-xs text-gray-400 mt-0.5">Gestione los usuarios del sistema</p>
    </div>

    {{-- ── Formulario ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">

        {{-- Cabecera --}}
        <div class="usu-form-header px-5 py-2.5 flex items-center gap-2" style="background-color:#00AEEF">
            <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
            <span class="text-sm font-semibold text-white"
                  x-text="editando ? 'Editar usuario: ' + form.usuario : 'Nuevo usuario'"></span>
        </div>

        <form @submit.prevent="guardar()" class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4">

                {{-- Usuario --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Usuario <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="form.usuario"
                           :disabled="editando"
                           :class="editando ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : ''"
                           @blur="verificarUsuario()"
                           placeholder="Usuario" maxlength="50"
                           class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Clave --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Clave <span class="text-red-400" x-show="!editando">*</span>
                        <span class="text-gray-400 font-normal text-[11px]" x-show="editando && !modoEditarClave"> — usa el lápiz para cambiarla</span>
                        <span class="text-green-500 font-normal text-[11px]" x-show="editando && modoEditarClave"> — escribe y presiona ✓</span>
                    </label>
                    <div class="relative">
                        <input :type="verClave ? 'text' : 'password'"
                               x-model="form.clave"
                               id="input-clave"
                               :disabled="editando && !modoEditarClave"
                               :class="(editando && !modoEditarClave) ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : ''"
                               placeholder="••••••" maxlength="100"
                               class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 pr-16 transition-all">

                        <div class="absolute right-1 top-1/2 -translate-y-1/2 flex items-center gap-0.5">
                            {{-- Ojo --}}
                            <button type="button" x-show="!editando || modoEditarClave"
                                    @click="verClave = !verClave"
                                    class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                                <svg x-show="!verClave" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg x-show="verClave" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                            {{-- Lápiz / Check --}}
                            <button type="button" x-show="editando"
                                    @click="toggleEditarClave()"
                                    :disabled="guardandoClave"
                                    :title="modoEditarClave ? 'Guardar nueva clave' : 'Cambiar clave'"
                                    class="p-1.5 rounded-md transition-colors"
                                    :class="modoEditarClave ? 'text-green-600 hover:bg-green-50' : 'hover:bg-pink-50'"
                                    :style="!modoEditarClave ? 'color:#ff1498' : ''">
                                <svg x-show="guardandoClave" class="usu-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <svg x-show="!guardandoClave && !modoEditarClave" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>
                                </svg>
                                <svg x-show="!guardandoClave && modoEditarClave" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Correo --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Correo electrónico</label>
                    <input type="email" x-model="form.correo"
                           placeholder="correo@empresa.com" maxlength="150"
                           class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Celular --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Celular</label>
                    <input type="tel" x-model="form.telefonos"
                           @input="form.telefonos = $event.target.value.replace(/\D/g,'').slice(0,10)"
                           placeholder="Ej: 3104238567" maxlength="10"
                           inputmode="numeric"
                           class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Nombres --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Nombres <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="form.nombres"
                           placeholder="Nombres completos" maxlength="100"
                           class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Apellidos --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Apellidos <span class="text-red-400">*</span>
                    </label>
                    <input type="text" x-model="form.apellidos"
                           placeholder="Apellidos completos" maxlength="100"
                           class="usu-input w-full text-sm border border-gray-200 rounded-lg px-3 py-2 transition-all">
                </div>

                {{-- Departamento — T_DPTO_SYS.DESCRIPCION --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Departamento <span class="text-red-400">*</span>
                    </label>
                    <select x-model="form.dpto_id"
                            class="usu-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                        <option value="">Seleccione...</option>
                        <template x-for="d in catalogs.departamentos" :key="d.id">
                            <option :value="d.id" x-text="d.nombre"></option>
                        </template>
                    </select>
                </div>

                {{-- Rol — T_ROLES.TITULO --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Rol <span class="text-red-400">*</span>
                    </label>
                    <select x-model="form.roles_id"
                            class="usu-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                        <option value="">Seleccione...</option>
                        <template x-for="r in catalogs.roles" :key="r.id">
                            <option :value="r.id" x-text="r.nombre"></option>
                        </template>
                    </select>
                </div>

                {{-- Bodega — T_BODEGAS.BODEGA + CIUDAD --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Bodega <span class="text-red-400">*</span>
                    </label>
                    <select x-model="form.bodega"
                            class="usu-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                        <option value="">Seleccione...</option>
                        <template x-for="b in catalogs.bodegas" :key="b.id">
                            <option :value="b.id" x-text="b.label"></option>
                        </template>
                    </select>
                </div>

                {{-- Estado --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">
                        Estado <span class="text-red-400">*</span>
                    </label>
                    <select x-model="form.estado"
                            class="usu-select w-full text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white transition-all">
                        <option value="A">Activo</option>
                        <option value="I">Inactivo</option>
                    </select>
                </div>

            </div>

            {{-- Botones --}}
            <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <button type="submit" :disabled="guardando"
                            class="usu-btn-primary inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold rounded-lg">
                        <svg x-show="guardando" class="usu-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="guardando ? 'Guardando...' : (editando ? 'Actualizar' : 'Crear usuario')"></span>
                    </button>
                    <button type="button" x-show="editando" @click="cancelarEdicion()"
                            class="usu-btn-secondary px-4 py-2 text-sm font-medium rounded-lg">
                        Cancelar
                    </button>
                </div>
                <button type="button" @click="if(_dtUsuarios) { document.getElementById('us-search-input').value=''; _dtUsuarios.search('').draw(); }"
                        class="usu-btn-secondary inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg">
                    Ver todos
                </button>
            </div>
        </form>
    </div>

    {{-- ── Spinner carga DataTable ────────────────────────────────────── --}}
    <div x-show="usCargandoDt" x-cloak class="usu-modal-backdrop" style="z-index:9999;">
        <div class="usu-loading-box"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="usu-loading-header">
                <svg class="usu-loading-icon" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="usu-loading-title">Cargando usuarios...</span>
            </div>
            <div class="usu-loading-body">
                <p class="usu-loading-sub">Por favor espera un momento</p>
            </div>
        </div>
    </div>

    {{-- ── Tabla de resultados ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">

        {{-- Cabecera con contador + búsqueda --}}
        <div class="px-5 py-3 border-b border-gray-50 flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text" id="us-search-input"
                       placeholder="Buscar por usuario, nombre o apellido..."
                       class="usu-search w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 transition-all">
            </div>

            {{-- Botón Buscar --}}
            <button type="button"
                    @click="if(_dtUsuarios){ _dtUsuarios.search(document.getElementById('us-search-input').value).draw(); }"
                    :disabled="usCargandoDt"
                    class="usu-btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg disabled:opacity-60 disabled:cursor-not-allowed">
                <svg x-show="!usCargandoDt" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <svg x-show="usCargandoDt" class="w-3.5 h-3.5 usu-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="usCargandoDt ? 'Buscando...' : 'Buscar'"></span>
            </button>

            <span class="text-xs text-gray-400 whitespace-nowrap"
                  x-text="usTotalRegistros + ' registro(s)'"></span>
        </div>

        {{-- Tabla DataTables --}}
        <div id="us-tabla-card" class="overflow-x-auto">
            <table id="tbl-usuarios" class="w-full text-xs" style="width:100%">
                <thead>
                    <tr>
                        <th class="usu-th">Usuario</th>
                        <th class="usu-th">Nombres</th>
                        <th class="usu-th">Apellidos</th>
                        <th class="usu-th">Correo</th>
                        <th class="usu-th">Celular</th>
                        <th class="usu-th">Departamento</th>
                        <th class="usu-th">Rol</th>
                        <th class="usu-th">Punto de venta</th>
                        <th class="usu-th">Estado</th>
                        <th class="usu-th"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div>
