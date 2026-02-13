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
            <?php echo e(__('Reporte de Llamadas')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800"><i class="bi bi-telephone-inbound-fill me-2"></i>Dashboard de Control</h3>
                <span class="text-gray-500 text-sm">Generado: <?php echo e(date('d/m/Y H:i')); ?></span>
            </div>
            
            <?php if(Auth::user()->canSyncCalls()): ?>
            <form action="<?php echo e(route('cdr.sync')); ?>" method="POST" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow-md"
                   onclick="this.innerHTML='<i class=\'fas fa-sync fa-spin\'></i> Buscando...'; this.classList.add('opacity-50', 'cursor-not-allowed'); this.disabled=true; this.form.submit();">
                    <i class="fas fa-cloud-download-alt"></i> Sincronizar Ahora
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Éxito!</strong>
                <span class="block sm:inline"><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo e(session('error')); ?></span>
            </div>
        <?php endif; ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white p-3 shadow-sm border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Total Llamadas</h6>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo e(number_format($totalLlamadas)); ?></h2>
                    </div>
                    <div class="text-4xl text-blue-500 opacity-25"><i class="fas fa-list-ul"></i></div>
                </div>
            </div>

            <div class="bg-white p-3 shadow-sm border-l-4 border-cyan-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Tiempo Facturable</h6>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo e(number_format($minutosFacturables ?? 0)); ?> <span class="text-base text-gray-500">min</span></h2>
                    </div>
                    <div class="text-4xl text-cyan-500 opacity-25"><i class="fas fa-stopwatch"></i></div>
                </div>
            </div>

            <div class="bg-white p-3 shadow-sm border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-sm font-bold">Total a Cobrar</h6>
                        <div class="text-3xl font-extrabold text-green-600">$<?php echo e(number_format($totalPagar, 0, ',', '.')); ?></div>
                        <small class="text-gray-500 text-xs">(Tarifas dinámicas por tipo)</small>
                    </div>
                    <div class="text-4xl text-green-500 opacity-25"><i class="fas fa-cash-stack"></i></div>
                </div>
            </div>
        </div>
        
        

        <div class="bg-white shadow-sm mb-4">
            <div class="bg-gray-800 text-white py-2 px-4">
                <i class="fas fa-filter me-1"></i> Filtros de Búsqueda
            </div>
            <div class="p-4">
                <form action="<?php echo e(url('/')); ?>" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    
                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Desde:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>">
                    </div>
                    
                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Hasta:</label>
                        <input type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" name="fecha_fin" value="<?php echo e($fechaFin); ?>">
                    </div>

                    <div class="md:col-span-1">
                        <label class="block font-bold text-sm">Anexo / Origen:</label>
                        <div class="flex mt-1">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm"><i class="fas fa-phone"></i></span>
                            <input type="text" class="flex-1 block w-full rounded-none rounded-r-md border-gray-300 shadow-sm" name="anexo" value="<?php echo e($anexo); ?>" placeholder="Ej: 3002">
                        </div>
                    </div>
                    
                    <div class="md:col-span-2 flex gap-2 items-end">
                        <!-- Toggle Salientes/Entrantes -->
                        <div class="flex flex-col w-full">
                            <label class="block font-bold text-sm mb-1">Tipo de Llamada:</label>
                            <div class="flex rounded-lg overflow-hidden border border-gray-300 bg-gray-100">
                                <button type="submit" 
                                        name="tipo_llamada" 
                                        value="internal"
                                        title="Llamadas hechas por anexos"
                                        class="flex-1 py-2 px-4 text-sm font-bold transition-all duration-200 flex items-center justify-center gap-2
                                            <?php echo e(request('tipo_llamada', 'all') === 'internal' ? 'bg-blue-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'); ?>">
                                    <i class="fas fa-arrow-up"></i> Salientes
                                </button>
                                <button type="submit" 
                                        name="tipo_llamada" 
                                        value="all"
                                        title="Todas las llamadas"
                                        class="flex-1 py-2 px-4 text-sm font-bold transition-all duration-200 flex items-center justify-center gap-2
                                            <?php echo e(request('tipo_llamada', 'all') === 'all' ? 'bg-gray-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'); ?>">
                                    <i class="fas fa-list"></i> Todas
                                </button>
                                <button type="submit" 
                                        name="tipo_llamada" 
                                        value="external"
                                        title="Llamadas recibidas desde afuera"
                                        class="flex-1 py-2 px-4 text-sm font-bold transition-all duration-200 flex items-center justify-center gap-2
                                            <?php echo e(request('tipo_llamada', 'all') === 'external' ? 'bg-green-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'); ?>">
                                    <i class="fas fa-arrow-down"></i> Entrantes
                                </button>
                            </div>
                        </div>
                        
                        <?php if(Auth::user()->canExportPdf()): ?>
                        <button type="button" 
                                onclick="pedirTituloYDescargar()" 
                                class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded" 
                                title="Descargar PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button> 
                        <?php endif; ?>
                        <?php if(Auth::user()->canExportExcel()): ?>
                        <a href="<?php echo e(route('calls.export', request()->all())); ?>" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-center">
                            <i class="fas fa-file-excel"></i> Excel

                        </a>
                        <?php endif; ?>

                        <a href="<?php echo e(url('/')); ?>" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-center" title="Limpiar">
                            <i class="fas fa-undo"></i>
                        </a>
                        <input type="hidden" name="titulo" id="titulo_pdf" value="<?php echo e($titulo ?? 'Reporte de Llamadas'); ?>">
                        <input type="hidden" name="tipo_llamada_hidden" id="tipo_llamada_hidden" value="<?php echo e(request('tipo_llamada', 'all')); ?>">
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white shadow border-0">
            <div class="border-b py-3 px-4 flex justify-between items-center">
                <h6 class="m-0 font-bold text-gray-800">Detalle de Registros</h6>
                <span class="bg-gray-100 text-gray-800 border px-2 py-1 rounded-md text-sm">Viendo <?php echo e($llamadas->count()); ?> de <?php echo e($llamadas->total()); ?></span>
            </div>
            <div class="overflow-x-auto">
                <?php
                    $currentSort = request('sort', 'start_time');
                    $currentDir = request('dir', 'desc');
                ?>
                <table class="min-w-full bg-white" id="tabla-llamadas">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-sm leading-normal">
                        <tr>
                            <th class="py-3 px-6 text-center">
                                <a href="<?php echo e(request()->fullUrlWithQuery(['sort' => 'start_time', 'dir' => ($currentSort == 'start_time' && $currentDir == 'asc') ? 'desc' : 'asc'])); ?>" 
                                   class="flex items-center justify-center gap-1 hover:text-gray-700">
                                    Hora
                                    <span class="flex flex-col text-xs leading-none">
                                        <i class="fas fa-caret-up <?php echo e($currentSort == 'start_time' && $currentDir == 'asc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                        <i class="fas fa-caret-down <?php echo e($currentSort == 'start_time' && $currentDir == 'desc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-left">Origen / Nombre</th>
                            <th class="py-3 px-6 text-center">Destino</th>
                            <th class="py-3 px-6 text-center">
                                <a href="<?php echo e(request()->fullUrlWithQuery(['sort' => 'tipo', 'dir' => ($currentSort == 'tipo' && $currentDir == 'asc') ? 'desc' : 'asc'])); ?>" 
                                   class="flex items-center justify-center gap-1 hover:text-gray-700">
                                    Tipo
                                    <span class="flex flex-col text-xs leading-none">
                                        <i class="fas fa-caret-up <?php echo e($currentSort == 'tipo' && $currentDir == 'asc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                        <i class="fas fa-caret-down <?php echo e($currentSort == 'tipo' && $currentDir == 'desc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">
                                <a href="<?php echo e(request()->fullUrlWithQuery(['sort' => 'billsec', 'dir' => ($currentSort == 'billsec' && $currentDir == 'asc') ? 'desc' : 'asc'])); ?>" 
                                   class="flex items-center justify-center gap-1 hover:text-gray-700">
                                    Duración
                                    <span class="flex flex-col text-xs leading-none">
                                        <i class="fas fa-caret-up <?php echo e($currentSort == 'billsec' && $currentDir == 'asc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                        <i class="fas fa-caret-down <?php echo e($currentSort == 'billsec' && $currentDir == 'desc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">
                                <a href="<?php echo e(request()->fullUrlWithQuery(['sort' => 'costo', 'dir' => ($currentSort == 'costo' && $currentDir == 'asc') ? 'desc' : 'asc'])); ?>" 
                                   class="flex items-center justify-center gap-1 hover:text-gray-700">
                                    Costo
                                    <span class="flex flex-col text-xs leading-none">
                                        <i class="fas fa-caret-up <?php echo e($currentSort == 'costo' && $currentDir == 'asc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                        <i class="fas fa-caret-down <?php echo e($currentSort == 'costo' && $currentDir == 'desc' ? 'text-blue-500' : 'text-gray-300'); ?>"></i>
                                    </span>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php $__empty_1 = true; $__currentLoopData = $llamadas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cdr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="py-3 px-6 text-center">
                                    <div class="font-bold text-gray-800"><?php echo e(date('H:i:s', strtotime($cdr->start_time))); ?></div>
                                    <small class="text-gray-500"><?php echo e(date('d/m/Y', strtotime($cdr->start_time))); ?></small>
                                </td>

                                <td class="py-3 px-6 text-left">
                                    <div class="flex items-center">
                                        <span class="font-bold text-lg me-2 text-gray-800"><?php echo e($cdr->source); ?></span>
                                        
                                        <?php if($cdr->extension && $cdr->extension->fullname): ?>
                                            <span class="text-blue-500 text-sm italic me-2">
                                                <i class="fas fa-user"></i> <?php echo e($cdr->extension->fullname); ?>

                                            </span>
                                        <?php endif; ?>

                                        <button class="text-gray-400 hover:text-gray-600"
                                                onclick="editarNombre('<?php echo e($cdr->source); ?>', '<?php echo e($cdr->extension->fullname ?? ''); ?>')"
                                                title="Editar nombre localmente">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <?php if($cdr->caller_name && $cdr->caller_name != $cdr->source && $cdr->caller_name != ($cdr->extension->fullname ?? '')): ?>
                                        <div class="text-sm text-gray-500 italic">
                                            <i class="fas fa-phone-alt"></i> ID Central: <?php echo e($cdr->caller_name); ?>

                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-6 text-center">
                                    <span class="bg-gray-100 text-gray-800 border px-3 py-1 rounded-full font-mono">
                                        <?php echo e($cdr->destination); ?>

                                    </span>
                                </td>

                                <td class="py-3 px-6 text-center">
                                    <?php $tipo = $cdr->call_type; ?>
                                    <?php if($tipo == 'Celular'): ?>
                                        <span class="bg-purple-100 text-purple-800 py-1 px-2 rounded-full text-xs">Celular</span>
                                    <?php elseif($tipo == 'Internacional'): ?>
                                        <span class="bg-red-100 text-red-800 py-1 px-2 rounded-full text-xs">Internacional</span>
                                    <?php elseif($tipo == 'Interna'): ?>
                                        <span class="bg-gray-100 text-gray-600 py-1 px-2 rounded-full text-xs">Interna</span>
                                    <?php else: ?>
                                        <span class="bg-blue-100 text-blue-800 py-1 px-2 rounded-full text-xs">Nacional</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-6 text-center font-bold <?php echo e($cdr->billsec > 0 ? 'text-gray-800' : 'text-gray-400'); ?>">
                                    <?php echo e($cdr->billsec); ?>s
                                </td>

                                <td class="py-3 px-6 text-center font-bold <?php echo e($cdr->cost > 0 ? 'text-green-600' : 'text-gray-400'); ?>">
                                    $<?php echo e(number_format($cdr->cost, 0, ',', '.')); ?>

                                </td>

                                <td class="py-3 px-6 text-center">
                                    <?php if($cdr->disposition == 'ANSWERED'): ?>
                                        <span class="bg-green-200 text-green-800 py-1 px-3 rounded-full text-xs">Contestada</span>
                                    <?php elseif($cdr->disposition == 'NO ANSWER'): ?>
                                        <span class="bg-red-200 text-red-800 py-1 px-3 rounded-full text-xs">No Contestan</span>
                                    <?php elseif($cdr->disposition == 'BUSY'): ?>
                                        <span class="bg-yellow-200 text-yellow-800 py-1 px-3 rounded-full text-xs">Ocupado</span>
                                    <?php elseif($cdr->disposition == 'FAILED'): ?>
                                        <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs">Fallida</span>
                                    <?php else: ?>
                                        <span class="bg-gray-200 text-gray-800 py-1 px-3 rounded-full text-xs"><?php echo e($cdr->disposition); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="text-gray-400">
                                        <i class="fas fa-inbox text-4xl"></i>
                                        <p class="mt-2">No se encontraron llamadas con estos filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="py-4">
             <?php echo e($llamadas->appends(request()->input())->links()); ?>

        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function editarNombre(extension, nombreActual) {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { extension, nombreActual } }));
        }

        function pedirTituloYDescargar() {
            const form = document.querySelector('form[action="<?php echo e(url('/')); ?>"]');
            const inputTitulo = document.getElementById('titulo_pdf');
            const inputTipoLlamada = document.getElementById('tipo_llamada_hidden');
            const tituloActual = inputTitulo ? inputTitulo.value : 'Reporte de Llamadas';
            const nuevoTitulo = prompt('Título del reporte', tituloActual);
            if (nuevoTitulo === null) return; // cancelado
            if (inputTitulo) inputTitulo.value = (nuevoTitulo.trim() || tituloActual);
            // Renombrar el hidden para que se envíe como tipo_llamada
            if (inputTipoLlamada) inputTipoLlamada.name = 'tipo_llamada';
            const oldAction = form.action;
            const oldTarget = form.target;
            form.action = "<?php echo e(route('cdr.pdf')); ?>";
            form.target = "_blank";
            form.submit();
            form.action = oldAction;
            form.target = oldTarget;
            // Restaurar nombre original
            if (inputTipoLlamada) inputTipoLlamada.name = 'tipo_llamada_hidden';
        }
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
<?php endif; ?><?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/reporte.blade.php ENDPATH**/ ?>