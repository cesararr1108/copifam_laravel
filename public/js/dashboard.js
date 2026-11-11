function dashboard() {
    return {
        sidebarOpen: true,
        activeModule: null,
        activeSub: null,
        moduloCargando: false,
        currentTitle: 'Dashboard',
        currentBreadcrumb: 'Inicio',
        currentSubName: '',
        currentModuleIcon: '',

        modules: [
            {
                id: '01', code:'01', name: 'Inventarios', icon: 'inventarios',
                subs: [
                    { code: '0101', name: 'Ingresos M' },
                    { code: '0103', name: 'Traslados' },
                    { code: '0104', name: 'Sug. Compras' },
                    { code: '0107', name: 'Data Maestra' },
                    { code: '0108', name: 'Toma de Inventarios' },
                    { code: '0112', name: 'Act. de Precios' },
                    { code: '0113', name: 'Sugerido PDV' },
                    { code: '0114', name: 'Entradas Inventarios' },
                    { code: '0115', name: 'Salidas Inventarios' },
                    { code: '0116', name: 'Inventario' },
                    { code: '0117', name: 'PQRS' },
                    { code: '0118', name: 'Ingresos Preliminares' },
                ]
            },
            {
                id: '02', code:'02', name: 'Ventas', icon: 'ventas',
                subs: [
                    { code: '0201', name: 'Ventas' },
                    { code: '0202', name: 'Dev. en Ventas' },
                    { code: '0203', name: 'A/C de Cajas' },
                    { code: '0204', name: 'Despachos' },
                    { code: '0205', name: 'Dispensación' },
                    { code: '0206', name: 'Cuadre CajasV' },
                    { code: '0208', name: 'Pedidos' },
                    { code: '0209', name: 'Cuadre CajaP' },
                ]
            },
            {
                id: '03', code:'03', name: 'Administración', icon: 'administracion',
                subs: [
                    { code: '0301', name: 'Usuarios' },
                    { code: '0302', name: 'Roles' },
                    { code: '0303', name: 'Permisos' },
                    { code: '0304', name: 'Asignar Permisos' },
                    { code: '0305', name: 'Bodegas' },
                    { code: '0306', name: 'Módulos Permisos' },
                    { code: '0307', name: 'Reubicar Usuarios' },
                ]
            },
            {
                id: '04', code:'04', name: 'Reportes', icon: 'reportes',
                subs: [
                    { code: '0401', name: 'Seg. Pedidos' },
                    { code: '0405', name: 'Inv. Actual' },
                    { code: '0408', name: 'Ventas Mensuales' },
                    { code: '0413', name: 'Contabilización' },
                    { code: '0414', name: 'Ingresos Ventas' },
                    { code: '0415', name: 'Listados de Precios' },

                    { code: '0418', name: 'Reportes ADG' },
                    { code: '0420', name: 'Kardex' },
                    { code: '0421', name: 'Arqueo de Caja' },
                    { code: '0422', name: 'Inventario PDV' },
                    { code: '0423', name: 'Seguimiento PQRS' },
                    { code: '0424', name: 'Ordenes de Compra' },
                    { code: '0425', name: 'Ajuste de Inventario' },
                    { code: '0426', name: 'Bensac' },
                    { code: '0427', name: 'Traslados' },
                    { code: '0428', name: 'Flotantes y Sobrantes' },
                ]
            },
            {
                id: '05', code:'05', name: 'Tesorería', icon: 'tesoreria',
                subs: [
                    { code: '0501', name: 'Gastos' },
                ]
            },
            {
                id: '06', code:'06', name: 'Contabilidad', icon: 'contabilidad',
                subs: [
                    { code: '0601', name: 'Fact. electrónica' },
                ]
            },
            {
                id: '07', code:'07', name: 'Mercadeo', icon: 'mercadeo',
                subs: [
                    { code: '0701', name: 'Teleferias' },
                    { code: '0702', name: 'Shopping' },
                ]
            },
            {
                id: '08', code:'08', name: 'Cartera', icon: 'cartera',
                subs: [
                    { code: '0801', name: 'Validación Disp.' },
                    { code: '0802', name: 'Info Fact. Disp.' },
                    { code: '0803', name: 'Reimp. Remisiones' },
                    { code: '0806', name: 'Comp. Diario de Venta' },
                    { code: '0807', name: 'Disp. Facturadas' },
                    { code: '0808', name: 'Disp. Pendientes' },
                ]
            },
            {
                id: '11', code: '11', name: 'Gestión Humana', icon: 'gestion',
                subs: [
                    { code: '1101', name: 'Control Asistencia' },
                    { code: '1102', name: 'Control E/S' },
                ]
            },
            {
                id: '12', code: '12', name: 'Soporte', icon: 'soporte',
                subs: [
                    { code: '1201', name: 'Formulario de Soporte' },
                ]
            },
        ],

        init() {
            // Filtrar el menú según los permisos del usuario en sesión (compatibilidad números/strings)
            const perms = (USER_PERMISOS || []).map(p => String(p));
            this.modules = this.modules
                .map(mod => ({
                    ...mod,
                    subs: mod.subs.filter(sub => perms.includes(String(sub.code)))
                }))
                .filter(mod => mod.subs.length > 0);

            // Ajustar estado de la barra lateral dependiendo del tamaño de pantalla
            this.checkWidth();
            window.addEventListener('resize', () => {
                this.checkWidth();
            });
        },

        checkWidth() {
            if (window.innerWidth < 1024) {
                this.sidebarOpen = false;
            } else {
                this.sidebarOpen = true;
            }
        },

        getIcon(name) {
            const el = document.getElementById('icon-' + name);
            if (!el) return '';
            const clone = el.cloneNode(true);
            clone.removeAttribute('id');
            clone.setAttribute('style', '');
            clone.classList.add('w-full', 'h-full');
            return clone.outerHTML;
        },

        toggle(moduleId) {
            // Si la sidebar está contraída, expandirla primero
            if (!this.sidebarOpen) {
                this.sidebarOpen = true;
                this.activeModule = moduleId;
                // Abrir automáticamente el primer submódulo disponible
                const mod = this.modules.find(m => m.id === moduleId);
                if (mod && mod.subs.length > 0) {
                    const firstSub = mod.subs[0];
                    setTimeout(() => {
                        this.selectSub(moduleId, firstSub.code);
                    }, 100);
                }
                return;
            }
            
            // Si ya está expandida, comportamiento normal (toggle del módulo)
            this.activeModule = this.activeModule === moduleId ? null : moduleId;
            if (this.activeModule !== moduleId) {
                this.activeSub = null;
                this.currentTitle = 'Dashboard';
                this.currentBreadcrumb = 'Inicio';
            }
        },

        selectSub(moduleId, subCode) {
            this.moduloCargando = true;
            this.activeSub = subCode;
            const mod = this.modules.find(m => m.id === moduleId);
            const sub = mod.subs.find(s => s.code === subCode);
            this.currentModuleIcon = mod.icon;
            this.currentSubName = sub.name;
            this.currentTitle = sub.name;
            this.currentBreadcrumb = mod.name + ' / ' + sub.name;
            setTimeout(() => { this.moduloCargando = false; }, 600);
        }
    };
}
