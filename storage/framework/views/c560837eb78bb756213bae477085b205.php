<!DOCTYPE html>
<html>
<head>
    <title>Seleccionar Central - <?php echo e(config('app.name')); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .header h1 { margin: 0; color: #333; font-size: 1.5rem; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .user-info { color: #666; }
        .btn-logout { 
            background: none; 
            border: none; 
            color: #666; 
            cursor: pointer; 
            font-size: 0.9rem;
        }
        .btn-logout:hover { color: #333; }
        
        .page-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .page-title h2 { color: #333; margin-bottom: 10px; }
        .page-title p { color: #666; margin: 0; }
        
        .btn-add {
            display: inline-block;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 15px;
        }
        .btn-add:hover { background: #218838; }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card.active { border: 2px solid #28a745; }
        
        .card-header {
            background: #343a40;
            color: white;
            padding: 15px;
        }
        .card-header h3 { margin: 0 0 5px 0; font-size: 1.1rem; }
        .card-header .ip { color: #adb5bd; font-family: monospace; font-size: 0.9rem; }
        .badge-active {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
        
        .card-body { padding: 15px; }
        .card-body .info { color: #666; font-size: 0.9rem; margin-bottom: 15px; }
        .card-body .info span { color: #333; }
        
        .btn-connect {
            display: block;
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            margin-bottom: 10px;
        }
        .btn-connect:hover { background: #0056b3; }
        
        .btn-disconnect {
            display: block;
            width: 100%;
            padding: 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .btn-disconnect:hover { background: #5a6268; }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        .btn-edit, .btn-delete {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-edit:hover { background: #e0a800; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .btn-delete:disabled { background: #ccc; cursor: not-allowed; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .empty-state i { font-size: 4rem; color: #ccc; margin-bottom: 20px; }
        .empty-state h3 { color: #666; margin-bottom: 10px; }
        .empty-state p { color: #999; }
        
        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal {
            background: white;
            border-radius: 8px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }
        .modal-header {
            background: #343a40;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .modal-header h3 { margin: 0; }
        .modal-body { padding: 20px; }
        .modal-body label { 
            display: block; 
            margin-bottom: 5px; 
            color: #333;
            font-weight: 500;
        }
        .modal-body input[type="text"],
        .modal-body input[type="number"],
        .modal-body input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .modal-body .row {
            display: flex;
            gap: 15px;
        }
        .modal-body .row > div { flex: 1; }
        .modal-body .row > div:last-child { flex: 0 0 100px; }
        .modal-body .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .modal-body .checkbox-group input { width: auto; margin: 0; }
        .modal-body .hint { font-size: 0.8rem; color: #666; margin-top: -10px; margin-bottom: 15px; }
        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #5a6268; }
        .btn-save {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-save:hover { background: #0056b3; }
    </style>
</head>
<body x-data="pbxManager()">
    <div class="container">
        
        <div class="header">
            <h1><i class="fas fa-phone-volume"></i> <?php echo e(config('app.name')); ?></h1>
            <div class="header-right">
                <span class="user-info">
                    <i class="fas fa-user"></i> <?php echo e(auth()->user()->name); ?>

                    <?php if(auth()->user()->isAdmin()): ?>
                        <span style="background: #ffc107; color: #333; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px;">Admin</span>
                    <?php endif; ?>
                </span>
                <form action="<?php echo e(route('logout')); ?>" method="POST" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Salir</button>
                </form>
            </div>
        </div>

        
        <?php if(session('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if(session('warning')): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo e(session('warning')); ?></div>
        <?php endif; ?>
        <?php if(session('info')): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> <?php echo e(session('info')); ?></div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> 
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php echo e($error); ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        
        <div class="page-title">
            <h2><i class="fas fa-server"></i> Seleccionar Central</h2>
            <p>Elige la central telefónica con la que deseas trabajar</p>
            <?php if(auth()->user()->isAdmin()): ?>
            <button @click="openCreateModal()" class="btn-add">
                <i class="fas fa-plus"></i> Agregar Nueva Central
            </button>
            <?php endif; ?>
        </div>

        
        <?php if($connections->isEmpty()): ?>
            <div class="empty-state">
                <i class="fas fa-server"></i>
                <h3>No hay centrales configuradas</h3>
                <p>Agrega tu primera central PBX para comenzar</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php $__currentLoopData = $connections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $connection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $cardClass = session('active_pbx_id') === $connection->id ? 'active' : '';
                        if ($connection->status === 'syncing') $cardClass .= ' syncing';
                        if ($connection->status === 'pending') $cardClass .= ' pending';
                        if ($connection->status === 'error') $cardClass .= ' error';
                    ?>
                    <div class="card <?php echo e($cardClass); ?>">
                        
                        <?php if($connection->status === 'syncing' && !auth()->user()->isAdmin()): ?>
                            <div class="sync-overlay">
                                <i class="fas fa-sync fa-spin"></i>
                                <p>Sincronizando datos...</p>
                                <p style="font-size: 0.8rem; color: #999;">Disponible pronto</p>
                            </div>
                        <?php endif; ?>

                        <div class="card-header">
                            <h3>
                                <?php echo e($connection->name ?? 'Sin nombre'); ?>

                                <?php if(session('active_pbx_id') === $connection->id): ?>
                                    <span class="badge-active">ACTIVA</span>
                                <?php endif; ?>
                                <?php if(auth()->user()->isAdmin() && $connection->status !== 'ready'): ?>
                                    <span class="badge-status badge-<?php echo e($connection->status); ?>">
                                        <?php switch($connection->status):
                                            case ('pending'): ?> PENDIENTE <?php break; ?>
                                            <?php case ('syncing'): ?> SINCRONIZANDO <?php break; ?>
                                            <?php case ('error'): ?> ERROR <?php break; ?>
                                            <?php default: ?> <?php echo e(strtoupper($connection->status)); ?>

                                        <?php endswitch; ?>
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <div class="ip"><i class="fas fa-network-wired"></i> <?php echo e($connection->ip); ?>:<?php echo e($connection->port); ?></div>
                        </div>
                        <div class="card-body">
                            <div class="info">
                                <i class="fas fa-user"></i> Usuario: <span><?php echo e($connection->username); ?></span><br>
                                <i class="fas fa-shield-alt"></i> SSL: 
                                <span><?php echo e($connection->verify_ssl ? 'Verificado' : 'Sin verificar'); ?></span>
                                <?php if(auth()->user()->isAdmin() && $connection->last_sync_at): ?>
                                    <br><i class="fas fa-clock"></i> Última sync: 
                                    <span><?php echo e($connection->last_sync_at->diffForHumans()); ?></span>
                                <?php endif; ?>
                            </div>

                            
                            <?php if(auth()->user()->isAdmin() && $connection->status === 'error' && $connection->sync_message): ?>
                                <div style="background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 0.85rem;">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo e($connection->sync_message); ?>

                                </div>
                            <?php endif; ?>

                            
                            <?php if(auth()->user()->isAdmin() && $connection->status === 'pending'): ?>
                                <a href="<?php echo e(route('pbx.setup', $connection)); ?>" class="btn-connect" style="background: #ffc107; color: #333;">
                                    <i class="fas fa-cog"></i> CONFIGURAR CENTRAL
                                </a>
                            <?php elseif(auth()->user()->isAdmin() && $connection->status === 'syncing'): ?>
                                <a href="<?php echo e(route('pbx.setup', $connection)); ?>" class="btn-connect" style="background: #17a2b8;">
                                    <i class="fas fa-sync fa-spin"></i> VER PROGRESO
                                </a>
                            <?php elseif(auth()->user()->isAdmin() && $connection->status === 'error'): ?>
                                <a href="<?php echo e(route('pbx.setup', $connection)); ?>" class="btn-connect" style="background: #dc3545;">
                                    <i class="fas fa-redo"></i> REINTENTAR SYNC
                                </a>
                            <?php elseif(session('active_pbx_id') === $connection->id): ?>
                                <?php if(auth()->user()->isAdmin()): ?>
                                <form action="<?php echo e(route('pbx.disconnect')); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="btn-disconnect">
                                        <i class="fas fa-power-off"></i> DESCONECTAR
                                    </button>
                                </form>
                                <?php else: ?>
                                <div style="padding: 10px; background: #e9ecef; border-radius: 4px; text-align: center; color: #666; margin-bottom: 10px;">
                                    <i class="fas fa-check-circle" style="color: #28a745;"></i> Central Activa
                                </div>
                                <?php endif; ?>
                            <?php elseif($connection->status === 'ready'): ?>
                                <a href="<?php echo e(route('pbx.select', $connection)); ?>" class="btn-connect">
                                    <i class="fas fa-plug"></i> CONECTAR
                                </a>
                            <?php endif; ?>

                            <?php if(auth()->user()->isAdmin()): ?>
                            <div class="card-actions">
                                <button @click="openEditModal(<?php echo e($connection->toJson()); ?>)" class="btn-edit">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button @click="confirmDelete(<?php echo e($connection->id); ?>, '<?php echo e($connection->name ?? $connection->ip); ?>')" 
                                        class="btn-delete"
                                        <?php echo e(session('active_pbx_id') === $connection->id ? 'disabled' : ''); ?>>
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>

    
    <div x-show="showModal" class="modal-backdrop" style="display: none;" x-transition>
        <div class="modal" @click.outside="closeModal()">
            <form :action="formAction" method="POST">
                <?php echo csrf_field(); ?>
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="modal-header">
                    <h3><i class="fas" :class="isEditing ? 'fa-edit' : 'fa-plus-circle'"></i> 
                        <span x-text="isEditing ? 'Editar Central' : 'Nueva Central'"></span>
                    </h3>
                </div>

                <div class="modal-body">
                    <label>Nombre (opcional)</label>
                    <input type="text" name="name" x-model="form.name" placeholder="Ej: Central Principal">

                    <div class="row">
                        <div>
                            <label>Dirección IP *</label>
                            <input type="text" name="ip" x-model="form.ip" required placeholder="192.168.1.100">
                        </div>
                        <div>
                            <label>Puerto *</label>
                            <input type="number" name="port" x-model="form.port" required placeholder="443">
                        </div>
                    </div>

                    <label>Usuario *</label>
                    <input type="text" name="username" x-model="form.username" required placeholder="admin">

                    <label>Contraseña <span x-show="!isEditing">*</span></label>
                    <input type="password" name="password" x-model="form.password" :required="!isEditing" placeholder="••••••••">
                    <p x-show="isEditing" class="hint">Dejar vacío para mantener la actual</p>

                    <div class="checkbox-group">
                        <input type="checkbox" name="verify_ssl" x-model="form.verify_ssl" value="1" id="verify_ssl">
                        <label for="verify_ssl" style="margin:0;">Verificar certificado SSL</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" @click="closeModal()" class="btn-cancel">Cancelar</button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> <span x-text="isEditing ? 'Actualizar' : 'Guardar'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div x-show="showDeleteModal" class="modal-backdrop" style="display: none;" x-transition>
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header" style="background: #dc3545;">
                <h3><i class="fas fa-exclamation-triangle"></i> Eliminar Central</h3>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de eliminar <strong x-text="deleteConnectionName"></strong>?</p>
                <p style="color: #dc3545; font-size: 0.9rem;">
                    <i class="fas fa-warning"></i> Se eliminarán todas las llamadas y extensiones asociadas.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" @click="showDeleteModal = false" class="btn-cancel">Cancelar</button>
                <form :action="deleteAction" method="POST" style="margin:0;">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn-delete" style="padding: 10px 20px;">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function pbxManager() {
            return {
                showModal: false,
                showDeleteModal: false,
                isEditing: false,
                formAction: '<?php echo e(route("pbx.store")); ?>',
                deleteAction: '',
                deleteConnectionName: '',
                form: { name: '', ip: '', port: '', username: '', password: '', verify_ssl: false },

                openCreateModal() {
                    this.isEditing = false;
                    this.formAction = '<?php echo e(route("pbx.store")); ?>';
                    this.form = { name: '', ip: '', port: '', username: '', password: '', verify_ssl: false };
                    this.showModal = true;
                },

                openEditModal(connection) {
                    this.isEditing = true;
                    this.formAction = '<?php echo e(url("pbx")); ?>/' + connection.id;
                    this.form = {
                        name: connection.name || '',
                        ip: connection.ip,
                        port: connection.port,
                        username: connection.username,
                        password: '',
                        verify_ssl: connection.verify_ssl
                    };
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                },

                confirmDelete(id, name) {
                    this.deleteAction = '<?php echo e(url("pbx")); ?>/' + id;
                    this.deleteConnectionName = name;
                    this.showDeleteModal = true;
                }
            }
        }
    </script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/pbx/index.blade.php ENDPATH**/ ?>