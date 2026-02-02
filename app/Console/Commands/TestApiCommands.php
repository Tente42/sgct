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
                            {--start-time= : Fecha inicial para queueapi (YYYY-MM-DD)}
                            {--end-time= : Fecha final para queueapi (YYYY-MM-DD)}';
    
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
            $this->newLine();
            $this->info("Uso: php artisan api:test --pbx=1 --action=listExtensionGroup");
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
     * Probar cdrapi - Obtener 1 llamada reciente
     */
    private function testCdrApi(): int
    {
        $caller = $this->option('caller');
        $uniqueid = $this->option('uniqueid');
        
        if ($uniqueid) {
            $this->info("📞 Buscando llamada con Unique ID: {$uniqueid}...");
        } elseif ($caller) {
            $this->info("📞 Buscando última llamada contestada del origen: {$caller}...");
        } else {
            $this->info("📞 Obteniendo CDR (1 registro reciente)...");
        }
        $this->newLine();

        $params = [
            'format' => 'json',
            'numRecords' => $uniqueid ? 1000 : ($caller ? 100 : 1), // Buscar en más registros para uniqueid
            'minDur' => 0
        ];
        
        if ($caller) {
            $params['caller'] = $caller;
        }

        $response = $this->connectApi('cdrapi', $params, 60);

        if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
            $this->warn("⚠️ No se encontraron registros CDR" . ($caller ? " para el origen {$caller}" : "") . ".");
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
                // Mostrar el uniqueid de los primeros 3 registros para debug
                if ($totalRecords <= 5) {
                    $this->line("  🔍 Registro uniqueid: " . ($record['uniqueid'] ?? 'N/A'));
                }
                
                if (isset($record['uniqueid']) && $record['uniqueid'] === $uniqueid) {
                    $found = $record;
                    break;
                }
            }
            
            if (!$found) {
                $this->warn("⚠️ No se encontró llamada con Unique ID: {$uniqueid} en los últimos {$totalRecords} registros.");
                $this->info("💡 Intenta buscar por --caller si conoces el número de origen.");
                return 0;
            }
            
            $cdr = $found;
        }
        // Si se especificó caller, filtrar por ANSWERED y tomar la primera
        elseif ($caller) {
            $answered = array_filter($response['cdr_root'], function($record) {
                return $record['disposition'] === 'ANSWERED';
            });
            
            if (empty($answered)) {
                $this->warn("⚠️ No se encontraron llamadas contestadas para el origen {$caller}.");
                return 0;
            }
            
            $cdr = reset($answered);
        } else {
            $cdr = $response['cdr_root'][0];
        }

        $this->info("✅ Registro CDR encontrado:");
        $this->newLine();

        // Mostrar de manera ordenada y completa
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📋 IDENTIFICACIÓN DEL REGISTRO</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Account ID:</>           " . ($cdr['AcctId'] ?? 'N/A'));
        $this->line("  <fg=white>Account Code:</>         " . ($cdr['accountcode'] ?? 'N/A'));
        $this->line("  <fg=white>Session ID:</>           " . ($cdr['session'] ?? 'N/A'));
        $this->line("  <fg=white>Unique ID:</>            " . ($cdr['uniqueid'] ?? 'N/A'));
        $this->line("  <fg=white>CDR:</>                  " . ($cdr['cdr'] ?? 'N/A'));
        
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📞 INFORMACIÓN DEL ORIGEN (CALLER)</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
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
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📱 INFORMACIÓN DEL DESTINO (CALLEE)</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Número Destino (dst):</>       " . ($cdr['dst'] ?? 'N/A'));
        $this->line("  <fg=white>Quien Respondió:</>            " . ($cdr['dstanswer'] ?? 'N/A'));
        $this->line("  <fg=white>Canal Destino:</>              " . ($cdr['dstchannel'] ?? 'N/A'));
        $this->line("  <fg=white>Ext. Canal Destino:</>         " . ($cdr['dstchannel_ext'] ?? 'N/A'));
        $this->line("  <fg=white>Canal Extra Destino:</>        " . ($cdr['dstchanext'] ?? 'N/A'));
        $this->line("  <fg=white>Trunk Destino:</>              " . ($cdr['dst_trunk_name'] ?: '<fg=gray>Vacío</>'));
        $this->line("  <fg=white>Contexto Destino:</>           " . ($cdr['dcontext'] ?? 'N/A'));
        
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>⏱️  INFORMACIÓN DE TIEMPOS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Inicio (start):</>             " . ($cdr['start'] ?? 'N/A'));
        $this->line("  <fg=white>Respuesta (answer):</>         " . ($cdr['answer'] ?? 'N/A'));
        $this->line("  <fg=white>Fin (end):</>                  " . ($cdr['end'] ?? 'N/A'));
        $this->line("  <fg=white>Duración Total:</>             <fg=cyan>" . ($cdr['duration'] ?? '0') . "</> segundos");
        $this->line("  <fg=white>Tiempo Facturable:</>          <fg=cyan>" . ($cdr['billsec'] ?? '0') . "</> segundos");
        
        $this->newLine();
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>📊 ESTADO Y DISPOSICIÓN</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
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
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("<fg=yellow;options=bold>🔧 APLICACIÓN Y DATOS TÉCNICOS</>");
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        $this->line("  <fg=white>Última Aplicación:</>          " . ($cdr['lastapp'] ?? 'N/A'));
        $this->line("  <fg=white>Datos de la App:</>            " . ($cdr['lastdata'] ?? 'N/A'));
        
        if (!empty($cdr['recordfiles'])) {
            $this->newLine();
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>🎙️  GRABACIÓN</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
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
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            $this->line("<fg=yellow;options=bold>➕ CAMPOS ADICIONALES</>");
            $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
            foreach ($extraFields as $key => $value) {
                $this->line("  <fg=white>{$key}:</>  " . (is_array($value) ? json_encode($value) : $value));
            }
        }
        
        $this->line("<fg=cyan>══════════════════════════════════════════════════════════════════════════════════════════</>");
        
        $this->newLine();
        $this->info("✅ Comando ejecutado exitosamente.");

        return 0;
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
        $startTime = $this->option('start-time');
        $endTime = $this->option('end-time');
        
        // Validar fechas requeridas
        if (!$startTime || !$endTime) {
            $this->warn("⚠️ Debes especificar las fechas de inicio y fin.");
            $this->info("Uso: php artisan api:test --pbx=1 --action=queueapi --start-time=2026-02-01 --end-time=2026-02-02 [--queue=6500]");
            return 1;
        }

        $queueLabel = $queue ? "cola {$queue}" : "todas las colas";
        $this->info("📊 Obteniendo estadísticas de {$queueLabel}...");
        $this->info("📅 Período: {$startTime} al {$endTime}");
        $this->newLine();

        $params = [
            'format' => 'json',
            'startTime' => $startTime,
            'endTime' => $endTime,
            'statisticsType' => 'overview'
        ];
        
        if ($queue) {
            $params['queue'] = $queue;
        }

        $response = $this->connectApi('queueapi', $params, 60);

        // La respuesta puede venir en diferentes formatos según la API
        if (isset($response['status']) && $response['status'] !== 0) {
            $this->error("❌ Error en la API: " . json_encode($response));
            return 1;
        }

        // Verificar si hay datos
        if (empty($response) || (!isset($response['root_statistics']) && !isset($response['total']))) {
            $this->warn("⚠️ No se encontraron estadísticas para el período especificado.");
            return 0;
        }

        // Parsear datos (pueden venir en root_statistics o directamente)
        $data = $response['root_statistics'] ?? $response;
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
}

