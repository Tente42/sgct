<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gestión de Usuarios') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-users me-2"></i>Administración de Usuarios
                </h3>
                <span class="text-gray-500 text-sm">Total de usuarios: {{ $users->total() }}</span>
            </div>
            <a href="{{ route('users.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 text-white font-semibold hover:bg-green-700 shadow-sm transition-colors">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo Usuario</span>
            </a>
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

        @if(session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Aviso!</strong>
                <span class="block sm:inline">{{ session('warning') }}</span>
            </div>
        @endif

        <div class="bg-white shadow border-0 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Permisos</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $user)
                        <tr class="hover:bg-gray-50 {{ $user->id === auth()->id() ? 'bg-blue-50' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full {{ $user->isAdmin() ? 'bg-yellow-500' : 'bg-gray-400' }} text-white font-bold">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                            @if($user->id === auth()->id())
                                                <span class="text-xs text-blue-600">(Tú)</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Creado: {{ $user->created_at->format('d/m/Y') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $user->role === 'admin' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $user->role === 'supervisor' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $user->role === 'user' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ $user->getRoleDisplayName() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @if($user->isAdmin())
                                        <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">Todos</span>
                                    @else
                                        @if($user->can_sync_calls)
                                            <span class="px-1 py-0.5 text-xs bg-blue-100 text-blue-700 rounded" title="Sincronizar">Sync</span>
                                        @endif
                                        @if($user->can_edit_extensions)
                                            <span class="px-1 py-0.5 text-xs bg-purple-100 text-purple-700 rounded" title="Editar Anexos">Ext</span>
                                        @endif
                                        @if($user->can_edit_rates)
                                            <span class="px-1 py-0.5 text-xs bg-orange-100 text-orange-700 rounded" title="Editar Tarifas">Tar</span>
                                        @endif
                                        @if($user->can_manage_pbx)
                                            <span class="px-1 py-0.5 text-xs bg-red-100 text-red-700 rounded" title="Gestionar PBX">PBX</span>
                                        @endif
                                        @if($user->can_export_pdf)
                                            <span class="px-1 py-0.5 text-xs bg-pink-100 text-pink-700 rounded" title="Exportar PDF">PDF</span>
                                        @endif
                                        @if($user->can_export_excel)
                                            <span class="px-1 py-0.5 text-xs bg-green-100 text-green-700 rounded" title="Exportar Excel">XLS</span>
                                        @endif
                                        @if(!$user->can_sync_calls && !$user->can_edit_extensions && !$user->can_edit_rates && !$user->can_manage_pbx && !$user->can_export_pdf && !$user->can_export_excel)
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">Solo lectura</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                @if($user->id !== auth()->id())
                                    <a href="{{ route('users.edit', $user) }}" 
                                       class="inline-flex items-center gap-1 px-3 py-2 rounded border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300 mr-1"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                        <span>Editar</span>
                                    </a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" 
                                          onsubmit="return confirm('¿Estás seguro de eliminar a {{ $user->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center gap-1 px-3 py-2 rounded border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:border-red-300"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400 text-xs">N/A</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No hay usuarios registrados
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200">
                {{ $users->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
