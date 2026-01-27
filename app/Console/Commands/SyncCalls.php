<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Call;
use App\Traits\GrandstreamTrait;

class SyncCalls extends Command
{
    use GrandstreamTrait;

    protected $signature = 'calls:sync {--year=2026}';
    protected $description = 'Sincronizador Inteligente con Auto-Limpieza (usa Cookie Auth)';

    public function handle()
    {
        ini_set('memory_limit', '1024M'); 
        $year = $this->option('year');
        
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $now = Carbon::now();

        $this->info("============================================");
        $this->info("  SINCRONIZADOR CON FILTRO DE LIMPIEZA");
        $this->info("  (Método: Cookie Auth - Puerto 7110)");
        $this->info("============================================");

        while ($startDate->lessThanOrEqualTo($now)) {
            
            $start = $startDate->copy()->startOfMonth();
            $end   = $startDate->copy()->endOfMonth();
            if ($end->isFuture()) $end = $now->copy();

            $this->line(" Consultando: " . $start->format('Y-m-d H:i') . " -> " . $end->format('Y-m-d H:i'));

            try {
                // Usar Cookie Auth a través del trait (puerto 7110)
                // FORMATO DE FECHA: YYYY-MM-DDTHH:MM:SS (la T es obligatoria)
                $data = $this->connectApi('cdrapi', [
                    'format' => 'json',
                    'startTime' => $start->format('Y-m-d\TH:i:s'),
                    'endTime' => $end->format('Y-m-d\TH:i:s'),
                    'minDur' => 0
                ], 120); // Timeout de 120s para rangos grandes

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
                            $bar->advance();
                            continue;
                        }

                        // --- 2. FILTRO ---
                        
                        // A. Eliminar registros "fantasmas" (main_cdr sin estado)
                        $validSegments = array_filter($validSegments, function($seg) {
                            return !empty($seg['disposition']); 
                        });

                        if (empty($validSegments)) {
                            $bar->advance();
                            continue;
                        }

                        // --- 3. CONSOLIDAR LLAMADA (Buscar anexo y sumar tiempos) ---
                        $consolidated = $this->consolidateCall($validSegments);

                        if (empty($consolidated)) {
                            $bar->advance();
                            continue;
                        }

                        // --- 4. GUARDAR REGISTRO CONSOLIDADO ---
                        $uniqueKey = $consolidated['unique_id'];

                        if (Call::where('unique_id', $uniqueKey)->exists()) {
                            $actualizadas++;
                        } else {
                            $nuevas++;
                        }

                        Call::updateOrCreate(
                            ['unique_id' => $uniqueKey], 
                            [
                                'start_time'    => $consolidated['start_time'],
                                'source'        => $consolidated['source'],
                                'destination'   => $consolidated['destination'],
                                'duration'      => $consolidated['duration'],
                                'billsec'       => $consolidated['billsec'],
                                'disposition'   => $consolidated['disposition'],
                                'caller_name'   => $consolidated['caller_name'],
                                'recording_file'=> $consolidated['recording_file'],
                            ]
                        );

                        $bar->advance();
                    }
                    $bar->finish();
                    $this->newLine();
                    $this->info("    Registros DB: $nuevas Nuevos | $actualizadas Actualizados");
                    $this->newLine();
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
            // Recolectamos todo lo que tenga duracion o estado (incluso 0 segundos)
            // El filtro de arriba decidira si lo borra o no.
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

    /**
     * Consolida todos los segmentos de una llamada en un solo registro.
     * - Busca el anexo (4 dígitos) como origen principal
     * - Suma los tiempos de todos los segmentos
     * - Determina el destino externo (número no-anexo)
     */
    private function consolidateCall(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        // Reindexar array para evitar problemas con array_filter
        $segments = array_values($segments);

        // Variables para consolidar
        $anexoOrigen = null;
        $numeroExterno = null;
        $totalBillsec = 0;
        $totalDuration = 0;
        $startTime = null;
        $disposition = 'NO ANSWER';
        $callerName = null;
        $recordingFile = null;
        $uniqueId = null;

        // Determinar si es llamada ENTRANTE o SALIENTE
        // Si el primer segmento tiene src como número largo (externo) y dst como anexo = ENTRANTE
        // Si el primer segmento tiene src como anexo y dst como número largo = SALIENTE
        $firstSeg = $segments[0];
        $firstSrc = $firstSeg['src'] ?? '';
        $firstDst = $firstSeg['dst'] ?? '';

        $esEntrante = $this->esNumeroExterno($firstSrc) && $this->esAnexo($firstDst);
        $esSaliente = $this->esAnexo($firstSrc) && $this->esNumeroExterno($firstDst);
        $esInterna = $this->esAnexo($firstSrc) && $this->esAnexo($firstDst);

        // Recorrer todos los segmentos
        foreach ($segments as $seg) {
            $src = $seg['src'] ?? '';
            $dst = $seg['dst'] ?? '';
            $billsec = (int)($seg['billsec'] ?? 0);
            $duration = (int)($seg['duration'] ?? 0);

            // Capturar el start_time más temprano
            if ($startTime === null || (isset($seg['start']) && $seg['start'] < $startTime)) {
                $startTime = $seg['start'] ?? null;
            }

            // Capturar unique_id del primer segmento válido
            if ($uniqueId === null) {
                $uniqueId = $seg['acctid'] ?? $seg['uniqueid'] ?? null;
            }

            // Sumar tiempos
            $totalBillsec += $billsec;
            $totalDuration += $duration;

            // Buscar anexo y número externo según tipo de llamada
            if ($esEntrante) {
                // ENTRANTE: El anexo es el destino, el externo es el origen
                if ($this->esAnexo($dst) && $anexoOrigen === null) {
                    $anexoOrigen = $dst;
                }
                if ($this->esNumeroExterno($src) && $numeroExterno === null) {
                    $numeroExterno = $src;
                }
            } elseif ($esSaliente || $esInterna) {
                // SALIENTE/INTERNA: El anexo es el origen, el destino es el externo/otro anexo
                if ($this->esAnexo($src) && $anexoOrigen === null) {
                    $anexoOrigen = $src;
                }
                if ($numeroExterno === null) {
                    $numeroExterno = $dst;
                }
            } else {
                // Caso mixto: buscar cualquier anexo
                if ($this->esAnexo($src) && $anexoOrigen === null) {
                    $anexoOrigen = $src;
                } elseif ($this->esAnexo($dst) && $anexoOrigen === null) {
                    $anexoOrigen = $dst;
                }
                if ($this->esNumeroExterno($dst) && $numeroExterno === null) {
                    $numeroExterno = $dst;
                } elseif ($this->esNumeroExterno($src) && $numeroExterno === null) {
                    $numeroExterno = $src;
                }
            }

            // Capturar caller_name si existe
            if (empty($callerName) && !empty($seg['caller_name'])) {
                $callerName = $seg['caller_name'];
            }

            // Capturar archivo de grabación si existe
            if (empty($recordingFile) && !empty($seg['recordfiles'])) {
                $recordingFile = $seg['recordfiles'];
            }

            // Si algún segmento fue contestado, la llamada fue contestada
            if ($billsec > 0) {
                $disposition = 'ANSWERED';
            }
        }

        // Si no se encontró anexo, usar el primer src disponible
        if ($anexoOrigen === null) {
            $anexoOrigen = $firstSrc ?: 'Desconocido';
        }

        // Si no se encontró número externo, usar el primer dst disponible
        if ($numeroExterno === null) {
            $numeroExterno = $firstDst ?: 'Desconocido';
        }

        // Determinar disposition final si no hubo ANSWERED
        if ($disposition !== 'ANSWERED' && !empty($segments)) {
            // Buscar el disposition más relevante
            foreach ($segments as $seg) {
                $disp = strtoupper($seg['disposition'] ?? '');
                if (strpos($disp, 'BUSY') !== false) {
                    $disposition = 'BUSY';
                    break;
                } elseif (strpos($disp, 'FAILED') !== false) {
                    $disposition = 'FAILED';
                }
            }
        }

        // Generar unique_id si no existe
        if ($uniqueId === null) {
            $uniqueId = md5($startTime . $anexoOrigen . $numeroExterno);
        }

        return [
            'unique_id'     => $uniqueId,
            'start_time'    => $startTime,
            'source'        => $anexoOrigen,
            'destination'   => $numeroExterno,
            'duration'      => $totalDuration,
            'billsec'       => $totalBillsec,
            'disposition'   => $disposition,
            'caller_name'   => $callerName,
            'recording_file'=> $recordingFile,
        ];
    }

    /**
     * Verifica si un número es un anexo interno (3-4 dígitos)
     */
    private function esAnexo(string $numero): bool
    {
        return preg_match('/^\d{3,4}$/', $numero) === 1;
    }

    /**
     * Verifica si un número es externo (más de 4 dígitos o empieza con + o tiene código de país)
     */
    private function esNumeroExterno(string $numero): bool
    {
        // Números largos (más de 4 dígitos) o que empiezan con +
        return preg_match('/^(\+|\d{5,})/', $numero) === 1;
    }
}