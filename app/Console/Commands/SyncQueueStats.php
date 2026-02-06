<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ConfiguresPbx;
use App\Models\QueueCallDetail;
use App\Models\PbxConnection;
use Carbon\Carbon;

class SyncQueueStats extends Command
{
    use ConfiguresPbx;

    protected $signature = 'sync:queue-stats 
                            {--pbx=1 : ID de la central PBX a sincronizar (OBLIGATORIO)}
                            {--queue= : Número de cola específica a sincronizar}
                            {--days=7 : Número de días hacia atrás a sincronizar (por defecto 7)}
                            {--start-date= : Fecha de inicio específica (YYYY-MM-DD)}
                            {--end-date= : Fecha de fin específica (YYYY-MM-DD)}
                            {--force : Forzar resincronización (elimina datos existentes del período)}';
    
    protected $description = 'Sincroniza estadísticas de colas desde queueapi hacia la base de datos local (requiere --pbx)';

    private int $totalInserted = 0;
    private int $totalSkipped = 0;
    private int $totalErrors = 0;

    public function handle(): int
    {
        $this->info("============================================");
        $this->info("  SINCRONIZACIÓN DE ESTADÍSTICAS DE COLA");
        $this->info("============================================");
        $this->newLine();

        // Determinar fechas
        $endDate = $this->option('end-date') 
            ? Carbon::parse($this->option('end-date')) 
            : Carbon::now();
        
        $startDate = $this->option('start-date')
            ? Carbon::parse($this->option('start-date'))
            : $endDate->copy()->subDays((int) $this->option('days'));

        $this->info("📅 Período: {$startDate->format('Y-m-d')} al {$endDate->format('Y-m-d')}");
        $this->newLine();

        // Obtener central a sincronizar (ahora es obligatorio)
        $pbxId = (int) $this->option('pbx');
        
        if (!$pbxId) {
            $this->error("❌ Debe especificar una central PBX con --pbx=ID");
            return 1;
        }
        
        $pbxConnections = PbxConnection::where('id', $pbxId)->get();
        if ($pbxConnections->isEmpty()) {
            $this->error("❌ No se encontró la central PBX con ID: {$pbxId}");
            return 1;
        }

        $this->info("🏢 Central a sincronizar: " . $pbxConnections->first()->name);
        $this->newLine();

        // Sincronizar cada central
        foreach ($pbxConnections as $pbx) {
            $this->syncPbx($pbx, $startDate, $endDate);
        }

        // Resumen final
        $this->newLine();
        $this->line("══════════════════════════════════════════");
        $this->info("📊 RESUMEN DE SINCRONIZACIÓN");
        $this->line("══════════════════════════════════════════");
        $this->info("✅ Registros insertados: {$this->totalInserted}");
        $this->info("⏭️  Registros omitidos (ya existían): {$this->totalSkipped}");
        if ($this->totalErrors > 0) {
            $this->warn("⚠️  Errores: {$this->totalErrors}");
        }
        $this->newLine();

        return 0;
    }

    /**
     * Sincronizar una central PBX específica
     */
    private function syncPbx(PbxConnection $pbx, Carbon $startDate, Carbon $endDate): void
    {
        $this->line("──────────────────────────────────────────");
        $this->info("🏢 Central: {$pbx->name} ({$pbx->ip_address})");
        $this->line("──────────────────────────────────────────");

        // Configurar la conexión activa
        $this->activePbx = $pbx;
        session(['active_pbx_id' => $pbx->id]);

        // Verificar conexión
        if (!$this->verifyConnection()) {
            $this->error("   ❌ No se pudo conectar a la central");
            $this->totalErrors++;
            return;
        }

        // Obtener lista de colas
        $queues = $this->getQueues();
        
        if (empty($queues)) {
            $this->warn("   ⚠️  No se encontraron colas en esta central");
            return;
        }

        $this->info("   📞 Colas encontradas: " . implode(', ', $queues));

        // Filtrar por cola específica si se especificó
        $queueFilter = $this->option('queue');
        if ($queueFilter) {
            if (!in_array($queueFilter, $queues)) {
                $this->warn("   ⚠️  La cola {$queueFilter} no existe en esta central");
                return;
            }
            $queues = [$queueFilter];
            $this->info("   🎯 Sincronizando solo cola: {$queueFilter}");
        }

        // Si force está activo, eliminar datos existentes del período
        if ($this->option('force')) {
            $deleted = QueueCallDetail::withoutGlobalScopes()
                ->where('pbx_connection_id', $pbx->id)
                ->whereIn('queue', $queues)
                ->whereBetween('call_time', [
                    $startDate->format('Y-m-d') . ' 00:00:00',
                    $endDate->format('Y-m-d') . ' 23:59:59'
                ])
                ->delete();
            
            if ($deleted > 0) {
                $this->warn("   🗑️  Eliminados {$deleted} registros existentes (--force)");
            }
        }

        // Sincronizar cada cola
        foreach ($queues as $queue) {
            $this->syncQueue($pbx, $queue, $startDate, $endDate);
        }
    }

    /**
     * Obtener lista de colas de la central
     */
    private function getQueues(): array
    {
        $response = $this->connectApi('listQueue', [
            'options' => 'extension',
            'sidx' => 'extension',
            'sord' => 'asc'
        ]);

        if (($response['status'] ?? -1) !== 0) {
            return [];
        }

        $queues = $response['response']['queue'] ?? [];
        return array_column($queues, 'extension');
    }

    /**
     * Sincronizar una cola específica
     */
    private function syncQueue(PbxConnection $pbx, string $queue, Carbon $startDate, Carbon $endDate): void
    {
        $this->line("   📞 Sincronizando cola {$queue}...");

        // Llamar a queueapi con statisticsType=calldetail
        $response = $this->connectApi('queueapi', [
            'format' => 'json',
            'queue' => $queue,
            'agent' => '*',
            'startTime' => $startDate->format('Y-m-d'),
            'endTime' => $endDate->format('Y-m-d'),
            'statisticsType' => 'calldetail'
        ], 120); // Timeout de 2 minutos

        if (!isset($response['queue_statistics']) || empty($response['queue_statistics'])) {
            $this->line("      ℹ️  Sin datos para este período");
            return;
        }

        // Extraer los detalles de llamadas de queue_statistics
        // La estructura REAL es: cada elemento tiene un campo 'agent' que contiene los datos
        $callDetails = [];
        foreach ($response['queue_statistics'] as $queueStat) {
            if (isset($queueStat['agent']) && is_array($queueStat['agent'])) {
                // Cada elemento 'agent' ES UNA LLAMADA
                $callDetails[] = $queueStat['agent'];
            }
        }

        if (empty($callDetails)) {
            $this->line("      ℹ️  Sin detalles de llamadas para este período");
            return;
        }

        $inserted = 0;
        $skipped = 0;
        $apiDuplicates = 0;
        $seenInBatch = []; // Para detectar duplicados dentro de la misma respuesta

        foreach ($callDetails as $call) {
            // Campos de la llamada según la estructura real de queueapi:
            // callernum, start_time, agent, extension (cola), wait_time, talk_time, connect
            $queueNumber = $call['extension'] ?? null; // Cola REAL de la llamada
            $callerNumber = $call['callernum'] ?? 'unknown';
            $agentNumber = $call['agent'] ?? 'NONE';
            $callTimeStr = $call['start_time'] ?? null;
            $connected = ($call['connect'] ?? 'no') === 'yes';
            $waitTime = (int) ($call['wait_time'] ?? 0);
            $talkTime = (int) ($call['talk_time'] ?? 0);

            if (!$callTimeStr || !$queueNumber) continue;

            // Si el agente es NONE, mantenerlo así
            if (!$agentNumber || $agentNumber === 'NONE') {
                $agentNumber = 'NONE';
            }

            // Parsear la hora de la llamada
            try {
                $callTime = Carbon::parse($callTimeStr);
            } catch (\Exception $e) {
                continue;
            }

            // Crear clave única para detectar duplicados en la misma respuesta de la API
            $uniqueKey = "{$queueNumber}|{$callerNumber}|{$agentNumber}|{$callTime->format('Y-m-d H:i:s')}";
            
            if (isset($seenInBatch[$uniqueKey])) {
                $apiDuplicates++;
                continue; // Saltar duplicados que vienen en la misma respuesta de la API
            }
            
            $seenInBatch[$uniqueKey] = true;

            // Intentar insertar o ignorar si ya existe
            try {
                $existing = QueueCallDetail::withoutGlobalScopes()
                    ->where('pbx_connection_id', $pbx->id)
                    ->where('queue', $queueNumber)
                    ->where('caller', $callerNumber)
                    ->where('agent', $agentNumber)
                    ->where('call_time', $callTime)
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                QueueCallDetail::create([
                    'pbx_connection_id' => $pbx->id,
                    'queue' => $queueNumber,
                    'caller' => $callerNumber,
                    'agent' => $agentNumber,
                    'call_time' => $callTime,
                    'wait_time' => $waitTime,
                    'talk_time' => $talkTime,
                    'connected' => $connected,
                ]);

                $inserted++;
            } catch (\Exception $e) {
                // Puede ser duplicado por unique constraint
                $skipped++;
            }
        }

        $message = "      ✅ Insertados: {$inserted} | Omitidos: {$skipped}";
        if ($apiDuplicates > 0) {
            $message .= " | ⚠️ Duplicados API: {$apiDuplicates}";
        }
        $this->info($message);
        
        $this->totalInserted += $inserted;
        $this->totalSkipped += $skipped;
    }
}
