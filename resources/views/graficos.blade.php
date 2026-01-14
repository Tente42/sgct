<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gráficos de Llamadas') }}
        </h2>
    </x-slot>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            {{-- Gráfico de Torta --}}
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-pie me-1"></i> Llamadas por Estado</h6>
                <div style="height: 300px;">
                    <canvas id="graficoTorta"></canvas>
                </div>
            </div>

            {{-- Gráfico de Líneas --}}
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-line me-1"></i> Tendencia de Llamadas (Últimos 30 días)</h6>
                <div style="height: 300px;">
                    <canvas id="graficoLineas"></canvas>
                </div>
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
