<!-- Sidebar - Parte Izquierda Fija -->
<div class="w-64 h-full bg-gray-800 text-white flex flex-col overflow-hidden">
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/') }}" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17v-2a4 4 0 00-4-4h-2a4 4 0 00-4 4v2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v2a4 4 0 014 4h2a4 4 0 014-4V4"></path></svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Reportes</span>
                </a>
            </li>
            <li>
                <a href="#" class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-gray-700 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-indigo-500 pr-6">
                    <span class="inline-flex justify-center items-center ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </span>
                    <span class="ml-2 text-sm tracking-wide truncate">Configuración</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Logout - SIEMPRE VISIBLE EN LA PARTE INFERIOR -->
    <div class="border-t border-gray-700 p-4 flex-shrink-0">
        @auth
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition flex items-center justify-center gap-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </button>
        </form>
        <div class="mt-3 text-center text-sm text-gray-400">
            <p>{{ Auth::user()->name }}</p>
        </div>
        @endauth
        @guest
        <a href="{{ route('login') }}" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition flex items-center justify-center gap-2 block text-center">
            <i class="fas fa-sign-in-alt"></i>
            <span>Iniciar Sesión</span>
        </a>
        @endguest
    </div>
</div>
