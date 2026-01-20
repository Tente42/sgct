<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gráficos de Llamadas') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <!-- Uptime - Esquina Superior Derecha -->
        <div class="flex justify-end" style="margin-bottom: 0.5rem;">
            <div class="bg-white shadow rounded-lg px-6 py-2 flex items-center" style="gap: 1.5rem;">
                <i class="fa fa-clock text-blue-500 text-xl"></i>
                <div>
                    <div class="text-xs text-gray-500 uppercase">Uptime</div>
                    <div class="text-lg font-semibold text-gray-800">{{ $systemData['uptime'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            {{-- Gráfico de Torta --}}
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-pie me-1"></i> Llamadas por Estado</h6>
                <div class="chart-container">
                    <canvas id="graficoTorta"></canvas>
                </div>
            </div>

            {{-- Gráfico de Líneas --}}
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-line me-1"></i> Tendencia de Llamadas</h6>
                <div class="chart-container">
                    <canvas id="graficoLineas"></canvas>
                </div>
            </div>

        </div>

        {{-- Filtros de Búsqueda --}}
        <div class="bg-white shadow-sm">
            <div class="bg-gray-800 text-white py-2 px-4">
                <i class="fas fa-filter me-1"></i> Filtros de Búsqueda
            </div>
            <div class="p-4">
                <form action="{{ route('cdr.charts') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <label class="block font-bold text-sm">Desde:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_inicio" value="{{ $fechaInicio }}">
                    </div>
                    
                    <div>
                        <label class="block font-bold text-sm">Hasta:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_fin" value="{{ $fechaFin }}">
                    </div>

                    <div>
                        <label class="block font-bold text-sm">Anexo / Origen:</label>
                        <div class="flex mt-1">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"><i class="fas fa-phone"></i></span>
                            <input type="text" class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 shadow-sm" name="anexo" value="{{ $anexo }}" placeholder="Ej: 3002">
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        
                        <a href="{{ route('cdr.charts') }}" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center" title="Limpiar">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- GRÁFICO DE TORTA ---
        const pieCtx = document.getElementById('graficoTorta');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: @json($pieChartLabels),
                    datasets: [{
                        label: 'Llamadas',
                        data: @json($pieChartData),
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(201, 203, 207, 0.7)',
                            'rgba(54, 162, 235, 0.7)'
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                }
            });
        }

        // --- GRÁFICO DE LÍNEAS ---
        const lineCtx = document.getElementById('graficoLineas');
        if (lineCtx) {
            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: @json($lineChartLabels),
                    datasets: [{
                        label: 'Total de Llamadas',
                        data: @json($lineChartData),
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }
    });
    </script>
    @endpush
</x-app-layout>
