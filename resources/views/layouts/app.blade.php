<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="font-sans antialiased bg-gray-50" x-data="{ sidebarOpen: false }">
    <!-- Sidebar - Parte Izquierda -->
    @include('layouts.sidebar')

    <!-- Indicador de Sincronización en Segundo Plano -->
    @auth
        @if(session('active_pbx_id'))
            <div id="syncIndicator" 
                 class="fixed bottom-4 right-4 z-50 hidden"
                 style="max-width: 350px;">
                <div class="bg-white rounded-lg shadow-2xl border border-blue-200 overflow-hidden">
                    <div class="bg-blue-600 px-4 py-2 flex items-center gap-2">
                        <i class="fas fa-sync-alt fa-spin text-white"></i>
                        <span class="text-white font-semibold text-sm">Sincronización en Progreso</span>
                    </div>
                    <div class="px-4 py-3">
                        <p id="syncProgressText" class="text-gray-600 text-sm">Cargando...</p>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-blue-600 h-1.5 rounded-full animate-pulse" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                (function() {
                    const pbxId = {{ session('active_pbx_id') }};
                    const indicator = document.getElementById('syncIndicator');
                    const progressText = document.getElementById('syncProgressText');
                    
                    function checkSync() {
                        fetch(`/pbx/sync-status/${pbxId}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.syncing) {
                                    indicator.classList.remove('hidden');
                                    progressText.textContent = data.progress || 'Sincronizando...';
                                    
                                    // Cambiar color si hay error
                                    if (data.progress && data.progress.includes('❌')) {
                                        indicator.querySelector('.bg-blue-600').classList.replace('bg-blue-600', 'bg-red-600');
                                    } else if (data.progress && data.progress.includes('✓')) {
                                        indicator.querySelector('.bg-blue-600')?.classList.replace('bg-blue-600', 'bg-green-600');
                                    }
                                } else {
                                    indicator.classList.add('hidden');
                                }
                            })
                            .catch(() => {});
                    }
                    
                    // Verificar cada 2 segundos
                    checkSync();
                    setInterval(checkSync, 2000);
                })();
            </script>

            {{-- Polling de sincronización de extensiones (sidebar indicator) --}}
            <script>
                (function() {
                    const syncMsg = document.getElementById('sidebarAnexosSyncMsg');
                    if (!syncMsg) return;

                    function checkExtensionSync() {
                        fetch('{{ route("extension.syncStatus") }}')
                            .then(r => r.json())
                            .then(data => {
                                if (data.status === 'syncing') {
                                    syncMsg.classList.remove('hidden');
                                } else if (data.status === 'completed') {
                                    syncMsg.classList.add('hidden');
                                    syncMsg.classList.remove('bg-red-500', 'text-white');
                                    syncMsg.classList.add('bg-yellow-500', 'text-yellow-900');
                                    syncMsg.innerHTML = '<i class="fas fa-sync fa-spin mr-1"></i> Sincronizando anexos, espere...';
                                    if (window.onExtensionSyncComplete) {
                                        window.onExtensionSyncComplete(data.message);
                                    }
                                } else if (data.status === 'error') {
                                    syncMsg.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error en sincronización';
                                    syncMsg.classList.remove('hidden', 'bg-yellow-500', 'text-yellow-900');
                                    syncMsg.classList.add('bg-red-500', 'text-white');
                                    setTimeout(() => syncMsg.classList.add('hidden'), 8000);
                                } else {
                                    syncMsg.classList.add('hidden');
                                    syncMsg.classList.remove('bg-red-500', 'text-white');
                                    syncMsg.classList.add('bg-yellow-500', 'text-yellow-900');
                                    syncMsg.innerHTML = '<i class="fas fa-sync fa-spin mr-1"></i> Sincronizando anexos, espere...';
                                }
                            })
                            .catch(() => {});
                    }

                    checkExtensionSync();
                    setInterval(checkExtensionSync, 3000);
                })();
            </script>
        @endif
    @endauth

    <!-- Main content - Con margen para el sidebar fijo -->
    <div class="flex flex-col min-h-screen" style="margin-left: 16rem;">
        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow-sm flex-shrink-0">
                <div class="w-full py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 page-transition-slide">
            {{ $slot }}
        </main>
    </div>
    @stack('scripts')
</body>
</html>