<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Call;
use App\Console\Commands\Concerns\ConfiguresPbx;
use App\Http\Controllers\Concerns\ProcessesCdr;

class SyncCalls extends Command
{
    use ConfiguresPbx, ProcessesCdr;

    protected $signature = 'calls:sync 
                            {--year=2026 : Año desde el cual sincronizar}
                            {--pbx= : ID de la central PBX a usar}';
    
    protected $description = 'Sincroniza llamadas (CDR) desde la central Grandstream';

    private int $pbxId;

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        // Configurar central
        $pbxId = $this->setupPbxConnection();
        if (!$pbxId) return 1;
        $this->pbxId = $pbxId;

        // Verificar conexión
        if (!$this->verifyConnection()) return 1;

        $this->syncCallsByMonth((int)$this->option('year'));
        
        $this->info(" ¡Sincronización Completa!");
        return 0;
    }

    /**
     * Sincronizar llamadas mes por mes desde el año indicado
     */
    private function syncCallsByMonth(int $year): void
    {
        $this->info("============================================");
        $this->info("  SINCRONIZADOR DE LLAMADAS (CDR)");
        $this->info("============================================");

        $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $now = Carbon::now();

        while ($startDate->lessThanOrEqualTo($now)) {
            $start = $startDate->copy()->startOfMonth();
            $end = $startDate->copy()->endOfMonth()->min($now);

            $this->processMonth($start, $end);
            $startDate->addMonth();
            sleep(1);
        }
    }

    /**
     * Procesar un mes de llamadas con paginación automática
     * 
     * La API cdrapi de Grandstream tiene un límite de registros por consulta.
     * Si un mes tiene más registros que el límite, se pagina automáticamente
     * desplazando la fecha de inicio al último registro recibido.
     */
    private function processMonth(Carbon $start, Carbon $end): void
    {
        $this->line(" Consultando: {$start->format('Y-m-d H:i')} -> {$end->format('Y-m-d H:i')}");

        $pageStart = $start->copy();
        $maxPerRequest = 1000;
        $totalDelMes = 0;
        $pagina = 1;

        try {
            do {
                $data = $this->connectApi('cdrapi', [
                    'format' => 'json',
                    'numRecords' => $maxPerRequest,
                    'startTime' => $pageStart->format('Y-m-d\TH:i:s'),
                    'endTime' => $end->format('Y-m-d\TH:i:s'),
                    'minDur' => 0
                ], 180);

                $calls = $data['cdr_root'] ?? [];
                $count = count($calls);
                $totalDelMes += $count;

                if ($pagina > 1) {
                    $this->info("    Página {$pagina}: {$count} paquetes (desde {$pageStart->format('Y-m-d H:i:s')})");
                } else {
                    $this->info("    Paquetes recibidos: {$count}");
                }

                if ($count > 0) {
                    $this->processCdrPackets($calls);

                    // Si recibimos el máximo, puede haber más → paginar
                    if ($count >= $maxPerRequest) {
                        // Obtener la fecha del último registro para continuar desde ahí
                        $lastCall = end($calls);
                        $lastStart = $lastCall['start'] ?? ($lastCall['main_cdr']['start'] ?? null);
                        
                        if ($lastStart) {
                            $newStart = Carbon::parse($lastStart);
                            // Evitar loop infinito: si la fecha no avanza, salir
                            if ($newStart->lessThanOrEqualTo($pageStart)) {
                                $this->warn("    ⚠ Fecha no avanza, deteniendo paginación para evitar loop infinito.");
                                break;
                            }
                            $pageStart = $newStart;
                            $pagina++;
                            $this->info("    → Hay más registros, continuando desde {$pageStart->format('Y-m-d H:i:s')}...");
                            sleep(1); // Pausa entre páginas para no saturar la API
                            continue;
                        }
                    }
                }

                break; // No hay más páginas

            } while (true);

            if ($totalDelMes > 0 && $pagina > 1) {
                $this->info("    Total del mes: {$totalDelMes} paquetes en {$pagina} páginas");
            }

        } catch (\Exception $e) {
            $this->error("    Error: " . $e->getMessage());
        }
    }

    /**
     * Procesar paquetes CDR y guardar en base de datos
     */
    private function processCdrPackets(array $calls): void
    {
        $bar = $this->output->createProgressBar(count($calls));
        $bar->start();
        $nuevas = 0;
        $actualizadas = 0;

        foreach ($calls as $cdrPacket) {
            $segments = $this->collectCdrSegments($cdrPacket);
            $segments = array_filter($segments, fn($s) => !empty($s['disposition']));

            if (empty($segments)) {
                $bar->advance();
                continue;
            }

            $consolidated = $this->consolidateCdrSegments(array_values($segments));
            if (empty($consolidated)) {
                $bar->advance();
                continue;
            }

            $exists = Call::withoutGlobalScope('current_pbx')
                ->where('unique_id', $consolidated['unique_id'])
                ->where('pbx_connection_id', $this->pbxId)
                ->exists();

            $exists ? $actualizadas++ : $nuevas++;

            Call::withoutGlobalScope('current_pbx')->updateOrCreate(
                ['unique_id' => $consolidated['unique_id'], 'pbx_connection_id' => $this->pbxId],
                $consolidated
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("    Registros: {$nuevas} Nuevos | {$actualizadas} Actualizados");
        $this->newLine();
    }
}
