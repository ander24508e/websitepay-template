<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>@yield('title', 'Admin Panel')</title>
    @vite(['resources/css/app.css', 'resources/scss/admin/layout.scss', 'resources/js/app.js'])
    @stack('styles')
    @livewireStyles
</head>

<body>
    <button type="button" class="menu-toggle-mobile" id="menuToggleMobile" aria-label="Abrir menu">
        <x-heroicon-o-bars-3 class="w-6 h-6" />
    </button>

    <!-- Tooltip flotante para feedback -->
    <div class="sidebar-tooltip" id="sidebarTooltip"></div>

    <!-- Overlay para sidebar mÃ³vil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar (Colapsable al hacer clic) -->
    <aside class="sidebar flex flex-col h-full" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-panel-box">
                <div class="flex items-center gap-2">
                    <span class="panel-icon-wrap"><x-heroicon-o-building-library class="w-5 h-5" /></span>
                    <span>Admin Panel</span>
                </div>
                <p>{{ auth()->user()?->name ?? 'Administrador' }}</p>
            </div>
        </div>

        <nav class="sidebar-nav flex-1">
            <p class="px-4 pt-1 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Navegacion
            </p>
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                <span><x-heroicon-o-globe-alt class="w-5 h-5" /></span>
                <span>Página web</span>
            </a>
            @can('dashboard.view')
                <a href="{{ route('admin.dashboard') }}"
                    class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span><x-heroicon-o-chart-bar class="w-5 h-5" /></span>
                    <span>Dashboard</span>
                </a>
            @endcan

            @canany(['banners.view', 'banners.create'])
                <a href="{{ auth()->user()->can('banners.view') ? route('admin.banners.index') : route('admin.banners.create') }}"
                    class="{{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-rectangle-group class="w-5 h-5" /></span>
                    <span>Banners</span>
                </a>
            @endcanany

            <p class="px-4 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Comercial</p>
            @canany(['sales.view', 'sales.create'])
                <a href="{{ auth()->user()->can('sales.view') ? route('admin.ventas.index') : route('admin.ventas.create') }}"
                    class="{{ request()->routeIs('admin.ventas.*') || request()->routeIs('admin.transactions.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-banknotes class="w-5 h-5" /></span>
                    <span>Ventas</span>
                </a>
            @endcanany
            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}"
                    class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-shopping-bag class="w-5 h-5" /></span>
                    <span>Órdenes</span>
                </a>
            @endcan
            <p class="px-3 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Activos
                Operacionales</p>

            @can('catalog.view')
                <a href="{{ route('admin.catalog.index') }}"
                    class="{{ request()->routeIs('admin.catalog.*') || request()->routeIs('admin.catalog-types.*') || request()->routeIs('admin.catalog-categories.*') || request()->routeIs('admin.catalog-items.*') || request()->routeIs('admin.catalog-variants.*') || request()->routeIs('admin.catalog-service-prices.*') || request()->routeIs('admin.catalog.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-squares-2x2 class="w-5 h-5" /></span>
                    <span>Catálogo</span>
                </a>
            @endcan
            @can('inventory.view')
                <a href="{{ route('admin.inventario.index') }}"
                    class="{{ request()->routeIs('admin.inventario.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-archive-box class="w-5 h-5" /></span>
                    <span>Inventario</span>
                </a>
            @endcan
            @can('vehicles.view')
                <a href="{{ route('admin.vehiculos.index') }}"
                    class="{{ request()->routeIs('admin.vehiculos.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-truck class="w-5 h-5" /></span>
                    <span>Vehiculos</span>
                </a>
            @endcan

            <p class="px-4 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">
                Administracion</p>

            @can('clients.view')
                <a href="{{ route('admin.clientes.index') }}"
                    class="{{ request()->routeIs('admin.clientes.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-users class="w-5 h-5" /></span>
                    <span>Clientes</span>
                </a>
            @endcan

            @canany(['users.view', 'users.create_employees', 'users.create_managers'])
                <a href="{{ auth()->user()->can('users.view') ? route('admin.usuarios.index') : route('admin.usuarios.create') }}"
                    class="{{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-user-group class="w-5 h-5" /></span>
                    <span>Usuarios</span>
                </a>
            @endcanany

        </nav>

        <div class="sidebar-footer mt-auto">
            <details class="sidebar-settings" {{ request()->routeIs('profile.edit') ? 'open' : '' }}>
                <summary class="sidebar-settings-summary">
                    <span><x-heroicon-o-cog-6-tooth class="w-5 h-5" /></span>
                    <span>Configuracion</span>
                </summary>
                <div class="sidebar-settings-links">
                    @can('company.view')
                        <a href="{{ route('admin.empresa.edit') }}"
                            class="{{ request()->routeIs('admin.empresa.*') ? 'active' : '' }}">
                            {{-- <span><x-heroicon-o-building-office class="w-5 h-5" /></span> --}}
                            <span>Mi Empresa</span>
                        </a>
                    @endcan
                    <a href="{{ route('profile.edit', ['tab' => 'account']) }}"
                        class="{{ request()->routeIs('profile.edit') && request('tab', 'account') === 'account' ? 'active' : '' }}">Cuenta</a>
                    <a href="{{ route('profile.edit', ['tab' => 'security']) }}"
                        class="{{ request()->routeIs('profile.edit') && request('tab') === 'security' ? 'active' : '' }}">Seguridad</a>
                </div>
            </details>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center gap-3 text-red-400">
                    <span><x-heroicon-o-arrow-right-start-on-rectangle class="w-5 h-5" /></span>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>
    <main class="main-content" id="mainContent">
        {{-- Toast Notifications - Aparecen y desaparecen automÃ¡ticamente --}}
        @include('partials.admin-notifications')

        @yield('content')
    </main>


    <!-- Overlay difuminado para bottom sheet -->
    <div class="bottom-sheet-overlay" id="bottomSheetOverlay"></div>

    <!-- BOTTOM SHEET (MenÃº deslizable desde abajo) -->
    <div class="bottom-sheet" id="bottomSheet">
        <div class="bottom-sheet-handle">
            <span></span>
        </div>

        <div class="sidebar-header">
            <div class="sidebar-panel-box">
                <div class="flex items-center gap-2">
                    <span class="panel-icon-wrap"><x-heroicon-o-building-library class="w-5 h-5" /></span>
                    <span>Admin Panel</span>
                </div>
                <p>{{ auth()->user()?->name ?? 'Administrador' }}</p>
            </div>
        </div>

        <nav class="bottom-sheet-nav">
            <p class="px-3 pt-1 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Navegacion
            </p>
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                <span><x-heroicon-o-globe-alt class="w-5 h-5" /></span>
                <span>Pagina web</span>
            </a>

            @can('dashboard.view')
                <a href="{{ route('admin.dashboard') }}"
                    class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span><x-heroicon-o-chart-bar class="w-5 h-5" /></span>
                    <span>Dashboard</span>
                </a>
            @endcan

            @canany(['banners.view', 'banners.create'])
                <a href="{{ auth()->user()->can('banners.view') ? route('admin.banners.index') : route('admin.banners.create') }}"
                    class="{{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-rectangle-group class="w-5 h-5" /></span>
                    <span>Banners</span>
                </a>
            @endcanany

            <p class="px-3 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Comercial</p>
            @canany(['sales.view', 'sales.create'])
                <a href="{{ auth()->user()->can('sales.view') ? route('admin.ventas.index') : route('admin.ventas.create') }}"
                    class="{{ request()->routeIs('admin.ventas.*') || request()->routeIs('admin.transactions.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-banknotes class="w-5 h-5" /></span>
                    <span>Ventas</span>
                </a>
            @endcanany

            @can('orders.view')
                <a href="{{ route('admin.orders.index') }}"
                    class="{{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-shopping-bag class="w-5 h-5" /></span>
                    <span>Órdenes</span>
                </a>
            @endcan

            <p class="px-3 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">Activos
                Operacionales</p>

            @can('catalog.view')
                <a href="{{ route('admin.catalog.index') }}"
                    class="{{ request()->routeIs('admin.catalog.*') || request()->routeIs('admin.catalog-types.*') || request()->routeIs('admin.catalog-categories.*') || request()->routeIs('admin.catalog-items.*') || request()->routeIs('admin.catalog-variants.*') || request()->routeIs('admin.catalog-service-prices.*') || request()->routeIs('admin.catalog.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-squares-2x2 class="w-5 h-5" /></span>
                    <span>Catálogo</span>
                </a>
            @endcan

            @can('inventory.view')
                <a href="{{ route('admin.inventario.index') }}"
                    class="{{ request()->routeIs('admin.inventario.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-archive-box class="w-5 h-5" /></span>
                    <span>Inventario</span>
                </a>
            @endcan
            @can('vehicles.view')
                <a href="{{ route('admin.vehiculos.index') }}"
                    class="{{ request()->routeIs('admin.vehiculos.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-truck class="w-5 h-5" /></span>
                    <span>Vehiculos</span>
                </a>
            @endcan

            <p class="px-3 pt-4 pb-2 text-[11px] uppercase tracking-wider text-slate-400/80 font-semibold">
                Administracion</p>
            @can('clients.view')
                <a href="{{ route('admin.clientes.index') }}"
                    class="{{ request()->routeIs('admin.clientes.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-users class="w-5 h-5" /></span>
                    <span>Clientes</span>
                </a>
            @endcan

            @canany(['users.view', 'users.create_employees', 'users.create_managers'])
                <a href="{{ auth()->user()->can('users.view') ? route('admin.usuarios.index') : route('admin.usuarios.create') }}"
                    class="{{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
                    <span><x-heroicon-o-user-group class="w-5 h-5" /></span>
                    <span>Usuarios</span>
                </a>
            @endcanany
        </nav>

        <div class="bottom-sheet-footer">
            <details class="sidebar-settings" {{ request()->routeIs('profile.edit') ? 'open' : '' }}>
                <summary class="sidebar-settings-summary">
                    <span><x-heroicon-o-cog-6-tooth class="w-5 h-5" /></span>
                    <span>Configuracion</span>
                </summary>
                <div class="sidebar-settings-links">
                    @can('company.view')
                        <a href="{{ route('admin.empresa.edit') }}"
                            class="{{ request()->routeIs('admin.empresa.*') ? 'active' : '' }}">

                            <span>Mi Empresa</span>
                            {{-- <span><x-heroicon-o-building-office class="w-5 h-5" /></span> --}}
                        </a>
                    @endcan

                    <a href="{{ route('profile.edit', ['tab' => 'account']) }}"
                        class="{{ request()->routeIs('profile.edit') && request('tab', 'account') === 'account' ? 'active' : '' }}">Cuenta</a>

                    <a href="{{ route('profile.edit', ['tab' => 'security']) }}"
                        class="{{ request()->routeIs('profile.edit') && request('tab') === 'security' ? 'active' : '' }}">Seguridad</a>
                </div>
            </details>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-3 w-full">
                    <span><x-heroicon-o-arrow-right-start-on-rectangle class="w-5 h-5" /></span>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // ========== SIDEBAR COLLAPSE AL HACER CLIC (DESKTOP) ==========
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const tooltip = document.getElementById('sidebarTooltip');

        // Recuperar estado guardado
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed && window.innerWidth >= 768) {
            sidebar.classList.add('collapsed');
        }

        // Variable para controlar si el clic fue en un enlace
        let clickOnLink = false;

        // Detectar clics en enlaces para no colapsar
        document.querySelectorAll('#sidebar a, #sidebar button').forEach(el => {
            el.addEventListener('click', (e) => {
                clickOnLink = true;
                // En mÃ³vil, cerrar sidebar despuÃ©s del clic
                if (window.innerWidth < 768) {
                    closeMobileSidebar();
                }
                // Dejar que el enlace haga su trabajo
                setTimeout(() => {
                    clickOnLink = false;
                }, 100);
            });
        });

        // Colapsar/expandir al hacer clic en el sidebar (solo en desktop)
        sidebar?.addEventListener('click', (e) => {
            // Solo en desktop (>= 768px)
            if (window.innerWidth >= 768) {
                // Si el clic fue en un enlace o botÃ³n, no colapsar
                if (clickOnLink) {
                    clickOnLink = false;
                    return;
                }

                // Colapsar/expandir
                sidebar.classList.toggle('collapsed');
                const collapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', collapsed);
            }
        });

        // Prevenir que los clics en elementos interactivos propaguen al sidebar
        document.querySelectorAll(
            '#sidebar a, #sidebar button, #sidebar .sidebar-header, #sidebar details, #sidebar summary').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });

        // ========== SIDEBAR MÃ“VIL ==========
        const menuToggleMobile = document.getElementById('menuToggleMobile');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function openMobileSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        menuToggleMobile?.addEventListener('click', openMobileSidebar);
        sidebarOverlay?.addEventListener('click', closeMobileSidebar);

        // ========== BOTTOM SHEET MÃ“VIL ==========
        const bottomSheetTrigger = document.getElementById('bottomSheetTrigger');
        const bottomSheet = document.getElementById('bottomSheet');
        const bottomSheetOverlay = document.getElementById('bottomSheetOverlay');

        function openBottomSheet() {
            bottomSheet.classList.add('open');
            bottomSheetOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBottomSheet() {
            bottomSheet.classList.remove('open');
            bottomSheetOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        bottomSheetTrigger?.addEventListener('click', openBottomSheet);
        bottomSheetOverlay?.addEventListener('click', closeBottomSheet);

        // Cerrar bottom sheet al hacer clic en enlaces
        document.querySelectorAll('.bottom-sheet-nav a, .bottom-sheet-footer button, .bottom-sheet-footer a').forEach(
            el => {
                el.addEventListener('click', () => {
                    if (window.innerWidth < 768) {
                        closeBottomSheet();
                    }
                });
            });

        // Cerrar con tecla ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeMobileSidebar();
                if (bottomSheet.classList.contains('open')) closeBottomSheet();
            }
        });

        // ========== SWIPE PARA ABRIR BOTTOM SHEET ==========
        let touchStartY = 0;
        let touchEndY = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        });

        document.addEventListener('touchend', (e) => {
            touchEndY = e.changedTouches[0].clientY;
            const swipeDistance = touchEndY - touchStartY;
            const isNearBottom = touchStartY > window.innerHeight - 100;

            if (isNearBottom && swipeDistance < -30 && !bottomSheet.classList.contains('open')) {
                openBottomSheet();
            }
        });

        // Cerrar con swipe hacia abajo
        let sheetStartY = 0;
        bottomSheet?.addEventListener('touchstart', (e) => {
            sheetStartY = e.touches[0].clientY;
        });

        bottomSheet?.addEventListener('touchmove', (e) => {
            const currentY = e.touches[0].clientY;
            const delta = currentY - sheetStartY;
            if (delta > 30 && bottomSheet.classList.contains('open')) {
                closeBottomSheet();
            }
        });

        // ========== RESIZE ==========
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth >= 768) {
                    closeMobileSidebar();
                    closeBottomSheet();
                    document.body.style.overflow = '';
                }
            }, 250);
        });
    </script>

    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-password-toggle]');

            if (!button) return;

            const input = document.getElementById(button.dataset.passwordToggle);

            if (!input) return;

            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(!isVisible));
            button.setAttribute('aria-label', isVisible ?
                button.getAttribute('aria-label').replace('Ocultar', 'Mostrar') :
                button.getAttribute('aria-label').replace('Mostrar', 'Ocultar'));
            button.querySelector('[data-password-visible-icon]')?.classList.toggle('hidden', !isVisible);
            button.querySelector('[data-password-hidden-icon]')?.classList.toggle('hidden', isVisible);
        });
    </script>

    @livewireScripts
    @stack('scripts')
</body>

</html>
