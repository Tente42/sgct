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

class CdrController extends Controller
{
    // ==========================================
    // MÉTODO 1: DASHBOARD (Sin cambios)
    // ==========================================
    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');
        $tarifa      = $request->input('tarifa', 50); 

        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ]);

        if ($anexo) {
            $query->where('source', $anexo);
        }

        // Gráfico
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
        $totalPagar = $minutosFacturables * $tarifa;

        $llamadas = $query->orderBy('start_time', 'desc')->paginate(50);

        return view('reporte', compact(
            'llamadas', 'fechaInicio', 'fechaFin', 'anexo', 'totalLlamadas', 
            'totalSegundos', 'totalPagar', 'minutosFacturables', 'tarifa', 'labels', 'data'
        ));
    }

    // ==========================================
    // MÉTODO 2: SINCRONIZAR INTELIGENTE (WEB)
    // ==========================================
    public function syncCDRs()
    {
        // 1. CONFIGURACIÓN
        $url      = config('services.grandstream.host') . '/cdrapi';
        $usuario  = config('services.grandstream.user');
        $password = config('services.grandstream.pass');

        // 2. DEFINIR RANGO DE TIEMPO INCREMENTAL
        $ultimaLlamada = Call::orderBy('start_time', 'desc')->first();

        if ($ultimaLlamada) {
            // "Colchón" de 1 hora para asegurar que no perdemos llamadas en el borde
            $start = Carbon::parse($ultimaLlamada->start_time)->subHour();
        } else {
            // Primera vez: últimos 30 días
            $start = now()->subDays(30);
        }

        $end = now(); 

        try {
            // 3. CONECTAR A LA API
            $response = Http::withDigestAuth($usuario, $password)
                ->timeout(60) // Aumentamos timeout por si hay mucha data procesando
                ->withoutVerifying()
                ->get($url, [
                    'format'    => 'JSON',
                    'startTime' => $start->format('Y-m-d\TH:i:s'),
                    'endTime'   => $end->format('Y-m-d\TH:i:s'),
                    'minDur'    => 0 // Traer todo para luego filtrar inteligentemente
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['cdr_root']) || empty($data['cdr_root'])) {
                    return back()->with('success', 'Conexión exitosa. No hay llamadas nuevas.');
                }

                $calls = $data['cdr_root'];
                $contadorNuevas = 0;
                $contadorActualizadas = 0;

                // 4. PROCESAMIENTO ROBUSTO (Igual que en consola)
                foreach ($calls as $cdrPacket) {
                    
                    // A. RECOLECTAR TODOS LOS TRAMOS (Recursividad)
                    $validSegments = $this->collectAllSegments($cdrPacket);

                    if (empty($validSegments)) {
                        continue;
                    }

                    // B. FILTRO DE LIMPIEZA
                    // 1. Quitar fantasmas sin estado
                    $validSegments = array_filter($validSegments, function($seg) {
                        return !empty($seg['disposition']); 
                    });

                    // 2. Si alguien contestó en este grupo, borramos los intentos fallidos (0 seg)
                    $huboExito = false;
                    foreach ($validSegments as $seg) {
                        if (($seg['billsec'] ?? 0) > 0) { 
                            $huboExito = true; 
                            break; 
                        }
                    }

                    if ($huboExito) {
                        $validSegments = array_filter($validSegments, function($seg) {
                            return ($seg['billsec'] ?? 0) > 0;
                        });
                    }

                    // C. GUARDAR EN BASE DE DATOS
                    foreach ($validSegments as $record) {
                        
                        // Generación de ID Inteligente (Prioridad AcctId)
                        if (!empty($record['acctid'])) {
                            $uniqueKey = $record['acctid'];
                        } else {
                            $baseId = $record['uniqueid'] ?? md5($record['start']);
                            $uniqueKey = $baseId . '_' . ($record['dst'] ?? 'x');
                        }

                        $call = Call::updateOrCreate(
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

                        if ($call->wasRecentlyCreated) {
                            $contadorNuevas++;
                        } else {
                            $contadorActualizadas++;
                        }
                    }
                }

                return back()->with('success', "Sincronización Inteligente: {$contadorNuevas} nuevas, {$contadorActualizadas} actualizadas.");

            } else {
                return back()->with('error', 'Error Central HTTP: ' . $response->status());
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

    // ==========================================
    // MÉTODO 3: PDF 
    // ==========================================
    public function descargarPDF(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');
        $tarifa      = $request->input('tarifa', 50); 

        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ]);

        if ($anexo) {
            $query->where('source', $anexo);
        }

        $llamadas = $query->orderBy('start_time', 'asc')->get();

        $totalLlamadas = $llamadas->count();
        $totalSegundos = $llamadas->sum('billsec');
        $minutosFacturables = ceil($totalSegundos / 60);
        $totalPagar = $minutosFacturables * $tarifa;

        $pdf = Pdf::loadView('pdf_reporte', compact(
            'llamadas', 'fechaInicio', 'fechaFin', 'anexo', 
            'totalLlamadas', 'totalPagar', 'minutosFacturables', 'tarifa'
        ));

        return $pdf->download('Reporte_Llamadas_' . date('dmY_His') . '.pdf');
    }

    // ==========================================
    // MÉTODO 4: EXCEL & GRÁFICOS 
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

        return view('graficos', compact(
            'pieChartLabels', 'pieChartData', 'lineChartLabels', 'lineChartData',
            'fechaInicio', 'fechaFin', 'anexo'
        ));
    }
}