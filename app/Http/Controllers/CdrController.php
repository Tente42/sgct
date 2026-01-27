<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Call;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\CallsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\GrandstreamTrait;

class CdrController extends Controller
{
    use GrandstreamTrait;
    // ==========================================
    // MÉTODO 1: DASHBOARD (Sin cambios)
    // ==========================================
    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');
        $titulo      = $request->input('titulo', 'Reporte de Llamadas');
        
        // Ordenamiento - solo uno activo a la vez
        $sortBy = $request->input('sort', 'start_time');
        $sortDir = $request->input('dir', 'desc');
        
        // Validar columnas permitidas para ordenar
        $allowedSorts = ['start_time', 'billsec', 'tipo', 'costo'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'start_time';
        }
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ]);

        if ($anexo) {
            $query->where('source', $anexo);
        }

        // Grafico
        $datosGrafico = (clone $query)
                        ->selectRaw('DATE(start_time) as fecha, count(*) as total')
                        ->groupBy('fecha')
                        ->orderBy('fecha', 'asc')
                        ->get();

        $labels = $datosGrafico->pluck('fecha');
        $data   = $datosGrafico->pluck('total');

        // Totales
        $totalLlamadas = $query->count();
        $totalSegundos = $query->sum('billsec');
        $minutosFacturables = ceil($totalSegundos / 60);
        
        // Calcular el total sumando el costo de cada llamada (usando el accessor)
        $llamadasParaCosto = (clone $query)->get();
        $totalPagar = $llamadasParaCosto->sum(fn($call) => $call->cost);

        // Aplicar ordenamiento
        if ($sortBy === 'tipo') {
            // Ordenar por tipo de llamada usando CASE en SQL
            $query->orderByRaw("
                CASE 
                    WHEN destination REGEXP '^[0-9]{3,4}$' THEN 1
                    WHEN destination REGEXP '^800' THEN 2
                    WHEN destination REGEXP '^9[0-9]{8}$' THEN 3
                    WHEN destination REGEXP '^\\\\+?569[0-9]{8}$' THEN 3
                    WHEN destination REGEXP '^(\\\\+|00)' AND destination NOT REGEXP '^\\\\+?56' THEN 5
                    ELSE 4
                END " . $sortDir);
        } elseif ($sortBy === 'costo') {
            // Ordenar por costo calculado
            // Obtener tarifas
            $prices = \App\Models\Setting::pluck('value', 'key')->toArray();
            $priceMobile = $prices['price_mobile'] ?? 80;
            $priceNational = $prices['price_national'] ?? 40;
            $priceInternational = $prices['price_international'] ?? 500;
            
            $query->orderByRaw("
                CASE 
                    WHEN billsec <= 3 THEN 0
                    WHEN destination REGEXP '^[0-9]{3,4}$' THEN 0
                    WHEN destination REGEXP '^800' THEN 0
                    WHEN destination REGEXP '^9[0-9]{8}$' THEN CEIL(billsec/60) * {$priceMobile}
                    WHEN destination REGEXP '^\\\\+?569[0-9]{8}$' THEN CEIL(billsec/60) * {$priceMobile}
                    WHEN destination REGEXP '^(\\\\+|00)' AND destination NOT REGEXP '^\\\\+?56' THEN CEIL(billsec/60) * {$priceInternational}
                    ELSE CEIL(billsec/60) * {$priceNational}
                END " . $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $llamadas = $query->paginate(50);

        return view('reporte', compact(
            'llamadas', 'fechaInicio', 'fechaFin', 'anexo', 'totalLlamadas', 
            'totalSegundos', 'totalPagar', 'minutosFacturables', 'labels', 'data', 'titulo',
            'sortBy', 'sortDir'
        ));
    }

    // ==========================================
    // METODO 2: SINCRONIZAR (WEB)
    // ==========================================
    public function syncCDRs()
    {
        // 1. DEFINIR RANGO DE TIEMPO INCREMENTAL
        $ultimaLlamada = Call::orderBy('start_time', 'desc')->first();

        if ($ultimaLlamada) {
            // "Colchon" de 1 hora para asegurar que no perdemos llamadas en el borde
            $start = Carbon::parse($ultimaLlamada->start_time)->subHour();
        } else {
            // Primera vez: ultimos 30 dias
            $start = now()->subDays(30);
        }

        $end = now(); 

        try {
            // 2. CONECTAR A LA API (usando el trait - puerto 7110 con Cookie Auth)
            // FORMATO DE FECHA: YYYY-MM-DDTHH:MM:SS (la T es obligatoria)
            $response = $this->connectApi('cdrapi', [
                'format' => 'json',
                'startTime' => $start->format('Y-m-d\TH:i:s'),
                'endTime' => $end->format('Y-m-d\TH:i:s'),
                'minDur' => 0
            ], 120); // Timeout de 120 segundos para CDRs

            if (($response['status'] ?? -1) == 0 || isset($response['cdr_root'])) {
                
                if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
                    return back()->with('success', 'Conexión exitosa. No hay llamadas nuevas.');
                }

                $calls = $response['cdr_root'];
                $contadorNuevas = 0;
                $contadorActualizadas = 0;

                // 3. PROCESAMIENTO 
                foreach ($calls as $cdrPacket) {
                    
                    // A. RECOLECTAR TODOS LOS TRAMOS (Recursividad)
                    $validSegments = $this->collectAllSegments($cdrPacket);

                    if (empty($validSegments)) {
                        continue;
                    }

                    // B. FILTRO DE LIMPIEZA - Quitar fantasmas sin estado
                    $validSegments = array_filter($validSegments, function($seg) {
                        return !empty($seg['disposition']); 
                    });

                    if (empty($validSegments)) {
                        continue;
                    }

                    // C. CONSOLIDAR LLAMADA (Buscar anexo y sumar tiempos)
                    $consolidated = $this->consolidateCall($validSegments);

                    if (empty($consolidated)) {
                        continue;
                    }

                    // D. GUARDAR REGISTRO CONSOLIDADO
                    $call = Call::updateOrCreate(
                        ['unique_id' => $consolidated['unique_id']], 
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

                    if ($call->wasRecentlyCreated) {
                        $contadorNuevas++;
                    } else {
                        $contadorActualizadas++;
                    }
                }

                return back()->with('success', "Sincronización: {$contadorNuevas} nuevas, {$contadorActualizadas} actualizadas.");

            } else {
                $errorMsg = $response['response']['body'] ?? json_encode($response);
                return back()->with('error', 'Error Central: ' . $errorMsg);
            }

        } catch (\Exception $e) {
            Log::error("Error Sync Web: " . $e->getMessage());
            return back()->with('error', 'Error técnico: ' . $e->getMessage());
        }
    }

    // ==========================================
    // HELPER: RECOLECTOR RECURSIVO (Privado)
    // ==========================================
    private function collectAllSegments($cdrNode)
    {
        $collected = [];

        // 1. Analizar el nodo actual
        if (is_array($cdrNode) && isset($cdrNode['start']) && !empty($cdrNode['start'])) {
            $collected[] = $cdrNode;
        }

        // 2. Buscar recursivamente en hijos (sub_cdr, main_cdr)
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

        $segments = array_values($segments);

        $anexoOrigen = null;
        $numeroExterno = null;
        $totalBillsec = 0;
        $totalDuration = 0;
        $startTime = null;
        $disposition = 'NO ANSWER';
        $callerName = null;
        $recordingFile = null;
        $uniqueId = null;

        $firstSeg = $segments[0];
        $firstSrc = $firstSeg['src'] ?? '';
        $firstDst = $firstSeg['dst'] ?? '';

        $esEntrante = $this->esNumeroExterno($firstSrc) && $this->esAnexo($firstDst);
        $esSaliente = $this->esAnexo($firstSrc) && $this->esNumeroExterno($firstDst);
        $esInterna = $this->esAnexo($firstSrc) && $this->esAnexo($firstDst);

        foreach ($segments as $seg) {
            $src = $seg['src'] ?? '';
            $dst = $seg['dst'] ?? '';
            $billsec = (int)($seg['billsec'] ?? 0);
            $duration = (int)($seg['duration'] ?? 0);

            if ($startTime === null || (isset($seg['start']) && $seg['start'] < $startTime)) {
                $startTime = $seg['start'] ?? null;
            }

            if ($uniqueId === null) {
                $uniqueId = $seg['acctid'] ?? $seg['uniqueid'] ?? null;
            }

            $totalBillsec += $billsec;
            $totalDuration += $duration;

            if ($esEntrante) {
                if ($this->esAnexo($dst) && $anexoOrigen === null) {
                    $anexoOrigen = $dst;
                }
                if ($this->esNumeroExterno($src) && $numeroExterno === null) {
                    $numeroExterno = $src;
                }
            } elseif ($esSaliente || $esInterna) {
                if ($this->esAnexo($src) && $anexoOrigen === null) {
                    $anexoOrigen = $src;
                }
                if ($numeroExterno === null) {
                    $numeroExterno = $dst;
                }
            } else {
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

            if (empty($callerName) && !empty($seg['caller_name'])) {
                $callerName = $seg['caller_name'];
            }

            if (empty($recordingFile) && !empty($seg['recordfiles'])) {
                $recordingFile = $seg['recordfiles'];
            }

            if ($billsec > 0) {
                $disposition = 'ANSWERED';
            }
        }

        if ($anexoOrigen === null) {
            $anexoOrigen = $firstSrc ?: 'Desconocido';
        }

        if ($numeroExterno === null) {
            $numeroExterno = $firstDst ?: 'Desconocido';
        }

        if ($disposition !== 'ANSWERED' && !empty($segments)) {
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

    private function esAnexo(string $numero): bool
    {
        return preg_match('/^\d{3,4}$/', $numero) === 1;
    }

    private function esNumeroExterno(string $numero): bool
    {
        return preg_match('/^(\+|\d{5,})/', $numero) === 1;
    }

    // ==========================================
    // MÉTODO 3: PDF 
    // ==========================================
    public function descargarPDF(Request $request)
    {
        // Aumentar límite de memoria temporalmente para PDFs grandes
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', 300);
        
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');

        // Query base: solo llamadas CONTESTADAS (las que generan costo)
        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ])->where('disposition', 'LIKE', '%ANSWERED%');

        if ($anexo) {
            $query->where('source', $anexo);
        }

        // Contar total de registros en el período
        $totalRegistros = (clone $query)->count();
        
        // Calcular totales del período COMPLETO usando agregación en BD
        $totalesPeriodo = (clone $query)->selectRaw('
            COUNT(*) as total_llamadas,
            SUM(billsec) as total_segundos
        ')->first();

        $totalLlamadas = $totalesPeriodo->total_llamadas ?? 0;
        $totalSegundos = $totalesPeriodo->total_segundos ?? 0;
        $minutosFacturables = ceil($totalSegundos / 60);

        // Limitar registros para el detalle del PDF (máximo 500)
        $limitePDF = 500;
        $llamadas = $query->orderBy('start_time', 'asc')->limit($limitePDF)->get();
        
        // Calcular el total a pagar sumando el costo de cada llamada del período completo
        // Usamos chunks para no agotar memoria
        $totalPagar = 0;
        Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ])->where('disposition', 'LIKE', '%ANSWERED%')
          ->when($anexo, fn($q) => $q->where('source', $anexo))
          ->chunk(500, function ($chunk) use (&$totalPagar) {
              foreach ($chunk as $call) {
                  $totalPagar += $call->cost;
              }
          });

        $titulo = $request->input('titulo', 'Reporte de Llamadas');
        $ip_central = config('services.grandstream.host');
        
        // Indicar si el reporte está truncado
        $truncado = $totalRegistros > $limitePDF;
        $registrosMostrados = min($totalRegistros, $limitePDF);

        $pdf = Pdf::loadView('pdf_reporte', compact(
            'llamadas', 'fechaInicio', 'fechaFin', 'anexo', 
            'totalLlamadas', 'totalPagar', 'minutosFacturables',
            'titulo', 'ip_central', 'truncado', 'registrosMostrados', 'totalRegistros'
        ))->setPaper('letter', 'portrait');

        return $pdf->download('Reporte_Llamadas_' . date('dmY_His') . '.pdf');
    }

    // ==========================================
    // METODO 4: EXCEL & GRAFICOS 
    // ==========================================
    public function exportarExcel(\Illuminate\Http\Request $request)
    {
        $filtros = [
            'fecha_inicio' => $request->get('fecha_inicio'),
            'fecha_fin'    => $request->get('fecha_fin'),
            'anexo'        => $request->get('anexo'),
        ];
        $nombreArchivo = 'reporte_' . date('Y-m-d') . '.xlsx';
        return Excel::download(new CallsExport($filtros), $nombreArchivo);
    }

    public function showCharts(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(30)->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');

        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ]);

        if ($anexo) {
            $query->where('source', $anexo);
        }

        $callsByDisposition = (clone $query)
            ->selectRaw('disposition, count(*) as total')
            ->groupBy('disposition')
            ->pluck('total', 'disposition');

        $pieChartLabels = $callsByDisposition->keys();
        $pieChartData = $callsByDisposition->values();

        $callsPerDay = (clone $query)
            ->selectRaw('DATE(start_time) as fecha, count(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha', 'asc')
            ->get();

        $lineChartLabels = $callsPerDay->pluck('fecha');
        $lineChartData = $callsPerDay->pluck('total');

        // Obtener uptime del sistema usando EstadoCentral
        $estadoCentral = new \App\Http\Controllers\EstadoCentral();
        $systemData = $estadoCentral->getSystemData();

        return view('graficos', compact(
            'pieChartLabels', 'pieChartData', 'lineChartLabels', 'lineChartData',
            'fechaInicio', 'fechaFin', 'anexo', 'systemData'
        ));
    }
}