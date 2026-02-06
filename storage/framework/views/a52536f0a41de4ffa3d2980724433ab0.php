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
            <?php echo e(__('Crear Nuevo Usuario')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">

        <div class="mb-4">
            <a href="<?php echo e(route('users.index')); ?>" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left"></i>
                <span>Volver al listado</span>
            </a>
        </div>

        <?php if($errors->any()): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <ul class="mt-2 list-disc list-inside">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="bg-gray-800 px-6 py-4">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fas fa-user-plus"></i>
                    Nuevo Usuario
                </h3>
            </div>

            <form action="<?php echo e(route('users.store')); ?>" method="POST" class="p-6" x-data="userForm()">
                <?php echo csrf_field(); ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Columna Izquierda: Datos del Usuario -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-700 border-b pb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i> Información del Usuario
                        </h4>

                        <!-- Nombre -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre Completo <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="<?php echo e(old('name')); ?>" required
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Juan Pérez">
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Correo Electrónico <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" id="email" value="<?php echo e(old('email')); ?>" required
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="correo@ejemplo.com">
                        </div>

                        <!-- Contraseña -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                Contraseña <span class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password" id="password" required
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Mínimo 6 caracteres">
                        </div>

                        <!-- Confirmar Contraseña -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirmar Contraseña <span class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Repite la contraseña">
                        </div>

                        <!-- Rol Base -->
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                Rol Base <span class="text-red-500">*</span>
                            </label>
                            <select name="role" id="role" x-model="selectedRole" @change="updateRole()"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="user">Usuario</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrador</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <span x-show="selectedRole === 'admin'" class="text-yellow-600">
                                    <i class="fas fa-crown"></i> El administrador tiene acceso total y no se pueden modificar sus permisos.
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Columna Derecha: Permisos -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-700 border-b pb-2">
                            <i class="fas fa-key text-gray-400 mr-1"></i> Permisos
                        </h4>

                        <!-- Selector de Plantilla -->
                        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                            <label class="block text-sm font-medium text-blue-800 mb-2">
                                <i class="fas fa-magic mr-1"></i> Aplicar Plantilla Predefinida
                            </label>
                            <select @change="applyTemplate($event.target.value)" 
                                    class="w-full rounded-md border-blue-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    :disabled="selectedRole === 'admin'">
                                <option value="">-- Seleccionar plantilla --</option>
                                <?php $__currentLoopData = $roleTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($key); ?>"><?php echo e($template['name']); ?> - <?php echo e($template['description']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <!-- Lista de Permisos -->
                        <div class="space-y-3" :class="{ 'opacity-50 pointer-events-none': selectedRole === 'admin' }">
                            
                            <!-- Acciones de API -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h5 class="text-sm font-semibold text-gray-600 mb-2">
                                    <i class="fas fa-server text-orange-500 mr-1"></i> Acciones de API (Central)
                                </h5>
                                
                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_sync_calls" :value="permissions.can_sync_calls ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_sync_calls"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Sincronizar Llamadas</span>
                                        <p class="text-xs text-gray-500">Puede ejecutar sincronización de CDRs desde la central</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_edit_extensions" :value="permissions.can_edit_extensions ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_edit_extensions"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Editar Anexos</span>
                                        <p class="text-xs text-gray-500">Puede modificar información de anexos en la central</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_update_ips" :value="permissions.can_update_ips ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_update_ips"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Actualizar IPs</span>
                                        <p class="text-xs text-gray-500">Puede actualizar las IPs de los anexos desde la central</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_manage_pbx" :value="permissions.can_manage_pbx ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_manage_pbx"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Gestionar Centrales PBX</span>
                                        <p class="text-xs text-gray-500">Puede crear, editar y eliminar centrales telefónicas</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Configuración -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h5 class="text-sm font-semibold text-gray-600 mb-2">
                                    <i class="fas fa-cog text-purple-500 mr-1"></i> Configuración
                                </h5>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_edit_rates" :value="permissions.can_edit_rates ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_edit_rates"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Editar Tarifas</span>
                                        <p class="text-xs text-gray-500">Puede modificar los precios por minuto de llamadas</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Reportes -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h5 class="text-sm font-semibold text-gray-600 mb-2">
                                    <i class="fas fa-file-alt text-green-500 mr-1"></i> Reportes y Exportación
                                </h5>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_export_pdf" :value="permissions.can_export_pdf ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_export_pdf"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Exportar PDF</span>
                                        <p class="text-xs text-gray-500">Puede descargar reportes en formato PDF</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_export_excel" :value="permissions.can_export_excel ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_export_excel"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Exportar Excel</span>
                                        <p class="text-xs text-gray-500">Puede descargar reportes en formato Excel</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 py-2 cursor-pointer hover:bg-gray-100 px-2 rounded">
                                    <input type="hidden" name="can_view_charts" :value="permissions.can_view_charts ? '1' : '0'">
                                    <input type="checkbox" x-model="permissions.can_view_charts"
                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Ver Gráficos</span>
                                        <p class="text-xs text-gray-500">Puede acceder a la sección de gráficos y estadísticas</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div x-show="selectedRole === 'admin'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <p class="text-sm text-yellow-700">
                                <i class="fas fa-info-circle mr-1"></i>
                                Los administradores tienen automáticamente todos los permisos.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="mt-6 pt-4 border-t flex justify-end gap-3">
                    <a href="<?php echo e(route('users.index')); ?>" 
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-semibold">
                        <i class="fas fa-save mr-1"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>

    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function userForm() {
            return {
                selectedRole: '<?php echo e(old('role', 'user')); ?>',
                permissions: {
                    can_sync_calls: <?php echo e(old('can_sync_calls') ? 'true' : 'false'); ?>,
                    can_edit_extensions: <?php echo e(old('can_edit_extensions') ? 'true' : 'false'); ?>,
                    can_update_ips: <?php echo e(old('can_update_ips') ? 'true' : 'false'); ?>,
                    can_edit_rates: <?php echo e(old('can_edit_rates') ? 'true' : 'false'); ?>,
                    can_manage_pbx: <?php echo e(old('can_manage_pbx') ? 'true' : 'false'); ?>,
                    can_export_pdf: <?php echo e(old('can_export_pdf', true) ? 'true' : 'false'); ?>,
                    can_export_excel: <?php echo e(old('can_export_excel', true) ? 'true' : 'false'); ?>,
                    can_view_charts: <?php echo e(old('can_view_charts', true) ? 'true' : 'false'); ?>,
                },
                templates: <?php echo json_encode($roleTemplates, 15, 512) ?>,

                updateRole() {
                    if (this.selectedRole === 'admin') {
                        // Admin has all permissions
                        for (let key in this.permissions) {
                            this.permissions[key] = true;
                        }
                    }
                },

                applyTemplate(templateKey) {
                    if (templateKey && this.templates[templateKey]) {
                        const perms = this.templates[templateKey].permissions;
                        for (let key in perms) {
                            if (this.permissions.hasOwnProperty(key)) {
                                this.permissions[key] = perms[key];
                            }
                        }
                    }
                }
            }
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
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/users/create.blade.php ENDPATH**/ ?>