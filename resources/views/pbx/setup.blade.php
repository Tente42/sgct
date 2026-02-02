<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuración Inicial') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Aviso!</strong>
                    <span class="block sm:inline">{{ session('warning') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-8 text-white">
                    <div class="flex items-center gap-4">
                        <div class="bg-white/20 rounded-full p-4">
                            <i class="fas fa-server text-3xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">{{ $pbx->name }}</h3>
                            <p class="text-blue-100">{{ $pbx->ip }}:{{ $pbx->port }}</p>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <div class="mb-6">
                        <div class="flex items-center gap-3 text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                            <div>
                                <p class="font-semibold">Esta central no tiene datos sincronizados</p>
                                <p class="text-sm text-amber-700">Selecciona qué información deseas importar desde la central telefónica.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Estado actual -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-gray-700">{{ number_format($extensionCount) }}</div>
                            <div class="text-sm text-gray-500">Extensiones</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-gray-700">{{ number_format($callCount) }}</div>
                            <div class="text-sm text-gray-500">Llamadas</div>
                        </div>
                    </div>

                    <!-- Formulario de sincronización -->
                    <form method="POST" action="{{ route('pbx.syncInitial', $pbx) }}" id="syncForm">
                        @csrf

                        <div class="space-y-4">
                            <!-- Sincronizar Extensiones -->
                            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" name="sync_extensions" value="1" checked
                                       class="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900">
                                        <i class="fas fa-users text-blue-500 mr-2"></i>
                                        Sincronizar Extensiones
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Importa todos los anexos/extensiones configurados en la central.
                                        <span class="text-blue-600">(Modo rápido - solo datos básicos)</span>
                                    </p>
                                </div>
                            </label>

                            <!-- Sincronizar Llamadas -->
                            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" name="sync_calls" value="1"
                                       class="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900">
                                        <i class="fas fa-phone-alt text-green-500 mr-2"></i>
                                        Sincronizar Historial de Llamadas
                                        <span class="text-xs text-orange-600 font-normal ml-2">(Puede tardar mucho tiempo)</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Importa el registro de llamadas (CDR) desde la central.
                                        <span class="text-orange-600">⚠️ Recomendado: sincroniza año por año desde terminal.</span>
                                    </p>
                                    
                                    <div class="mt-3">
                                        <label class="text-sm font-medium text-gray-700">Desde el año:</label>
                                        <select name="year" class="ml-2 rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            @for($y = date('Y'); $y >= 2020; $y--)
                                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Advertencia de tiempo -->
                        <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                                <div class="text-sm text-amber-800">
                                    <p class="font-semibold">Información importante:</p>
                                    <ul class="mt-1 list-disc list-inside space-y-1">
                                        <li>Las extensiones se sincronizan en <strong>modo rápido</strong> (~30 segundos)</li>
                                        <li>Las llamadas pueden tardar <strong>varios minutos u horas</strong> dependiendo del rango de fechas</li>
                                        <li>Puedes cerrar esta página, la sincronización continúa en segundo plano</li>
                                        <li>Verás un indicador en la esquina inferior derecha con el progreso</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="mt-6 flex items-center justify-between">
                            <a href="{{ route('pbx.index') }}" 
                               class="inline-flex items-center gap-2 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fas fa-arrow-left"></i>
                                <span>Volver a Centrales</span>
                            </a>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('dashboard') }}" 
                                   class="inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                                    <span>Omitir por ahora</span>
                                </a>
                                
                                <button type="submit" 
                                        id="syncButton"
                                        class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-lg hover:shadow-xl transition-all">
                                    <i class="fas fa-sync-alt" id="syncIcon"></i>
                                    <span id="syncText">Iniciar Sincronización</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comando alternativo -->
            <div class="mt-6 bg-gray-800 rounded-lg p-4 text-gray-300">
                <div class="flex items-center gap-2 text-gray-400 text-sm mb-2">
                    <i class="fas fa-terminal"></i>
                    <span>También puedes ejecutar desde la terminal:</span>
                </div>
                <code class="text-green-400 text-sm">
                    php artisan extensions:import --pbx={{ $pbx->id }}<br>
                    php artisan calls:sync --pbx={{ $pbx->id }} --year={{ date('Y') }}
                </code>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        const pbxId = {{ $pbx->id }};
        const syncForm = document.getElementById('syncForm');
        const syncButton = document.getElementById('syncButton');
        const syncIcon = document.getElementById('syncIcon');
        const syncText = document.getElementById('syncText');
        
        // Verificar estado de sincronización al cargar la página
        checkSyncStatus();
        
        // Verificar cada 3 segundos si hay sincronización en progreso
        let statusInterval = setInterval(checkSyncStatus, 3000);
        
        function checkSyncStatus() {
            fetch(`/pbx/sync-status/${pbxId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.syncing) {
                        disableForm(data.progress || 'Sincronización en progreso...', true);
                    } else {
                        enableForm();
                    }
                })
                .catch(err => console.error('Error checking sync status:', err));
        }
        
        function disableForm(message, disableInputs = false) {
            syncButton.disabled = true;
            syncButton.classList.add('opacity-75', 'cursor-not-allowed');
            syncButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            syncButton.classList.add('bg-yellow-600');
            syncIcon.classList.add('fa-spin');
            syncText.textContent = message;
            
            // Solo deshabilitar checkboxes si se indica (no durante submit)
            if (disableInputs) {
                document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = true);
                document.querySelector('select[name="year"]').disabled = true;
            }
        }
        
        function enableForm() {
            syncButton.disabled = false;
            syncButton.classList.remove('opacity-75', 'cursor-not-allowed', 'bg-yellow-600');
            syncButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
            syncIcon.classList.remove('fa-spin');
            syncText.textContent = 'Iniciar Sincronización';
            
            // Habilitar checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = false);
            document.querySelector('select[name="year"]').disabled = false;
        }
        
        syncForm.addEventListener('submit', function(e) {
            // Solo cambiar visual del botón, NO deshabilitar los inputs
            disableForm('Iniciando sincronización...', false);
        });
        
        // Limpiar interval al salir de la página
        window.addEventListener('beforeunload', function() {
            clearInterval(statusInterval);
        });
    </script>
    @endpush
</x-app-layout>
