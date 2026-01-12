<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Call;

class SyncCalls extends Command
{
    // Nombre del comando
    protected $signature = 'calls:sync {--year=2023}';
    
    // Descripción
    protected $description = 'Descarga historial de llamadas desde la central';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        $year = $this->option('year');
        $startDate = Carbon::createFromDate($year, 1, 1);
        $now = Carbon::now();

        $this->info("Iniciando sincronización... (Año: $year)");

        while ($startDate->lessThanOrEqualTo($now)) {
            
            $start = $startDate->copy()->startOfMonth();
            $end = $startDate->copy()->endOfMonth();
            
            if ($end->isFuture()) $end = $now;

            $this->line("Procesando: " . $start->format('Y-m-d'));

            try {
                // --- TU CONFIGURACIÓN (Verificada) ---
                $url = 'https://10.36.1.10:8443/cdrapi';
                
                $response = Http::withDigestAuth('cdrapi', '123api')
                    ->timeout(30)
                    ->withoutVerifying()
                    ->get($url, [
                        'format'    => 'JSON',
                        'startTime' => $start->format('Y-m-d\TH:i:s'),
                        'endTime'   => $end->format('Y-m-d\TH:i:s'),
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $calls = $data['cdr_root'] ?? [];
                    $count = count($calls);

                    $this->info("   Recibidas: {$count}");

                    if ($count > 0) {
                        $bar = $this->output->createProgressBar($count);
                        $bar->start();

                        foreach ($calls as $cdr) {
                            // BLINDAJE: Si no tiene ID, saltar
                            if (!isset($cdr['uniqueid'])) continue;

                            Call::updateOrCreate(
                                ['unique_id' => $cdr['uniqueid']],
                                [
                                    'start_time'    => $cdr['start'],
                                    'source'        => $cdr['src'],
                                    'destination'   => $cdr['dst'],
                                    'duration'      => $cdr['duration'],
                                    'billsec'       => $cdr['billsec'] ?? 0,
                                    'disposition'   => $cdr['disposition'],
                                    'caller_name'   => $cdr['caller_name'] ?? null,
                                    'recording_file'=> $cdr['recordfiles'] ?? null,
                                ]
                            );
                            $bar->advance();
                        }
                        $bar->finish();
                        $this->newLine();
                    }
                } else {
                    $this->error("   Error HTTP: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("   Error: " . $e->getMessage());
            }

            $startDate->addMonth();
            sleep(1);
        }
        
        $this->info(" ¡Listo!");
    }
}
