<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Call;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\CallsExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\GrandstreamTrait;
use App\Http\Controllers\Concerns\ProcessesCdr;

class CdrController extends Controller
{
    use GrandstreamTrait, ProcessesCdr;

    /**
     * Dashboard principal de llamadas
     */
    public function index(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo = $request->input('anexo');
        $titulo = $request->input('titulo', 'Reporte de Llamadas');
        $tipoLlamada = $request->input('tipo_llamada', 'all');
        $sortBy = $this->validateSort($request->input('sort', 'start_time'));
        $sortDir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $query = $this->buildCallQuery($fechaInicio, $fechaFin, $anexo, $tipoLlamada === 'all' ? null : $tipoLlamada);

        // Gráfico
        $datosGrafico = (clone $query)
            ->selectRaw('DATE(start_time) as fecha, count(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        // Totales
        $totalLlamadas = $query->count();
        $totalSegundos = $query->sum('billsec');
        $minutosFacturables = ceil($totalSegundos / 60);
        $totalPagar = (int) (clone $query)->selectRaw($this->getCostSumSql())->value('total_cost');

        // Aplicar ordenamiento y paginar
        $llamadas = $this->applySorting(clone $query, $sortBy, $sortDir)->paginate(50);

        return view('reporte', [
            'llamadas' => $llamadas,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'anexo' => $anexo,
            'totalLlamadas' => $totalLlamadas,
            'totalSegundos' => $totalSegundos,
            'totalPagar' => $totalPagar,
            'minutosFacturables' => $minutosFacturables,
            'labels' => $datosGrafico->pluck('fecha'),
            'data' => $datosGrafico->pluck('total'),
            'titulo' => $titulo,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Sincronizar CDRs desde la web
     */
    public function syncCDRs()
    {
        // Verificar permiso
        if (!auth()->user()->canSyncCalls()) {
            abort(403, 'No tienes permiso para sincronizar llamadas.');
        }

        $ultimaLlamada = Call::orderBy('start_time', 'desc')->first();
        $start = $ultimaLlamada 
            ? Carbon::parse($ultimaLlamada->start_time)->subHour() 
            : now()->subDays(30);

        try {
            $totalNuevas = 0;
            $totalActualizadas = 0;
            $maxPerRequest = 10000;
            $pageStart = $start->copy();
            $endTime = now();

            do {
                $response = $this->connectApi('cdrapi', [
                    'format' => 'json',
                    'numRecords' => $maxPerRequest,
                    'startTime' => $pageStart->format('Y-m-d\TH:i:s'),
                    'endTime' => $endTime->format('Y-m-d\TH:i:s'),
                    'minDur' => 0
                ], 180);

                if (!isset($response['cdr_root']) || empty($response['cdr_root'])) {
                    break;
                }

                $calls = $response['cdr_root'];
                $stats = $this->processCdrPackets($calls);
                $totalNuevas += $stats['nuevas'];
                $totalActualizadas += $stats['actualizadas'];

                // Si recibimos el máximo, puede haber más → paginar
                if (count($calls) >= $maxPerRequest) {
                    $lastCall = end($calls);
                    $lastStart = $lastCall['start'] ?? ($lastCall['main_cdr']['start'] ?? null);
                    if ($lastStart) {
                        $newStart = Carbon::parse($lastStart);
                        if ($newStart->lessThanOrEqualTo($pageStart)) break;
                        $pageStart = $newStart;
                        continue;
                    }
                }

                break;
            } while (true);

            if ($totalNuevas === 0 && $totalActualizadas === 0) {
                return back()->with('success', 'Conexión exitosa. No hay llamadas nuevas.');
            }

            return back()->with('success', "Sincronización: {$totalNuevas} nuevas, {$totalActualizadas} actualizadas.");

        } catch (\Exception $e) {
            return back()->with('error', 'Error técnico: ' . $e->getMessage());
        }
    }

    /**
     * Descargar reporte en PDF
     */
    public function descargarPDF(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canExportPdf()) {
            abort(403, 'No tienes permiso para exportar a PDF.');
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 300);

        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo = $request->input('anexo');
        $tipoLlamada = $request->input('tipo_llamada', 'all');
        $limitePDF = 500;

        $query = $this->buildCallQuery($fechaInicio, $fechaFin, $anexo, $tipoLlamada === 'all' ? null : $tipoLlamada)
            ->where('disposition', 'LIKE', '%ANSWERED%');

        $totalRegistros = (clone $query)->count();
        $totales = (clone $query)->selectRaw('COUNT(*) as total, SUM(billsec) as segundos')->first();

        $llamadas = $query->orderBy('start_time')->limit($limitePDF)->get();

        // Calcular costo total directamente en SQL (eficiente para grandes volúmenes)
        $totalPagar = (int) $this->buildCallQuery($fechaInicio, $fechaFin, $anexo, $tipoLlamada === 'all' ? null : $tipoLlamada)
            ->where('disposition', 'LIKE', '%ANSWERED%')
            ->selectRaw($this->getCostSumSql())
            ->value('total_cost');

        $pdf = Pdf::loadView('pdf_reporte', [
            'llamadas' => $llamadas,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'anexo' => $anexo,
            'tipoLlamada' => $tipoLlamada,
            'totalLlamadas' => $totales->total ?? 0,
            'totalPagar' => $totalPagar,
            'minutosFacturables' => ceil(($totales->segundos ?? 0) / 60),
            'titulo' => $request->input('titulo', 'Reporte de Llamadas'),
            'ip_central' => config('services.grandstream.host'),
            'truncado' => $totalRegistros > $limitePDF,
            'registrosMostrados' => min($totalRegistros, $limitePDF),
            'totalRegistros' => $totalRegistros,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('Reporte_Llamadas_' . date('dmY_His') . '.pdf');
    }

    /**
     * Exportar a Excel
     */
    public function exportarExcel(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canExportExcel()) {
            abort(403, 'No tienes permiso para exportar a Excel.');
        }

        return Excel::download(
            new CallsExport($request->only(['fecha_inicio', 'fecha_fin', 'anexo', 'tipo_llamada'])),
            'reporte_' . date('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Mostrar gráficos
     */
    public function showCharts(Request $request)
    {
        // Verificar permiso
        if (!auth()->user()->canViewCharts()) {
            abort(403, 'No tienes permiso para ver los gráficos.');
        }

        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subDays(30)->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo = $request->input('anexo');

        $query = $this->buildCallQuery($fechaInicio, $fechaFin, $anexo);

        $callsByDisposition = (clone $query)
            ->selectRaw('disposition, count(*) as total')
            ->groupBy('disposition')
            ->pluck('total', 'disposition');

        $callsPerDay = (clone $query)
            ->selectRaw('DATE(start_time) as fecha, count(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return view('graficos', [
            'pieChartLabels' => $callsByDisposition->keys(),
            'pieChartData' => $callsByDisposition->values(),
            'lineChartLabels' => $callsPerDay->pluck('fecha'),
            'lineChartData' => $callsPerDay->pluck('total'),
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'anexo' => $anexo,
        ]);
    }

    // ========== MÉTODOS PRIVADOS ==========

    private function buildCallQuery(string $fechaInicio, string $fechaFin, ?string $anexo, ?string $tipoLlamada = null)
    {
        return Call::whereBetween('start_time', ["{$fechaInicio} 00:00:00", "{$fechaFin} 23:59:59"])
            ->when($anexo, fn($q) => $q->where('source', $anexo))
            ->when($tipoLlamada === 'internal', fn($q) => $q->where(function($query) {
                // Internas: Llamadas originadas por anexos (Internal + Outbound)
                $query->whereIn('userfield', ['Internal', 'Outbound'])
                      ->orWhere(function($q2) {
                          // Fallback para registros sin userfield: origen es anexo (3-4 dígitos)
                          $q2->where(function($q3) {
                              $q3->whereNull('userfield')->orWhere('userfield', '');
                          })->whereRaw("source REGEXP '^[0-9]{3,4}$'");
                      });
            }))
            ->when($tipoLlamada === 'external', fn($q) => $q->where(function($query) {
                // Externas: Llamadas entrantes desde afuera (Inbound)
                $query->where('userfield', 'Inbound')
                      ->orWhere(function($q2) {
                          // Fallback para registros sin userfield: origen NO es anexo
                          $q2->where(function($q3) {
                              $q3->whereNull('userfield')->orWhere('userfield', '');
                          })->whereRaw("source NOT REGEXP '^[0-9]{3,4}$'");
                      });
            }));
    }

    private function validateSort(string $sort): string
    {
        return in_array($sort, ['start_time', 'billsec', 'tipo', 'costo']) ? $sort : 'start_time';
    }

    private function applySorting($query, string $sortBy, string $sortDir)
    {
        if ($sortBy === 'tipo') {
            return $query->orderByRaw($this->getTypeSortSql($sortDir));
        }
        
        if ($sortBy === 'costo') {
            return $query->orderByRaw($this->getCostSortSql($sortDir));
        }

        return $query->orderBy($sortBy, $sortDir);
    }

    private function getTypeSortSql(string $dir): string
    {
        return "CASE 
            WHEN destination REGEXP '^[0-9]{3,4}$' THEN 1
            WHEN destination REGEXP '^800' THEN 2
            WHEN destination REGEXP '^9[0-9]{8}$' THEN 3
            WHEN destination REGEXP '^\\\\+?569[0-9]{8}$' THEN 3
            WHEN destination REGEXP '^(\\\\+|00)' AND destination NOT REGEXP '^\\\\+?56' THEN 5
            ELSE 4
        END {$dir}";
    }

    /**
     * Obtener tarifas cacheadas para SQL (evita consultas redundantes)
     */
    private function getCachedPrices(): array
    {
        $prices = Call::getPrices();
        return [
            'mobile' => (int) ($prices['price_mobile'] ?? 80),
            'national' => (int) ($prices['price_national'] ?? 40),
            'international' => (int) ($prices['price_international'] ?? 500),
        ];
    }

    /**
     * SQL para sumar costos directamente en la BD (evita cargar todos los registros en memoria)
     */
    private function getCostSumSql(): string
    {
        ['mobile' => $mobile, 'national' => $national, 'international' => $international] = $this->getCachedPrices();

        return "SUM(CASE 
            WHEN billsec <= 3 THEN 0
            WHEN userfield != 'Outbound' OR userfield IS NULL THEN 0
            WHEN destination REGEXP '^[0-9]{3,4}$' THEN 0
            WHEN destination REGEXP '^800' THEN 0
            WHEN destination REGEXP '^9[0-9]{8}$' THEN CEIL(billsec/60) * {$mobile}
            WHEN destination REGEXP '^\\\\+?569[0-9]{8}$' THEN CEIL(billsec/60) * {$mobile}
            WHEN destination REGEXP '^(\\\\+|00)' AND destination NOT REGEXP '^\\\\+?56' THEN CEIL(billsec/60) * {$international}
            ELSE CEIL(billsec/60) * {$national}
        END) as total_cost";
    }

    private function getCostSortSql(string $dir): string
    {
        ['mobile' => $mobile, 'national' => $national, 'international' => $international] = $this->getCachedPrices();

        return "CASE 
            WHEN billsec <= 3 THEN 0
            WHEN userfield != 'Outbound' OR userfield IS NULL THEN 0
            WHEN destination REGEXP '^[0-9]{3,4}$' THEN 0
            WHEN destination REGEXP '^800' THEN 0
            WHEN destination REGEXP '^9[0-9]{8}$' THEN CEIL(billsec/60) * {$mobile}
            WHEN destination REGEXP '^\\\\+?569[0-9]{8}$' THEN CEIL(billsec/60) * {$mobile}
            WHEN destination REGEXP '^(\\\\+|00)' AND destination NOT REGEXP '^\\\\+?56' THEN CEIL(billsec/60) * {$international}
            ELSE CEIL(billsec/60) * {$national}
        END {$dir}";
    }

    private function processCdrPackets(array $packets): array
    {
        $nuevas = 0;
        $actualizadas = 0;
        $pbxId = session('active_pbx_id');

        foreach ($packets as $packet) {
            $segments = array_filter(
                $this->collectCdrSegments($packet),
                fn($s) => !empty($s['disposition'])
            );

            if (empty($segments)) continue;

            $data = $this->consolidateCdrSegments($segments);
            if (empty($data)) continue;

            $call = Call::updateOrCreate(
                ['pbx_connection_id' => $pbxId, 'unique_id' => $data['unique_id']],
                $data
            );

            $call->wasRecentlyCreated ? $nuevas++ : $actualizadas++;
        }

        return compact('nuevas', 'actualizadas');
    }
}
