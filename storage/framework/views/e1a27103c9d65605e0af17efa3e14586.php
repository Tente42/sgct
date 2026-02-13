<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('favicon.ico')); ?>v=2">

    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="font-sans antialiased bg-gray-50" x-data="{ sidebarOpen: false }">
    <!-- Sidebar - Parte Izquierda -->
    <?php echo $__env->make('layouts.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- Indicador de Sincronización en Segundo Plano -->
    <?php if(auth()->guard()->check()): ?>
        <?php if(session('active_pbx_id')): ?>
            <div id="syncIndicator" 
                 class="fixed bottom-4 right-4 z-50 hidden"
                 style="max-width: 350px;">
                <div class="bg-white rounded-lg shadow-2xl border border-blue-200 overflow-hidden">
                    <div class="bg-blue-600 px-4 py-2 flex items-center gap-2">
                        <i class="fas fa-sync-alt fa-spin text-white"></i>
                        <span class="text-white font-semibold text-sm">Sincronización en Progreso</span>
                    </div>
                    <div class="px-4 py-3">
                        <p id="syncProgressText" class="text-gray-600 text-sm">Cargando...</p>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-blue-600 h-1.5 rounded-full animate-pulse" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                (function() {
                    const pbxId = <?php echo e(session('active_pbx_id')); ?>;
                    const indicator = document.getElementById('syncIndicator');
                    const progressText = document.getElementById('syncProgressText');
                    
                    function checkSync() {
                        fetch(`/pbx/sync-status/${pbxId}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.syncing) {
                                    indicator.classList.remove('hidden');
                                    progressText.textContent = data.progress || 'Sincronizando...';
                                    
                                    // Cambiar color si hay error
                                    if (data.progress && data.progress.includes('❌')) {
                                        indicator.querySelector('.bg-blue-600').classList.replace('bg-blue-600', 'bg-red-600');
                                    } else if (data.progress && data.progress.includes('✓')) {
                                        indicator.querySelector('.bg-blue-600')?.classList.replace('bg-blue-600', 'bg-green-600');
                                    }
                                } else {
                                    indicator.classList.add('hidden');
                                }
                            })
                            .catch(() => {});
                    }
                    
                    // Verificar cada 2 segundos
                    checkSync();
                    setInterval(checkSync, 2000);
                })();
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Main content - Con margen para el sidebar fijo -->
    <div class="flex flex-col min-h-screen" style="margin-left: 16rem;">
        <!-- Page Heading -->
        <?php if(isset($header)): ?>
            <header class="bg-white shadow-sm flex-shrink-0">
                <div class="w-full py-6 px-4 sm:px-6 lg:px-8">
                    <?php echo e($header); ?>

                </div>
            </header>
        <?php endif; ?>

        <!-- Page Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 page-transition-slide">
            <?php echo e($slot); ?>

        </main>
    </div>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/layouts/app.blade.php ENDPATH**/ ?>