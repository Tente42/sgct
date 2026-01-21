<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Call;

class SyncCalls extends Command
{
    protected $signature = 'calls:sync {--year=2026}';
    protected $description = 'Sincronizador Inteligente con Auto-Limpieza';

    protected $ip;
    protected $user;
    protected $pass;
    protected $cdrUrl;

    public function __construct()
    {
        parent::__construct();
        $this->ip = config('services.grandstream.host'); // IP o dominio del PBX Grandstream
        $this->user = config('services.grandstream.user'); // Usuario con permisos de API   
        $this->pass = config('services.grandstream.pass'); // Contraseña del usuario API
        // Puerto 8443 con DigestAuth para CDR
        $this->cdrUrl = "https://{$this->ip}:8443/cdrapi";
    }

    public function handle()
    {
        ini_set('memory_limit', '1024M'); 
        $year = $this->option('year');
        
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $now = Carbon::now();

        $this->info("============================================");
        $this->info("  SINCRONIZADOR CON FILTRO DE LIMPIEZA");
        $this->info("============================================");
        $this->info("  URL: {$this->cdrUrl}");

        while ($startDate->lessThanOrEqualTo($now)) {
            
            $start = $startDate->copy()->startOfMonth();
            $end   = $startDate->copy()->endOfMonth();
            if ($end->isFuture()) $end = $now->copy();

            $this->line(" Consultando: " . $start->format('Y-m-d H:i') . " -> " . $end->format('Y-m-d H:i'));

            try {
                // Usar DigestAuth en puerto 8443
                $response = Http::withDigestAuth($this->user, $this->pass)
                    ->timeout(60)
                    ->withoutVerifying()
                    ->get($this->cdrUrl, [
                        'format'    => 'JSON',
                        'startTime' => $start->format('Y-m-d\TH:i:s'), 
                        'endTime'   => $end->format('Y-m-d\TH:i:s'),
                        'minDur'    => 0 
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $calls = $data['cdr_root'] ?? [];
                    $count = count($calls);

                    $this->info("    Paquetes recibidos: {$count}");

                    if ($count > 0) {
                        $bar = $this->output->createProgressBar($count);
                        $bar->start();
                        $nuevas = 0; $actualizadas = 0;

                        foreach ($calls as $cdrPacket) {
                            
                            // 1. RECOLECTAR TODO
                            $validSegments = $this->collectAllSegments($cdrPacket);

                            if (empty($validSegments)) {
                                continue;
                            }

                            // --- 2. FILTRO ---
                            
                            // A. Eliminar registros "fantasmas" (main_cdr sin estado)
                            // En el debug vi que main_cdr venía con disposition="" (vacio). Eso es basura.
                            $validSegments = array_filter($validSegments, function($seg) {
                                return !empty($seg['disposition']); 
                            });

                            // B. Si hubo ÉXITO, borrar los FALLOS
                            // Verificamos si alguien contestó en este grupo
                            $huboExito = false;
                            foreach ($validSegments as $seg) {
                                if (($seg['billsec'] ?? 0) > 0) { 
                                    $huboExito = true; 
                                    break; 
                                }
                            }

                            // Si alguien contestó, filtramos y SOLO dejamos las contestadas
                            // Así eliminamos la llamada de "0 segundos" que te molesta
                            if ($huboExito) {
                                $validSegments = array_filter($validSegments, function($seg) {
                                    return ($seg['billsec'] ?? 0) > 0;
                                });
                            }

                            // --- 3. GUARDAR LO QUE QUEDÓ ---
                            foreach ($validSegments as $record) {
                                
                                // ID: Preferimos AcctId
                                if (!empty($record['acctid'])) {
                                    $uniqueKey = $record['acctid'];
                                } else {
                                    $baseId = $record['uniqueid'] ?? md5($record['start']);
                                    $uniqueKey = $baseId . '_' . ($record['dst'] ?? 'x');
                                }

                                if (Call::where('unique_id', $uniqueKey)->exists()) {
                                    $actualizadas++;
                                } else {
                                    $nuevas++;
                                }

                                Call::updateOrCreate(
                                    ['unique_id' => $uniqueKey], 
                                    [
                                        'start_time'    => $record['start'],
                                        'source'        => $record['src'] ?? 'Desconocido',
                                        'destination'   => $record['dst'] ?? 'Desconocido',
                                        'duration'      => $record['duration'] ?? 0,
                                        'billsec'       => $record['billsec'] ?? 0,
                                        'disposition'   => $record['disposition'] ?? 'UNKNOWN',
                                        'caller_name'   => $record['caller_name'] ?? null,
                                        'recording_file'=> $record['recordfiles'] ?? null,
                                    ]
                                );
                            }

                            $bar->advance();
                        }
                        $bar->finish();
                        $this->newLine();
                        $this->info("    Registros DB: $nuevas Nuevos | $actualizadas Actualizados");
                        $this->newLine();
                    }
                } else {
                    $this->error("    Error HTTP: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("    Error: " . $e->getMessage());
            }
            $startDate->addMonth();
            sleep(1);
        }
        $this->info(" ¡Sincronización Completa!");
    }

    private function collectAllSegments($cdrNode)
    {
        $collected = [];

        // Analizar el nodo actual
        if (is_array($cdrNode) && isset($cdrNode['start']) && !empty($cdrNode['start'])) {
            // Recolectamos todo lo que tenga duración o estado (incluso 0 segundos)
            // El filtro de arriba decidirá si lo borra o no.
            $collected[] = $cdrNode;
        }

        // Buscar recursivamente
        foreach ($cdrNode as $key => $value) {
            if (is_array($value) && (str_starts_with($key, 'sub_cdr') || $key === 'main_cdr')) {
                $children = $this->collectAllSegments($value);
                $collected = array_merge($collected, $children);
            }
        }

        return $collected;
    }
}