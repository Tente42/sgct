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
            <?php echo e(__('KPIs de Colas (QUEUE)')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header con título y filtros -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-chart-line me-2 text-indigo-600"></i>Análisis de KPIs por Franja Horaria
                </h3>
                <span class="text-gray-500 text-sm">Métricas exclusivas de llamadas de cola (QUEUE)</span>
                <?php if($ultimaSincronizacion): ?>
                <div class="text-xs text-gray-400 mt-1">
                    <i class="fas fa-sync-alt me-1"></i>Última sincronización: <?php echo e(\Carbon\Carbon::parse($ultimaSincronizacion)->diffForHumans()); ?>

                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Botón de sincronización (solo admins) -->
                <?php if(auth()->user() && auth()->user()->isAdmin()): ?>
                <button type="button" id="btnSyncColas" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded shadow-sm text-sm inline-flex items-center gap-2 transition-colors duration-200">
                    <i class="fas fa-sync-alt" id="syncIcon"></i>
                    <span>Sincronizar Colas</span>
                </button>
                <?php endif; ?>

                <!-- Filtros -->
                <form method="GET" class="flex flex-wrap items-center gap-3 bg-white p-3 rounded-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Desde:</label>
                    <input type="date" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>" 
                           class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Hasta:</label>
                    <input type="date" name="fecha_fin" value="<?php echo e($fechaFin); ?>"
                           class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <?php if(!empty($colasDisponibles)): ?>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Cola:</label>
                    <select name="cola" class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Todas las colas</option>
                        <?php $__currentLoopData = $colasDisponibles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cola): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($cola); ?>" <?php echo e(($colaFiltro ?? '') == $cola ? 'selected' : ''); ?>>Cola <?php echo e($cola); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow-sm text-sm transition-colors duration-200 inline-flex items-center">
                    <i class="fas fa-filter me-1"></i> Filtrar
                </button>
            </form>
            </div>
        </div>

        <!-- Tarjetas de Resumen Global -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-xs font-bold">Volumen Total</h6>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo e(number_format($totales['volumen'])); ?></h2>
                        <p class="text-xs text-gray-400">llamadas únicas</p>
                    </div>
                    <div class="text-3xl text-blue-500 opacity-25"><i class="fas fa-phone-volume"></i></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-xs font-bold">Atendidas</h6>
                        <h2 class="text-2xl font-bold text-green-600"><?php echo e(number_format($totales['atendidas'])); ?></h2>
                        <p class="text-xs text-gray-400"><?php echo e($totales['volumen'] > 0 ? round(($totales['atendidas'] / $totales['volumen']) * 100, 1) : 0); ?>% del total</p>
                    </div>
                    <div class="text-3xl text-green-500 opacity-25"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 <?php echo e(($totales['abandono_valor'] ?? 0) > 20 ? 'border-red-500' : (($totales['abandono_valor'] ?? 0) > 15 ? 'border-yellow-500' : 'border-orange-500')); ?>">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-xs font-bold">% Abandono</h6>
                        <h2 class="text-2xl font-bold <?php echo e(($totales['abandono_valor'] ?? 0) > 20 ? 'text-red-600' : (($totales['abandono_valor'] ?? 0) > 15 ? 'text-yellow-600' : 'text-orange-600')); ?>">
                            <?php echo e($totales['abandono_pct']); ?>

                        </h2>
                        <p class="text-xs text-gray-400"><?php echo e(number_format($totales['abandonadas'])); ?> abandonadas</p>
                    </div>
                    <div class="text-3xl <?php echo e(($totales['abandono_valor'] ?? 0) > 20 ? 'text-red-500' : 'text-orange-500'); ?> opacity-25"><i class="fas fa-phone-slash"></i></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-cyan-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-xs font-bold">Espera Promedio</h6>
                        <h2 class="text-2xl font-bold text-cyan-600"><?php echo e($totales['tiempo_espera_promedio']); ?></h2>
                        <p class="text-xs text-gray-400">antes de contestar</p>
                    </div>
                    <div class="text-3xl text-cyan-500 opacity-25"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-purple-500">
                <div class="flex justify-between items-center">
                    <div>
                        <h6 class="text-gray-500 uppercase text-xs font-bold">Agentes Activos</h6>
                        <h2 class="text-2xl font-bold text-purple-600"><?php echo e(count($totales['agentes'])); ?></h2>
                        <p class="text-xs text-gray-400">en el período</p>
                    </div>
                    <div class="text-3xl text-purple-500 opacity-25"><i class="fas fa-headset"></i></div>
                </div>
            </div>
        </div>

        <!-- Sección de Gráficos -->
        <?php if($totales['volumen'] > 0): ?>
        <div class="mb-6">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-200">
                    <h4 class="font-semibold text-blue-800">
                        <i class="fas fa-chart-bar me-2"></i>Visualización de Datos
                    </h4>
                </div>
                <div class="p-6">
                    <!-- Gráfico de Líneas -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h5 class="text-sm font-semibold text-gray-700 mb-4">
                            <i class="fas fa-chart-line me-2 text-blue-600"></i>Volumen de Llamadas por Hora
                        </h5>
                        <div style="height: 300px;">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Barras y Áreas -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Gráfico de Barras: Atendidas vs Abandonadas -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h5 class="text-sm font-semibold text-gray-700 mb-4">
                                <i class="fas fa-chart-bar me-2 text-green-600"></i>Atendidas vs Abandonadas
                            </h5>
                            <div style="height: 300px;">
                                <canvas id="barChart"></canvas>
                            </div>
                        </div>

                        <!-- Gráfico de Áreas: Tiempo de Espera -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h5 class="text-sm font-semibold text-gray-700 mb-4">
                                <i class="fas fa-chart-area me-2 text-cyan-600"></i>Tiempo de Espera Promedio
                            </h5>
                            <div style="height: 300px;">
                                <canvas id="areaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabla de KPIs por Hora -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h4 class="font-semibold text-gray-700">
                    <i class="fas fa-clock me-2 text-indigo-600"></i>Desglose por Franja Horaria
                </h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Volumen</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Atendidas</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Abandono</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Espera Prom.</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ASA</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agentes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__empty_1 = true; $__currentLoopData = $kpisPorHora; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hora => $datos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php if($datos['volumen'] > 0): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-medium text-gray-900"><?php echo e($datos['hora']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo e($datos['volumen']); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?php echo e($datos['atendidas']); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php
                                        $abandonoValor = $datos['abandono_valor'] ?? 0;
                                        $colorClass = $abandonoValor > 20 ? 'bg-red-100 text-red-800' : 
                                                     ($abandonoValor > 15 ? 'bg-yellow-100 text-yellow-800' : 
                                                     ($abandonoValor > 0 ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'));
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($colorClass); ?>">
                                        <?php if($abandonoValor > 20): ?>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php endif; ?>
                                        <?php echo e($datos['abandono_pct']); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php
                                        $esperaValor = $datos['tiempo_espera_valor'] ?? 0;
                                        $colorEspera = $esperaValor > 60 ? 'text-red-600' : ($esperaValor > 30 ? 'text-yellow-600' : 'text-gray-900');
                                    ?>
                                    <span class="font-medium <?php echo e($colorEspera); ?>"><?php echo e($datos['tiempo_espera_promedio']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="font-medium text-gray-900"><?php echo e($datos['asa']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <?php $__empty_2 = true; $__currentLoopData = $datos['agentes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agente): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-user me-1"></i><?php echo e($agente); ?>

                                            </span>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                            <span class="text-gray-400 text-xs">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                                    <p>No hay datos para el período seleccionado</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($totales['volumen'] > 0): ?>
                    <tfoot class="bg-gray-100">
                        <tr class="font-semibold">
                            <td class="px-6 py-4 text-gray-900">TOTALES</td>
                            <td class="px-6 py-4 text-center text-gray-900"><?php echo e($totales['volumen']); ?></td>
                            <td class="px-6 py-4 text-center text-green-700"><?php echo e($totales['atendidas']); ?></td>
                            <td class="px-6 py-4 text-center <?php echo e(($totales['abandono_valor'] ?? 0) > 20 ? 'text-red-700' : 'text-orange-700'); ?>"><?php echo e($totales['abandono_pct']); ?></td>
                            <td class="px-6 py-4 text-center text-gray-900"><?php echo e($totales['tiempo_espera_promedio']); ?></td>
                            <td class="px-6 py-4 text-center text-gray-900">-</td>
                            <td class="px-6 py-4 text-purple-700"><?php echo e(count($totales['agentes'])); ?> agentes</td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Alertas y Recomendaciones -->
        <?php
            $alertas = [];
            foreach ($kpisPorHora as $hora => $datos) {
                if ($datos['volumen'] == 0) continue;
                $abandonoValor = $datos['abandono_valor'] ?? 0;
                $esperaValor = $datos['tiempo_espera_valor'] ?? 0;
                
                if ($abandonoValor > 20) {
                    $alertas[] = ['tipo' => 'danger', 'icono' => 'exclamation-circle', 'mensaje' => "Hora {$datos['hora']}: Abandono crítico ({$datos['abandono_pct']})."];
                } elseif ($abandonoValor > 15) {
                    $alertas[] = ['tipo' => 'warning', 'icono' => 'exclamation-triangle', 'mensaje' => "Hora {$datos['hora']}: Abandono elevado ({$datos['abandono_pct']})."];
                }
                
                if ($esperaValor > 60) {
                    $alertas[] = ['tipo' => 'danger', 'icono' => 'clock', 'mensaje' => "Hora {$datos['hora']}: Tiempo de espera crítico ({$datos['tiempo_espera_promedio']})."];
                } elseif ($esperaValor > 30) {
                    $alertas[] = ['tipo' => 'warning', 'icono' => 'clock', 'mensaje' => "Hora {$datos['hora']}: Tiempo de espera prolongado ({$datos['tiempo_espera_promedio']})."];
                }
            }
        ?>

        <?php if(!empty($alertas)): ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-200">
                <h4 class="font-semibold text-yellow-800">
                    <i class="fas fa-bell me-2"></i>Alertas y Recomendaciones
                </h4>
            </div>
            <div class="p-4 space-y-2">
                <?php $__currentLoopData = $alertas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alerta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center gap-3 p-3 rounded-lg <?php echo e($alerta['tipo'] === 'danger' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700'); ?>">
                        <i class="fas fa-<?php echo e($alerta['icono']); ?> text-lg"></i>
                        <span><?php echo e($alerta['mensaje']); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php elseif($totales['volumen'] > 0): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3 text-green-700">
                <i class="fas fa-check-circle text-2xl"></i>
                <div>
                    <p class="font-semibold">¡Excelente!</p>
                    <p class="text-sm">No se detectaron problemas críticos en los KPIs analizados.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- KPIs por Cola -->
        <?php if(!empty($kpisPorCola)): ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h4 class="font-semibold text-gray-700">
                    <i class="fas fa-layer-group me-2 text-teal-600"></i>Estadísticas por Cola
                </h4>
                <?php if(!empty($agentesPorCola)): ?>
                <button type="button" onclick="document.getElementById('modalAgentes').classList.remove('hidden')" 
                        class="text-sm text-teal-600 hover:text-teal-800 font-medium">
                    <i class="fas fa-users me-1"></i>Ver detalle de agentes
                </button>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cola</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Volumen</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Atendidas</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">% Abandono</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ASA</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Agentes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__currentLoopData = $kpisPorCola; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cola => $datos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-teal-100 text-teal-800">
                                    <i class="fas fa-phone-volume me-2"></i><?php echo e($datos['cola']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="font-medium text-gray-900"><?php echo e($datos['volumen']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <?php echo e($datos['atendidas']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                    $abandonoValor = $datos['abandono_valor'] ?? 0;
                                    $colorClass = $abandonoValor > 20 ? 'bg-red-100 text-red-800' : 
                                                 ($abandonoValor > 15 ? 'bg-yellow-100 text-yellow-800' : 
                                                 ($abandonoValor > 0 ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'));
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($colorClass); ?>">
                                    <?php echo e($datos['abandono_pct']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="font-medium text-gray-900"><?php echo e($datos['asa']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php
                                    $agentesEnCola = $agentesPorCola[$datos['cola']] ?? [];
                                    $cantAgentes = count($agentesEnCola);
                                ?>
                                <?php if($cantAgentes > 0): ?>
                                <button type="button" 
                                        onclick="mostrarAgentes('<?php echo e($datos['cola']); ?>')"
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800 hover:bg-purple-200 transition-colors cursor-pointer">
                                    <i class="fas fa-users me-1"></i><?php echo e($cantAgentes); ?> agente<?php echo e($cantAgentes > 1 ? 's' : ''); ?>

                                </button>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">Sin datos</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rendimiento de Agentes (desde queue_call_details - sincronizado con queueapi) -->
        <?php if(!empty($rendimientoAgentes)): ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-indigo-50 border-b border-purple-200">
                <h4 class="font-semibold text-purple-800">
                    <i class="fas fa-user-tie me-2"></i>Rendimiento de Agentes
  
                </h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agente</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Llamadas Atendidas</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tasa Atención</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tiempo Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio/Llamada</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Espera Prom.</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__currentLoopData = $rendimientoAgentes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agente => $datos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-headset text-purple-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">Ext. <?php echo e($datos['agente']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo e($datos['llamadas_totales']); ?> llamadas asignadas</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-800">
                                    <?php echo e($datos['llamadas_atendidas']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                    $tasaColor = $datos['tasa_atencion'] >= 80 ? 'text-green-600' : 
                                                ($datos['tasa_atencion'] >= 60 ? 'text-yellow-600' : 'text-red-600');
                                ?>
                                <span class="font-medium <?php echo e($tasaColor); ?>"><?php echo e($datos['tasa_atencion']); ?>%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="font-medium text-gray-900"><?php echo e($datos['tiempo_total_formato']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo e($datos['tiempo_promedio_formato']); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800">
                                    <?php echo e($datos['tiempo_espera_promedio_formato']); ?>

                                </span>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <!-- Mensaje cuando no hay datos de agentes sincronizados -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-yellow-800">
                        Datos de agentes no sincronizados
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Para ver el rendimiento de agentes, ejecuta el siguiente comando:</p>
                        <code class="block mt-2 bg-yellow-100 p-2 rounded text-xs font-mono">
                            php artisan sync:queue-stats --days=7
                        </code>
                        <p class="mt-2 text-xs">
                            Este comando sincroniza los datos de <strong>queueapi</strong> a la base de datos local.
                            Puedes programarlo en el cron para ejecutarse automáticamente.
                        </p>
                        <?php if(auth()->user() && auth()->user()->isAdmin()): ?>
                        <button type="button" onclick="sincronizarColas()" 
                                class="mt-3 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-sm transition-colors duration-200">
                            <i class="fas fa-sync-alt me-1"></i>Sincronizar ahora
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Modal de Agentes por Cola -->
    <div id="modalAgentes" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 w-full max-w-2xl">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <h3 id="modalTitulo" class="text-xl font-semibold text-gray-900"></h3>
                    <button type="button" onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modalContenido" class="p-6 max-h-96 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación de sincronización -->
    <div id="modalSync" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 w-full max-w-md">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="text-center">
                        <i class="fas fa-sync-alt text-4xl text-purple-500 mb-4" id="syncModalIcon"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2" id="syncModalTitulo">Sincronizar Colas</h3>
                        <p class="text-gray-600 mb-4" id="syncModalMensaje">
                            ¿Cuántos días deseas sincronizar?
                        </p>
                        <div id="syncFormContainer">
                            <select id="syncDays" class="border-gray-300 rounded-md shadow-sm mb-4 w-full">
                                <option value="1">Último día</option>
                                <option value="7" selected>Últimos 7 días</option>
                                <option value="15">Últimos 15 días</option>
                                <option value="30">Últimos 30 días</option>
                            </select>
                            <div class="flex gap-3 justify-center">
                                <button type="button" onclick="cerrarModalSync()" 
                                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors duration-200 font-medium">
                                    Cancelar
                                </button>
                                <button type="button" onclick="ejecutarSincronizacion()" id="btnEjecutarSync"
                                        class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition-colors duration-200 font-medium shadow-sm">
                                    <i class="fas fa-sync-alt me-1"></i>Sincronizar
                                </button>
                            </div>
                        </div>
                        <div id="syncResultContainer" class="hidden">
                            <button type="button" onclick="location.reload()" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors duration-200 font-medium shadow-sm">
                                <i class="fas fa-redo me-1"></i>Recargar página
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
        // Datos para gráficos (convertidos a arrays indexados para evitar problemas con claves numéricas)
        const kpisPorHora = <?php echo json_encode(array_values($kpisPorHora), 15, 512) ?>;
        const kpisPorCola = <?php echo json_encode(array_values($kpisPorCola ?? []), 15, 512) ?>;
        const totales = <?php echo json_encode($totales, 15, 512) ?>;

        // ============================================
        // CONFIGURACIÓN DE CHART.JS
        // ============================================
        Chart.defaults.font.family = "'Figtree', sans-serif";
        Chart.defaults.color = '#6B7280';

        // ============================================
        // 1. LINE CHART - VOLUMEN POR HORA
        // ============================================
        const pieCtx = document.getElementById('pieChart');
        if (pieCtx && kpisPorCola.length > 0) {
            const colasLabels = kpisPorCola.map(c => 'Cola ' + c.cola);
            const colasData = kpisPorCola.map(c => c.volumen);
            
            const colores = [
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', 
                '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16'
            ];

            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: colasLabels,
                    datasets: [{
                        data: colasData,
                        backgroundColor: colores,
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        },
                        datalabels: {
                            color: '#FFFFFF',
                            font: {
                                weight: 'bold',
                                size: 14
                            },
                            formatter: function(value, context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(0);
                                return percentage + '%';
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }

        // ============================================
        // 3. LINE CHART - VOLUMEN POR HORA
        // ============================================
        const lineCtx = document.getElementById('lineChart');
        if (lineCtx) {
            const horasLabels = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.hora.split(' - ')[0]);
            const volumenData = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.volumen);

            new Chart(lineCtx, {
                type: 'line',
                data: {
                    labels: horasLabels,
                    datasets: [{
                        label: 'Volumen de Llamadas',
                        data: volumenData,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    return 'Llamadas: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // ============================================
        // 2. BAR CHART - ATENDIDAS VS ABANDONADAS
        // ============================================
        const barCtx = document.getElementById('barChart');
        if (barCtx) {
            const horasLabels = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.hora.split(' - ')[0]);
            const atendidasData = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.atendidas);
            const abandonadasData = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.abandonadas);

            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: horasLabels,
                    datasets: [
                        {
                            label: 'Atendidas',
                            data: atendidasData,
                            backgroundColor: '#10B981',
                            borderRadius: 4
                        },
                        {
                            label: 'Abandonadas',
                            data: abandonadasData,
                            backgroundColor: '#EF4444',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' llamadas';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            stacked: false,
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            stacked: false,
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // ============================================
        // 3. AREA CHART - TIEMPO DE ESPERA PROMEDIO
        // ============================================
        const areaCtx = document.getElementById('areaChart');
        if (areaCtx) {
            const horasLabels = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.hora.split(' - ')[0]);
            const esperaData = kpisPorHora
                .filter(h => h.volumen > 0)
                .map(h => h.tiempo_espera_valor || 0);

            new Chart(areaCtx, {
                type: 'line',
                data: {
                    labels: horasLabels,
                    datasets: [{
                        label: 'Tiempo de Espera (segundos)',
                        data: esperaData,
                        borderColor: '#06B6D4',
                        backgroundColor: function(context) {
                            const ctx = context.chart.ctx;
                            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                            gradient.addColorStop(0, 'rgba(6, 182, 212, 0.3)');
                            gradient.addColorStop(1, 'rgba(6, 182, 212, 0.01)');
                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#06B6D4',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        segment: {
                            borderColor: function(context) {
                                const value = context.p1.parsed.y;
                                if (value > 60) return '#EF4444'; // Rojo si > 60s
                                if (value > 30) return '#F59E0B'; // Amarillo si > 30s
                                return '#06B6D4'; // Cyan normal
                            }
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return 'Espera: ' + context.parsed.y + ' segundos';
                                },
                                afterLabel: function(context) {
                                    const value = context.parsed.y;
                                    if (value > 60) return '🔴 Crítico';
                                    if (value > 30) return '⚠️ Elevado';
                                    return '✅ Normal';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return value + 's';
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // ============================================
        // FUNCIONES DE MODAL Y SINCRONIZACIÓN
        // ============================================
        // Datos de agentes por cola (pasados desde PHP)
        const agentesPorCola = <?php echo json_encode($agentesPorCola ?? [], 15, 512) ?>;

        // Mostrar agentes de una cola específica
        function mostrarAgentes(cola) {
            const modal = document.getElementById('modalAgentes');
            const titulo = document.getElementById('modalTitulo');
            const contenido = document.getElementById('modalContenido');
            
            titulo.innerHTML = `<i class="fas fa-users me-2"></i>Agentes de la Cola ${cola}`;
            
            const agentes = agentesPorCola[cola] || [];
            
            if (agentes.length === 0) {
                contenido.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-user-slash text-4xl mb-3 text-gray-300"></i>
                        <p>No hay datos de agentes para esta cola en el período seleccionado.</p>
                        <p class="text-sm mt-2">Ejecuta la sincronización para obtener los datos.</p>
                    </div>
                `;
            } else {
                let html = `
                    <div class="mb-4 text-sm text-gray-600">
                        <i class="fas fa-info-circle me-1"></i>
                        Mostrando ${agentes.length} agente${agentes.length > 1 ? 's' : ''} para el período seleccionado
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agente</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Intentos</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Contestadas</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Efectividad</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Tiempo Total</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Espera Prom.</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                `;
                
                agentes.forEach(agente => {
                    const efectividadColor = agente.efectividad >= 80 ? 'text-green-600' : 
                                            (agente.efectividad >= 60 ? 'text-yellow-600' : 'text-red-600');
                    
                    html += `
                        <tr class="hover:bg-purple-50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-purple-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-headset text-purple-600 text-sm"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">Ext. ${agente.agente}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="text-sm text-gray-900">${agente.intentos}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ${agente.contestadas}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="font-medium ${efectividadColor}">${agente.efectividad}%</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="text-sm text-gray-900">${agente.tiempo_formato}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="text-sm text-gray-900">${agente.espera_promedio}s</span>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                contenido.innerHTML = html;
            }
            
            modal.classList.remove('hidden');
        }

        // Cerrar modal de agentes
        function cerrarModal() {
            document.getElementById('modalAgentes').classList.add('hidden');
        }

        // Sincronizar colas (abrir modal)
        function sincronizarColas() {
            document.getElementById('modalSync').classList.remove('hidden');
            document.getElementById('syncFormContainer').classList.remove('hidden');
            document.getElementById('syncResultContainer').classList.add('hidden');
            document.getElementById('syncModalIcon').className = 'fas fa-sync-alt text-4xl text-purple-500 mb-4';
            document.getElementById('syncModalTitulo').textContent = 'Sincronizar Colas';
            document.getElementById('syncModalMensaje').textContent = '¿Cuántos días deseas sincronizar?';
        }

        // Cerrar modal de sincronización
        function cerrarModalSync() {
            document.getElementById('modalSync').classList.add('hidden');
        }

        // Ejecutar sincronización
        async function ejecutarSincronizacion() {
            const days = document.getElementById('syncDays').value;
            const btn = document.getElementById('btnEjecutarSync');
            const icon = document.getElementById('syncModalIcon');
            const titulo = document.getElementById('syncModalTitulo');
            const mensaje = document.getElementById('syncModalMensaje');
            
            // Mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sincronizando...';
            icon.className = 'fas fa-spinner fa-spin text-4xl text-purple-500 mb-4';
            titulo.textContent = 'Sincronizando...';
            mensaje.textContent = 'Por favor espera, esto puede tardar unos segundos...';
            
            try {
                const response = await fetch('<?php echo e(route("stats.sync-colas")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                    },
                    body: JSON.stringify({ days: days })
                });
                
                const data = await response.json();
                
                document.getElementById('syncFormContainer').classList.add('hidden');
                document.getElementById('syncResultContainer').classList.remove('hidden');
                
                if (data.success) {
                    icon.className = 'fas fa-check-circle text-4xl text-green-500 mb-4';
                    titulo.textContent = '¡Sincronización completada!';
                    
                    // Mensaje diferente si no hay nuevos registros
                    if (data.insertados > 0) {
                        mensaje.innerHTML = `
                            <span class="text-green-600">✅ ${data.insertados} registros nuevos insertados</span><br>
                            <span class="text-gray-500">⏭️ ${data.omitidos} registros omitidos (ya existían)</span>
                        `;
                    } else {
                        mensaje.innerHTML = `
                            <span class="text-blue-600"><i class="fas fa-info-circle me-1"></i>Los datos ya están actualizados</span><br>
                            <span class="text-gray-500">Se encontraron ${data.omitidos} registros que ya existían en la base de datos.</span><br>
                            <span class="text-gray-400 text-sm mt-2 block">La página se recargará para mostrar los datos actualizados.</span>
                        `;
                        
                        // Recargar la página después de 2 segundos
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    icon.className = 'fas fa-exclamation-circle text-4xl text-red-500 mb-4';
                    titulo.textContent = 'Error en sincronización';
                    mensaje.textContent = data.message || 'Ocurrió un error durante la sincronización.';
                }
            } catch (error) {
                document.getElementById('syncFormContainer').classList.add('hidden');
                document.getElementById('syncResultContainer').classList.remove('hidden');
                icon.className = 'fas fa-exclamation-circle text-4xl text-red-500 mb-4';
                titulo.textContent = 'Error';
                mensaje.textContent = 'Error de conexión: ' + error.message;
            }
        }

        // Botón de sincronización en el header
        document.getElementById('btnSyncColas')?.addEventListener('click', sincronizarColas);

        // Cerrar modales al hacer clic fuera
        document.getElementById('modalAgentes').addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
        document.getElementById('modalSync').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalSync();
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
<?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/stats/kpi-turnos.blade.php ENDPATH**/ ?>