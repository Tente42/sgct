<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ConfiguresPbx;

class TestApiCommands extends Command
{
    use ConfiguresPbx;

    protected $signature = 'api:test 
                            {--pbx= : ID de la central PBX a usar}
                            {--action= : Acción de la API a ejecutar}
                            {--caller= : Número de origen para buscar en cdrapi}
                            {--uniqueid= : Unique ID para buscar llamada específica en cdrapi}
                            {--route-id= : ID de la ruta entrante para getInboundRoute}
                            {--outbound-id= : ID de la ruta saliente para getOutboundRoute}
                            {--extension= : Número de extensión para getSIPAccount}
                            {--queue= : Número de cola para queueapi}
                            {--start-time= : Fecha inicial para queueapi (YYYY-MM-DD). Por defecto: hoy}
                            {--end-time= : Fecha final para queueapi (YYYY-MM-DD). Por defecto: hoy}
                            {--days=30 : Número de días hacia atrás para buscar llamadas (por defecto 30)}
                            {--all-calls : Mostrar todas las llamadas (entrantes y salientes) del anexo}
                            {--incoming : Solo mostrar llamadas entrantes}
                            {--outgoing : Solo mostrar llamadas salientes}
                            {--agent= : Número de agente para queueapi (usa * para todos)}
                            {--stats-type=overview : Tipo de estadísticas: overview, calldetail, loginhistory, pausedhistory}
                            {--today : Usar solo la fecha de hoy para queueapi}';
    
    protected $description = 'Comando para probar llamados a la API de Grandstream';

    public function handle(): int
    {
        // Configurar central
        $pbxId = $this->setupPbxConnection();
        if (!$pbxId) return 1;

        // Verificar conexión
        if (!$this->verifyConnection()) return 1;

        $this->info("============================================");
        $this->info("  PRUEBA DE API GRANDSTREAM");
        $this->info("============================================");
        $this->newLine();

        $action = $this->option('action');

        if (!$action) {
            $this->warn("No se especificó acción. Acciones disponibles:");
            $this->info("  - listExtensionGroup");
            $this->info("  - listQueue");
            $this->info("  - listOutboundRoute");
            $this->info("  - listInboundRoute");
            $this->info("  - listDepartment");
            $this->info("  - listBridgedChannels");
            $this->info("  - getInboundRoute");
            $this->info("  - getOutboundRoute");
            $this->info("  - getSIPAccount");
            $this->info("  - queueapi");
            $this->info("  - cdrapi");
            $this->info("  - kpi-turnos");
            $this->info("  - explore-action-types");
            $this->newLine();
            $this->info("Ejemplos de uso:");
            $this->info("  php artisan api:test --pbx=1 --action=listExtensionGroup");
            $this->info("  php artisan api:test --pbx=1 --action=cdrapi --caller=4445 --days=30 --all-calls");
            $this->info("  php artisan api:test --pbx=1 --action=cdrapi --caller=4445 --incoming --days=7");
            $this->info("  php artisan api:test --pbx=1 --action=queueapi --today --queue=6500");
            $this->info("  php artisan api:test --pbx=1 --action=queueapi --start-time=2026-02-01 --end-time=2026-02-02");
            $this->info("  php artisan api:test --pbx=1 --action=kpi-turnos --today --queue=6500");
            $this->info("  php artisan api:test --pbx=1 --action=explore-action-types");
            return 0;
        }

        switch ($action) {
            case 'listExtensionGroup':
                return $this->testListExtensionGroup();
            case 'listQueue':
                return $this->testListQueue();
            case 'listOutboundRoute':
                return $this->testListOutboundRoute();
            case 'listInboundRoute':
                return $this->testListInboundRoute();
            case 'listDepartment':
                return $this->testListDepartment();
            case 'listBridgedChannels':
                return $this->testListBridgedChannels();
            case 'getInboundRoute':
                return $this->testGetInboundRoute();
            case 'getOutboundRoute':
                return $this->testGetOutboundRoute();
            case 'getSIPAccount':
                return $this->testGetSIPAccount();
            case 'queueapi':
                return $this->testQueueApi();
            case 'cdrapi':
                return $this->testCdrApi();
            case 'kpi-turnos':
                return $this->testKpiTurnos();
            case 'explore-action-types':
                return $this->exploreActionTypes();
            default:
                $this->error("Acción '{$action}' no reconocida.");
                return 1;
        }
    }

    /**
     * Probar listExtensionGroup
     */
    private function testListExtensionGroup(): int
    {
        $this->info("📋 Listando grupos de extensiones...");
        $this->newLine();

        $response = $this->connectApi('listExtensionGroup', [
            'item_num' => 100,
            'sidx' => 'group_name',
            'sord' => 'asc',
            'page' => 1,
            'options' => 'group_name,members,group_id'
        ]);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $groups = $response['response']['extension_group'] ?? [];
        $totalItems = $response['response']['total_item'] ?? 0;
        $totalPages = $response['response']['total_page'] ?? 0;

        $this->info("✅ Total de grupos: {$totalItems}");
        $this->info("📄 Páginas: {$totalPages}");
        $this->newLine();

        if (empty($groups)) {
            $this->warn("No se encontraron grupos de extensiones.");
            return 0;
        }

        // Mostrar tabla
        $tableData = [];
        foreach ($groups as $group) {
            $tableData[] = [
                $group['group_id'] ?? 'N/A',
                $group['group_name'] ?? 'N/A',
                $group['members'] ?? 'Sin miembros',
            ];
        }

        $this->table(
            ['ID Grupo', 'Nombre', 'Miembros'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar listQueue
     */
    private function testListQueue(): int
    {
        $this->info("📞 Listando colas de llamadas...");
        $this->newLine();

        $response = $this->connectApi('listQueue', [
            'options' => 'extension,queue_name,strategy,queue_chairmans,members',
            'sidx' => 'extension',
            'sord' => 'asc'
        ]);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $queues = $response['response']['queue'] ?? [];
        $totalItems = $response['response']['total_item'] ?? 0;
        $totalPages = $response['response']['total_page'] ?? 0;

        $this->info("✅ Total de colas: {$totalItems}");
        $this->info("📄 Páginas: {$totalPages}");
        $this->newLine();

        if (empty($queues)) {
            $this->warn("No se encontraron colas de llamadas.");
            return 0;
        }

        // Mostrar tabla
        $tableData = [];
        foreach ($queues as $queue) {
            $tableData[] = [
                $queue['extension'] ?? 'N/A',
                $queue['queue_name'] ?? 'N/A',
                $queue['strategy'] ?? 'N/A',
                $queue['queue_chairmans'] ?? 'Sin supervisores',
                $queue['members'] ?? 'Sin agentes',
            ];
        }

        $this->table(
            ['Extensión', 'Nombre Cola', 'Estrategia', 'Supervisores', 'Agentes'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar listOutboundRoute
     */
    private function testListOutboundRoute(): int
    {
        $this->info("🚪 Listando rutas salientes...");
        $this->newLine();

        $response = $this->connectApi('listOutboundRoute', [
            'options' => 'outbound_rt_name,outbound_rt_index,permission,sequence,pattern,out_of_service'
        ]);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $routes = $response['response']['outbound_route'] ?? [];
        $totalItems = $response['response']['total_item'] ?? 0;
        $totalPages = $response['response']['total_page'] ?? 0;

        $this->info("✅ Total de rutas: {$totalItems}");
        $this->info("📄 Páginas: {$totalPages}");
        $this->newLine();

        if (empty($routes)) {
            $this->warn("No se encontraron rutas salientes.");
            return 0;
        }

        // Mostrar tabla
        $tableData = [];
        foreach ($routes as $route) {
            $tableData[] = [
                $route['outbound_rt_index'] ?? 'N/A',
                $route['outbound_rt_name'] ?? 'N/A',
                $route['permission'] ?? 'N/A',
                $route['sequence'] ?? 'N/A',
                $route['pattern'] ?? 'N/A',
                ($route['out_of_service'] ?? 'no') === 'yes' ? '🔴 Sí' : '🟢 No',
            ];
        }

        $this->table(
            ['ID', 'Nombre', 'Permiso', 'Secuencia', 'Patrón', 'Fuera de Servicio'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar listInboundRoute
     */
    private function testListInboundRoute(): int
    {
        $this->info("📞 Listando rutas entrantes...");
        $this->newLine();

        // Primero necesitamos listar los trunks para obtener un trunk_index
        $this->line("🔍 Obteniendo lista de trunks...");
        $trunksResponse = $this->connectApi('listTrunk', ['options' => 'trunk_index,trunk_name']);
        
        if (($trunksResponse['status'] ?? -1) !== 0 || empty($trunksResponse['response']['trunk'] ?? [])) {
            $this->warn("⚠️ No se encontraron trunks. Usando trunk_index=1 por defecto.");
            $trunkIndex = 1;
        } else {
            $trunk = $trunksResponse['response']['trunk'][0];
            $trunkIndex = $trunk['trunk_index'] ?? 1;
            $this->info("✅ Usando trunk: {$trunk['trunk_name']} (ID: {$trunkIndex})");
        }
        
        $this->newLine();

        $response = $this->connectApi('listInboundRoute', [
            'trunk_index' => $trunkIndex,
            'options' => 'inbound_rt_index,did_pattern_match,did_pattern_allow,out_of_service'
        ]);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $routes = $response['response']['inbound_route'] ?? [];
        $totalItems = $response['response']['total_item'] ?? 0;
        $totalPages = $response['response']['total_page'] ?? 0;

        $this->info("✅ Total de rutas entrantes: {$totalItems}");
        $this->info("📄 Páginas: {$totalPages}");
        $this->newLine();

        if (empty($routes)) {
            $this->warn("No se encontraron rutas entrantes para este trunk.");
            return 0;
        }

        // Mostrar tabla
        $tableData = [];
        foreach ($routes as $route) {
            $tableData[] = [
                $route['inbound_rt_index'] ?? 'N/A',
                $route['trunk_index'] ?? 'N/A',
                $route['did_pattern_match'] ?? '_.',
                $route['did_pattern_allow'] ?? 'Ninguno',
                $route['destination_type'] ?? 'N/A',
                ($route['out_of_service'] ?? 'no') === 'yes' ? '🔴 Sí' : '🟢 No',
            ];
        }

        $this->table(
            ['ID', 'Trunk', 'Patrón Match', 'Patrón Allow', 'Tipo Destino', 'Fuera de Servicio'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar cdrapi - Obtener llamadas de un anexo en los últimos N días
     */
    private function testCdrApi(): int
    {
        $caller = $this->option('caller');
        $uniqueid = $this->option('uniqueid');
        $days = (int) $this->option('days');
        $allCalls = $this->option('all-calls');
        $incoming = $this->option('incoming');
        $outgoing = $this->option('outgoing');
        
        // Validaciones
        if ($uniqueid) {
            $this->info("📞 Buscando llamada con Unique ID: {$uniqueid}...");
        } elseif ($caller) {
            $typeText = $allCalls ? 'todas las llamadas' : ($incoming ? 'llamadas entrantes' : ($outgoing ? 'llamadas salientes' : 'llamadas contestadas'));
            $this->info("📞 Buscando {$typeText} del anexo {$caller} en los últimos {$days} días...");
        } else {
            $this->info("📞 Obteniendo CDR (1 registro reciente)...");
        }
        $this->newLine();

        // Calcular fechas si se especifica un anexo
        $params = [
            'format' => 'json',
            'numRecords' => $uniqueid ? 1000 : ($caller && ($allCalls || $incoming || $outgoing) ? 5000 : ($caller ? 100 : 1)), // Más registros para búsqueda completa
            'minDur' => 0
        ];
        
        // Solo usar el parámetro caller de la API si NO estamos buscando todas las llamadas
        if ($caller && !$allCalls && !$incoming && !$outgoing) {
            $params['caller'] = $caller;
        }
        
        if ($caller) {
            // Agregar fechas para limitar búsqueda
            if ($days > 0) {
                $endDate = now();
                $startDate = now()->subDays($days);
                $params['start_time'] = $startDate->format('Y-m-d H:i:s');
                $params['end_time'] = $endDate->format('Y-m-d H:i:s');
            }
        }

        $response = $this->connectApi('cdrapi', $params, 120);

        if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
            $this->warn("⚠️ No se encontraron registros CDR" . ($caller ? " para el anexo {$caller}" : "") . ".");
            return 0;
        }

        // Depuración: mostrar cuántos registros se obtuvieron
        $totalRecords = count($response['cdr_root']);
        $this->info("📊 Se obtuvieron {$totalRecords} registros CDR de la API.");
        $this->newLine();

        // Si se especificó uniqueid, buscar por ese ID
        if ($uniqueid) {
            $found = null;
            foreach ($response['cdr_root'] as $record) {
                if (isset($record['uniqueid']) && $record['uniqueid'] === $uniqueid) {
                    $found = $record;
                    break;
                }
            }
            
            if (!$found) {
                $this->warn("⚠️ No se encontró llamada con Unique ID: {$uniqueid} en los últimos {$totalRecords} registros.");
                return 0;
            }
            
            $this->displaySingleCall($found);
            return 0;
        }
        // Si se especificó caller con opciones de filtro
        elseif ($caller && ($allCalls || $incoming || $outgoing)) {
            $filteredCalls = $this->filterCallsByType($response['cdr_root'], $caller, $allCalls, $incoming, $outgoing);
            
            if (empty($filteredCalls)) {
                $typeText = $allCalls ? 'llamadas' : ($incoming ? 'llamadas entrantes' : ($outgoing ? 'llamadas salientes' : 'llamadas'));
                $this->warn("⚠️ No se encontraron {$typeText} para el anexo {$caller} en los últimos {$days} días.");
                return 0;
            }
            
            $this->displayMultipleCalls($filteredCalls, $caller, $days);
            return 0;
        }
        // Si se especificó caller sin filtros, mostrar solo la última contestada
        elseif ($caller) {
            $answered = array_filter($response['cdr_root'], function($record) {
                return $record['disposition'] === 'ANSWERED';
            });
            
            if (empty($answered)) {
                $this->warn("⚠️ No se encontraron llamadas contestadas para el anexo {$caller}.");
                return 0;
            }
            
            $cdr = reset($answered);
            $this->displaySingleCall($cdr);
            return 0;
        } else {
            $cdr = $response['cdr_root'][0];
            $this->displaySingleCall($cdr);
            return 0;
        }

    }

    /**
     * Filtrar llamadas por tipo (entrantes, salientes, todas)
     */
    private function filterCallsByType(array $calls, string $extension, bool $allCalls, bool $incoming, bool $outgoing): array
    {
        if ($allCalls) {
            // Todas las llamadas donde el anexo está involucrado como origen o destino
            return array_filter($calls, function($call) use ($extension) {
                $src = $call['src'] ?? '';
                $dst = $call['dst'] ?? '';
                $dstanswer = $call['dstanswer'] ?? '';
                $channelExt = $call['channel_ext'] ?? '';
                $dstChannelExt = $call['dstchannel_ext'] ?? '';
                
                return ($src === $extension || $dst === $extension || $dstanswer === $extension || 
                        $channelExt === $extension || $dstChannelExt === $extension);
            });
        }
        
        if ($incoming) {
            // Llamadas entrantes: el anexo es el destino o quien responde
            return array_filter($calls, function($call) use ($extension) {
                $src = $call['src'] ?? '';
                $dst = $call['dst'] ?? '';
                $dstanswer = $call['dstanswer'] ?? '';
                $dstChannelExt = $call['dstchannel_ext'] ?? '';
                
                return (($dst === $extension || $dstanswer === $extension || $dstChannelExt === $extension) && $src !== $extension);
            });
        }
        
        if ($outgoing) {
            // Llamadas salientes: el anexo es el origen
            return array_filter($calls, function($call) use ($extension) {
                $src = $call['src'] ?? '';
                $channelExt = $call['channel_ext'] ?? '';
                
                return ($src === $extension || $channelExt === $extension);
            });
        }
        
        // Por defecto, todas las llamadas
        return array_filter($calls, function($call) use ($extension) {
            $src = $call['src'] ?? '';
            $dst = $call['dst'] ?? '';
            $dstanswer = $call['dstanswer'] ?? '';
            $channelExt = $call['channel_ext'] ?? '';
            $dstChannelExt = $call['dstchannel_ext'] ?? '';
            
            return ($src === $extension || $dst === $extension || $dstanswer === $extension || 
                    $channelExt === $extension || $dstChannelExt === $extension);
        });
    }

    /**
     * Mostrar múltiples llamadas en formato tabla resumida
     */
    private function displayMultipleCalls(array $calls, string $extension, int $days): void
    {
        $totalCalls = count($calls);
        $answered = array_filter($calls, fn($call) => ($call['disposition'] ?? '') === 'ANSWERED');
        $totalAnswered = count($answered);
        $totalDuration = array_sum(array_column($calls, 'duration'));
        $totalBillable = array_sum(array_map(fn($call) => $call['billsec'] ?? 0, $answered));
        
        $this->info("✅ Se encontraron {$totalCalls} llamadas para el anexo {$extension} en los últimos {$days} días:");
        $this->info("📞 Llamadas contestadas: {$totalAnswered}");
        $this->info("⏱️ Duración total: " . gmdate('H:i:s', $totalDuration));
        $this->info("💰 Tiempo facturable: " . gmdate('H:i:s', $totalBillable));
        $this->newLine();

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📋 RESUMEN DE LLAMADAS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        // Ordenar llamadas por fecha (más recientes primero)
        usort($calls, function($a, $b) {
            $timeA = isset($a['start']) ? strtotime($a['start']) : 0;
            $timeB = isset($b['start']) ? strtotime($b['start']) : 0;
            return $timeB - $timeA;
        });
        
        // Encabezados de tabla
        $this->line(sprintf(
            "  <fg=white;options=bold>%-20s %-12s %-12s %-12s %-10s %-8s</>",
            'FECHA/HORA', 'ORIGEN', 'DESTINO', 'QUIEN RESP.', 'ESTADO', 'DURACIÓN'
        ));
        $this->line("<fg=cyan>──────────────────────────────────────────────────────────────────────────────────────────</>");
        
        foreach ($calls as $index => $call) {
            $date = isset($call['start']) ? date('d/m/Y H:i:s', strtotime($call['start'])) : 'N/A';
            $src = substr($call['src'] ?? 'N/A', 0, 11);
            $dst = substr($call['dst'] ?? 'N/A', 0, 11);
            $answered = substr($call['dstanswer'] ?? 'N/A', 0, 11);
            $disposition = $call['disposition'] ?? 'N/A';
            $duration = gmdate('i:s', $call['duration'] ?? 0);
            
            $dispositionColor = match($disposition) {
                'ANSWERED' => 'green',
                'NO ANSWER' => 'yellow',
                'BUSY' => 'red',
                'FAILED' => 'red',
                default => 'white'
            };
            
            // Resaltar en qué posición está nuestro anexo
            $srcDisplay = $src === $extension ? "<fg=yellow;options=bold>{$src}</>" : $src;
            $dstDisplay = $dst === $extension ? "<fg=yellow;options=bold>{$dst}</>" : $dst;
            $ansDisplay = $answered === $extension ? "<fg=yellow;options=bold>{$answered}</>" : $answered;
            
            $this->line(sprintf(
                "  %-20s %-12s %-12s %-12s <fg={$dispositionColor}>%-10s</> %-8s",
                substr($date, 0, 19),
                $srcDisplay,
                $dstDisplay, 
                $ansDisplay,
                substr($disposition, 0, 9),
                $duration
            ));
            
            // Mostrar detalles completos solo si hay pocas llamadas
            if ($totalCalls <= 5 && $index === 0) {
                $this->newLine();
                $this->line("<fg=cyan>── DETALLE DE LA LLAMADA MÁS RECIENTE ──</>");
                $this->displaySingleCall($call, false);
                $this->line("<fg=cyan>────────────────────────────────────────</>");
            }
        }
        
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        if ($totalCalls > 5) {
            $this->newLine();
            $this->info("💡 Mostrando resumen. Para ver detalles completos de una llamada específica, usa:");
            $this->info("   php artisan api:test --pbx={$this->activePbx->id} --action=cdrapi --uniqueid=<UNIQUE_ID>");
        }
    }

    /**
     * Mostrar una sola llamada con todos los detalles
     */
    private function displaySingleCall(array $cdr, bool $showHeaders = true): void
    {
        if ($showHeaders) {
            $this->info("✅ Registro CDR encontrado:");
            $this->newLine();
        }

        // Mostrar de manera ordenada y completa
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>📋 IDENTIFICACIÓN DEL REGISTRO</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("  <fg=white>Account ID:</>           " . ($cdr['AcctId'] ?? 'N/A'));
        $this->line("  <fg=white>Account Code:</>         " . ($cdr['accountcode'] ?? 'N/A'));
        $this->line("  <fg=white>Session ID:</>           " . ($cdr['session'] ?? 'N/A'));
        $this->line("  <fg=white>Unique ID:</>            " . ($cdr['uniqueid'] ?? 'N/A'));
        $this->line("  <fg=white>CDR:</>                  " . ($cdr['cdr'] ?? 'N/A'));
        
        $this->newLine();
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>📞 INFORMACIÓN DEL ORIGEN (CALLER)</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("  <fg=white>Número Origen (src):</>        " . ($cdr['src'] ?? 'N/A'));
        $this->line("  <fg=white>Nombre Caller:</>              " . ($cdr['caller_name'] ?? 'N/A'));
        $this->line("  <fg=white>Caller ID (clid):</>           " . ($cdr['clid'] ?? 'N/A'));
        $this->line("  <fg=white>Canal:</>                      " . ($cdr['channel'] ?? 'N/A'));
        $this->line("  <fg=white>Extensión del Canal:</>        " . ($cdr['channel_ext'] ?? 'N/A'));
        $this->line("  <fg=white>Canal Extra (chanext):</>      " . ($cdr['chanext'] ?? 'N/A'));
        $this->line("  <fg=white>Trunk Origen:</>               " . ($cdr['src_trunk_name'] ?: '<fg=gray>Vacío</>'));
        $this->line("  <fg=white>Action Owner:</>               " . ($cdr['action_owner'] ?? 'N/A'));
        $this->line("  <fg=white>Action Type:</>                " . ($cdr['action_type'] ?? 'N/A'));
        
        $this->newLine();
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>📱 INFORMACIÓN DEL DESTINO (CALLEE)</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("  <fg=white>Número Destino (dst):</>       " . ($cdr['dst'] ?? 'N/A'));
        $this->line("  <fg=white>Quien Respondió:</>            " . ($cdr['dstanswer'] ?? 'N/A'));
        $this->line("  <fg=white>Canal Destino:</>              " . ($cdr['dstchannel'] ?? 'N/A'));
        $this->line("  <fg=white>Ext. Canal Destino:</>         " . ($cdr['dstchannel_ext'] ?? 'N/A'));
        $this->line("  <fg=white>Canal Extra Destino:</>        " . ($cdr['dstchanext'] ?? 'N/A'));
        $this->line("  <fg=white>Trunk Destino:</>              " . ($cdr['dst_trunk_name'] ?: '<fg=gray>Vacío</>'));
        $this->line("  <fg=white>Contexto Destino:</>           " . ($cdr['dcontext'] ?? 'N/A'));
        
        $this->newLine();
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>⏱️  INFORMACIÓN DE TIEMPOS</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("  <fg=white>Inicio (start):</>             " . ($cdr['start'] ?? 'N/A'));
        $this->line("  <fg=white>Respuesta (answer):</>         " . ($cdr['answer'] ?? 'N/A'));
        $this->line("  <fg=white>Fin (end):</>                  " . ($cdr['end'] ?? 'N/A'));
        $this->line("  <fg=white>Duración Total:</>             <fg=cyan>" . ($cdr['duration'] ?? '0') . "</> segundos");
        $this->line("  <fg=white>Tiempo Facturable:</>          <fg=cyan>" . ($cdr['billsec'] ?? '0') . "</> segundos");
        
        $this->newLine();
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>📊 ESTADO Y DISPOSICIÓN</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        
        $disposition = $cdr['disposition'] ?? 'N/A';
        $dispositionColor = match($disposition) {
            'ANSWERED' => 'green',
            'NO ANSWER' => 'yellow',
            'BUSY' => 'red',
            'FAILED' => 'red',
            default => 'white'
        };
        
        $this->line("  <fg=white>Disposición:</>                <fg={$dispositionColor};options=bold>{$disposition}</>");
        $this->line("  <fg=white>Tipo de Servicio:</>           " . ($cdr['service'] ?? 'N/A'));
        $this->line("  <fg=white>User Field:</>                 " . ($cdr['userfield'] ?? 'N/A'));
        $this->line("  <fg=white>AMA Flags:</>                  " . ($cdr['amaflags'] ?? 'N/A'));
        
        $this->newLine();
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("<fg=yellow;options=bold>🔧 APLICACIÓN Y DATOS TÉCNICOS</>");
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        }
        $this->line("  <fg=white>Última Aplicación:</>          " . ($cdr['lastapp'] ?? 'N/A'));
        $this->line("  <fg=white>Datos de la App:</>            " . ($cdr['lastdata'] ?? 'N/A'));
        
        if (!empty($cdr['recordfiles'])) {
            $this->newLine();
            if ($showHeaders) {
                $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            }
            $this->line("<fg=yellow;options=bold>🎙️  GRABACIÓN</>");
            if ($showHeaders) {
                $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            }
            $this->line("  <fg=white>Archivo(s):</>                 <fg=green>" . $cdr['recordfiles'] . "</>");
        }
        
        // Mostrar cualquier campo adicional que no hayamos contemplado
        $knownFields = [
            'AcctId', 'accountcode', 'action_owner', 'action_type', 'amaflags',
            'answer', 'billsec', 'caller_name', 'cdr', 'chanext', 'channel', 'channel_ext',
            'clid', 'dcontext', 'disposition', 'dst', 'dst_trunk_name', 'dstanswer',
            'dstchanext', 'dstchannel', 'dstchannel_ext', 'duration', 'end',
            'lastapp', 'lastdata', 'recordfiles', 'service', 'session',
            'src', 'src_trunk_name', 'start', 'uniqueid', 'userfield'
        ];
        
        $extraFields = array_diff_key($cdr, array_flip($knownFields));
        if (!empty($extraFields)) {
            $this->newLine();
            if ($showHeaders) {
                $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            }
            $this->line("<fg=yellow;options=bold>➕ CAMPOS ADICIONALES</>");
            if ($showHeaders) {
                $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            }
            foreach ($extraFields as $key => $value) {
                $this->line("  <fg=white>{$key}:</>  " . (is_array($value) ? json_encode($value) : $value));
            }
        }
        
        if ($showHeaders) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->newLine();
            $this->info("✅ Comando ejecutado exitosamente.");
        }
    }

    /**
     * Probar listDepartment - Listar departamentos
     */
    private function testListDepartment(): int
    {
        $this->info("🏭 Obteniendo lista de departamentos...");
        $this->newLine();

        $response = $this->connectApi('listDepartment');

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $departments = $response['response']['local_department_info'] ?? [];

        if (empty($departments)) {
            $this->warn("No se encontraron departamentos.");
            return 0;
        }

        $total = count($departments);
        $this->info("✅ Total de departamentos: {$total}");
        $this->newLine();

        // Organizar departamentos por jerarquía
        $rootDepts = [];
        $childDepts = [];
        
        foreach ($departments as $dept) {
            $fatherId = $dept['father_id'] ?? 0;
            if ($fatherId == 0) {
                $rootDepts[] = $dept;
            } else {
                if (!isset($childDepts[$fatherId])) {
                    $childDepts[$fatherId] = [];
                }
                $childDepts[$fatherId][] = $dept;
            }
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🏭 ESTRUCTURA JERÁRQUICA DE DEPARTAMENTOS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        // Mostrar jerarquía de departamentos
        foreach ($rootDepts as $root) {
            $this->displayDepartment($root, 0, $childDepts);
        }

        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📊 DETALLE COMPLETO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        // Tabla con todos los detalles
        $tableData = [];
        foreach ($departments as $dept) {
            $tableData[] = [
                $dept['department_id'] ?? 'N/A',
                $dept['name'] ?? 'N/A',
                $dept['father_id'] ?? '0',
                $dept['users_cnt'] ?? '0',
                $dept['policy_id'] ?? 'N/A',
                $dept['type'] ?? '0',
                $dept['cloud_department_id'] ?? 'N/A',
                $dept['cloud_father_id'] ?? '0',
            ];
        }

        $this->table(
            ['ID', 'Nombre', 'Padre ID', 'Usuarios', 'Policy ID', 'Tipo', 'Cloud ID', 'Cloud Padre'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Mostrar departamento con jerarquía visual
     */
    private function displayDepartment(array $dept, int $level, array $childDepts): void
    {
        $indent = str_repeat('  ', $level);
        $icon = $level === 0 ? '🏭' : ($level === 1 ? '📁' : '📂');
        
        $deptId = $dept['department_id'] ?? 'N/A';
        $name = $dept['name'] ?? 'Sin nombre';
        $usersCnt = $dept['users_cnt'] ?? 0;
        $policyId = $dept['policy_id'] ?? 'N/A';
        
        $usersLabel = $usersCnt > 0 ? "<fg=green>{$usersCnt} usuarios</>" : '<fg=gray>0 usuarios</>';
        
        $this->line("{$indent}{$icon} <fg=cyan;options=bold>{$name}</> <fg=gray>(ID: {$deptId})</> - {$usersLabel} - <fg=yellow>Policy: {$policyId}</>");
        
        // Mostrar subdepartamentos
        if (isset($childDepts[$deptId])) {
            foreach ($childDepts[$deptId] as $child) {
                $this->displayDepartment($child, $level + 1, $childDepts);
            }
        }
    }

    /**
     * Probar listBridgedChannels - Listar llamadas activas
     */
    private function testListBridgedChannels(): int
    {
        $this->info("📞 Obteniendo lista de llamadas activas...");
        $this->newLine();

        $response = $this->connectApi('listBridgedChannels');

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $channels = $response['response']['channel'] ?? [];
        $totalItems = $response['response']['total_item'] ?? 0;
        $page = $response['response']['page'] ?? 1;
        $totalPages = $response['response']['total_page'] ?? 1;

        $this->info("✅ Llamadas activas encontradas: {$totalItems}");
        $this->info("📊 Página {$page} de {$totalPages}");
        $this->newLine();

        if (empty($channels)) {
            $this->line("<fg=yellow>☎️  No hay llamadas activas en este momento.</>");
            return 0;
        }

        // Mostrar cada llamada de manera detallada
        foreach ($channels as $index => $call) {
            $callNumber = $index + 1;
            
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📞 LLAMADA #{$callNumber}</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            // Bridge Info
            $bridgeId = $call['bridge_id'] ?? 'N/A';
            $bridgeTime = $call['bridge_time'] ?? 'N/A';
            $this->line("  <fg=white>Bridge ID:</>                        <fg=gray>{$bridgeId}</>");
            $this->line("  <fg=white>Inicio de Llamada:</>                <fg=cyan>{$bridgeTime}</>");
            
            // Calcular duración si es posible
            if ($bridgeTime !== 'N/A') {
                try {
                    $start = new \DateTime($bridgeTime);
                    $now = new \DateTime();
                    $duration = $now->diff($start);
                    $durationStr = sprintf('%02d:%02d:%02d', 
                        $duration->h + ($duration->days * 24), 
                        $duration->i, 
                        $duration->s
                    );
                    $this->line("  <fg=white>Duración:</>                          <fg=green;options=bold>{$durationStr}</>");
                } catch (\Exception $e) {
                    // Ignorar error de parse de fecha
                }
            }
            
            $this->newLine();
            
            // Origen (Caller)
            $this->line("  <fg=yellow;options=bold>☎️  ORIGEN (Llamante)</>");
            $this->line("  " . str_repeat("─", 88));
            $channel1 = $call['channel1'] ?? 'N/A';
            $callerId1 = $call['callerid1'] ?? 'N/A';
            $name1 = $call['name1'] ?? '';
            $uniqueId1 = $call['uniqueid1'] ?? 'N/A';
            
            $this->line("  <fg=white>  Canal:</>                          <fg=cyan>{$channel1}</>");
            $this->line("  <fg=white>  Caller ID:</>                      <fg=cyan>{$callerId1}</>");
            if ($name1) {
                $this->line("  <fg=white>  Nombre:</>                         <fg=cyan>{$name1}</>");
            }
            $this->line("  <fg=white>  Unique ID:</>                      <fg=gray>{$uniqueId1}</>");
            
            $this->newLine();
            
            // Destino (Callee)
            $this->line("  <fg=green;options=bold>☎️  DESTINO (Receptor)</>");
            $this->line("  " . str_repeat("─", 88));
            $channel2 = $call['channel2'] ?? 'N/A';
            $callerId2 = $call['callerid2'] ?? 'N/A';
            $name2 = $call['name2'] ?? '';
            $uniqueId2 = $call['uniqueid2'] ?? 'N/A';
            
            $this->line("  <fg=white>  Canal:</>                          <fg=cyan>{$channel2}</>");
            $this->line("  <fg=white>  Caller ID:</>                      <fg=cyan>{$callerId2}</>");
            if ($name2) {
                $this->line("  <fg=white>  Nombre:</>                         <fg=cyan>{$name2}</>");
            }
            $this->line("  <fg=white>  Unique ID:</>                      <fg=gray>{$uniqueId2}</>");
            
            // Trunks
            $inboundTrunk = $call['inbound_trunk_name'] ?? '';
            $outboundTrunk = $call['outbound_trunk_name'] ?? '';
            
            if ($inboundTrunk || $outboundTrunk) {
                $this->newLine();
                $this->line("  <fg=blue;options=bold>🌐 TRUNKS</>");
                $this->line("  " . str_repeat("─", 88));
                
                if ($inboundTrunk) {
                    $this->line("  <fg=white>  Trunk Entrante:</>                 <fg=cyan>{$inboundTrunk}</>");
                }
                if ($outboundTrunk) {
                    $this->line("  <fg=white>  Trunk Saliente:</>                 <fg=cyan>{$outboundTrunk}</>");
                }
            }
            
            // Dirección de la llamada
            $this->newLine();
            $callDirection = $inboundTrunk ? '⬇️  Entrante' : ($outboundTrunk ? '⬆️  Saliente' : '🔄 Interna');
            $directionColor = $inboundTrunk ? 'green' : ($outboundTrunk ? 'yellow' : 'cyan');
            $this->line("  <fg=white>Dirección:</>                        <fg={$directionColor};options=bold>{$callDirection}</>");
            
            $this->newLine();
        }
        
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar getInboundRoute - Obtener información detallada de una ruta entrante
     */
    private function testGetInboundRoute(): int
    {
        $routeId = $this->option('route-id');
        
        if ($routeId) {
            $this->info("📞 Obteniendo información de la ruta entrante #{$routeId}...");
        } else {
            $this->info("📞 Obteniendo información de ruta entrante (sin ID especificado)...");
        }
        $this->newLine();

        $params = [];
        if ($routeId) {
            $params['inbound_route'] = $routeId;
        }

        $response = $this->connectApi('getInboundRoute', $params);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $route = $response['response']['inbound_routes'] ?? [];
        $destinations = $response['response']['inbound_did_destination'] ?? [];

        if (empty($route)) {
            $this->warn("⚠️ No se encontró la ruta entrante #{$routeId}.");
            return 0;
        }

        $this->info("✅ Ruta entrante encontrada:");
        $this->newLine();

        // ==================== INFORMACIÓN BÁSICA ====================
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📋 INFORMACIÓN BÁSICA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>ID de Ruta:</>                      <fg=cyan>" . ($route['inbound_rt_index'] ?? 'N/A') . "</>");
        $this->line("  <fg=white>Trunk ID:</>                         <fg=cyan>" . ($route['trunk_index'] ?? 'N/A') . "</>");
        
        $outOfService = ($route['out_of_service'] ?? 'no') === 'yes';
        $serviceStatus = $outOfService ? '<fg=red;options=bold>FUERA DE SERVICIO</>' : '<fg=green;options=bold>EN SERVICIO</>';
        $this->line("  <fg=white>Estado:</>                           {$serviceStatus}");
        
        $this->line("  <fg=white>Nivel de Privilegios:</>             " . ($route['permission'] ?? 'N/A'));

        // ==================== PATRONES DID ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🎯 PATRONES DID</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $patternMatch = $route['did_pattern_match'] ?? '';
        $patternAllow = $route['did_pattern_allow'] ?? '';
        $this->line("  <fg=white>Patrón de Coincidencia:</>           <fg=cyan>" . ($patternMatch ?: '<fg=gray>Vacío</>') . "</>");
        $this->line("  <fg=white>Patrón Permitido:</>                 <fg=cyan>" . ($patternAllow ?: '<fg=gray>Vacío</>') . "</>");
        $this->line("  <fg=white>DID Strip:</>                        " . ($route['did_strip'] ?? '0'));

        // ==================== CALLER ID CONFIGURATION ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🆔 CONFIGURACIÓN DE CALLER ID</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $setCallerIdEnabled = ($route['set_callerid_enable'] ?? 'no') === 'yes';
        $callerIdStatus = $setCallerIdEnabled ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>';
        $this->line("  <fg=white>Modificar Caller ID:</>              {$callerIdStatus}");
        $this->line("  <fg=white>Patrón Número:</>                    " . ($route['set_callerid_number'] ?? 'N/A'));
        $this->line("  <fg=white>Patrón Nombre:</>                    " . ($route['set_callerid_name'] ?? 'N/A'));
        $this->line("  <fg=white>Patrón CallerID:</>                  " . ($route['did_pattern_allow'] ?? '<fg=gray>Sin restricción</>'));

        // ==================== DESTINO PREDETERMINADO ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🎯 DESTINO PREDETERMINADO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Tipo de Destino:</>                  <fg=yellow;options=bold>" . ($route['destination_type'] ?? 'N/A') . "</>");
        
        // Mostrar el destino específico configurado
        $destinations_map = [
            'account' => 'Extensión',
            'callback' => 'Callback',
            'external_number' => 'Número Externo',
            'directory' => 'Directorio (Dial by Name)',
            'disa' => 'DISA',
            'fax' => 'Fax',
            'paginggroup' => 'Grupo de Paging',
            'queue' => 'Cola',
            'ringgroup' => 'Grupo de Timbre',
            'ivr' => 'IVR',
            'vmgroup' => 'Grupo de Voicemail',
            'conference' => 'Sala de Conferencia',
            'voicemail' => 'Voicemail',
            'announcement' => 'Anuncio'
        ];

        foreach ($destinations_map as $key => $label) {
            if (!empty($route[$key])) {
                $this->line("  <fg=white>{$label}:</>                        <fg=green>" . $route[$key] . "</>");
            }
        }

        // ==================== MODIFICACIONES DE LLAMADA ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔧 MODIFICACIONES DE LLAMADA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Prefijo (Prepend):</>                " . ($route['incoming_prepend'] ?? '<fg=gray>Sin prefijo</>'));
        $this->line("  <fg=white>Sufijo de Modo Entrante:</>          " . ($route['inbound_suffix'] ?? '<fg=gray>Sin sufijo</>'));
        
        $prependTrunk = ($route['prepend_trunk_name'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Anteponer Nombre del Trunk:</>       " . ($prependTrunk ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
        
        $prependName = $route['prepend_inbound_name_enable'] ?? null;
        if ($prependName === 'yes') {
            $this->line("  <fg=white>Anteponer Nombre Entrante:</>        <fg=green>Sí</> (" . ($route['prepend_inbound_name'] ?? 'N/A') . ")");
        } else {
            $this->line("  <fg=white>Anteponer Nombre Entrante:</>        <fg=gray>No</>");
        }

        // ==================== FAX Y DETECCIÓN ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📠 CONFIGURACIÓN DE FAX</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $faxDetect = ($route['enable_fax_detect'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Detección de Fax:</>                 " . ($faxDetect ? '<fg=green>Habilitada</>' : '<fg=gray>Deshabilitada</>'));
        
        if ($faxDetect) {
            $this->line("  <fg=white>Ruta Inteligente de Fax:</>          " . ($route['fax_intelligent_route'] ?? 'N/A'));
            $this->line("  <fg=white>Destino de Ruta Fax:</>              " . ($route['fax_intelligent_route_destination'] ?? '<fg=gray>No configurado</>'));
        }

        // ==================== MODO MÚLTIPLE ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔀 MODO MÚLTIPLE ENTRANTE</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $multiModeEnabled = ($route['enable_inbound_muti_mode'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Modo Múltiple:</>                    " . ($multiModeEnabled ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        if ($multiModeEnabled) {
            $this->line("  <fg=white>Modo Actual:</>                      " . ($route['inbound_muti_mode'] ?? '0'));
        }

        // ==================== OPCIONES ADICIONALES ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⚙️  OPCIONES ADICIONALES</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $dialDirect = ($route['dialdirect'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Marcación Directa:</>                " . ($dialDirect ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
        
        $blockCollect = ($route['blocking_did_collect_calls'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Bloquear Llamadas por Cobrar:</>     " . ($blockCollect ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
        
        $this->line("  <fg=white>Alert Info:</>                       " . ($route['alertinfo'] ?? '<fg=gray>No configurado</>'));
        
        $whitelist = $route['seamless_transfer_did_whitelist'] ?? '';
        $this->line("  <fg=white>Whitelist Transfer Seamless:</>      " . ($whitelist ?: '<fg=gray>Vacío</>'));

        // ==================== DESTINOS PERMITIDOS (By DID) ====================
        if (!empty($destinations)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>✅ DESTINOS PERMITIDOS (By DID)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $destinationLabels = [
                'ext_local' => 'Extensiones Locales',
                'ext_group' => 'Grupos de Timbre',
                'ext_queues' => 'Colas',
                'ext_conference' => 'Salas de Conferencia',
                'ext_multimedia_conference' => 'Conferencias Multimedia',
                'voicemenus' => 'Menús de Voz (IVR)',
                'voicemailgroups' => 'Grupos de Voicemail',
                'ext_fax' => 'Fax',
                'ext_directory' => 'Directorio (Dial by Name)',
                'ext_paging' => 'Paging/Intercom',
                'dial_trunk' => 'Marcar a Trunk'
            ];

            foreach ($destinationLabels as $key => $label) {
                if (isset($destinations[$key])) {
                    $allowed = $destinations[$key] === 'yes';
                    $status = $allowed ? '<fg=green>✓ Permitido</>' : '<fg=red>✗ No permitido</>';
                    $this->line("  <fg=white>{$label}:</>" . str_repeat(' ', 35 - strlen($label)) . $status);
                }
            }
        }

        // ==================== CAMPOS ADICIONALES ====================
        $knownFields = [
            'account', 'accout_voicemail_out_of_service', 'alertinfo', 'announcement',
            'blocking_did_collect_calls', 'callback', 'conference', 'destination_type',
            'dialdirect', 'did_pattern_allow', 'did_pattern_match', 'did_strip',
            'directory', 'disa', 'en_multi_mode', 'enable_fax_detect',
            'enable_inbound_muti_mode', 'external_number', 'fax', 'fax_intelligent_route',
            'fax_intelligent_route_destination', 'inbound_muti_mode', 'inbound_rt_index',
            'inbound_suffix', 'incoming_prepend', 'ivr', 'multimedia_conference',
            'out_of_service', 'paginggroup', 'permission', 'prepend_inbound_name',
            'prepend_inbound_name_enable', 'prepend_trunk_name', 'queue', 'ringgroup',
            'seamless_transfer_did_whitelist', 'set_callerid_enable', 'set_callerid_name',
            'set_callerid_number', 'trunk_index', 'vmgroup', 'voicemail'
        ];
        
        $extraFields = array_diff_key($route, array_flip($knownFields));
        if (!empty($extraFields)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>➕ CAMPOS ADICIONALES</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            foreach ($extraFields as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : ($value ?? '<fg=gray>null</>');
                $this->line("  <fg=white>{$key}:</>  {$displayValue}");
            }
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar getOutboundRoute - Obtener información detallada de una ruta saliente
     */
    private function testGetOutboundRoute(): int
    {
        $outboundId = $this->option('outbound-id');
        
        if ($outboundId) {
            $this->info("📞 Obteniendo información de la ruta saliente #{$outboundId}...");
        } else {
            $this->info("📞 Obteniendo información de ruta saliente (sin ID especificado)...");
        }
        $this->newLine();

        $params = [];
        if ($outboundId) {
            $params['outbound_route'] = $outboundId;
        }

        $response = $this->connectApi('getOutboundRoute', $params);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $route = $response['response']['outbound_route'] ?? [];
        $patterns = $response['response']['pattern'] ?? [];
        $failovers = $response['response']['failover_outbound_data'] ?? [];

        if (empty($route)) {
            $this->warn("⚠️ No se encontró la ruta saliente" . ($outboundId ? " #{$outboundId}" : "") . ".");
            return 0;
        }

        $this->info("✅ Ruta saliente encontrada:");
        $this->newLine();

        // ==================== INFORMACIÓN BÁSICA ====================
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📋 INFORMACIÓN BÁSICA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>ID de Ruta:</>                      <fg=cyan>" . ($route['outbound_rt_index'] ?? 'N/A') . "</>");
        $this->line("  <fg=white>Nombre:</>                           <fg=cyan;options=bold>" . ($route['outbound_rt_name'] ?? 'N/A') . "</>");
        $this->line("  <fg=white>Trunk Predeterminado:</>             <fg=cyan>" . ($route['default_trunk_index'] ?? 'N/A') . "</>");
        
        $outOfService = ($route['out_of_service'] ?? 'no') === 'yes';
        $serviceStatus = $outOfService ? '<fg=red;options=bold>FUERA DE SERVICIO</>' : '<fg=green;options=bold>EN SERVICIO</>';
        $this->line("  <fg=white>Estado:</>                           {$serviceStatus}");

        // ==================== PERMISOS Y SEGURIDAD ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔒 PERMISOS Y SEGURIDAD</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $permission = $route['permission'] ?? 'none';
        $permissionLabel = match($permission) {
            'none' => '<fg=red>Deshabilitado</>',
            'internal' => '<fg=yellow>Interno</>',
            'internal-local' => '<fg=cyan>Local</>',
            'internal-local-national' => '<fg=blue>Nacional</>',
            'internal-local-national-international' => '<fg=green>Internacional</>',
            default => $permission
        };
        $this->line("  <fg=white>Nivel de Privilegios:</>             {$permissionLabel}");
        
        $hasPassword = !empty($route['password']);
        $passwordStatus = $hasPassword ? '<fg=green>Configurada</>' : '<fg=gray>Sin contraseña</>';
        $this->line("  <fg=white>Contraseña:</>                       {$passwordStatus}");
        
        $pinSetsId = $route['pin_sets_id'] ?? null;
        if ($pinSetsId) {
            $this->line("  <fg=white>PIN Set ID:</>                       <fg=cyan>{$pinSetsId}</>");
        }

        // ==================== MODIFICACIONES DE NÚMERO ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔢 MODIFICACIONES DE NÚMERO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Strip (Eliminar dígitos):</>         <fg=cyan>" . ($route['strip'] ?? '0') . "</> dígitos del inicio");
        $prepend = $route['prepend'] ?? null;
        $this->line("  <fg=white>Prepend (Agregar dígitos):</>        " . ($prepend ? "<fg=cyan>{$prepend}</>" : '<fg=gray>Sin prefijo</>'));

        // ==================== FILTRO DE CALLER ID ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📞 FILTRO POR CALLER ID</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $wlistEnabled = ($route['enable_wlist'] ?? 'no') === 'yes';
        $wlistStatus = $wlistEnabled ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>';
        $this->line("  <fg=white>Filtro en Caller ID Origen:</>       {$wlistStatus}");
        
        if ($wlistEnabled) {
            $members = $route['members'] ?? null;
            $customMember = $route['custom_member'] ?? null;
            
            if ($members) {
                $this->line("  <fg=white>Extensiones/Grupos:</>               <fg=cyan>{$members}</>");
            }
            if ($customMember) {
                $this->line("  <fg=white>Patrón Personalizado:</>             <fg=cyan>{$customMember}</>");
            }
        }

        // ==================== RESTRICCIÓN DE TIEMPO ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⏰ RESTRICCIÓN DE TIEMPO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $timeMode = $route['time_mode'] ?? 0;
        $limitime = $route['limitime'] ?? null;
        
        if ($timeMode > 0 && $limitime) {
            $this->line("  <fg=white>Modo de Tiempo:</>                   <fg=green>Activo</> (ID: {$timeMode})");
            $this->line("  <fg=white>Configuración:</>                    <fg=cyan>{$limitime}</>");
        } else {
            $this->line("  <fg=white>Modo de Tiempo:</>                   <fg=gray>Sin restricción</>");
        }

        // ==================== PATRONES DE MARCACIÓN ====================
        if (!empty($patterns)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🎯 PATRONES DE MARCACIÓN</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->newLine();
            
            $tableData = [];
            foreach ($patterns as $pattern) {
                $tableData[] = [
                    $pattern['match'] ?? 'N/A',
                    $pattern['allow'] ?? '<fg=gray>Sin restricción</>',
                    $pattern['strip_prefix'] ?? '<fg=gray>0</>',
                ];
            }
            
            $this->table(
                ['Patrón Match', 'Patrón Allow', 'Strip Prefix'],
                $tableData
            );
        } else {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🎯 PATRONES DE MARCACIÓN</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("  <fg=gray>Sin patrones configurados</>");
        }

        // ==================== TRUNKS DE FAILOVER ====================
        if (!empty($failovers)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🔄 TRUNKS DE FAILOVER</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->newLine();
            
            $tableData = [];
            foreach ($failovers as $failover) {
                $tableData[] = [
                    $failover['failover_trunk_sequence'] ?? 'N/A',
                    $failover['failover_trunk_index'] ?? 'N/A',
                    $failover['failover_strip'] ?? '0',
                    $failover['failover_prepend'] ?? '<fg=gray>Ninguno</>',
                ];
            }
            
            $this->table(
                ['Secuencia', 'Trunk ID', 'Strip', 'Prepend'],
                $tableData
            );
        } else {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🔄 TRUNKS DE FAILOVER</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("  <fg=gray>Sin trunks de failover configurados</>");
        }

        // ==================== CAMPOS ADICIONALES ====================
        $knownFields = [
            'custom_member', 'default_trunk_index', 'enable_wlist', 'limitime',
            'members', 'out_of_service', 'outbound_rt_index', 'outbound_rt_name',
            'password', 'permission', 'pin_sets_id', 'prepend', 'strip', 'time_mode'
        ];
        
        $extraFields = array_diff_key($route, array_flip($knownFields));
        if (!empty($extraFields)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>➕ CAMPOS ADICIONALES</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            foreach ($extraFields as $key => $value) {
                $displayValue = is_array($value) ? json_encode($value) : ($value ?? '<fg=gray>null</>');
                $this->line("  <fg=white>{$key}:</>  {$displayValue}");
            }
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Probar getSIPAccount - Obtener información detallada de una extensión SIP
     */
    private function testGetSIPAccount(): int
    {
        $extension = $this->option('extension');
        
        if (!$extension) {
            $this->warn("⚠️ Debes especificar el número de extensión.");
            $this->info("Uso: php artisan api:test --pbx=1 --action=getSIPAccount --extension=1000");
            return 1;
        }

        $this->info("📞 Obteniendo información de la extensión {$extension}...");
        $this->newLine();

        $response = $this->connectApi('getSIPAccount', [
            'extension' => $extension
        ]);

        if (($response['status'] ?? -1) !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        $ext = $response['response']['extension'] ?? [];
        $presence = $response['response']['sip_presence_settings'] ?? [];
        $voicemail = $response['response']['voicemail'] ?? [];
        $cti = $response['response']['cti_feature_privilege'] ?? [];

        if (empty($ext)) {
            $this->warn("⚠️ No se encontró la extensión {$extension}.");
            return 0;
        }

        $this->info("✅ Extensión encontrada:");
        $this->newLine();

        // ==================== INFORMACIÓN BÁSICA ====================
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📋 INFORMACIÓN BÁSICA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Extensión:</>                        <fg=cyan;options=bold>" . ($ext['extension'] ?? 'N/A') . "</>");
        $this->line("  <fg=white>Tipo de Cuenta:</>                   <fg=cyan>" . ($ext['account_type'] ?? 'N/A') . "</>");
        $this->line("  <fg=white>Nombre Completo:</>                  " . ($ext['fullname'] ?? '<fg=gray>Sin nombre</>'));
        $this->line("  <fg=white>Caller ID Number:</>                 " . ($ext['cidnumber'] ?? 'N/A'));
        
        $outOfService = ($ext['out_of_service'] ?? 'no') === 'yes';
        $serviceStatus = $outOfService ? '<fg=red;options=bold>DESHABILITADA</>' : '<fg=green;options=bold>HABILITADA</>';
        $this->line("  <fg=white>Estado:</>                           {$serviceStatus}");

        // ==================== SEGURIDAD Y AUTENTICACIÓN ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔐 SEGURIDAD Y AUTENTICACIÓN</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Contraseña SIP:</>                   " . (isset($ext['secret']) ? '<fg=green>Configurada</>' : '<fg=gray>Sin contraseña</>'));
        $this->line("  <fg=white>Auth ID:</>                          " . ($ext['authid'] ?? '<fg=gray>Usa extensión</>'));
        $this->line("  <fg=white>Contraseña para Trunk:</>            " . ($ext['user_outrt_passwd'] ?? '<fg=gray>Sin contraseña</>'));
        
        $encryption = $ext['encryption'] ?? 'no';
        $encryptionLabel = match($encryption) {
            'yes' => '<fg=green>Habilitado (Forzado)</>',
            'support' => '<fg=yellow>Soportado (No forzado)</>',
            default => '<fg=gray>Deshabilitado</>'
        };
        $this->line("  <fg=white>Encriptación SRTP:</>                {$encryptionLabel}");
        
        if (($ext['enable_webrtc'] ?? 'no') === 'yes') {
            $mediaEncryption = $ext['media_encryption'] ?? 'auto_dtls';
            $this->line("  <fg=white>Encriptación Media (WebRTC):</>      <fg=cyan>{$mediaEncryption}</>");
        }

        // ==================== PERMISOS Y RESTRICCIONES ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔓 PERMISOS Y RESTRICCIONES</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $permission = $ext['permission'] ?? 'internal';
        $permissionLabel = match($permission) {
            'internal' => '<fg=yellow>Interno</>',
            'internal-local' => '<fg=cyan>Local</>',
            'internal-local-national' => '<fg=blue>Nacional</>',
            'internal-local-national-international' => '<fg=green>Internacional</>',
            default => $permission
        };
        $this->line("  <fg=white>Permisos de Llamada:</>              {$permissionLabel}");
        
        $bypassAuth = $ext['bypass_outrt_auth'] ?? 'no';
        $bypassLabel = match($bypassAuth) {
            'yes' => '<fg=green>Siempre</>',
            'bytime' => '<fg=yellow>Por tiempo (ID: ' . ($ext['skip_auth_timetype'] ?? '0') . ')</>',
            default => '<fg=gray>No</>'
        };
        $this->line("  <fg=white>Omitir Auth en Trunk:</>             {$bypassLabel}");
        
        $maxDuration = $ext['limitime'] ?? null;
        $this->line("  <fg=white>Duración Máxima:</>                  " . ($maxDuration ? "<fg=cyan>{$maxDuration}</> segundos" : '<fg=gray>Sin límite</>'));

        // ==================== ACL DE RED ====================
        $strategy = $ext['strategy_ipacl'] ?? 0;
        if ($strategy > 0) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🌐 CONTROL DE ACCESO DE RED (ACL)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $strategyLabel = match($strategy) {
                1 => '<fg=cyan>Red Local</>',
                2 => '<fg=yellow>IP Específica</>',
                default => '<fg=green>Permitir Todo</>'
            };
            $this->line("  <fg=white>Política ACL:</>                     {$strategyLabel}");
            
            if ($strategy == 1) {
                $networks = [];
                for ($i = 1; $i <= 10; $i++) {
                    $network = $ext["local_network{$i}"] ?? null;
                    if ($network) {
                        $networks[] = $network;
                    }
                }
                if (!empty($networks)) {
                    $this->line("  <fg=white>Redes Permitidas:</>                 <fg=cyan>" . implode(', ', $networks) . "</>");
                }
            } elseif ($strategy == 2 && !empty($ext['specific_ip'])) {
                $this->line("  <fg=white>IP Específica:</>                    <fg=cyan>" . $ext['specific_ip'] . "</>");
            }
        }

        // ==================== CODECS Y AUDIO ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🎵 CODECS Y AUDIO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $codecs = $ext['allow'] ?? 'N/A';
        $this->line("  <fg=white>Codecs Permitidos:</>                <fg=cyan>{$codecs}</>");
        
        $dtmf = $ext['dtmfmode'] ?? 'rfc2833';
        $this->line("  <fg=white>Modo DTMF:</>                        <fg=cyan>{$dtmf}</>");
        
        $moh = $ext['mohsuggest'] ?? 'default';
        $this->line("  <fg=white>Música en Espera:</>                 <fg=cyan>{$moh}</>");

        // ==================== CONFIGURACIÓN DE LLAMADAS ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📞 CONFIGURACIÓN DE LLAMADAS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $ringTimeout = $ext['ring_timeout'] ?? null;
        $this->line("  <fg=white>Ring Timeout:</>                     " . ($ringTimeout ? "<fg=cyan>{$ringTimeout}</> segundos" : '<fg=gray>Por defecto (60s)</>'));
        
        $callWaiting = ($ext['call_waiting'] ?? 'yes') === 'yes';
        $this->line("  <fg=white>Llamada en Espera:</>                " . ($callWaiting ? '<fg=green>Habilitada</>' : '<fg=gray>Deshabilitada</>'));
        
        $autoRecord = $ext['auto_record'] ?? 'off';
        $recordLabel = match($autoRecord) {
            'all' => '<fg=green>Todas las llamadas</>',
            'external' => '<fg=cyan>Solo externas</>',
            'internal' => '<fg=yellow>Solo internas</>',
            default => '<fg=gray>Deshabilitado</>'
        };
        $this->line("  <fg=white>Auto-Grabación:</>                   {$recordLabel}");
        
        $directMedia = ($ext['directmedia'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Direct Media:</>                     " . ($directMedia ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        $nat = ($ext['nat'] ?? 'yes') === 'yes';
        $this->line("  <fg=white>NAT:</>                              " . ($nat ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));

        // ==================== DND Y LISTAS ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🚫 DND Y LISTAS DE ACCESO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $dnd = ($ext['dnd'] ?? 'no') === 'yes';
        $dndStatus = $dnd ? '<fg=red;options=bold>ACTIVADO</>' : '<fg=green>Desactivado</>';
        $this->line("  <fg=white>Do Not Disturb:</>                   {$dndStatus}");
        
        if ($dnd) {
            $dndTime = $ext['dnd_timetype'] ?? 0;
            $timeLabel = $this->getTimeModeLabel($dndTime);
            $this->line("  <fg=white>DND Activo en:</>                    {$timeLabel}");
            
            $dndWhitelist = $ext['dndwhitelist'] ?? '';
            if ($dndWhitelist) {
                $this->line("  <fg=white>DND Whitelist:</>                    <fg=cyan>{$dndWhitelist}</>");
            }
        }
        
        $fwdWhitelist = $ext['fwdwhitelist'] ?? null;
        if ($fwdWhitelist) {
            $this->line("  <fg=white>Forward Whitelist:</>                <fg=cyan>{$fwdWhitelist}</>");
        }
        
        $seamlessMembers = $ext['seamless_transfer_members'] ?? '';
        if ($seamlessMembers) {
            $this->line("  <fg=white>Transfer Seamless:</>                <fg=cyan>{$seamlessMembers}</>");
        }
        
        $barging = $ext['callbarging_monitor'] ?? '';
        if ($barging) {
            $this->line("  <fg=white>Permitido Call Barging:</>           <fg=cyan>{$barging}</>");
        }

        // ==================== FAX ====================
        $faxDetect = ($ext['faxdetect'] ?? 'no') === 'yes';
        $t38 = ($ext['t38_udptl'] ?? 'no') === 'yes';
        $faxGateway = ($ext['fax_gateway'] ?? 'no') === 'yes';
        
        if ($faxDetect || $t38 || $faxGateway) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📠 CONFIGURACIÓN DE FAX</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $this->line("  <fg=white>Detección de Fax:</>                 " . ($faxDetect ? '<fg=green>Habilitada</>' : '<fg=gray>Deshabilitada</>'));
            $this->line("  <fg=white>T.38 UDPTL:</>                       " . ($t38 ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
            $this->line("  <fg=white>Fax Gateway:</>                      " . ($faxGateway ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
            
            $sendToFax = $ext['sendtofax'] ?? null;
            if ($sendToFax) {
                $this->line("  <fg=white>Enviar a Email:</>                   " . ($sendToFax === 'yes' ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
            }
        }

        // ==================== CARACTERÍSTICAS AVANZADAS ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⚙️ CARACTERÍSTICAS AVANZADAS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $maxContacts = $ext['max_contacts'] ?? 1;
        $this->line("  <fg=white>Registros Concurrentes:</>           <fg=cyan>{$maxContacts}</>");
        
        $hotdesk = ($ext['enablehotdesk'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Hot Desking:</>                      " . ($hotdesk ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        $sca = ($ext['sca_enable'] ?? 'no') === 'yes';
        $this->line("  <fg=white>SCA (Shared Call Appearance):</>     " . ($sca ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        $autoAnswer = ($ext['custom_autoanswer'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Auto-Answer Personalizado:</>        " . ($autoAnswer ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        $ldap = ($ext['enable_ldap'] ?? 'yes') === 'yes';
        $this->line("  <fg=white>LDAP:</>                             " . ($ldap ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        $telUri = $ext['tel_uri'] ?? 'disabled';
        $telUriLabel = match($telUri) {
            'enabled' => '<fg=green>Habilitado</>',
            'user_phone' => '<fg=cyan>User=Phone</>',
            default => '<fg=gray>Deshabilitado</>'
        };
        $this->line("  <fg=white>TEL URI:</>                          {$telUriLabel}");
        
        $alertInfo = $ext['alertinfo'] ?? 'none';
        if ($alertInfo !== 'none' && $alertInfo) {
            $this->line("  <fg=white>Alert-Info:</>                       <fg=cyan>{$alertInfo}</>");
        }
        
        $emergCid = $ext['emergcidnumber'] ?? null;
        if ($emergCid) {
            $this->line("  <fg=white>CID para Emergencias:</>             <fg=red;options=bold>{$emergCid}</>");
        }

        // ==================== WEBRTC ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🌐 WEBRTC Y KEEP-ALIVE</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $webrtc = ($ext['enable_webrtc'] ?? 'no') === 'yes';
        $this->line("  <fg=white>WebRTC:</>                           " . ($webrtc ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        if ($webrtc) {
            $iceSupport = ($ext['ice_support'] ?? 'yes') === 'yes';
            $this->line("  <fg=white>ICE Support:</>                      " . ($iceSupport ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
            
            $useAvpf = ($ext['use_avpf'] ?? 'yes') === 'yes';
            $this->line("  <fg=white>Use AVPF:</>                         " . ($useAvpf ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        }
        
        $qualify = ($ext['enable_qualify'] ?? 'no') === 'yes';
        $this->line("  <fg=white>Keep-Alive:</>                       " . ($qualify ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        if ($qualify) {
            $qualifyTime = $ext['qualify'] ?? 1000;
            $qualifyFreq = $ext['qualifyfreq'] ?? 60;
            $this->line("  <fg=white>Qualify Timeout:</>                  <fg=cyan>{$qualifyTime}</> ms");
            $this->line("  <fg=white>Qualify Frequency:</>                <fg=cyan>{$qualifyFreq}</> segundos");
        }

        // ==================== RING SIMULTANEOUSLY ====================
        $ringBoth = ($ext['en_ringboth'] ?? 'no') === 'yes';
        if ($ringBoth) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🔔 RING SIMULTANEOUSLY (TIMBRE SIMULTÁNEO)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $externalNumber = $ext['external_number'] ?? null;
            $this->line("  <fg=white>Habilitado:</>                       <fg=green>Sí</>");
            $this->line("  <fg=white>Número Externo:</>                   " . ($externalNumber ? "<fg=cyan>{$externalNumber}</>" : '<fg=gray>No configurado</>'));
            
            $ringBothTime = $ext['ringboth_timetype'] ?? 0;
            $timeLabel = $this->getTimeModeLabel($ringBothTime);
            $this->line("  <fg=white>Activo en:</>                        {$timeLabel}");
            
            $useDodFwdRb = ($ext['use_callee_dod_on_fwd_rb'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Usar DOD del Callee:</>              " . ($useDodFwdRb ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
        }

        // ==================== VOICEMAIL ====================
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📧 VOICEMAIL</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $hasVm = ($ext['hasvoicemail'] ?? 'yes') === 'yes';
        $this->line("  <fg=white>Voicemail:</>                        " . ($hasVm ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        
        if ($hasVm) {
            $vmSecret = $ext['vmsecret'] ?? null;
            $this->line("  <fg=white>Contraseña VM:</>                    " . ($vmSecret ? '<fg=green>Configurada</>' : '<fg=gray>Sin contraseña</>'));
            
            $skipVmSecret = ($ext['skip_vmsecret'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Omitir Contraseña VM:</>             " . ($skipVmSecret ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
            
            $vmAttach = $voicemail['vm_attach'] ?? null;
            $vmAttachLabel = match($vmAttach) {
                'yes' => '<fg=green>Sí</>',
                'no' => '<fg=gray>No</>',
                default => '<fg=cyan>Por defecto</>'
            };
            $this->line("  <fg=white>Enviar VM por Email:</>              {$vmAttachLabel}");
            
            $vmReserve = $voicemail['vm_reserve'] ?? null;
            $vmReserveLabel = match($vmReserve) {
                'yes' => '<fg=green>Sí</>',
                'no' => '<fg=gray>No</>',
                default => '<fg=cyan>Por defecto</>'
            };
            $this->line("  <fg=white>Conservar VM después de Email:</>    {$vmReserveLabel}");
            
            $missedCall = ($ext['missed_call'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Notif. Llamada Perdida:</>           " . ($missedCall ? '<fg=green>Habilitado</>' : '<fg=gray>Deshabilitado</>'));
        }

        // ==================== CALL FORWARDING ====================
        if (!empty($presence)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📲 CALL FORWARDING (DESVÍO DE LLAMADAS)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $presenceLabels = [
                'available' => '🟢 Disponible',
                'away' => '🟡 Ausente',
                'chat' => '💬 Chat',
                'dnd' => '🔴 No Molestar',
                'unavailable' => '⚫ No Disponible',
                'userdef' => '⚙️ Definido por Usuario'
            ];
            
            foreach ($presence as $pres) {
                $status = $pres['presence_status'] ?? 'available';
                $label = $presenceLabels[$status] ?? $status;
                
                $this->newLine();
                $this->line("  <fg=cyan;options=bold>{$label}</>");
                $this->line("  " . str_repeat("─", 88));
                
                // CFU
                $cfu = $pres['cfu'] ?? null;
                if ($cfu) {
                    $cfuType = $pres['cfu_destination_type'] ?? '0';
                    $cfuTime = $pres['cfu_timetype'] ?? 0;
                    $destTypeLabel = $this->getDestinationTypeLabel($cfuType);
                    $timeLabel = $this->getTimeModeLabel($cfuTime);
                    $this->line("  <fg=white>  • CFU (Incondicional):</>         <fg=green>{$cfu}</> ({$destTypeLabel}) - {$timeLabel}");
                }
                
                // CFB
                $cfb = $pres['cfb'] ?? null;
                if ($cfb) {
                    $cfbType = $pres['cfb_destination_type'] ?? '0';
                    $cfbTime = $pres['cfb_timetype'] ?? 0;
                    $destTypeLabel = $this->getDestinationTypeLabel($cfbType);
                    $timeLabel = $this->getTimeModeLabel($cfbTime);
                    $this->line("  <fg=white>  • CFB (Ocupado):</>                <fg=yellow>{$cfb}</> ({$destTypeLabel}) - {$timeLabel}");
                }
                
                // CFN
                $cfn = $pres['cfn'] ?? null;
                if ($cfn) {
                    $cfnType = $pres['cfn_destination_type'] ?? '0';
                    $cfnTime = $pres['cfn_timetype'] ?? 0;
                    $destTypeLabel = $this->getDestinationTypeLabel($cfnType);
                    $timeLabel = $this->getTimeModeLabel($cfnTime);
                    $this->line("  <fg=white>  • CFN (No Responde):</>            <fg=cyan>{$cfn}</> ({$destTypeLabel}) - {$timeLabel}");
                }
                
                if (!$cfu && !$cfb && !$cfn) {
                    $this->line("  <fg=gray>  Sin desvíos configurados</>");
                }
            }
        }

        // ==================== CTI FEATURES ====================
        if (!empty($cti)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🖥️  PRIVILEGIOS CTI</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $activeCall = ($cti['active_call'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Ver Llamadas Activas:</>             " . ($activeCall ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
            
            $extStatus = ($cti['extension_status'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Ver Estado de Extensiones:</>        " . ($extStatus ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
            
            $callBarge = ($cti['callbarge'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Call Barge:</>                       " . ($callBarge ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
            
            $hangup = ($cti['hangup'] ?? 'no') === 'yes';
            $this->line("  <fg=white>Colgar Llamadas:</>                  " . ($hangup ? '<fg=green>Sí</>' : '<fg=gray>No</>'));
        }

        // ==================== CALL CENTER (CC) ====================
        $ccAgent = $ext['cc_agent_policy'] ?? 'never';
        $ccMonitor = $ext['cc_monitor_policy'] ?? 'never';
        
        if ($ccAgent !== 'never' || $ccMonitor !== 'never') {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📞 CALL CENTER (CC)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $this->line("  <fg=white>Política CC Agent:</>                <fg=cyan>{$ccAgent}</>");
            $this->line("  <fg=white>Máx. Agentes CC:</>                  <fg=cyan>" . ($ext['cc_max_agents'] ?? '1') . "</>");
            $this->line("  <fg=white>Política CC Monitor:</>              <fg=cyan>{$ccMonitor}</>");
            $this->line("  <fg=white>Máx. Monitores CC:</>                <fg=cyan>" . ($ext['cc_max_monitors'] ?? '2') . "</>");
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Obtener etiqueta legible para modo de tiempo
     */
    private function getTimeModeLabel(int $mode): string
    {
        return match($mode) {
            0 => '<fg=cyan>Todo el tiempo</>',
            1 => '<fg=green>Horario de oficina</>',
            2 => '<fg=yellow>Fuera de horario</>',
            3 => '<fg=red>Feriado</>',
            4 => '<fg=green>Fuera de feriado</>',
            5 => '<fg=yellow>Fuera de horario o feriado</>',
            6 => '<fg=blue>Tiempo específico</>',
            8 => '<fg=green>Horario de oficina y fuera de feriado</>',
            default => "<fg=gray>Modo {$mode}</>"
        };
    }

    /**
     * Obtener etiqueta legible para tipo de destino
     */
    private function getDestinationTypeLabel(string $type): string
    {
        return match($type) {
            '0' => 'Ninguno',
            '1' => 'Extensión',
            '2' => 'Número personalizado',
            '3' => 'Voicemail',
            '4' => 'Grupo de timbre',
            '5' => 'Cola',
            '6' => 'Grupo de voicemail',
            default => "Tipo {$type}"
        };
    }

    /**
     * Probar queueapi - Obtener estadísticas de colas
     */
    private function testQueueApi(): int
    {
        $queue = $this->option('queue');
        $agent = $this->option('agent');
        $statsType = $this->option('stats-type');
        $today = $this->option('today');
        $startTime = $this->option('start-time');
        $endTime = $this->option('end-time');
        
        // Manejar fechas por defecto
        if ($today) {
            $startTime = now()->format('Y-m-d');
            $endTime = now()->format('Y-m-d');
        } elseif (!$startTime || !$endTime) {
            // Si no se especifican fechas, usar hoy por defecto
            $startTime = now()->format('Y-m-d');
            $endTime = now()->format('Y-m-d');
            $this->info("📅 No se especificaron fechas, usando hoy: {$startTime}");
        }

        $queueLabel = $queue ? "cola {$queue}" : "todas las colas";
        $agentLabel = $agent ? "agente {$agent}" : "todos los agentes";
        $statsLabel = match($statsType) {
            'calldetail' => 'detalles de llamadas',
            'loginhistory' => 'historial de login',
            'pausedhistory' => 'historial de pausas',
            default => 'resumen general'
        };
        
        $this->info("📊 Obteniendo {$statsLabel} de {$queueLabel} para {$agentLabel}...");
        $this->info("📅 Período: {$startTime} al {$endTime}");
        $this->newLine();

        $params = [
            'format' => 'json',
            'startTime' => $startTime,
            'endTime' => $endTime,
            'statisticsType' => $statsType
        ];
        
        if ($queue) {
            $params['queue'] = $queue;
        }
        
        if ($agent) {
            $params['agent'] = $agent;
        }

        $response = $this->connectApi('queueapi', $params, 60);

        // La respuesta puede venir en diferentes formatos según la API
        if (isset($response['status']) && $response['status'] !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        // Verificar si hay datos
        if (empty($response) || (!isset($response['root_statistics']) && !isset($response['total']) && !isset($response['calldetail']) && !isset($response['loginhistory']) && !isset($response['queue_statistics']))) {
            $this->warn("⚠️ No se encontraron estadísticas para el período especificado.");
            $this->info("💡 Sugerencia: Verifica que las colas tengan actividad en la fecha especificada.");
            $this->info("💡 Puedes usar --today para buscar estadísticas de hoy.");
            return 0;
        }

        // Manejar diferentes tipos de respuestas
        if ($statsType === 'calldetail') {
            return $this->displayCallDetails($response, $startTime, $endTime);
        } elseif ($statsType === 'loginhistory') {
            return $this->displayLoginHistory($response, $startTime, $endTime);
        } elseif ($statsType === 'pausedhistory') {
            return $this->displayPausedHistory($response, $startTime, $endTime);
        }

        // Parsear datos para overview - manejar la estructura queue_statistics
        if (isset($response['queue_statistics'])) {
            // Nueva estructura de la API
            $data = [
                'total' => null,
                'queue' => [],
                'agent' => []
            ];
            
            foreach ($response['queue_statistics'] as $queueStat) {
                if (isset($queueStat['queue'])) {
                    $data['queue'][] = $queueStat['queue'];
                }
                if (isset($queueStat['agent'])) {
                    // Puede ser un array de agentes o un solo agente
                    if (is_array($queueStat['agent'])) {
                        $data['agent'] = array_merge($data['agent'], $queueStat['agent']);
                    } else {
                        $data['agent'][] = $queueStat['agent'];
                    }
                }
            }
        } else {
            // Estructura antigua
            $data = $response['root_statistics'] ?? $response;
        }
        $total = $data['total'] ?? null;
        $queues = $data['queue'] ?? [];
        $agents = $data['agent'] ?? [];

        // Asegurar que queues y agents sean arrays
        if (isset($queues) && !isset($queues[0])) {
            $queues = [$queues];
        }
        if (isset($agents) && !isset($agents[0])) {
            $agents = [$agents];
        }

        $this->info("✅ Estadísticas obtenidas exitosamente:");
        $this->newLine();

        // ==================== RESUMEN GENERAL ====================
        if ($total) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📊 RESUMEN GENERAL</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $queueChairman = $total['queuechairman'] ?? 'N/A';
            $totalCalls = $total['total_calls'] ?? '0';
            $abandonedRate = $total['abandoned_rate'] ?? '0';
            $avgWait = $total['avg_wait'] ?? '0';
            $avgTalk = $total['avg_talk'] ?? '0';
            $vqTotalCalls = $total['vq_total_calls'] ?? '0';
            
            $this->line("  <fg=white>Chairman de Cola:</>                 <fg=cyan>{$queueChairman}</>");
            $this->line("  <fg=white>Total de Llamadas:</>                <fg=cyan;options=bold>{$totalCalls}</>");
            $this->line("  <fg=white>Tasa de Abandono:</>                 <fg=red>{$abandonedRate}%</>");
            $this->line("  <fg=white>Espera Promedio:</>                  <fg=yellow>{$avgWait}</> segundos");
            $this->line("  <fg=white>Duración Promedio:</>                <fg=green>{$avgTalk}</> segundos");
            $this->line("  <fg=white>Total Llamadas VQ:</>                <fg=cyan>{$vqTotalCalls}</>");
            $this->newLine();
        }

        // ==================== ESTADÍSTICAS POR COLA ====================
        if (!empty($queues)) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📞 ESTADÍSTICAS POR COLA</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->newLine();
            
            foreach ($queues as $q) {
                $queueNum = $q['queue'] ?? 'N/A';
                $queueChairman = $q['queuechairman'] ?? 'N/A';
                $totalCalls = $q['total_calls'] ?? '0';
                $answeredCalls = $q['answered_calls'] ?? '0';
                $answeredRate = $q['answered_rate'] ?? '0';
                $avgWait = $q['avg_wait'] ?? '0';
                $avgTalk = $q['avg_talk'] ?? '0';
                $vqTotalCalls = $q['vq_total_calls'] ?? '0';
                
                $this->line("  <fg=cyan;options=bold>🔢 Cola {$queueNum}</>");
                $this->line("  " . str_repeat("─", 88));
                $this->line("  <fg=white>  Chairman:</>                      <fg=cyan>{$queueChairman}</>");
                $this->line("  <fg=white>  Total de Llamadas:</>             <fg=cyan;options=bold>{$totalCalls}</>");
                $this->line("  <fg=white>  Llamadas Contestadas:</>          <fg=green>{$answeredCalls}</>");
                $this->line("  <fg=white>  Tasa de Respuesta:</>             <fg=green>{$answeredRate}%</>");
                $this->line("  <fg=white>  Espera Promedio:</>               <fg=yellow>{$avgWait}</> segundos");
                $this->line("  <fg=white>  Duración Promedio:</>             <fg=green>{$avgTalk}</> segundos");
                $this->line("  <fg=white>  Llamadas VQ:</>                   <fg=cyan>{$vqTotalCalls}</>");
                $this->newLine();
            }
        }

        // ==================== ESTADÍSTICAS POR AGENTE ====================
        if (!empty($agents)) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>👤 ESTADÍSTICAS POR AGENTE</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->newLine();
            
            // Mostrar tabla de agentes
            $tableData = [];
            foreach ($agents as $agent) {
                $agentNum = $agent['agent'] ?? 'N/A';
                $totalCalls = $agent['total_calls'] ?? '0';
                $answeredCalls = $agent['answered_calls'] ?? '0';
                $answeredRate = $agent['answered_rate'] ?? '0';
                $avgTalk = $agent['avg_talk'] ?? '0';
                
                $tableData[] = [
                    $agentNum,
                    $totalCalls,
                    $answeredCalls,
                    $answeredRate . '%',
                    $avgTalk . 's',
                ];
            }
            
            $this->table(
                ['Agente', 'Total Llamadas', 'Contestadas', 'Tasa Respuesta', 'Duración Prom.'],
                $tableData
            );
        }

        // ==================== RESUMEN DE RENDIMIENTO ====================
        if ($total || !empty($queues)) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>📈 INDICADORES DE RENDIMIENTO</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            $totalCallsSum = 0;
            $answeredCallsSum = 0;
            $avgWaitSum = 0;
            $avgTalkSum = 0;
            $queueCount = count($queues);
            
            foreach ($queues as $q) {
                $totalCallsSum += intval($q['total_calls'] ?? 0);
                $answeredCallsSum += intval($q['answered_calls'] ?? 0);
                $avgWaitSum += floatval($q['avg_wait'] ?? 0);
                $avgTalkSum += floatval($q['avg_talk'] ?? 0);
            }
            
            $avgWaitGlobal = $queueCount > 0 ? round($avgWaitSum / $queueCount, 2) : 0;
            $avgTalkGlobal = $queueCount > 0 ? round($avgTalkSum / $queueCount, 2) : 0;
            $answeredRateGlobal = $totalCallsSum > 0 ? round(($answeredCallsSum / $totalCallsSum) * 100, 1) : 0;
            $abandonedCalls = $totalCallsSum - $answeredCallsSum;
            $abandonedRateGlobal = $totalCallsSum > 0 ? round(($abandonedCalls / $totalCallsSum) * 100, 1) : 0;
            
            $this->newLine();
            $this->line("  <fg=white>📊 Llamadas Totales:</>              <fg=cyan;options=bold>{$totalCallsSum}</>");
            $this->line("  <fg=white>✅ Llamadas Contestadas:</>          <fg=green;options=bold>{$answeredCallsSum}</>");
            $this->line("  <fg=white>❌ Llamadas Abandonadas:</>          <fg=red;options=bold>{$abandonedCalls}</>");
            $this->newLine();
            $this->line("  <fg=white>📈 Tasa de Respuesta:</>             <fg=green;options=bold>{$answeredRateGlobal}%</>");
            $this->line("  <fg=white>📉 Tasa de Abandono:</>              <fg=red;options=bold>{$abandonedRateGlobal}%</>");
            $this->newLine();
            $this->line("  <fg=white>⏱️  Espera Promedio Global:</>       <fg=yellow;options=bold>{$avgWaitGlobal}s</>");
            $this->line("  <fg=white>⏱️  Duración Promedio Global:</>     <fg=green;options=bold>{$avgTalkGlobal}s</>");
            
            // Indicador visual de rendimiento
            $this->newLine();
            if ($answeredRateGlobal >= 90) {
                $this->line("  <fg=green;options=bold>🌟 EXCELENTE RENDIMIENTO</>  (≥90% de respuesta)");
            } elseif ($answeredRateGlobal >= 75) {
                $this->line("  <fg=yellow;options=bold>⚠️  RENDIMIENTO ACEPTABLE</>  (75-89% de respuesta)");
            } else {
                $this->line("  <fg=red;options=bold>🚨 RENDIMIENTO BAJO</>  (<75% de respuesta)");
            }
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
    }

    /**
     * Mostrar detalles de llamadas de cola
     */
    private function displayCallDetails(array $response, string $startTime, string $endTime): int
    {
        $this->info("✅ Detalles de llamadas obtenidos exitosamente:");
        $this->newLine();

        // Los detalles vienen en queue_statistics[].agent para calldetail
        $callDetails = [];
        if (isset($response['queue_statistics'])) {
            foreach ($response['queue_statistics'] as $queueStat) {
                if (isset($queueStat['agent'])) {
                    $callDetails[] = $queueStat['agent'];
                }
            }
        } else {
            $callDetails = $response['calldetail'] ?? $response;
        }
        
        if (empty($callDetails)) {
            $this->warn("⚠️ No se encontraron detalles de llamadas para el período especificado.");
            return 0;
        }

        // Contar estadísticas rápidas
        $totalCalls = count($callDetails);
        $answeredCalls = count(array_filter($callDetails, fn($call) => ($call['connect'] ?? '') === 'yes'));
        $averageWait = $totalCalls > 0 ? round(array_sum(array_column($callDetails, 'wait_time')) / $totalCalls, 1) : 0;
        $averageTalk = $answeredCalls > 0 ? round(array_sum(array_map(fn($call) => ($call['connect'] ?? '') === 'yes' ? ($call['talk_time'] ?? 0) : 0, $callDetails)) / $answeredCalls, 1) : 0;

        $this->info("📊 Resumen rápido: {$totalCalls} intentos de llamada, {$answeredCalls} contestadas");
        $this->info("⏱️ Espera promedio: {$averageWait}s | Conversación promedio: {$averageTalk}s");
        $this->newLine();

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📞 DETALLES COMPLETOS DE LLAMADAS DE COLA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        // Agrupar por llamador y mostrar
        $groupedCalls = [];
        foreach ($callDetails as $call) {
            $caller = $call['callernum'] ?? 'Desconocido';
            $groupedCalls[$caller][] = $call;
        }
        
        foreach ($groupedCalls as $caller => $calls) {
            $this->newLine();
            $this->line("<fg=yellow;options=bold>📱 Llamadas desde: {$caller}</>");
            $this->line(str_repeat("─", 88));
            
            // Encabezados de tabla
            $this->line(sprintf(
                "  <fg=white;options=bold>%-19s %-8s %-8s %-6s %-6s %-10s</>",
                'HORA', 'AGENTE', 'COLA', 'ESPERA', 'HABLÓ', 'CONTESTADA'
            ));
            $this->line("  " . str_repeat("─", 86));
            
            foreach ($calls as $call) {
                $time = isset($call['start_time']) ? date('H:i:s', strtotime($call['start_time'])) : 'N/A';
                $agent = $call['agent'] ?? 'N/A';
                $queue = $call['extension'] ?? 'N/A';
                $waitTime = ($call['wait_time'] ?? 0) . 's';
                $talkTime = ($call['talk_time'] ?? 0) . 's';
                $connected = ($call['connect'] ?? 'no') === 'yes' ? '✅ SÍ' : '❌ NO';
                
                // Colorear según si fue contestada
                $connectColor = ($call['connect'] ?? 'no') === 'yes' ? 'green' : 'red';
                $agentDisplay = $agent === 'NONE' ? '<fg=gray>NINGUNO</>' : $agent;
                
                $this->line(sprintf(
                    "  %-19s %-8s %-8s %-6s %-6s <fg={$connectColor}>%-10s</>",
                    $time,
                    $agentDisplay,
                    $queue,
                    $waitTime,
                    $talkTime,
                    $connected
                ));
            }
        }
        
        $this->newLine();
        
        // Tabla resumen por agente
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>👤 RESUMEN POR AGENTE</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $agentStats = [];
        foreach ($callDetails as $call) {
            $agent = $call['agent'] ?? 'NONE';
            if (!isset($agentStats[$agent])) {
                $agentStats[$agent] = [
                    'intentos' => 0,
                    'contestadas' => 0,
                    'tiempo_espera' => 0,
                    'tiempo_conversacion' => 0
                ];
            }
            
            $agentStats[$agent]['intentos']++;
            if (($call['connect'] ?? 'no') === 'yes') {
                $agentStats[$agent]['contestadas']++;
                $agentStats[$agent]['tiempo_conversacion'] += intval($call['talk_time'] ?? 0);
            }
            $agentStats[$agent]['tiempo_espera'] += intval($call['wait_time'] ?? 0);
        }
        
        $tableData = [];
        foreach ($agentStats as $agent => $stats) {
            $efectividad = $stats['intentos'] > 0 ? round(($stats['contestadas'] / $stats['intentos']) * 100, 1) : 0;
            $promedioEspera = $stats['intentos'] > 0 ? round($stats['tiempo_espera'] / $stats['intentos'], 1) : 0;
            $promedioConversacion = $stats['contestadas'] > 0 ? round($stats['tiempo_conversacion'] / $stats['contestadas'], 1) : 0;
            
            $tableData[] = [
                $agent === 'NONE' ? 'NINGUNO' : $agent,
                $stats['intentos'],
                $stats['contestadas'],
                $efectividad . '%',
                $promedioEspera . 's',
                $promedioConversacion . 's'
            ];
        }
        
        $this->table(
            ['Agente', 'Intentos', 'Contestadas', 'Efectividad', 'Espera Prom.', 'Conversación Prom.'],
            $tableData
        );

        $this->info("✅ Se mostraron {$totalCalls} intentos de llamada detallados.");
        return 0;
    }

    /**
     * Mostrar historial de login de agentes
     */
    private function displayLoginHistory(array $response, string $startTime, string $endTime): int
    {
        $this->info("✅ Historial de login obtenido exitosamente:");
        $this->newLine();

        $loginHistory = $response['loginhistory'] ?? $response;
        
        if (empty($loginHistory)) {
            $this->warn("⚠️ No se encontró historial de login para el período especificado.");
            return 0;
        }

        // Asegurar que loginHistory sea un array
        if (!isset($loginHistory[0])) {
            $loginHistory = [$loginHistory];
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>👤 HISTORIAL DE LOGIN DE AGENTES</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        // Encabezados de tabla
        $tableData = [];
        foreach ($loginHistory as $login) {
            $tableData[] = [
                $login['agent'] ?? 'N/A',
                $login['queue'] ?? 'N/A',
                $login['login_time'] ?? 'N/A',
                $login['logout_time'] ?? 'N/A',
                $login['login_duration'] ?? 'N/A',
                $login['status'] ?? 'N/A'
            ];
        }
        
        $this->table(
            ['Agente', 'Cola', 'Login', 'Logout', 'Duración', 'Estado'],
            $tableData
        );

        $this->info("✅ Se mostraron " . count($loginHistory) . " registros de login.");
        return 0;
    }

    /**
     * Mostrar historial de pausas de agentes
     */
    private function displayPausedHistory(array $response, string $startTime, string $endTime): int
    {
        $this->info("✅ Historial de pausas obtenido exitosamente:");
        $this->newLine();

        $pausedHistory = $response['pausedhistory'] ?? $response;
        
        if (empty($pausedHistory)) {
            $this->warn("⚠️ No se encontró historial de pausas para el período especificado.");
            return 0;
        }

        // Asegurar que pausedHistory sea un array
        if (!isset($pausedHistory[0])) {
            $pausedHistory = [$pausedHistory];
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⏸️  HISTORIAL DE PAUSAS DE AGENTES</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        // Encabezados de tabla
        $tableData = [];
        foreach ($pausedHistory as $pause) {
            $tableData[] = [
                $pause['agent'] ?? 'N/A',
                $pause['queue'] ?? 'N/A',
                $pause['pause_time'] ?? 'N/A',
                $pause['unpause_time'] ?? 'N/A',
                $pause['pause_duration'] ?? 'N/A',
                $pause['pause_reason'] ?? 'N/A'
            ];
        }
        
        $this->table(
            ['Agente', 'Cola', 'Inicio Pausa', 'Fin Pausa', 'Duración', 'Razón'],
            $tableData
        );

        $this->info("✅ Se mostraron " . count($pausedHistory) . " registros de pausas.");
        return 0;
    }

    /**
     * Analizar KPIs de turnos - Obtener indicadores clave por franjas horarias
     */
    private function testKpiTurnos(): int
    {
        $queue = $this->option('queue');
        $today = $this->option('today');
        $startTime = $this->option('start-time');
        $endTime = $this->option('end-time');
        
        // Manejar fechas por defecto
        if ($today) {
            $startTime = now()->format('Y-m-d');
            $endTime = now()->format('Y-m-d');
        } elseif (!$startTime || !$endTime) {
            // Si no se especifican fechas, usar hoy por defecto
            $startTime = now()->format('Y-m-d');
            $endTime = now()->format('Y-m-d');
            $this->info("📅 No se especificaron fechas, usando hoy: {$startTime}");
        }

        $queueLabel = $queue ? "cola {$queue}" : "todas las colas";
        $this->info("📊 Analizando KPIs de turnos para {$queueLabel}...");
        $this->info("📅 Período: {$startTime} al {$endTime}");
        $this->newLine();

        // Obtener CDR data - usar más registros para análisis completo
        $params = [
            'format' => 'json',
            'numRecords' => 5000,
            'minDur' => 0
        ];
        
        // No usar filtros de fecha en la API, filtraremos después
        // para obtener más datos y filtrar por fecha en el código

        $response = $this->connectApi('cdrapi', $params, 120);

        if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
            $this->warn("⚠️ No se encontraron registros CDR para el período especificado.");
            return 0;
        }

        $allCalls = $response['cdr_root'];
        $this->info("📊 Se obtuvieron " . count($allCalls) . " registros CDR totales.");

        // Filtrar por fecha en el código
        $dateFilteredCalls = array_filter($allCalls, function($call) use ($startTime, $endTime) {
            $callDate = $call['start'] ?? null;
            if (!$callDate) return false;
            
            $callDateOnly = date('Y-m-d', strtotime($callDate));
            return $callDateOnly >= $startTime && $callDateOnly <= $endTime;
        });

        $this->info("📅 Llamadas CDR en el período: " . count($dateFilteredCalls));

        // Obtener datos de cola usando queueapi con calldetail
        $queueParams = [
            'format' => 'json',
            'startTime' => $startTime,
            'endTime' => $endTime,
            'statisticsType' => 'calldetail'
        ];
        
        if ($queue) {
            $queueParams['queue'] = $queue;
        }

        $queueResponse = $this->connectApi('queueapi', $queueParams, 120);

        if (!isset($queueResponse['queue_statistics']) || empty($queueResponse['queue_statistics'])) {
            $this->warn("⚠️ No se encontraron datos de cola para el período especificado.");
            $this->info("💡 Asegúrate de que la cola {$queue} tenga actividad en la fecha especificada.");
            return 0;
        }

        // Extraer llamadas de cola de la respuesta
        $queueCalls = [];
        foreach ($queueResponse['queue_statistics'] as $queueStat) {
            if (isset($queueStat['agent'])) {
                $queueCalls[] = $queueStat['agent'];
            }
        }

        $this->info("📞 Registros de cola encontrados: " . count($queueCalls));
        $this->info("💡 Nota: Las llamadas CDR y los registros de cola pueden diferir (diferentes fuentes de datos)");
        $this->newLine();

        // Procesar KPIs por franjas horarias
        $kpisByHour = $this->processKpisByHour($queueCalls);
        
        // Mostrar resultados
        $this->displayKpiResults($kpisByHour, $queue);

        return 0;
    }

    /**
     * Procesar llamadas y calcular KPIs por hora
     */
    private function processKpisByHour(array $calls): array
    {
        $hourlyData = [];

        foreach ($calls as $call) {
            $startTime = $call['start_time'] ?? null;
            if (!$startTime) continue;

            $hour = date('H', strtotime($startTime));
            
            if (!isset($hourlyData[$hour])) {
                $hourlyData[$hour] = [
                    'hour' => $hour,
                    'total_calls' => 0,
                    'answered_calls' => 0,
                    'abandoned_calls' => 0,
                    'total_wait_time' => 0,
                    'total_talk_time' => 0,
                    'agents' => [],
                    'call_details' => []
                ];
            }

            $hourlyData[$hour]['total_calls']++;
            $hourlyData[$hour]['call_details'][] = $call;

            $connected = ($call['connect'] ?? 'no') === 'yes';
            $agent = $call['agent'] ?? '';
            
            if ($connected) {
                $hourlyData[$hour]['answered_calls']++;
                $talkTime = intval($call['talk_time'] ?? 0);
                $hourlyData[$hour]['total_talk_time'] += $talkTime;
                
                if ($agent && $agent !== 'NONE') {
                    if (!isset($hourlyData[$hour]['agents'][$agent])) {
                        $hourlyData[$hour]['agents'][$agent] = [
                            'calls' => 0,
                            'talk_time' => 0
                        ];
                    }
                    $hourlyData[$hour]['agents'][$agent]['calls']++;
                    $hourlyData[$hour]['agents'][$agent]['talk_time'] += $talkTime;
                }
            } else {
                $hourlyData[$hour]['abandoned_calls']++;
            }

            // Tiempo de espera viene directamente del campo wait_time
            $waitTime = intval($call['wait_time'] ?? 0);
            $hourlyData[$hour]['total_wait_time'] += $waitTime;
        }

        // Ordenar por hora
        ksort($hourlyData);
        
        return $hourlyData;
    }

    /**
     * Mostrar resultados de KPIs
     */
    private function displayKpiResults(array $hourlyData, ?string $queue): void
    {
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📊 KPIs DE TURNOS POR FRANJA HORARIA</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        // Encabezados de tabla resumen
        $this->line(sprintf(
            "  <fg=white;options=bold>%-6s %-8s %-10s %-10s %-12s %-8s</>",
            'HORA', 'VOLUMEN', 'ATENDIDAS', 'ABANDONO%', 'ESP.PROM', 'ASA'
        ));
        $this->line("  " . str_repeat("─", 88));

        $totalCalls = 0;
        $totalAnswered = 0;
        $totalAbandoned = 0;
        $totalWaitTime = 0;
        $totalTalkTime = 0;

        foreach ($hourlyData as $hour => $data) {
            $totalCalls += $data['total_calls'];
            $totalAnswered += $data['answered_calls'];
            $totalAbandoned += $data['abandoned_calls'];
            $totalWaitTime += $data['total_wait_time'];
            $totalTalkTime += $data['total_talk_time'];

            $abandonRate = $data['total_calls'] > 0 ? 
                round(($data['abandoned_calls'] / $data['total_calls']) * 100, 1) : 0;
            
            $avgWaitTime = $data['total_calls'] > 0 ? 
                round($data['total_wait_time'] / $data['total_calls'], 1) : 0;
                
            $asa = $data['answered_calls'] > 0 ? 
                round($data['total_talk_time'] / $data['answered_calls'], 1) : 0;

            // Colorear según umbrales críticos
            $abandonColor = $abandonRate > 20 ? 'red' : ($abandonRate > 15 ? 'yellow' : 'green');
            $waitColor = $avgWaitTime > 60 ? 'red' : ($avgWaitTime > 30 ? 'yellow' : 'green');
            $asaColor = $asa > 60 ? 'red' : ($asa > 30 ? 'yellow' : 'green');

            $this->line(sprintf(
                "  %-6s %-8s %-10s <fg={$abandonColor}>%-10s</> <fg={$waitColor}>%-12s</> <fg={$asaColor}>%-8s</>",
                $hour . ':00',
                $data['total_calls'],
                $data['answered_calls'],
                $abandonRate . '%',
                $avgWaitTime . 's',
                $asa . 's'
            ));
        }

        $this->line("  " . str_repeat("═", 88));

        // Resumen global
        $globalAbandonRate = $totalCalls > 0 ? round(($totalAbandoned / $totalCalls) * 100, 1) : 0;
        $globalAvgWait = $totalCalls > 0 ? round($totalWaitTime / $totalCalls, 1) : 0;
        $globalAsa = $totalAnswered > 0 ? round($totalTalkTime / $totalAnswered, 1) : 0;

        $this->line(sprintf(
            "  <fg=cyan;options=bold>%-6s %-8s %-10s %-10s %-12s %-8s</>",
            'TOTAL',
            $totalCalls,
            $totalAnswered,
            $globalAbandonRate . '%',
            $globalAvgWait . 's',
            $globalAsa . 's'
        ));

        $this->newLine();

        // Análisis por agente en el período más activo
        $mostActiveHour = array_reduce($hourlyData, function($max, $current) {
            return ($current['total_calls'] ?? 0) > ($max['total_calls'] ?? 0) ? $current : $max;
        }, ['total_calls' => 0]);

        if ($mostActiveHour['total_calls'] > 0) {
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>👤 RENDIMIENTO DE AGENTES - HORA PICO ({$mostActiveHour['hour']}:00)</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            
            if (!empty($mostActiveHour['agents'])) {
                $agentTableData = [];
                foreach ($mostActiveHour['agents'] as $agent => $stats) {
                    $avgTalkTime = $stats['calls'] > 0 ? round($stats['talk_time'] / $stats['calls'], 1) : 0;
                    $agentTableData[] = [
                        $agent,
                        $stats['calls'],
                        gmdate('i:s', $stats['talk_time']),
                        $avgTalkTime . 's'
                    ];
                }
                
                $this->table(
                    ['Agente', 'Llamadas Atendidas', 'Tiempo Total', 'Promedio/Llamada'],
                    $agentTableData
                );
            } else {
                $this->info("  No hay datos de agentes para mostrar.");
            }
        }

        $this->newLine();

        // Alertas y recomendaciones
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⚠️  ALERTAS Y RECOMENDACIONES</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");

        $alertsFound = false;

        foreach ($hourlyData as $hour => $data) {
            $abandonRate = $data['total_calls'] > 0 ? 
                ($data['abandoned_calls'] / $data['total_calls']) * 100 : 0;
            $avgWaitTime = $data['total_calls'] > 0 ? 
                $data['total_wait_time'] / $data['total_calls'] : 0;

            if ($abandonRate > 20) {
                $this->line("  <fg=red>🚨 CRÍTICO:</> Hora {$hour}:00 - Abandono muy alto ({$abandonRate}%). Requiere más agentes.");
                $alertsFound = true;
            } elseif ($abandonRate > 15) {
                $this->line("  <fg=yellow>⚠️  ALERTA:</> Hora {$hour}:00 - Abandono elevado ({$abandonRate}%). Considerar refuerzo.");
                $alertsFound = true;
            }

            if ($avgWaitTime > 60) {
                $this->line("  <fg=red>🚨 CRÍTICO:</> Hora {$hour}:00 - Espera muy larga ({$avgWaitTime}s). Optimizar distribución.");
                $alertsFound = true;
            } elseif ($avgWaitTime > 30) {
                $this->line("  <fg=yellow>⚠️  ALERTA:</> Hora {$hour}:00 - Espera prolongada ({$avgWaitTime}s). Revisar capacidad.");
                $alertsFound = true;
            }
        }

        if (!$alertsFound) {
            $this->line("  <fg=green>✅ EXCELENTE:</> No se detectaron problemas críticos en los KPIs analizados.");
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Análisis de KPIs completado exitosamente.");
    }

    /**
     * Explorar todos los action_type disponibles en las llamadas CDR
     */
    private function exploreActionTypes(): int
    {
        $this->info("🔍 Explorando tipos de ACTION_TYPE en las llamadas...");
        $this->info("📅 Consultando base de datos local para historial completo...");
        $this->newLine();

        $pbxId = $this->getActivePbxId();

        // Obtener conteo por mes de la BD local
        $monthlyData = \DB::table('calls')
            ->where('pbx_connection_id', $pbxId)
            ->selectRaw('DATE_FORMAT(start_time, "%Y-%m") as mes')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MIN(start_time) as primera')
            ->selectRaw('MAX(start_time) as ultima')
            ->groupBy('mes')
            ->orderBy('mes', 'DESC')
            ->get();

        if ($monthlyData->isEmpty()) {
            $this->warn("⚠️ No se encontraron registros en la base de datos.");
            $this->newLine();
            $this->info("💡 Tip: Ejecuta primero la sincronización de llamadas para poblar la BD.");
            return 0;
        }

        $totalRecords = $monthlyData->sum('total');
        $this->info("✓ Total de llamadas en BD: " . number_format($totalRecords));
        $this->info("✓ Rango: {$monthlyData->last()->primera} hasta {$monthlyData->first()->ultima}");
        $this->newLine();

        // Mostrar resumen por mes
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📅 REGISTROS POR MES (HISTÓRICO COMPLETO DESDE BD LOCAL)</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();
        
        $maxCount = $monthlyData->max('total');
        
        foreach ($monthlyData as $row) {
            $percentage = $totalRecords > 0 ? round(($row->total / $totalRecords) * 100, 2) : 0;
            // Barra proporcional al mes con más registros
            $barLength = $maxCount > 0 ? (int)(($row->total / $maxCount) * 40) : 0;
            $bar = str_repeat('█', $barLength);
            
            $this->line(sprintf(
                "  <fg=white>%s</>  <fg=cyan>%-8s</> <fg=green>%-40s</> %.2f%%",
                $row->mes,
                number_format($row->total),
                $bar,
                $percentage
            ));
        }
        
        $this->newLine();
        $this->line(sprintf(
            "  <fg=white;options=bold>TOTAL</>   <fg=cyan;options=bold>%-8s</> 100%%",
            number_format($totalRecords)
        ));
        $this->newLine();

        // Ahora obtener datos de la API para analizar action_type en registros recientes
        $this->info("📥 Consultando API para analizar campos detallados (registros recientes)...");
        $params = [
            'format' => 'json',
            'numRecords' => 1000,
            'minDur' => 0
        ];

        $response = $this->connectApi('cdrapi', $params, 120);

        if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
            $this->warn("⚠️ No se pudieron obtener registros de la API para análisis detallado.");
            return 0;
        }

        $calls = array_filter($response['cdr_root'], function($call) {
            return isset($call['start']) && !empty($call['start']);
        });

        $this->info("✓ Analizando " . count($calls) . " registros recientes de la API");
        $this->newLine();

        // Analizar action_type
        $actionTypes = [];
        $actionOwners = [];
        $lastApps = [];
        $lastDatas = [];
        $dispositions = [];
        $examplesByType = [];

        foreach ($calls as $call) {
            // Action Type
            $type = $call['action_type'] ?? 'NO_DEFINIDO';
            if (!isset($actionTypes[$type])) {
                $actionTypes[$type] = 0;
                $examplesByType[$type] = $call;
            }
            $actionTypes[$type]++;

            // Action Owner
            $owner = $call['action_owner'] ?? 'NO_DEFINIDO';
            $actionOwners[$owner] = ($actionOwners[$owner] ?? 0) + 1;

            // Last App
            $lastApp = $call['lastapp'] ?? 'NO_DEFINIDO';
            $lastApps[$lastApp] = ($lastApps[$lastApp] ?? 0) + 1;

            // Last Data
            $lastData = $call['lastdata'] ?? 'NO_DEFINIDO';
            // Limitar longitud para análisis
            $lastDataShort = strlen($lastData) > 50 ? substr($lastData, 0, 50) . '...' : $lastData;
            $lastDatas[$lastDataShort] = ($lastDatas[$lastDataShort] ?? 0) + 1;

            // Disposition
            $disp = $call['disposition'] ?? 'NO_DEFINIDO';
            $dispositions[$disp] = ($dispositions[$disp] ?? 0) + 1;
        }

        // Ordenar todos por cantidad
        arsort($actionTypes);
        arsort($actionOwners);
        arsort($lastApps);
        arsort($lastDatas);
        arsort($dispositions);

        // ========== MOSTRAR ACTION_TYPE ==========
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📊 ACTION_TYPE ENCONTRADOS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        $this->line(sprintf(
            "  <fg=white;options=bold>%-30s %-12s %-10s</>",
            'ACTION_TYPE', 'CANTIDAD', '%'
        ));
        $this->line("  " . str_repeat("─", 60));

        foreach ($actionTypes as $type => $count) {
            $percentage = round(($count / $totalRecords) * 100, 2);
            $color = $percentage > 50 ? 'green' : ($percentage > 20 ? 'yellow' : 'white');
            
            $this->line(sprintf(
                "  <fg={$color}>%-30s %-12d %s</>",
                $type,
                $count,
                $percentage . '%'
            ));
        }

        // ========== MOSTRAR ACTION_OWNER ==========
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>👤 ACTION_OWNER ENCONTRADOS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        $this->line(sprintf(
            "  <fg=white;options=bold>%-30s %-12s %-10s</>",
            'ACTION_OWNER', 'CANTIDAD', '%'
        ));
        $this->line("  " . str_repeat("─", 60));

        $displayCount = 0;
        foreach ($actionOwners as $owner => $count) {
            if ($displayCount++ >= 15) break; // Limitar a top 15
            $percentage = round(($count / $totalRecords) * 100, 2);
            $color = $percentage > 50 ? 'green' : ($percentage > 20 ? 'yellow' : 'white');
            
            $this->line(sprintf(
                "  <fg={$color}>%-30s %-12d %s</>",
                strlen($owner) > 30 ? substr($owner, 0, 27) . '...' : $owner,
                $count,
                $percentage . '%'
            ));
        }

        // ========== MOSTRAR LAST_APP ==========
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📱 LAST_APP ENCONTRADOS (Aplicación Final)</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        $this->line(sprintf(
            "  <fg=white;options=bold>%-30s %-12s %-10s</>",
            'LAST_APP', 'CANTIDAD', '%'
        ));
        $this->line("  " . str_repeat("─", 60));

        foreach ($lastApps as $app => $count) {
            $percentage = round(($count / $totalRecords) * 100, 2);
            $color = $percentage > 50 ? 'green' : ($percentage > 20 ? 'yellow' : 'white');
            
            $this->line(sprintf(
                "  <fg={$color}>%-30s %-12d %s</>",
                $app,
                $count,
                $percentage . '%'
            ));
        }

        // ========== MOSTRAR DISPOSITION ==========
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📞 DISPOSITION ENCONTRADOS (Estado Final)</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        $this->line(sprintf(
            "  <fg=white;options=bold>%-30s %-12s %-10s</>",
            'DISPOSITION', 'CANTIDAD', '%'
        ));
        $this->line("  " . str_repeat("─", 60));

        foreach ($dispositions as $disp => $count) {
            $percentage = round(($count / $totalRecords) * 100, 2);
            $color = match($disp) {
                'ANSWERED' => 'green',
                'NO ANSWER' => 'yellow',
                'BUSY', 'FAILED' => 'red',
                default => 'white'
            };
            
            $this->line(sprintf(
                "  <fg={$color}>%-30s %-12d %s</>",
                $disp,
                $count,
                $percentage . '%'
            ));
        }

        // ========== EJEMPLOS DETALLADOS ==========
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📝 EJEMPLOS DETALLADOS POR ACTION_TYPE</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();

        foreach ($actionTypes as $type => $count) {
            $example = $examplesByType[$type];
            
            $this->line("<fg=yellow;options=bold>🔹 {$type}</> ({$count} ocurrencias)");
            $this->line("  <fg=white>Fecha:</>           " . ($example['start'] ?? 'N/A'));
            $this->line("  <fg=white>Origen:</>          " . ($example['src'] ?? 'N/A') . " → Destino: " . ($example['dst'] ?? 'N/A'));
            $this->line("  <fg=white>Respondió:</>       " . ($example['dstanswer'] ?? 'N/A'));
            $this->line("  <fg=white>Disposition:</>     " . ($example['disposition'] ?? 'N/A'));
            $this->line("  <fg=white>Duración:</>        " . gmdate('i:s', $example['duration'] ?? 0));
            $this->line("  <fg=white>Action Owner:</>    " . ($example['action_owner'] ?? 'N/A'));
            $this->line("  <fg=white>Last App:</>        " . ($example['lastapp'] ?? 'N/A'));
            $this->line("  <fg=white>Last Data:</>       " . (isset($example['lastdata']) ? (strlen($example['lastdata']) > 80 ? substr($example['lastdata'], 0, 80) . '...' : $example['lastdata']) : 'N/A'));
            $this->line("  <fg=white>Channel:</>         " . ($example['channel'] ?? 'N/A'));
            $this->newLine();
        }

        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->newLine();
        
        $this->info("✅ Exploración completada:");
        $this->info("   - " . count($actionTypes) . " tipos de ACTION_TYPE");
        $this->info("   - " . count($actionOwners) . " tipos de ACTION_OWNER");
        $this->info("   - " . count($lastApps) . " tipos de LAST_APP");
        $this->info("   - " . count($dispositions) . " tipos de DISPOSITION");

        return 0;
    }

    /**
     * Obtener descripción de un action_type
     */
    private function getActionTypeDescription(string $type): string
    {
        return match($type) {
            'NO_DEFINIDO' => 'No tiene action_type definido',
            'Outgoing' => 'Llamada saliente',
            'Incoming' => 'Llamada entrante',
            'Internal' => 'Llamada interna entre extensiones',
            'Queue' => 'Llamada a través de cola',
            'IVR' => 'Llamada procesada por IVR',
            'Voicemail' => 'Dejó mensaje de voz',
            'Conference' => 'Llamada en conferencia',
            'Transfer' => 'Llamada transferida',
            'Forward' => 'Llamada reenviada',
            'Callback' => 'Devolución de llamada',
            'Pickup' => 'Llamada capturada',
            'Park' => 'Llamada estacionada',
            default => 'Tipo no documentado'
        };
    }
}

