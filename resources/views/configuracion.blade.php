<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuración de Anexos') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6" x-data="extensionEditor()">

        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-cog me-2"></i>Gestión de Anexos</h3>
                    <span class="text-gray-500 text-sm">Total de anexos: {{ $extensions->total() }}</span>
                </div>
                @if(Auth::user()->canUpdateIps())
                <form method="POST" action="{{ route('extension.updateIps') }}" class="inline">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow-sm transition-colors">
                        <i class="fas fa-sync-alt"></i>
                        <span>Actualizar IPs</span>
                    </button>
                </form>
                @endif
            </div>

            @if(Auth::user()->canSyncExtensions())
            <div id="syncButtonContainer">
                <button type="button" id="syncButton" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow-md"
                   onclick="iniciarSyncExtensiones(this)">
                    <i class="fas fa-cloud-download-alt"></i> Sincronizar Ahora
                </button>
            </div>
            @endif
        </div>

        {{-- Banner de sincronización en progreso --}}
        <div id="extensionSyncBanner" class="hidden bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <div class="flex items-center gap-3">
                <i class="fas fa-sync fa-spin text-yellow-600 text-lg"></i>
                <div>
                    <strong class="font-bold">Sincronización en progreso</strong>
                    <p class="text-sm" id="extensionSyncMessage">Los anexos se están sincronizando desde la central. Este proceso puede tardar unos minutos...</p>
                </div>
            </div>
            <div class="mt-2 w-full bg-yellow-200 rounded-full h-1.5">
                <div class="bg-yellow-500 h-1.5 rounded-full animate-pulse" style="width: 100%"></div>
            </div>
        </div>

        {{-- Banner de sincronización completada --}}
        <div id="extensionSyncCompleteBanner" class="hidden bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-600 text-lg"></i>
                <div>
                    <strong class="font-bold">¡Sincronización completada!</strong>
                    <p class="text-sm" id="extensionSyncCompleteMessage">Los anexos han sido actualizados.</p>
                    <a href="{{ route('extension.index') }}" class="text-sm font-semibold text-green-700 underline hover:text-green-900 mt-1 inline-block">
                        <i class="fas fa-redo mr-1"></i>Recargar página para ver los cambios
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Éxito!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        <div class="bg-white shadow border-0">
            <div class="border-b py-3 px-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <h5 class="font-bold text-gray-700 mb-0 flex items-center gap-2">
                    <i class="fas fa-phone-alt"></i>
                    <span>Listado de Anexos</span>
                </h5>

                <form action="{{ route('extension.index') }}" method="GET" class="w-full md:w-auto flex items-center gap-2">
                    <label for="anexo" class="sr-only">Buscar anexo</label>
                    <div class="flex w-full md:w-64">
                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"><i class="fas fa-phone"></i></span>
                        <input type="text" id="anexo" name="anexo" value="{{ $anexo ?? '' }}" placeholder="Buscar anexo" class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 shadow-sm" />
                    </div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Filtrar</button>
                    <a href="{{ route('extension.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded" title="Limpiar">Limpiar</a>
                </form>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anexo</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permission</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">DND</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Max Contacts</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($extensions as $extension)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                {{ $extension->extension }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $extension->first_name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {{ $extension->last_name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $extension->email ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $extension->phone ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <span class="font-mono {{ $extension->ip ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $extension->ip ?? '---' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $extension->permission === 'International' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $extension->permission === 'National' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $extension->permission === 'Local' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $extension->permission === 'Internal' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $extension->permission ?? 'Internal' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $extension->do_not_disturb ? 'bg-red-500' : 'bg-green-500' }}" 
                                      title="{{ $extension->do_not_disturb ? 'No Molestar Activado' : 'Disponible' }}">
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600">
                                {{ $extension->max_contacts ?? 1 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                @if(Auth::user()->canEditExtensions())
                                <button type="button" 
                                        class="inline-flex items-center gap-1 px-3 py-2 rounded border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300"
                                        title="Editar"
                                        @click="openModal({
                                            extension: '{{ $extension->extension }}',
                                            first_name: '{{ addslashes($extension->first_name ?? '') }}',
                                            last_name: '{{ addslashes($extension->last_name ?? '') }}',
                                            email: '{{ $extension->email ?? '' }}',
                                            phone: '{{ $extension->phone ?? '' }}',
                                            permission: '{{ $extension->permission ?? 'Internal' }}',
                                            do_not_disturb: {{ $extension->do_not_disturb ? 'true' : 'false' }},
                                            max_contacts: {{ $extension->max_contacts ?? 1 }}
                                        })">
                                    <i class="fas fa-edit"></i>
                                    <span>Editar</span>
                                </button>
                                @else
                                <span class="text-gray-400 text-xs">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                No hay anexos registrados
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $extensions->links() }}
            </div>
        </div>

        <!-- Modal de Edición (Multi-paso) -->
        <div x-show="showModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            
            <!-- Fondo oscuro -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="!isSaving && closeModal()"></div>

            <!-- Contenedor del modal -->
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showModal"
                     @click.stop
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl">
                    
                    <!-- Header del Modal -->
                    <div class="px-4 py-3" :class="currentStep === 1 ? 'bg-gray-800' : 'bg-green-700'">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i :class="currentStep === 1 ? 'fas fa-user-edit' : 'fas fa-share'"></i>
                                <span x-text="currentStep === 1 ? 'Editar Anexo: ' + formData.extension : 'Configurar Desvíos: ' + formData.extension"></span>
                            </h3>
                            <!-- Indicador de pasos -->
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                      :class="currentStep === 1 ? 'bg-yellow-400 text-gray-800' : 'bg-white/30 text-white'">1</span>
                                <span class="text-white/50">—</span>
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                      :class="currentStep === 2 ? 'bg-yellow-400 text-gray-800' : 'bg-white/30 text-white'">2</span>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== PASO 1: Datos del Anexo ==================== -->
                    <div x-show="currentStep === 1" class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            
                            <!-- First Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-user text-gray-400 mr-1"></i> Nombre
                                </label>
                                <input type="text" 
                                       x-model="formData.first_name"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: Juan">
                            </div>

                            <!-- Last Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-user text-gray-400 mr-1"></i> Apellido
                                </label>
                                <input type="text" 
                                       x-model="formData.last_name"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Ej: Pérez">
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-envelope text-gray-400 mr-1"></i> Email
                                </label>
                                <input type="email" 
                                       x-model="formData.email"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="correo@ejemplo.com">
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-phone text-gray-400 mr-1"></i> Teléfono
                                </label>
                                <input type="text" 
                                       x-model="formData.phone"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="+56 9 1234 5678">
                            </div>

                            <!-- Permission -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-shield-alt text-gray-400 mr-1"></i> Permisos
                                </label>
                                <select x-model="formData.permission"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="Internal">Internal (Solo interna)</option>
                                    <option value="Local">Local (Urbana)</option>
                                    <option value="National">National (Nacional)</option>
                                    <option value="International">International (Todo)</option>
                                </select>
                            </div>

                            <!-- Max Contacts -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-users text-gray-400 mr-1"></i> Máx. Contactos SIP
                                </label>
                                <select x-model="formData.max_contacts"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <template x-for="n in 10" :key="n">
                                        <option :value="n" x-text="n"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Secret (SIP/IAX Password) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-key text-gray-400 mr-1"></i> Contraseña SIP/IAX
                                </label>
                                <input type="password" 
                                       x-model="formData.secret"
                                       minlength="5"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       placeholder="Mín. 5 caracteres (letra + número)">
                                <p class="text-xs text-gray-500 mt-1">Dejar vacío para no cambiar.</p>
                            </div>

                            <!-- DND (Do Not Disturb) -->
                            <div class="flex items-center">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" 
                                           x-model="formData.do_not_disturb"
                                           class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span class="text-sm font-medium text-gray-700">
                                        <i class="fas fa-moon text-red-400 mr-1"></i> 
                                        No Molestar (DND)
                                    </span>
                                </label>
                            </div>

                        </div>

                        <!-- Separador y botón de Desvíos -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-blue-50 rounded-lg border border-green-200">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-100 text-green-600">
                                        <i class="fas fa-share"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-gray-800">Desvíos de Llamadas</h4>
                                        <p class="text-xs text-gray-500">
                                            <span x-show="hasForwardingConfigured()" class="text-green-600">
                                                <i class="fas fa-check-circle"></i> Configurados
                                            </span>
                                            <span x-show="!hasForwardingConfigured()" class="text-gray-400">
                                                Sin configurar
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button"
                                        @click="goToStep2()"
                                        :disabled="forwardingLoading"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 text-white font-semibold hover:bg-green-700 disabled:opacity-50">
                                    <i class="fas fa-cog" x-show="!forwardingLoading"></i>
                                    <i class="fas fa-spinner fa-spin" x-show="forwardingLoading"></i>
                                    <span>Configurar</span>
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== PASO 2: Configuración de Desvíos ==================== -->
                    <div x-show="currentStep === 2" class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        
                        <!-- Indicador de carga -->
                        <div x-show="forwardingLoading" class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-4xl text-green-600"></i>
                            <p class="mt-3 text-gray-600">Cargando configuración desde la PBX...</p>
                        </div>

                        <div x-show="!forwardingLoading">
                            <!-- Selector de Horario -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-clock text-gray-400 mr-1"></i> ¿Cuándo deben aplicarse los desvíos?
                                </label>
                                <select x-model="forwardingData.timetype" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="0">Todo el tiempo</option>
                                    <option value="1">Horario de oficina</option>
                                    <option value="2">Fuera de horario de oficina</option>
                                    <option value="3">Feriados</option>
                                    <option value="4">Fines de semana</option>
                                </select>
                            </div>

                            <!-- Tarjeta CFU - Incondicional -->
                            <div class="mb-4 p-4 rounded-lg border-2 transition-all" 
                                 :class="forwardingData.cfu.dest_type !== 'none' ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'">
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-red-100 text-red-600">
                                        <i class="fas fa-forward"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-gray-800">Desvío Incondicional (CFU)</h4>
                                        <p class="text-xs text-gray-500">Todas las llamadas se desvían siempre</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de destino</label>
                                        <select x-model="forwardingData.cfu.dest_type" 
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                            <option value="none">Desactivado</option>
                                            <option value="extension">Extensión</option>
                                            <option value="queue">Cola</option>
                                            <option value="custom">Número personalizado</option>
                                        </select>
                                    </div>
                                    <div x-show="forwardingData.cfu.dest_type !== 'none'">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Destino</label>
                                        <template x-if="forwardingData.cfu.dest_type === 'queue'">
                                            <select x-model="forwardingData.cfu.destination" 
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                                <option value="">-- Seleccionar cola --</option>
                                                <template x-for="queue in forwardingData.queues" :key="queue.extension">
                                                    <option :value="queue.extension" x-text="queue.extension + ' - ' + queue.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="forwardingData.cfu.dest_type !== 'queue' && forwardingData.cfu.dest_type !== 'none'">
                                            <input type="text" 
                                                   x-model="forwardingData.cfu.destination"
                                                   :placeholder="forwardingData.cfu.dest_type === 'extension' ? 'Ej: 4445' : 'Ej: 951389199'"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarjeta CFB - Ocupado -->
                            <div class="mb-4 p-4 rounded-lg border-2 transition-all" 
                                 :class="forwardingData.cfb.dest_type !== 'none' ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200 bg-white'">
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-yellow-100 text-yellow-600">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-gray-800">Desvío por Ocupado (CFB)</h4>
                                        <p class="text-xs text-gray-500">Cuando la línea está ocupada</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de destino</label>
                                        <select x-model="forwardingData.cfb.dest_type" 
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                            <option value="none">Desactivado</option>
                                            <option value="extension">Extensión</option>
                                            <option value="queue">Cola</option>
                                            <option value="custom">Número personalizado</option>
                                        </select>
                                    </div>
                                    <div x-show="forwardingData.cfb.dest_type !== 'none'">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Destino</label>
                                        <template x-if="forwardingData.cfb.dest_type === 'queue'">
                                            <select x-model="forwardingData.cfb.destination" 
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                                <option value="">-- Seleccionar cola --</option>
                                                <template x-for="queue in forwardingData.queues" :key="queue.extension">
                                                    <option :value="queue.extension" x-text="queue.extension + ' - ' + queue.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="forwardingData.cfb.dest_type !== 'queue' && forwardingData.cfb.dest_type !== 'none'">
                                            <input type="text" 
                                                   x-model="forwardingData.cfb.destination"
                                                   :placeholder="forwardingData.cfb.dest_type === 'extension' ? 'Ej: 4445' : 'Ej: 951389199'"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarjeta CFN - No Responde -->
                            <div class="p-4 rounded-lg border-2 transition-all" 
                                 :class="forwardingData.cfn.dest_type !== 'none' ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white'">
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-600">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-bold text-gray-800">Desvío por No Respuesta (CFN)</h4>
                                        <p class="text-xs text-gray-500">Cuando no contesta en el tiempo configurado</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de destino</label>
                                        <select x-model="forwardingData.cfn.dest_type" 
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                            <option value="none">Desactivado</option>
                                            <option value="extension">Extensión</option>
                                            <option value="queue">Cola</option>
                                            <option value="custom">Número personalizado</option>
                                        </select>
                                    </div>
                                    <div x-show="forwardingData.cfn.dest_type !== 'none'">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Destino</label>
                                        <template x-if="forwardingData.cfn.dest_type === 'queue'">
                                            <select x-model="forwardingData.cfn.destination" 
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                                <option value="">-- Seleccionar cola --</option>
                                                <template x-for="queue in forwardingData.queues" :key="queue.extension">
                                                    <option :value="queue.extension" x-text="queue.extension + ' - ' + queue.name"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="forwardingData.cfn.dest_type !== 'queue' && forwardingData.cfn.dest_type !== 'none'">
                                            <input type="text" 
                                                   x-model="forwardingData.cfn.destination"
                                                   :placeholder="forwardingData.cfn.dest_type === 'extension' ? 'Ej: 4445' : 'Ej: 951389199'"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensajes de error/éxito -->
                    <div x-show="errorMessage" class="mx-4 mb-4 p-3 bg-red-100 border border-red-300 rounded-lg text-red-700 text-sm">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span x-text="errorMessage"></span>
                    </div>
                    <div x-show="successMessage" class="mx-4 mb-4 p-3 bg-green-100 border border-green-300 rounded-lg text-green-700 text-sm">
                        <i class="fas fa-check-circle mr-1"></i>
                        <span x-text="successMessage"></span>
                    </div>

                    <!-- Footer del Modal -->
                    <div class="bg-gray-50 px-4 py-3 flex flex-col sm:flex-row-reverse gap-2 sm:px-6">
                        <!-- Botones del Paso 1 -->
                        <template x-if="currentStep === 1">
                            <div class="flex flex-col sm:flex-row-reverse gap-2 w-full">
                                <button type="button"
                                        @click="saveAll()"
                                        :disabled="isSaving"
                                        class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-save mr-2" x-show="!isSaving"></i>
                                    <i class="fas fa-spinner fa-spin mr-2" x-show="isSaving"></i>
                                    <span x-text="isSaving ? 'Guardando...' : 'Guardar Cambios'"></span>
                                </button>
                                <button type="button" 
                                        @click="closeModal()"
                                        :disabled="isSaving"
                                        class="inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:w-auto disabled:opacity-50">
                                    <i class="fas fa-times mr-2"></i> Cancelar
                                </button>
                            </div>
                        </template>
                        
                        <!-- Botones del Paso 2 -->
                        <template x-if="currentStep === 2">
                            <div class="flex flex-col sm:flex-row-reverse gap-2 w-full">
                                <button type="button"
                                        @click="confirmForwarding()"
                                        :disabled="forwardingLoading"
                                        class="inline-flex w-full justify-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 sm:w-auto disabled:opacity-50">
                                    <i class="fas fa-check mr-2"></i> Confirmar y Volver
                                </button>
                                <button type="button" 
                                        @click="cancelForwarding()"
                                        :disabled="forwardingLoading"
                                        class="inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:w-auto disabled:opacity-50">
                                    <i class="fas fa-arrow-left mr-2"></i> Cancelar
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function extensionEditor() {
            return {
                showModal: false,
                currentStep: 1,
                isSaving: false,
                forwardingLoading: false,
                errorMessage: '',
                successMessage: '',
                
                // Datos del formulario principal
                formData: {
                    extension: '',
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    permission: 'Internal',
                    do_not_disturb: false,
                    max_contacts: 1,
                    secret: ''
                },
                
                // Datos de desvíos
                forwardingData: {
                    timetype: '0',
                    queues: [],
                    cfu: { dest_type: 'none', destination: '' },
                    cfb: { dest_type: 'none', destination: '' },
                    cfn: { dest_type: 'none', destination: '' }
                },
                
                // Backup para cancelar cambios en paso 2
                forwardingBackup: null,
                
                // Flag para saber si se cargaron datos de la PBX
                forwardingLoaded: false,
                
                openModal(data) {
                    this.formData = { ...data, secret: '' };
                    this.currentStep = 1;
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.resetForwarding();
                    this.showModal = true;
                    document.body.style.overflow = 'hidden';
                },
                
                closeModal() {
                    if (this.isSaving) return;
                    this.showModal = false;
                    this.currentStep = 1;
                    document.body.style.overflow = '';
                },
                
                resetForwarding() {
                    this.forwardingData = {
                        timetype: '0',
                        queues: [],
                        cfu: { dest_type: 'none', destination: '' },
                        cfb: { dest_type: 'none', destination: '' },
                        cfn: { dest_type: 'none', destination: '' }
                    };
                    this.forwardingBackup = null;
                    this.forwardingLoaded = false;
                },
                
                hasForwardingConfigured() {
                    return this.forwardingData.cfu.dest_type !== 'none' ||
                           this.forwardingData.cfb.dest_type !== 'none' ||
                           this.forwardingData.cfn.dest_type !== 'none';
                },
                
                async goToStep2() {
                    this.forwardingLoading = true;
                    this.errorMessage = '';
                    this.currentStep = 2;
                    
                    try {
                        const response = await fetch(`{{ route('extension.forwarding.get') }}?extension=${this.formData.extension}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (!data.success) {
                            this.errorMessage = data.message || 'Error al cargar la configuración.';
                            this.forwardingLoading = false;
                            return;
                        }
                        
                        // Cargar colas disponibles
                        this.forwardingData.queues = data.queues || [];
                        
                        // Si es la primera vez, cargar desde la PBX
                        if (!this.forwardingLoaded) {
                            const fw = data.forwarding;
                            
                            this.forwardingData.cfu = {
                                dest_type: this.parseDestType(fw.cfu, fw.cfu_destination_type),
                                destination: fw.cfu || ''
                            };
                            
                            this.forwardingData.cfb = {
                                dest_type: this.parseDestType(fw.cfb, fw.cfb_destination_type),
                                destination: fw.cfb || ''
                            };
                            
                            this.forwardingData.cfn = {
                                dest_type: this.parseDestType(fw.cfn, fw.cfn_destination_type),
                                destination: fw.cfn || ''
                            };
                            
                            this.forwardingData.timetype = fw.cfu_timetype || fw.cfb_timetype || fw.cfn_timetype || '0';
                            this.forwardingLoaded = true;
                        }
                        
                        // Guardar backup
                        this.forwardingBackup = JSON.parse(JSON.stringify(this.forwardingData));
                        
                    } catch (error) {
                        console.error('Error:', error);
                        this.errorMessage = 'Error de conexión al cargar la configuración.';
                    } finally {
                        this.forwardingLoading = false;
                    }
                },
                
                parseDestType(value, destType) {
                    if (!value || value === '') return 'none';
                    switch (destType) {
                        case '1': return 'extension';
                        case '5': return 'queue';
                        case '2': return 'custom';
                        default: 
                            if (value.length <= 5 && /^\d+$/.test(value)) {
                                return 'extension';
                            }
                            return 'custom';
                    }
                },
                
                confirmForwarding() {
                    // Validar que si un tipo está activo, tenga destino
                    const validations = [];
                    ['cfu', 'cfb', 'cfn'].forEach(type => {
                        const config = this.forwardingData[type];
                        if (config.dest_type !== 'none' && !config.destination.trim()) {
                            const labels = { cfu: 'Incondicional', cfb: 'Ocupado', cfn: 'No Respuesta' };
                            validations.push(`${labels[type]}: Debes especificar un destino.`);
                        }
                    });
                    
                    if (validations.length > 0) {
                        this.errorMessage = validations.join(' ');
                        return;
                    }
                    
                    this.errorMessage = '';
                    this.currentStep = 1;
                },
                
                cancelForwarding() {
                    // Restaurar backup
                    if (this.forwardingBackup) {
                        this.forwardingData = JSON.parse(JSON.stringify(this.forwardingBackup));
                    }
                    this.errorMessage = '';
                    this.currentStep = 1;
                },
                
                async saveAll() {
                    this.isSaving = true;
                    this.errorMessage = '';
                    this.successMessage = '';
                    
                    try {
                        // 1. Guardar datos del anexo
                        const formDataObj = new FormData();
                        formDataObj.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        formDataObj.append('extension', this.formData.extension);
                        formDataObj.append('first_name', this.formData.first_name || '');
                        formDataObj.append('last_name', this.formData.last_name || '');
                        formDataObj.append('email', this.formData.email || '');
                        formDataObj.append('phone', this.formData.phone || '');
                        formDataObj.append('permission', this.formData.permission);
                        formDataObj.append('max_contacts', this.formData.max_contacts);
                        if (this.formData.do_not_disturb) {
                            formDataObj.append('do_not_disturb', '1');
                        }
                        if (this.formData.secret) {
                            formDataObj.append('secret', this.formData.secret);
                        }
                        
                        const extResponse = await fetch('{{ route('extension.update') }}', {
                            method: 'POST',
                            body: formDataObj
                        });
                        
                        // 2. Guardar desvíos si se cargaron
                        if (this.forwardingLoaded) {
                            const forwards = ['cfu', 'cfb', 'cfn'].map(type => ({
                                type: type,
                                dest_type: this.forwardingData[type].dest_type,
                                destination: this.forwardingData[type].destination
                            }));
                            
                            const fwResponse = await fetch('{{ route('extension.forwarding.update') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    extension: this.formData.extension,
                                    timetype: this.forwardingData.timetype,
                                    forwards: forwards
                                })
                            });
                            
                            const fwData = await fwResponse.json();
                            if (!fwData.success) {
                                this.errorMessage = 'Anexo guardado, pero error en desvíos: ' + (fwData.message || 'Error desconocido');
                                this.isSaving = false;
                                return;
                            }
                        }
                        
                        // Recargar la página para ver los cambios
                        window.location.reload();
                        
                    } catch (error) {
                        console.error('Error:', error);
                        this.errorMessage = 'Error de conexión al guardar.';
                        this.isSaving = false;
                    }
                }
            }
        }
    </script>

    {{-- Script de sincronización y polling de extensiones --}}
    <script>
        // Función global para iniciar la sincronización via AJAX
        function iniciarSyncExtensiones(btn) {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.innerHTML = '<i class="fas fa-sync fa-spin"></i> Sincronizando...';

            // Mostrar banner de progreso inmediatamente
            const banner = document.getElementById('extensionSyncBanner');
            const completeBanner = document.getElementById('extensionSyncCompleteBanner');
            if (banner) banner.classList.remove('hidden');
            if (completeBanner) completeBanner.classList.add('hidden');

            // Enviar petición AJAX
            fetch('{{ route("extension.sync") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSyncCompleted(data.message);
                } else {
                    showSyncError(data.message || 'Error desconocido');
                }
            })
            .catch(err => {
                showSyncError('Error de conexión. Intente nuevamente.');
            });
        }

        function showSyncCompleted(message) {
            const banner = document.getElementById('extensionSyncBanner');
            const completeBanner = document.getElementById('extensionSyncCompleteBanner');
            const completeMessage = document.getElementById('extensionSyncCompleteMessage');
            const syncButton = document.getElementById('syncButton');

            if (banner) banner.classList.add('hidden');
            if (completeBanner) completeBanner.classList.remove('hidden');
            if (completeMessage) completeMessage.textContent = message || 'Los anexos han sido actualizados.';
            if (syncButton) {
                syncButton.disabled = false;
                syncButton.classList.remove('opacity-50', 'cursor-not-allowed');
                syncButton.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Sincronizar Ahora';
            }

            // Ocultar indicador del sidebar directamente (sin depender del polling)
            resetSidebarSyncIndicator();
        }

        function showSyncError(message) {
            const banner = document.getElementById('extensionSyncBanner');
            const syncMessage = document.getElementById('extensionSyncMessage');
            const syncButton = document.getElementById('syncButton');

            if (banner) {
                banner.classList.remove('hidden', 'bg-yellow-50', 'border-yellow-300', 'text-yellow-800');
                banner.classList.add('bg-red-50', 'border-red-300', 'text-red-800');
                const icon = banner.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-sync', 'fa-spin', 'text-yellow-600');
                    icon.classList.add('fa-exclamation-triangle', 'text-red-600');
                }
                const strong = banner.querySelector('strong');
                if (strong) strong.textContent = 'Error en sincronización';
            }
            if (syncMessage) syncMessage.textContent = message;
            if (syncButton) {
                syncButton.disabled = false;
                syncButton.classList.remove('opacity-50', 'cursor-not-allowed');
                syncButton.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Sincronizar Ahora';
            }

            // Ocultar indicador del sidebar directamente
            resetSidebarSyncIndicator();
        }

        // Función reutilizable para resetear el indicador de sincronización del sidebar
        function resetSidebarSyncIndicator() {
            const sMsg = document.getElementById('sidebarAnexosSyncMsg');
            if (sMsg) {
                sMsg.classList.add('hidden');
                sMsg.classList.remove('bg-red-500', 'text-white');
                sMsg.classList.add('bg-yellow-500', 'text-yellow-900');
                sMsg.innerHTML = '<i class="fas fa-sync fa-spin mr-1"></i> Sincronizando anexos, espere...';
            }
        }

        // Polling del estado de sincronización (para mostrar progreso y detectar syncs iniciadas por otros usuarios)
        (function() {
            const banner = document.getElementById('extensionSyncBanner');
            const syncMessage = document.getElementById('extensionSyncMessage');
            const syncButton = document.getElementById('syncButton');

            if (!banner) return;

            let pollInterval = null;

            // Callback global para cuando el sidebar detecta sync completado
            window.onExtensionSyncComplete = function(message) {
                showSyncCompleted(message);
            };

            function checkStatus() {
                fetch('{{ route("extension.syncStatus") }}')
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'syncing') {
                            // Otro usuario o proceso inició sync — mostrar progreso
                            banner.classList.remove('hidden');
                            banner.classList.remove('bg-red-50', 'border-red-300', 'text-red-800');
                            banner.classList.add('bg-yellow-50', 'border-yellow-300', 'text-yellow-800');
                            if (syncMessage) syncMessage.textContent = data.message || 'Sincronizando...';
                            if (syncButton) {
                                syncButton.disabled = true;
                                syncButton.classList.add('opacity-50', 'cursor-not-allowed');
                                syncButton.innerHTML = '<i class="fas fa-sync fa-spin"></i> Sincronizando...';
                            }
                        } else if (data.status === 'completed') {
                            showSyncCompleted(data.message);
                        } else if (data.status === 'error') {
                            showSyncError(data.message || 'Ocurrió un error.');
                        }
                        // Si 'idle', no hacer nada (dejar el estado actual del botón)
                    })
                    .catch(() => {});
            }

            // Verificar estado inmediatamente (por si otro usuario inició sync)
            checkStatus();
            // Polling cada 3 segundos para actualizar progreso
            pollInterval = setInterval(checkStatus, 3000);
        })();
    </script>
    @endpush
</x-app-layout>
