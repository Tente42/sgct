<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Llamadas</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 15px; }
        h1 { font-size: 16px; margin: 0 0 8px 0; }
        .header { text-align: center; margin-bottom: 12px; border-bottom: 1px solid #000; padding-bottom: 8px; }
        .info { font-size: 10px; margin-bottom: 10px; }
        .resumen { margin-bottom: 12px; }
        .resumen td { padding: 6px 10px; border: 1px solid #000; }
        .resumen .total { font-weight: bold; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px 5px; text-align: left; }
        th { background-color: #000; color: #fff; font-size: 10px; }
        .r { text-align: right; }
        .nota { font-size: 10px; margin: 8px 0; padding: 5px; border: 1px solid #000; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo e($titulo); ?></h1>
        <span class="info">Central: <?php echo e($ip_central); ?> | Generado: <?php echo e(now()->format('d/m/Y H:i')); ?></span>
    </div>

    <table class="resumen">
        <tr>
            <td><strong>Periodo:</strong> <?php echo e($fechaInicio); ?> al <?php echo e($fechaFin); ?></td>
            <td><strong>Llamadas:</strong> <?php echo e($totalLlamadas); ?></td>
            <td><strong>Minutos:</strong> <?php echo e($minutosFacturables); ?></td>
            <td class="total"><strong>Total: $<?php echo e(number_format($totalPagar, 0, ',', '.')); ?></strong></td>
        </tr>
    </table>

    <?php if(isset($truncado) && $truncado): ?>
    <div class="nota">Mostrando <?php echo e($registrosMostrados); ?> de <?php echo e($totalLlamadas); ?> llamadas. Use Excel para detalle completo.</div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>Tipo</th>
                <th class="r">Seg</th>
                <th class="r">Costo</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $llamadas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $call): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e(\Carbon\Carbon::parse($call->start_time)->format('d/m H:i')); ?></td>
                <td><?php echo e($call->source); ?></td>
                <td><?php echo e($call->destination); ?></td>
                <td><?php echo e(substr($call->call_type, 0, 3)); ?></td>
                <td class="r"><?php echo e($call->billsec); ?></td>
                <td class="r">$<?php echo e(number_format($call->cost, 0, ',', '.')); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div class="info" style="margin-top: 5px;">* Solo llamadas contestadas. Cel=Celular, Nac=Nacional, Int=Internacional, Loc=Local (800), Int=Interna</div>
</body>
</html><?php /**PATH C:\xampp\htdocs\panel_llamadas\resources\views/pdf_reporte.blade.php ENDPATH**/ ?>