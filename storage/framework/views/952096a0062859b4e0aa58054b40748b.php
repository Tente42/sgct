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
            <?php echo e(__('Gestión de Usuarios')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-users me-2"></i>Administración de Usuarios
                </h3>
                <span class="text-gray-500 text-sm">Total de usuarios: <?php echo e($users->total()); ?></span>
            </div>
            <a href="<?php echo e(route('users.create')); ?>" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 text-white font-semibold hover:bg-green-700 shadow-sm transition-colors">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo Usuario</span>
            </a>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Éxito!</strong>
                <span class="block sm:inline"><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo e(session('error')); ?></span>
            </div>
        <?php endif; ?>

        <?php if(session('warning')): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Aviso!</strong>
                <span class="block sm:inline"><?php echo e(session('warning')); ?></span>
            </div>
        <?php endif; ?>

        <!-- Leyenda de Plantillas de Rol -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <h4 class="font-semibold text-blue-800 mb-2">
                <i class="fas fa-info-circle mr-1"></i> Plantillas de Roles Disponibles
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                <?php $__currentLoopData = $roleTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="bg-white p-2 rounded border">
                    <span class="font-semibold text-gray-700"><?php echo e($template['name']); ?></span>
                    <p class="text-xs text-gray-500"><?php echo e($template['description']); ?></p>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

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
                        <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50 <?php echo e($user->id === auth()->id() ? 'bg-blue-50' : ''); ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full <?php echo e($user->isAdmin() ? 'bg-yellow-500' : 'bg-gray-400'); ?> text-white font-bold">
                                            <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo e($user->name); ?>

                                            <?php if($user->id === auth()->id()): ?>
                                                <span class="text-xs text-blue-600">(Tú)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Creado: <?php echo e($user->created_at->format('d/m/Y')); ?>

                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo e($user->email); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo e($user->role === 'admin' ? 'bg-yellow-100 text-yellow-800' : ''); ?>

                                    <?php echo e($user->role === 'supervisor' ? 'bg-blue-100 text-blue-800' : ''); ?>

                                    <?php echo e($user->role === 'user' ? 'bg-gray-100 text-gray-800' : ''); ?>">
                                    <?php echo e($user->getRoleDisplayName()); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    <?php if($user->isAdmin()): ?>
                                        <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">Todos</span>
                                    <?php else: ?>
                                        <?php if($user->can_sync_calls): ?>
                                            <span class="px-1 py-0.5 text-xs bg-blue-100 text-blue-700 rounded" title="Sincronizar">Sync</span>
                                        <?php endif; ?>
                                        <?php if($user->can_edit_extensions): ?>
                                            <span class="px-1 py-0.5 text-xs bg-purple-100 text-purple-700 rounded" title="Editar Anexos">Ext</span>
                                        <?php endif; ?>
                                        <?php if($user->can_edit_rates): ?>
                                            <span class="px-1 py-0.5 text-xs bg-orange-100 text-orange-700 rounded" title="Editar Tarifas">Tar</span>
                                        <?php endif; ?>
                                        <?php if($user->can_manage_pbx): ?>
                                            <span class="px-1 py-0.5 text-xs bg-red-100 text-red-700 rounded" title="Gestionar PBX">PBX</span>
                                        <?php endif; ?>
                                        <?php if($user->can_export_pdf): ?>
                                            <span class="px-1 py-0.5 text-xs bg-pink-100 text-pink-700 rounded" title="Exportar PDF">PDF</span>
                                        <?php endif; ?>
                                        <?php if($user->can_export_excel): ?>
                                            <span class="px-1 py-0.5 text-xs bg-green-100 text-green-700 rounded" title="Exportar Excel">XLS</span>
                                        <?php endif; ?>
                                        <?php if(!$user->can_sync_calls && !$user->can_edit_extensions && !$user->can_edit_rates && !$user->can_manage_pbx && !$user->can_export_pdf && !$user->can_export_excel): ?>
                                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">Solo lectura</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <?php if($user->id !== auth()->id()): ?>
                                    <a href="<?php echo e(route('users.edit', $user)); ?>" 
                                       class="inline-flex items-center gap-1 px-3 py-2 rounded border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300 mr-1"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                        <span>Editar</span>
                                    </a>
                                    <form action="<?php echo e(route('users.destroy', $user)); ?>" method="POST" class="inline" 
                                          onsubmit="return confirm('¿Estás seguro de eliminar a <?php echo e($user->name); ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" 
                                                class="inline-flex items-center gap-1 px-3 py-2 rounded border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:border-red-300"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No hay usuarios registrados
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-200">
                <?php echo e($users->links()); ?>

            </div>
        </div>

    </div>
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
<?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/users/index.blade.php ENDPATH**/ ?>