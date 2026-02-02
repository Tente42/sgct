<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuración de Tarifas') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-dollar-sign me-2"></i>Tarifas de Llamadas</h3>
                <span class="text-gray-500 text-sm">Configure los precios por minuto según tipo de llamada</span>
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

        <div class="bg-white shadow rounded-lg border-0">
            <div class="border-b py-3 px-4">
                <h5 class="font-bold text-gray-700 mb-0 flex items-center gap-2">
                    <i class="fas fa-tags"></i>
                    <span>Precios por Minuto</span>
                </h5>
            </div>

            <form action="{{ route('settings.update') }}" method="POST" class="p-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($settings as $setting)
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <label for="{{ $setting->key }}" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag text-gray-400 mr-1"></i>
                                {{ $setting->label }}
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 font-bold">$</span>
                                <input type="number" 
                                       name="{{ $setting->key }}" 
                                       id="{{ $setting->key }}"
                                       value="{{ $setting->value }}"
                                       min="0"
                                       class="w-full pl-8 pr-4 py-2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-lg font-semibold {{ Auth::user()->isAdmin() ? '' : 'bg-gray-100 cursor-not-allowed' }}"
                                       placeholder="0"
                                       {{ Auth::user()->isAdmin() ? '' : 'readonly' }}>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Precio en pesos por minuto</p>
                        </div>
                    @endforeach
                </div>

                @if(Auth::user()->isAdmin())
                <div class="mt-6 pt-4 border-t flex justify-end">
                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700 shadow-sm transition-colors">
                        <i class="fas fa-save"></i>
                        <span>Guardar Cambios</span>
                    </button>
                </div>
                @else
                <div class="mt-6 pt-4 border-t">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-700">
                        <i class="fas fa-lock mr-1"></i>
                        Solo los administradores pueden modificar las tarifas.
                    </div>
                </div>
                @endif
            </form>
        </div>

        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-800 mb-2">
                <i class="fas fa-info-circle mr-1"></i>
                Información
            </h4>
            <ul class="text-sm text-blue-700 space-y-1">
                <li><strong>Celular:</strong> Números que empiezan con 9 (ej: 912345678)</li>
                <li><strong>Fijo Nacional:</strong> Números fijos nacionales</li>
                <li><strong>Internacional:</strong> Números con código de país (ej: +1, 00XX)</li>
            </ul>
        </div>

    </div>
</x-app-layout>
