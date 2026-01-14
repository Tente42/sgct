<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Reporte de Llamadas') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800"><i class="bi bi-telephone-inbound-fill me-2"></i>Dashboard de Control</h3>
                <span class="text-gray-500 text-sm">Generado: {{ date('d/m/Y H:i') }}</span>
            </div>
            
            <a href="{{ route('cdr.sync') }}" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow-md"
               onclick="this.innerHTML='<i class=\'fas fa-sync fa-spin\'></i> Buscando...'; this.classList.add('opacity-50', 'cursor-not-allowed');">
                <i class="fas fa-cloud-download-alt"></i> Sincronizar Ahora
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Éxito!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white p-3 shadow-sm border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Total Llamadas</h6>
                        <h2 class="text-2xl font-bold text-gray-800">{{ number_format($totalLlamadas) }}</h2>
                    </div>
                    <div class="text-4xl text-blue-500 opacity-25"><i class="fas fa-list-ul"></i></div>
                </div>
            </div>

            <div class="bg-white p-3 shadow-sm border-l-4 border-cyan-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Tiempo Facturable</h6>
                        <h2 class="text-2xl font-bold text-gray-800">{{ number_format($minutosFacturables ?? 0) }} <span class="text-base text-gray-500">min</span></h2>
                    </div>
                    <div class="text-4xl text-cyan-500 opacity-25"><i class="fas fa-stopwatch"></i></div>
                </div>
            </div>

            <div class="bg-white p-3 shadow-sm border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Total a Cobrar</h6>
                        <div class="text-3xl font-extrabold text-green-600">${{ number_format($totalPagar, 0, ',', '.') }}</div>
                        <small class="text-gray-500 text-xs">(Tarifa: ${{ $tarifa }}/min)</small>
                    </div>
                    <div class="text-4xl text-green-500 opacity-25"><i class="fas fa-cash-stack"></i></div>
                </div>
            </div>
        </div>
        
        {{-- Gráfico de tendencia diaria eliminado temporalmente --}}

        <div class="bg-white shadow-sm mb-4">
            <div class="bg-gray-800 text-white py-2 px-4">
                <i class="fas fa-filter me-1"></i> Filtros de Búsqueda
            </div>
            <div class="p-4">
                <form action="{{ url('/') }}" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    
                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Desde:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_inicio" value="{{ $fechaInicio }}">
                    </div>
                    
                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Hasta:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_fin" value="{{ $fechaFin }}">
                    </div>

                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Anexo / Origen:</label>
                        <div class="flex mt-1">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"><i class="fas fa-phone"></i></span>
                            <input type="text" class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 shadow-sm" name="anexo" value="{{ $anexo }}" placeholder="Ej: 3002">
                        </div>
                    </div>

                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm text-green-600">Valor Minuto:</label>
                        <div class="flex mt-1">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-green-600 font-bold">$</span>
                            <input type="number" class="flex-1 block w-full rounded-none rounded-r-md border-green-500 shadow-sm" name="tarifa" 
                                   value="{{ $tarifa ?? 50 }}" min="0">
                        </div>
                    </div>
                    
                    <div class="md:col-span-1 flex gap-2">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-calculator"></i> Calcular
                        </button>
                        
                        <button type="submit" 
                                formaction="{{ route('cdr.pdf') }}" 
                                formtarget="_blank" 
                                class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded" 
                                title="Descargar PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button> 
                        <a href="{{ route('calls.export', request()->all()) }}" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-center">
                            <i class="fas fa-file-excel"></i> Excel

                        </a>

                        <a href="{{ url('/') }}" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center" title="Limpiar">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white shadow border-0">
            <div class="border-b py-3 px-4 flex justify-between items-center">
                <h6 class="m-0 font-bold text-gray-800">Detalle de Registros</h6>
                <span class="bg-gray-100 text-gray-800 border px-2 py-1 rounded-md text-sm">Viendo {{ $llamadas->count() }} de {{ $llamadas->total() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-sm leading-normal">
                        <tr>
                            <th class="py-3 px-6 text-center">Hora</th>
                            <th class="py-3 px-6 text-left">Origen / Nombre</th>
                            <th class="py-3 px-6 text-center">Destino</th>
                            <th class="py-3 px-6 text-center">Duración</th>
                            <th class="py-3 px-6 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        @forelse($llamadas as $cdr)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-center">
                                    <div class="font-bold text-gray-800">{{ date('H:i:s', strtotime($cdr->start_time)) }}</div>
                                    <small class="text-gray-500">{{ date('d/m/Y', strtotime($cdr->start_time)) }}</small>
                                </td>

                                <td class="py-3 px-6 text-left">
                                    <div class="flex items-center">
                                        <span class="font-bold text-lg me-2 text-gray-800">{{ $cdr->source }}</span>
                                        
                                        @if($cdr->extension && $cdr->extension->fullname)
                                            <span class="text-blue-500 text-sm italic me-2">
                                                <i class="fas fa-user"></i> {{ $cdr->extension->fullname }}
                                            </span>
                                        @endif

                                        <button class="text-gray-400 hover:text-gray-600"
                                                onclick="editarNombre('{{ $cdr->source }}', '{{ $cdr->extension->fullname ?? '' }}')"
                                                title="Editar nombre localmente">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    </div>
                                    
                                    @if($cdr->caller_name && $cdr->caller_name != $cdr->source && $cdr->caller_name != ($cdr->extension->fullname ?? ''))
                                        <div class="text-sm text-gray-500 italic">
                                            <i class="fas fa-phone-alt"></i> ID Central: {{ $cdr->caller_name }}
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 px-6 text-center">
                                    <span class="bg-gray-100 text-gray-800 border px-3 py-1 rounded-full font-mono">
                                        {{ $cdr->destination }}
                                    </span>
                                </td>

                                <td class="py-3 px-6 text-center font-bold {{ $cdr->billsec > 0 ? 'text-gray-800' : 'text-gray-400' }}">
                                    {{ $cdr->billsec }}s
                                </td>

                                <td class="py-3 px-6 text-center">
                                    @if($cdr->disposition == 'ANSWERED')
                                        <span class="bg-green-200 text-green-800 py-1 px-3 rounded-full text-xs">Contestada</span>
                                    @elseif($cdr->disposition == 'NO ANSWER')
                                        <span class="bg-red-200 text-red-800 py-1 px-3 rounded-full text-xs">No Contestan</span>
                                    @elseif($cdr->disposition == 'BUSY')
                                        <span class="bg-yellow-200 text-yellow-800 py-1 px-3 rounded-full text-xs">Ocupado</span>
                                    @elseif($cdr->disposition == 'FAILED')
                                        <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs">Fallida</span>
                                    @else
                                        <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs">{{ $cdr->disposition }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center">
                                    <div class="text-gray-400">
                                        <i class="fas fa-inbox text-4xl"></i>
                                        <p class="mt-2">No se encontraron llamadas con estos filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="py-4">
             {{ $llamadas->appends(request()->input())->links() }}
        </div>
    </div>

    @push('scripts')
    <script>
        function editarNombre(extension, nombreActual) {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { extension, nombreActual } }));
        }
    </script>
    @endpush
</x-app-layout>