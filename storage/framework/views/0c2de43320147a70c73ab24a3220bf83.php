<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <?php echo e(__('Gráficos de Llamadas')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-pie me-1"></i> Llamadas por Estado</h6>
                <div class="chart-container">
                    <canvas id="graficoTorta"></canvas>
                </div>
            </div>

            
            <div class="bg-white shadow-sm p-4">
                <h6 class="font-bold text-blue-500 mb-3"><i class="fas fa-chart-line me-1"></i> Tendencia de Llamadas</h6>
                <div class="chart-container">
                    <canvas id="graficoLineas"></canvas>
                </div>
            </div>

        </div>

        
        <div class="bg-white shadow-sm">
            <div class="bg-gray-800 text-white py-2 px-4">
                <i class="fas fa-filter me-1"></i> Filtros de Búsqueda
            </div>
            <div class="p-4">
                <form action="<?php echo e(route('cdr.charts')); ?>" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div>
                        <label class="block font-bold text-sm">Desde:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>">
                    </div>
                    
                    <div>
                        <label class="block font-bold text-sm">Hasta:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_fin" value="<?php echo e($fechaFin); ?>">
                    </div>

                    <div>
                        <label class="block font-bold text-sm">Anexo / Origen:</label>
                        <div class="flex mt-1">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"><i class="fas fa-phone"></i></span>
                            <input type="text" class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 shadow-sm" name="anexo" value="<?php echo e($anexo); ?>" placeholder="Ej: 3002">
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        
                        <a href="<?php echo e(route('cdr.charts')); ?>" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center" title="Limpiar">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <?php $__env->startPush('scripts'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- GRÁFICO DE TORTA ---
        const pieCtx = document.getElementById('graficoTorta');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($pieChartLabels, 15, 512) ?>,
                    datasets: [{
                        label: 'Llamadas',
                        data: <?php echo json_encode($pieChartData, 15, 512) ?>,
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
                    labels: <?php echo json_encode($lineChartLabels, 15, 512) ?>,
                    datasets: [{
                        label: 'Total de Llamadas',
                        data: <?php echo json_encode($lineChartData, 15, 512) ?>,
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
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/graficos.blade.php ENDPATH**/ ?>