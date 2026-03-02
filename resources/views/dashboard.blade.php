<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard — Copifam</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#ff1498' },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    {{-- Permisos del usuario inyectados desde Laravel --}}
    <script>const USER_PERMISOS = @json($permisos ?? []);</script>
    {{-- dashboard.js debe cargarse ANTES de Alpine para que encuentre dashboard() --}}
    <script src="{{ asset('js/dashboard.js') }}"></script>
    <script src="{{ asset('js/copifam-modal.js') }}"></script>
    {{-- Alpine SIEMPRE al último con defer --}}
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="font-sans bg-gray-50 text-gray-700" x-data="dashboard()" x-init="init()">

<div class="flex h-screen overflow-hidden">

    {{-- ===== SIDEBAR ===== --}}
    <aside class="flex flex-col bg-white border-r border-gray-100 transition-all duration-300 z-30"
           :class="sidebarOpen ? 'w-64' : 'w-16'">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-100 overflow-hidden">
            <img src="{{ asset('storage/img/logo_copifam1.png') }}"
                 alt="Logo Copifam"
                 class="flex-shrink-0 object-contain transition-all duration-300"
                 :class="sidebarOpen ? 'h-17 w-auto' : 'h-8 w-8'">
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
            <template x-for="mod in modules" :key="mod.id">
                <div>
                    {{-- Módulo principal --}}
                    <button @click="toggle(mod.id)"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left text-sm font-medium transition-all duration-200 group"
                            :class="activeModule === mod.id
                                ? 'text-white'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
                            :style="activeModule === mod.id ? 'background-color:#ff1498' : ''">

                        {{-- Icono --}}
                        <span class="flex-shrink-0 w-5 h-5" x-html="getIcon(mod.icon)"></span>

                        {{-- Nombre --}}
                        <span class="flex-1 whitespace-nowrap overflow-hidden transition-all duration-300 text-sm"
                              :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'"
                              x-text="mod.name"></span>

                        {{-- Chevron --}}
                        <svg x-show="sidebarOpen" class="w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200"
                             :class="activeModule === mod.id ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    {{-- Submódulos --}}
                    <div x-show="activeModule === mod.id && sidebarOpen"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="mt-0.5 ml-4 pl-3 border-l-2 space-y-0.5 pb-1"
                         style="border-color:#ff149840">
                        <template x-for="sub in mod.subs" :key="sub.code">
                            <a href="#"
                               @click.prevent="selectSub(mod.id, sub.code)"
                               class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm transition-all duration-150"
                               :class="activeSub === sub.code
                                   ? 'font-semibold'
                                   : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                               :style="activeSub === sub.code ? 'color:#ff1498' : ''">
                                <span class="text-gray-300 font-mono text-xs flex-shrink-0" x-text="sub.code"></span>
                                <span x-text="sub.name"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
        </nav>

        {{-- Footer sidebar --}}
        <div class="border-t border-gray-100 p-3">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-all duration-200 text-xs">
                <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarOpen ? '' : 'rotate-180'"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Contraer</span>
            </button>
        </div>
    </aside>

    {{-- ===== CONTENIDO PRINCIPAL ===== --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- Header --}}
        <header class="bg-white border-b border-gray-100 px-6 py-3 flex items-center justify-between flex-shrink-0">
            <div>
                <h1 class="text-sm font-semibold text-gray-800" x-text="currentTitle"></h1>
                <p class="text-xs text-gray-400 mt-0.5" x-text="currentBreadcrumb"></p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Notificaciones --}}
                <button class="relative p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full" style="background-color:#ff1498"></span>
                </button>

                {{-- Usuario --}}
                <div class="flex items-center gap-2.5 pl-3 border-l border-gray-100">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                         style="background-color:#ff1498">
                        {{ strtoupper(substr(Session::get('ses_CopNombres', 'U'), 0, 1)) }}
                    </div>
                    <div class="hidden sm:block">
                        <p class="text-xs font-medium text-gray-700 leading-none">{{ Session::get('ses_CopNombres', '') }} {{ Session::get('ses_CopApellidos', '') }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ Session::get('ses_CopLogin', '') }}</p>
                    </div>
                    <form id="formLogout" method="POST" action="{{ route('logout') }}" class="ml-1">
                        @csrf
                        <button type="button" title="Cerrar sesión"
                                onclick="confirmarCerrarSesion()"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Main --}}
        <main class="flex-1 overflow-y-auto p-6">

            {{-- Bienvenida (estado inicial) --}}
            <div x-show="!activeSub" class="h-full flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4" style="background-color:#ff149815">
                    <svg class="w-8 h-8" style="color:#ff1498" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-700 mb-1">
                    Bienvenido, {{ Session::get('ses_CopNombres', 'Usuario') }}
                </h2>
                <p class="text-sm text-gray-400 max-w-sm">
                    Seleccione un módulo del menú lateral para comenzar a trabajar.
                </p>

                {{-- Cards resumen módulos --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mt-8 w-full max-w-3xl">
                    <template x-for="mod in modules" :key="mod.id">
                        <button @click="toggle(mod.id); sidebarOpen = true"
                                class="flex flex-col items-center gap-2 p-4 bg-white rounded-xl border border-gray-100 hover:border-pink-200 hover:shadow-sm transition-all duration-200 group">
                            <span class="text-2xl w-8 h-8" x-html="getIcon(mod.icon)"></span>
                            <span class="text-xs font-medium text-gray-600 group-hover:text-gray-800 text-center leading-tight" x-text="mod.name"></span>
                            <span class="text-[10px] text-gray-300" x-text="mod.subs.length + ' módulos'"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Contenido del submódulo seleccionado --}}
            <div x-show="activeSub"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-50">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background-color:#ff149815">
                            <span class="w-5 h-5" x-html="getIcon(currentModuleIcon)"></span>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800" x-text="currentSubName"></h2>
                            <p class="text-xs text-gray-400" x-text="'Código: ' + activeSub"></p>
                        </div>
                    </div>
                    <div class="flex flex-col items-center justify-center py-12 text-center text-gray-300">
                        <svg class="w-10 h-10 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        <p class="text-sm">Módulo en construcción</p>
                        <p class="text-xs mt-1" x-text="currentSubName + ' estará disponible próximamente.'"></p>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

{{-- Confirmación cerrar sesión --}}
<script>
    function confirmarCerrarSesion() {
        CopifamModal.fire({
            title: 'Cerrar sesión',
            text: '¿Estás seguro de que deseas cerrar tu sesión? Tendrás que volver a iniciar sesión para acceder al sistema.',
            icon: 'warning',
            confirmText: 'Cerrar sesión',
            cancelText: 'Cancelar',
            confirmColor: '#EF4444',
            onConfirm: () => document.getElementById('formLogout').submit()
        });
    }
</script>

{{-- SVG Icons registry (ocultos) --}}
<div style="display:none">
    <svg id="icon-inventarios" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
    <svg id="icon-ventas" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
    <svg id="icon-administracion" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/></svg>
    <svg id="icon-reportes" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z"/></svg>
    <svg id="icon-tesoreria" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    <svg id="icon-contabilidad" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605"/></svg>
    <svg id="icon-mercadeo" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3c1.172 0 2.307.15 3.386.43A23.845 23.845 0 0 1 21 6.025m-10.66.635a23.848 23.848 0 0 1 8.835-2.535"/></svg>
    <svg id="icon-cartera" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
    <svg id="icon-gestion" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
    <svg id="icon-soporte" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"/></svg>
</div>

</body>
</html>
