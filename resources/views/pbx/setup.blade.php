<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuración Inicial de Central') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('info'))
                <div class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
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
                            <h3 class="text-2xl font-bold">{{ $pbx->name ?: 'Central PBX' }}</h3>
                            <p class="text-blue-100">{{ $pbx->ip }}:{{ $pbx->port }}</p>
                            <span class="inline-flex items-center gap-1 mt-2 px-3 py-1 rounded-full text-sm
                                {{ $pbx->status === 'ready' ? 'bg-green-500/30 text-green-100' : '' }}
                                {{ $pbx->status === 'syncing' ? 'bg-yellow-500/30 text-yellow-100' : '' }}
                                {{ $pbx->status === 'pending' ? 'bg-gray-500/30 text-gray-100' : '' }}
                                {{ $pbx->status === 'error' ? 'bg-red-500/30 text-red-100' : '' }}">
                                <i class="fas fa-circle text-xs"></i>
                                {{ $pbx->getStatusDisplayName() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6" x-data="syncManager()">
                    
                    <!-- Estado actual -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-gray-700" x-text="extensionCount">{{ number_format($extensionCount) }}</div>
                            <div class="text-sm text-gray-500">Extensiones</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-gray-700" x-text="callCount">{{ number_format($callCount) }}</div>
                            <div class="text-sm text-gray-500">Llamadas</div>
                        </div>
                    </div>

                    <!-- Panel de Sincronización -->
                    <div x-show="!isSyncing && status !== 'ready'" class="space-y-4">
                        
                        <!-- Aviso inicial -->
                        <div class="flex items-center gap-3 text-amber-600 bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                            <div>
                                <p class="font-semibold">Esta central necesita sincronización</p>
                                <p class="text-sm text-amber-700">Importa los anexos y llamadas desde la central telefónica.</p>
                            </div>
                        </div>

                        <!-- Opciones de sincronización -->
                        <div class="space-y-3">
                            <!-- Sincronizar Extensiones -->
                            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" x-model="syncOptions.extensions"
                                       class="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900">
                                        <i class="fas fa-users text-blue-500 mr-2"></i>
                                        Sincronizar Extensiones
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Importa <strong>todos</strong> los anexos con sus datos completos (nombre, email, teléfono, permisos, IP, etc.)
                                    </p>
                                </div>
                            </label>

                            <!-- Sincronizar Llamadas -->
                            <label class="flex items-start gap-4 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" x-model="syncOptions.calls"
                                       class="mt-1 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-900">
                                        <i class="fas fa-phone-alt text-green-500 mr-2"></i>
                                        Sincronizar Historial de Llamadas
                                        <span class="text-xs text-orange-600 font-normal ml-2">(Puede tardar varios minutos)</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Importa el registro de llamadas mes por mes.
                                    </p>
                                    
                                    <div class="mt-3 flex items-center gap-4" x-show="syncOptions.calls">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Desde año:</label>
                                            <select x-model="syncOptions.year" class="ml-2 rounded-md border-gray-300 shadow-sm text-sm">
                                                @for($y = date('Y'); $y >= 2020; $y--)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Botón Iniciar -->
                        <div class="mt-6 flex items-center justify-between">
                            <a href="{{ route('pbx.index') }}" 
                               class="inline-flex items-center gap-2 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                                <i class="fas fa-arrow-left"></i>
                                Volver
                            </a>

                            <button @click="startSync()" 
                                    :disabled="!syncOptions.extensions && !syncOptions.calls"
                                    :class="{'opacity-50 cursor-not-allowed': !syncOptions.extensions && !syncOptions.calls}"
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-lg transition-all">
                                <i class="fas fa-play"></i>
                                Iniciar Sincronización
                            </button>
                        </div>
                    </div>

                    <!-- Panel de Progreso -->
                    <div x-show="isSyncing" class="space-y-4">
                        
                        <!-- Indicador de progreso -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <div class="w-16 h-16 border-4 border-blue-200 rounded-full"></div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="fas fa-sync-alt fa-spin text-2xl text-blue-600"></i>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-blue-800">Sincronización en Progreso</h4>
                                    <p class="text-blue-600" x-text="currentMessage">Iniciando...</p>
                                    <p class="text-sm text-blue-500 mt-1">
                                        <span x-show="currentStep === 'extensions'">Paso 1 de 2: Extensiones</span>
                                        <span x-show="currentStep === 'calls'">Paso 2 de 2: Llamadas (Mes <span x-text="currentMonth"></span>/12)</span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Barra de progreso general -->
                            <div class="mt-4">
                                <div class="h-2 bg-blue-200 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 transition-all duration-500" :style="'width: ' + progress + '%'"></div>
                                </div>
                                <p class="text-right text-xs text-blue-500 mt-1" x-text="progress + '%'"></p>
                            </div>
                        </div>

                        <!-- Log de actividad -->
                        <div class="bg-gray-900 rounded-lg p-4 max-h-48 overflow-y-auto" x-ref="logContainer">
                            <template x-for="log in logs" :key="log.id">
                                <div class="text-sm font-mono" :class="{
                                    'text-green-400': log.type === 'success',
                                    'text-red-400': log.type === 'error',
                                    'text-yellow-400': log.type === 'warning',
                                    'text-gray-400': log.type === 'info'
                                }">
                                    <span class="text-gray-600" x-text="log.time"></span>
                                    <span x-text="log.message"></span>
                                </div>
                            </template>
                        </div>

                        <div class="text-center text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            No cierres esta página hasta que termine la sincronización.
                        </div>
                    </div>

                    <!-- Panel de Completado -->
                    <div x-show="status === 'ready' && !isSyncing" class="space-y-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-check text-3xl text-green-600"></i>
                            </div>
                            <h4 class="font-bold text-green-800 text-xl">¡Central Lista!</h4>
                            <p class="text-green-600 mt-2" x-text="lastMessage">La sincronización se completó correctamente.</p>
                        </div>

                        <div class="flex justify-center gap-4">
                            <a href="{{ route('dashboard') }}" 
                               class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-lg">
                                <i class="fas fa-tachometer-alt"></i>
                                Ir al Dashboard
                            </a>
                            <button @click="resetSync()" 
                                    class="inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-redo"></i>
                                Sincronizar de nuevo
                            </button>
                        </div>
                    </div>

                    <!-- Panel de Error -->
                    <div x-show="status === 'error' && !isSyncing" class="space-y-4">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-times text-3xl text-red-600"></i>
                            </div>
                            <h4 class="font-bold text-red-800 text-xl">Error en la Sincronización</h4>
                            <p class="text-red-600 mt-2" x-text="lastMessage">Ocurrió un error durante el proceso.</p>
                        </div>

                        <div class="flex justify-center gap-4">
                            <button @click="resetSync()" 
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 shadow-lg">
                                <i class="fas fa-redo"></i>
                                Reintentar
                            </button>
                            <a href="{{ route('pbx.index') }}" 
                               class="inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-arrow-left"></i>
                                Volver a Centrales
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        function syncManager() {
            return {
                pbxId: {{ $pbx->id }},
                status: '{{ $pbx->status }}',
                isSyncing: {{ $pbx->isSyncing() ? 'true' : 'false' }},
                
                syncOptions: {
                    extensions: true,
                    calls: false,
                    year: {{ date('Y') }}
                },
                
                currentStep: '',
                currentMonth: 1,
                currentMessage: 'Iniciando...',
                lastMessage: '{{ $pbx->sync_message }}',
                progress: 0,
                
                extensionCount: {{ $extensionCount }},
                callCount: {{ $callCount }},
                
                logs: [],
                logId: 0,

                init() {
                    // Si ya está sincronizando al cargar, mostrar el progreso
                    if (this.isSyncing) {
                        this.pollStatus();
                    }
                    // Si está listo, mostrar panel de completado
                    if (this.status === 'ready') {
                        this.lastMessage = '{{ $pbx->sync_message }}';
                    }
                },

                addLog(message, type = 'info') {
                    const now = new Date();
                    const time = now.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    this.logs.push({ id: ++this.logId, time: `[${time}]`, message, type });
                    
                    // Auto-scroll al final
                    this.$nextTick(() => {
                        if (this.$refs.logContainer) {
                            this.$refs.logContainer.scrollTop = this.$refs.logContainer.scrollHeight;
                        }
                    });
                },

                async startSync() {
                    this.isSyncing = true;
                    this.logs = [];
                    this.progress = 0;
                    
                    this.addLog('Iniciando sincronización...', 'info');
                    
                    try {
                        // Paso 1: Extensiones
                        if (this.syncOptions.extensions) {
                            this.currentStep = 'extensions';
                            this.currentMessage = 'Sincronizando extensiones...';
                            this.addLog('Sincronizando extensiones con datos completos...', 'info');
                            
                            const extResult = await this.syncExtensions();
                            
                            if (extResult.success) {
                                this.addLog(`✓ ${extResult.count} extensiones sincronizadas`, 'success');
                                this.extensionCount = extResult.count;
                                this.progress = this.syncOptions.calls ? 20 : 100;
                            } else {
                                throw new Error(extResult.message);
                            }
                        }

                        // Paso 2: Llamadas (mes por mes)
                        if (this.syncOptions.calls) {
                            this.currentStep = 'calls';
                            const year = this.syncOptions.year;
                            const currentDate = new Date();
                            const currentYear = currentDate.getFullYear();
                            const currentMonth = currentDate.getMonth() + 1;
                            
                            for (let month = 1; month <= 12; month++) {
                                // Si es año actual y mes futuro, saltar
                                if (year == currentYear && month > currentMonth) {
                                    this.addLog(`Saltando mes ${month}/${year} (futuro)`, 'warning');
                                    continue;
                                }

                                this.currentMonth = month;
                                this.currentMessage = `Sincronizando llamadas de ${month}/${year}...`;
                                this.addLog(`Obteniendo llamadas de ${month}/${year}...`, 'info');
                                
                                const callResult = await this.syncCallsMonth(year, month);
                                
                                if (callResult.success) {
                                    if (callResult.count > 0) {
                                        this.addLog(`✓ Mes ${month}/${year}: ${callResult.count} llamadas`, 'success');
                                    } else {
                                        this.addLog(`- Mes ${month}/${year}: sin llamadas`, 'info');
                                    }
                                } else {
                                    this.addLog(`⚠ Error en mes ${month}/${year}: ${callResult.message}`, 'warning');
                                }
                                
                                // Actualizar progreso (20% extensiones + 80% dividido en 12 meses)
                                const baseProgress = this.syncOptions.extensions ? 20 : 0;
                                const callProgress = (month / 12) * (this.syncOptions.extensions ? 80 : 100);
                                this.progress = Math.round(baseProgress + callProgress);
                                
                                // Actualizar conteo de llamadas
                                await this.refreshCounts();
                            }
                        }

                        // Finalizar
                        this.addLog('Finalizando sincronización...', 'info');
                        await this.finishSync();
                        
                        this.progress = 100;
                        this.status = 'ready';
                        this.isSyncing = false;
                        this.lastMessage = `Sincronización completada. ${this.extensionCount} extensiones, ${this.callCount} llamadas.`;
                        this.addLog('✓ ¡Sincronización completada!', 'success');
                        
                    } catch (error) {
                        this.addLog(`✗ Error: ${error.message}`, 'error');
                        this.status = 'error';
                        this.isSyncing = false;
                        this.lastMessage = error.message;
                    }
                },

                async syncExtensions() {
                    const response = await fetch(`/pbx/sync-extensions/${this.pbxId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    return await response.json();
                },

                async syncCallsMonth(year, month) {
                    const response = await fetch(`/pbx/sync-calls/${this.pbxId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ year, month })
                    });
                    return await response.json();
                },

                async finishSync() {
                    const response = await fetch(`/pbx/finish-sync/${this.pbxId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    return await response.json();
                },

                async refreshCounts() {
                    const response = await fetch(`/pbx/sync-status/${this.pbxId}`);
                    const data = await response.json();
                    this.extensionCount = data.extensionCount;
                    this.callCount = data.callCount;
                },

                async pollStatus() {
                    const response = await fetch(`/pbx/sync-status/${this.pbxId}`);
                    const data = await response.json();
                    
                    this.currentMessage = data.message || 'Sincronizando...';
                    this.extensionCount = data.extensionCount;
                    this.callCount = data.callCount;
                    
                    if (data.isSyncing) {
                        setTimeout(() => this.pollStatus(), 2000);
                    } else {
                        this.status = data.status;
                        this.isSyncing = false;
                        this.lastMessage = data.message;
                    }
                },

                resetSync() {
                    this.status = 'pending';
                    this.isSyncing = false;
                    this.logs = [];
                    this.progress = 0;
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
