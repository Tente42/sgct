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
                <button type="button" 
                        @click="openCreateModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 text-white font-semibold hover:bg-green-700 shadow-sm transition-colors">
                    <i class="fas fa-plus"></i>
                    <span>Crear Nuevo Anexo</span>
                </button>
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
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
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

        <!-- Modal de Edición -->
           <div x-show="showModal" 
               x-cloak
               class="fixed inset-0 z-50 overflow-y-auto" 
               aria-labelledby="modal-title" 
               role="dialog" 
               aria-modal="true">
            
            <!-- Fondo oscuro -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="closeModal()"></div>

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
                     class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    
                    <form method="POST" action="{{ route('extension.update') }}">
                        @csrf
                        
                        <!-- Header del Modal -->
                        <div class="bg-gray-800 px-4 py-3">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fas fa-user-edit"></i>
                                Editar Anexo: <span x-text="formData.extension" class="text-yellow-400"></span>
                            </h3>
                        </div>

                        <!-- Body del Modal -->
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            <input type="hidden" name="extension" x-model="formData.extension">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <!-- First Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-user text-gray-400 mr-1"></i> Nombre
                                    </label>
                                    <input type="text" 
                                           name="first_name" 
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
                                           name="last_name" 
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
                                           name="email" 
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
                                           name="phone" 
                                           x-model="formData.phone"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           placeholder="+56 9 1234 5678">
                                </div>

                                <!-- Permission -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-shield-alt text-gray-400 mr-1"></i> Permisos
                                    </label>
                                    <select name="permission" 
                                            x-model="formData.permission"
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
                                    <select name="max_contacts" 
                                            x-model="formData.max_contacts"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5</option>
                                        <option value="6">6</option>
                                        <option value="7">7</option>
                                        <option value="8">8</option>
                                        <option value="9">9</option>
                                        <option value="10">10</option>
                                    </select>
                                </div>

                                <!-- DND (Do Not Disturb) -->
                                <div class="md:col-span-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" 
                                               name="do_not_disturb" 
                                               value="1"
                                               x-model="formData.do_not_disturb"
                                               class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-moon text-red-400 mr-1"></i> 
                                            No Molestar (DND)
                                        </span>
                                        <span x-show="formData.do_not_disturb" class="text-xs text-red-600 font-semibold">(Activado)</span>
                                        <span x-show="!formData.do_not_disturb" class="text-xs text-green-600 font-semibold">(Desactivado)</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1 ml-8">
                                        Cuando está activo, las llamadas entrantes serán rechazadas.
                                    </p>
                                </div>

                            </div>
                        </div>

                        <!-- Footer del Modal -->
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                            <button type="submit" 
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto">
                                <i class="fas fa-save mr-2"></i> Guardar Cambios
                            </button>
                            <button type="button" 
                                    @click="closeModal()"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                <i class="fas fa-times mr-2"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal de Creación -->
        <div x-show="showCreateModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="modal-create-title" 
             role="dialog" 
             aria-modal="true">
            
            <!-- Fondo oscuro -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="closeCreateModal()"></div>

            <!-- Contenedor del modal -->
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showCreateModal"
                     @click.stop
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    
                    <form method="POST" action="{{ route('extension.store') }}" @submit="createFormSubmitting = true">
                        @csrf
                        
                        <!-- Header del Modal -->
                        <div class="bg-green-700 px-4 py-3">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fas fa-user-plus"></i>
                                Crear Nuevo Anexo
                            </h3>
                        </div>

                        <!-- Body del Modal -->
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                            
                            <!-- Mensaje de error si existe -->
                            <div x-show="createError" x-text="createError" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded text-sm"></div>
                            
                            <p class="text-sm text-gray-600 mb-4">
                                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                Crea el anexo con su contraseña. Luego podrás editarlo para agregar nombre, email y otros detalles.
                            </p>

                            <div class="space-y-4">
                                
                                <!-- Extension (Número de Anexo) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-phone-alt text-gray-400 mr-1"></i> Número de Anexo <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="extension" 
                                           x-model="createFormData.extension"
                                           required
                                           pattern="[0-9]+"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                           placeholder="Ej: 1001">
                                    <p class="text-xs text-gray-500 mt-1">Número único de extensión</p>
                                </div>

                                <!-- Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-lock text-gray-400 mr-1"></i> Contraseña <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" 
                                           name="password" 
                                           x-model="createFormData.password"
                                           required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                           placeholder="Ingrese la contraseña">
                                    <p class="text-xs text-gray-500 mt-1">Se usará para SIP, Voicemail y acceso de usuario</p>
                                </div>

                                <!-- Confirmar Password -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-lock text-gray-400 mr-1"></i> Confirmar Contraseña <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" 
                                           name="password_confirmation" 
                                           x-model="createFormData.password_confirmation"
                                           required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                           :class="createFormData.password && createFormData.password_confirmation && createFormData.password !== createFormData.password_confirmation ? 'border-red-500 ring-red-500' : ''"
                                           placeholder="Repita la contraseña">
                                    <p x-show="createFormData.password && createFormData.password_confirmation && createFormData.password !== createFormData.password_confirmation" 
                                       class="text-xs text-red-600 mt-1">
                                        <i class="fas fa-exclamation-circle mr-1"></i>Las contraseñas no coinciden
                                    </p>
                                </div>

                            </div>
                        </div>

                        <!-- Footer del Modal -->
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                            <button type="submit" 
                                    :disabled="createFormSubmitting || !createFormData.extension || !createFormData.password || !createFormData.password_confirmation || createFormData.password !== createFormData.password_confirmation"
                                    :class="(createFormSubmitting || !createFormData.extension || !createFormData.password || !createFormData.password_confirmation || createFormData.password !== createFormData.password_confirmation) ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-500'"
                                    class="inline-flex w-full justify-center rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm sm:ml-3 sm:w-auto">
                                <i class="fas fa-save mr-2" x-show="!createFormSubmitting"></i>
                                <i class="fas fa-spinner fa-spin mr-2" x-show="createFormSubmitting"></i>
                                <span x-text="createFormSubmitting ? 'Creando...' : 'Crear Anexo'"></span>
                            </button>
                            <button type="button" 
                                    @click="closeCreateModal()"
                                    :disabled="createFormSubmitting"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                <i class="fas fa-times mr-2"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function extensionEditor() {
            return {
                showModal: false,
                showCreateModal: false,
                createFormSubmitting: false,
                createError: '',
                formData: {
                    extension: '',
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    permission: 'Internal',
                    do_not_disturb: false,
                    max_contacts: 1
                },
                createFormData: {
                    extension: '',
                    password: '',
                    password_confirmation: ''
                },
                
                openModal(data) {
                    this.formData = { ...data };
                    this.showModal = true;
                    document.body.style.overflow = 'hidden';
                },
                
                closeModal() {
                    this.showModal = false;
                    document.body.style.overflow = '';
                },

                openCreateModal() {
                    this.createFormData = {
                        extension: '',
                        password: '',
                        password_confirmation: ''
                    };
                    this.createError = '';
                    this.createFormSubmitting = false;
                    this.showCreateModal = true;
                    document.body.style.overflow = 'hidden';
                },

                closeCreateModal() {
                    if (!this.createFormSubmitting) {
                        this.showCreateModal = false;
                        document.body.style.overflow = '';
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
