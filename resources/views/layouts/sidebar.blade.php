<!-- Sidebar - Parte Izquierda Fija -->
<aside class="w-64 bg-gray-800 text-white flex flex-col fixed inset-y-0 left-0 z-40" style="height: 100vh;">
    <!-- Logo -->
    <div class="flex items-center justify-center h-16 bg-gray-900 flex-shrink-0">
        <span class="text-xl font-bold">Panel de Llamadas</span>
    </div>

    <!-- Menu Items - Scrolleable solo si es necesario -->
    <nav class="flex-1 overflow-y-auto mt-4">
        <ul class="flex flex-col py-4 space-y-1">
            <li>
                <a href="{{ route('dashboard') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Llamadas</span>
                </a>
            </li>
            @if(Auth::user()->canViewCharts())
            <li>
                <a href="{{ route('cdr.charts') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Gráficos</span>
                </a>
            </li>
            @endif
            <li>
                <a href="{{ route('extension.index') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.879 6.196 9 9 0 015.121 17.804z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Anexos</span>
                </a>
            </li>
            <li>
                <a href="{{ route('settings.index') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Tarifas</span>
                </a>
            </li>
            
            @if(Auth::user()->isAdmin())
            <li class="mt-4 pt-4 border-t border-gray-700">
                <span class="ml-4 text-xs text-gray-500 uppercase tracking-wider">Administración</span>
            </li>
            <li>
                <a href="{{ route('users.index') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-yellow-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Gestión Usuarios</span>
                </a>
            </li>
            @endif
        </ul>
    </nav>

    <!-- Logout - SIEMPRE VISIBLE EN LA PARTE INFERIOR -->
    <div class="border-t border-gray-700 p-4 flex-shrink-0">
        @auth
        {{-- Mostrar central activa y botón cambiar --}}
        @if(session('active_pbx_name'))
        <div class="mb-3 text-center">
            <span class="text-xs text-gray-400">Central activa:</span>
            <p class="text-sm text-green-400 font-semibold truncate" title="{{ session('active_pbx_name') }}">
                <i class="fas fa-server mr-1"></i>{{ session('active_pbx_name') }}
            </p>
        </div>
        @endif
        
        <a href="{{ route('pbx.index') }}" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition flex items-center justify-center gap-2 mb-3">
            <i class="fas fa-exchange-alt"></i>
            <span>Cambiar Central</span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition flex items-center justify-center gap-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </button>
        </form>
        <div class="mt-3 text-center text-sm text-gray-400">
            <p>{{ Auth::user()->name }}</p>
            @if(Auth::user()->isAdmin())
                <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold bg-yellow-500 text-yellow-900 rounded-full">Administrador</span>
            @else
                <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold bg-gray-600 text-gray-300 rounded-full">Usuario</span>
            @endif
        </div>
        @endauth
        @guest
        <a href="{{ route('login') }}" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition flex items-center justify-center gap-2 block text-center">
            <i class="fas fa-sign-in-alt"></i>
            <span>Iniciar Sesión</span>
        </a>
        @endguest
    </div>
</aside>
