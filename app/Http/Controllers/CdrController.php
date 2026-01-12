<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Call;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class CdrController extends Controller
{
    // ==========================================
    // MÉTODO 1: DASHBOARD
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
    // MÉTODO 2: SINCRONIZAR (DIGEST AUTH) 
    // ==========================================
    public function syncCDRs()
    {
        // 1. CONFIGURACIÓN
        $url      = 'https://10.36.1.10:8443/cdrapi';
        $usuario  = 'cdrapi';
        $password = '123api'; // <--- ¡Confirma que esta sea la clave real!

        // ==========================================================
        // LÓGICA DE SINCRONIZACIÓN INCREMENTAL
        // ==========================================================
        
        // 1. Buscamos la fecha de la llamada más reciente en la base de datos
        $ultimaLlamada = Call::orderBy('start_time', 'desc')->first();

        if ($ultimaLlamada) {
            // CASO A: Ya estan los datos almacenados.
            // Buscamos desde la última fecha registrada - 1 hora.
            // ¿Por qué restar 1 hora? Como "colchón de seguridad" para asegurar 
            // que no perdemos ninguna llamada que haya entrado justo en el límite.
            $start = Carbon::parse($ultimaLlamada->start_time)->subHour();
        } else {
            // CASO B: Es la primera vez (Base de datos vacía).
            // Traemos el historial del último mes.
            $start = now()->subDays(30);
        }

        $end = now(); // Hasta el momento presente

        try {
            // 2. CONECTAR
            $response = Http::withDigestAuth($usuario, $password)
                ->timeout(30)
                ->withoutVerifying()
                ->get($url, [
                    'format'    => 'JSON',
                    'startTime' => $start->format('Y-m-d\TH:i:s'),
                    'endTime'   => $end->format('Y-m-d\TH:i:s'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Si conecta pero no hay llamadas
                if (!isset($data['cdr_root']) || empty($data['cdr_root'])) {
                    return back()->with('success', 'Conexión exitosa, pero no hay llamadas nuevas en los últimos 30 días.');
                }

                $calls = $data['cdr_root'];
                $contador = 0;

                // 3. GUARDAR EN BD
                foreach ($calls as $cdr) {
                    
                    // VALIDACIÓN DE SEGURIDAD:
                    // Si la llamada viene corrupta sin ID, la ignoramos.
                    if (!isset($cdr['uniqueid'])) {
                        continue;
                    }

                    Call::updateOrCreate(
                        [
                            'unique_id' => $cdr['uniqueid'], // Clave para no duplicar
                        ],
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
                    $contador++;
                }

                return back()->with('success', "¡Sincronización Exitosa! Se procesaron {$contador} llamadas.");

            } else {
                // Si la central rechaza la conexión
                return back()->with('error', 'Error Central: ' . $response->status());
            }

        } catch (\Exception $e) {
            Log::error("Error Sync: " . $e->getMessage());
            // Devolvemos el error en pantalla
            return back()->with('error', 'Ocurrió un error técnico: ' . $e->getMessage());
        }
    }
    // ==========================================
    // MÉTODO 3: GENERAR PDF 📄
    // ==========================================
    public function descargarPDF(Request $request)
    {
        // A. RECUPERAR LOS MISMOS FILTROS QUE EL DASHBOARD
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->format('Y-m-d')); 
        $fechaFin    = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $anexo       = $request->input('anexo');
        $tarifa      = $request->input('tarifa', 50); 

        // B. PREPARAR LA CONSULTA
        $query = Call::whereBetween('start_time', [
            $fechaInicio . ' 00:00:00', 
            $fechaFin . ' 23:59:59'
        ]);

        if ($anexo) {
            $query->where('source', $anexo);
        }

        // C. OBTENER DATOS (Sin paginar, queremos todo el reporte)
        $llamadas = $query->orderBy('start_time', 'asc')->get();

        // D. CALCULAR TOTALES PARA EL ENCABEZADO
        $totalLlamadas = $llamadas->count();
        $totalSegundos = $llamadas->sum('billsec');
        $minutosFacturables = ceil($totalSegundos / 60);
        $totalPagar = $minutosFacturables * $tarifa;

        // E. GENERAR EL PDF
        // Usaremos una vista nueva llamada 'pdf_reporte'
        $pdf = Pdf::loadView('pdf_reporte', compact(
            'llamadas', 'fechaInicio', 'fechaFin', 'anexo', 
            'totalLlamadas', 'totalPagar', 'minutosFacturables', 'tarifa'
        ));

        // F. DESCARGAR
        return $pdf->download('Reporte_Llamadas_' . date('dmY_His') . '.pdf');
    }
}