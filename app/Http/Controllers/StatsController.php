<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Call;
use App\Models\QueueCallDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    /**
     * Mostrar KPIs de turnos por franja horaria
     * 
     * EXCLUSIVO PARA COLAS (QUEUE)
     * 
     * Lógica de negocio:
     * - Agrupación por unique_id para no contar intentos fallidos como llamadas nuevas
     * - Una llamada = Un unique_id
     * - Volumen: Cantidad de unique_id únicos por hora
     * - Abandono: Si disposition = 'ANSWERED' y billsec > 0, fue atendida
     * - Tiempo espera: duration - billsec (tiempo total menos tiempo de conversación)
     * - Agentes: Extraídos de queue_call_details (sincronizado desde queueapi)
     */
    public function index(Request $request)
    {
        // Verificar permiso para ver gráficos/estadísticas
        if (!auth()->user()->canViewCharts()) {
            abort(403, 'No tienes permiso para ver estadísticas.');
        }

        // Por defecto mostrar el último mes
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $colaFiltro = $request->input('cola'); // Filtrar por cola específica

        // Obtener colas disponibles para el filtro
        $colasDisponibles = $this->obtenerColasDisponibles();

        // Obtener datos agrupados por hora (desde BD) - SIEMPRE solo QUEUE
        $kpisPorHora = $this->calcularKpisPorHora($fechaInicio, $fechaFin, $colaFiltro);
        
        // Calcular totales globales
        $totales = $this->calcularTotales($kpisPorHora);

        // Obtener estadísticas por cola (desde BD)
        $kpisPorCola = $this->calcularKpisPorCola($fechaInicio, $fechaFin);

        // Obtener rendimiento de agentes desde queue_call_details (sincronizado desde queueapi)
        $rendimientoAgentes = $this->obtenerRendimientoAgentes($fechaInicio, $fechaFin, $colaFiltro);

        // Verificar si hay datos sincronizados
        $hayDatosSincronizados = !empty($rendimientoAgentes);

        // Obtener agentes por cola (para el modal de ver agentes)
        $agentesPorCola = $this->obtenerAgentesPorCola($fechaInicio, $fechaFin);

        // Verificar última sincronización
        $ultimaSincronizacion = QueueCallDetail::max('created_at');

        return view('stats.kpi-turnos', [
            'kpisPorHora' => $kpisPorHora,
            'kpisPorCola' => $kpisPorCola,
            'totales' => $totales,
            'rendimientoAgentes' => $rendimientoAgentes,
            'hayDatosSincronizados' => $hayDatosSincronizados,
            'agentesPorCola' => $agentesPorCola,
            'ultimaSincronizacion' => $ultimaSincronizacion,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'colaFiltro' => $colaFiltro,
            'colasDisponibles' => $colasDisponibles,
        ]);
    }

    /**
     * Obtener rendimiento de agentes desde queue_call_details
     * Esta tabla se alimenta del comando sync:queue-stats que consulta queueapi
     */
    private function obtenerRendimientoAgentes(string $fechaInicio, string $fechaFin, ?string $cola = null): array
    {
        // Query base usando la tabla queue_call_details
        $query = QueueCallDetail::betweenDates($fechaInicio, $fechaFin)
            ->whereNotNull('agent')
            ->where('agent', '!=', 'NONE');

        // Filtrar por cola específica si se especifica
        if ($cola) {
            $query->forQueue($cola);
        }

        // Agrupar por agente y calcular métricas
        $agentesData = $query->select([
                'agent',
                DB::raw('COUNT(*) as llamadas_totales'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN 1 ELSE 0 END) as llamadas_atendidas'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN talk_time ELSE 0 END) as tiempo_total'),
                DB::raw('SUM(wait_time) as tiempo_espera_total'),
            ])
            ->groupBy('agent')
            ->get();

        $resultado = [];

        foreach ($agentesData as $datos) {
            $agente = $datos->agent;
            $llamadasAtendidas = (int) $datos->llamadas_atendidas;
            $tiempoTotal = (int) $datos->tiempo_total;
            $tiempoEsperaTotal = (int) $datos->tiempo_espera_total;
            $llamadasTotales = (int) $datos->llamadas_totales;

            $resultado[$agente] = [
                'agente' => $agente,
                'llamadas_totales' => $llamadasTotales,
                'llamadas_atendidas' => $llamadasAtendidas,
                'tiempo_total' => $tiempoTotal,
                'tiempo_total_formato' => gmdate('H:i:s', $tiempoTotal),
                'tiempo_promedio' => $llamadasAtendidas > 0 ? round($tiempoTotal / $llamadasAtendidas) : 0,
                'tiempo_promedio_formato' => $llamadasAtendidas > 0 ? round($tiempoTotal / $llamadasAtendidas) . 's' : '0s',
                'tiempo_espera_promedio' => $llamadasTotales > 0 ? round($tiempoEsperaTotal / $llamadasTotales) : 0,
                'tiempo_espera_promedio_formato' => $llamadasTotales > 0 ? round($tiempoEsperaTotal / $llamadasTotales) . 's' : '0s',
                'tasa_atencion' => $llamadasTotales > 0 
                    ? round(($llamadasAtendidas / $llamadasTotales) * 100, 1) 
                    : 0,
            ];
        }

        // Ordenar por llamadas atendidas (mayor a menor)
        uasort($resultado, fn($a, $b) => $b['llamadas_atendidas'] <=> $a['llamadas_atendidas']);

        return $resultado;
    }

    /**
     * Obtener lista de colas disponibles en el sistema
     * Combina datos de calls y queue_call_details
     */
    private function obtenerColasDisponibles(): array
    {
        // De la tabla calls
        $actionTypes = Call::where('action_type', 'LIKE', 'QUEUE%')
            ->distinct()
            ->pluck('action_type');

        $colas = [];
        foreach ($actionTypes as $at) {
            if (preg_match('/QUEUE\[(\d+)\]/', $at, $matches)) {
                $colas[] = $matches[1];
            }
        }

        // También de queue_call_details si hay datos
        $colasFromDetails = QueueCallDetail::distinct()->pluck('queue')->toArray();
        $colas = array_merge($colas, $colasFromDetails);

        return array_unique($colas);
    }

    /**
     * Calcular KPIs por franja horaria
     * EXCLUSIVO PARA COLAS (QUEUE)
     * 
     * FUENTE DE DATOS: queue_call_details (sincronizado desde queueapi)
     * Esta tabla tiene datos más precisos que la tabla calls para llamadas de cola
     */
    private function calcularKpisPorHora(string $fechaInicio, string $fechaFin, ?string $colaFiltro = null): array
    {
        // Inicializar array de KPIs por hora (08:00 a 20:00)
        $kpisPorHora = [];
        for ($h = 8; $h <= 20; $h++) {
            $hora = str_pad($h, 2, '0', STR_PAD_LEFT);
            $kpisPorHora[$hora] = [
                'hora' => "{$hora}:00 - " . str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ":00",
                'hora_key' => $hora,
                'volumen' => 0,
                'atendidas' => 0,
                'abandonadas' => 0,
                'abandono_pct' => '0%',
                'tiempo_espera_total' => 0,
                'tiempo_espera_promedio' => '0s',
                'asa' => '0s',
                'billsec_total' => 0,
                'agentes' => [],
            ];
        }

        // Obtener datos agregados por hora desde queue_call_details (fuente más precisa)
        $query = QueueCallDetail::betweenDates($fechaInicio, $fechaFin);
        
        if ($colaFiltro) {
            $query->forQueue($colaFiltro);
        }

        // Agregar en SQL en lugar de cargar todos los registros en memoria
        $datosPorHora = $query->select([
                DB::raw('HOUR(call_time) as hora'),
                DB::raw('COUNT(*) as volumen'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN 1 ELSE 0 END) as atendidas'),
                DB::raw('SUM(CASE WHEN connected = 0 THEN 1 ELSE 0 END) as abandonadas'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN wait_time ELSE 0 END) as tiempo_espera_total'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN talk_time ELSE 0 END) as billsec_total'),
            ])
            ->groupBy(DB::raw('HOUR(call_time)'))
            ->get()
            ->keyBy('hora');

        // Obtener agentes únicos por hora (solo esta parte necesita registros individuales)
        $agentesQuery = QueueCallDetail::betweenDates($fechaInicio, $fechaFin)
            ->where('connected', true)
            ->whereNotNull('agent')
            ->where('agent', '!=', 'NONE');
        
        if ($colaFiltro) {
            $agentesQuery->forQueue($colaFiltro);
        }

        $agentesPorHoraData = $agentesQuery
            ->select(DB::raw('HOUR(call_time) as hora'), 'agent')
            ->distinct()
            ->get()
            ->groupBy('hora');

        // Poblar estructura de KPIs
        foreach ($datosPorHora as $hora => $datos) {
            $horaKey = str_pad($hora, 2, '0', STR_PAD_LEFT);
            if (!isset($kpisPorHora[$horaKey])) continue;

            $kpisPorHora[$horaKey]['volumen'] = (int) $datos->volumen;
            $kpisPorHora[$horaKey]['atendidas'] = (int) $datos->atendidas;
            $kpisPorHora[$horaKey]['abandonadas'] = (int) $datos->abandonadas;
            $kpisPorHora[$horaKey]['tiempo_espera_total'] = (int) $datos->tiempo_espera_total;
            $kpisPorHora[$horaKey]['billsec_total'] = (int) $datos->billsec_total;

            // Asignar agentes
            if (isset($agentesPorHoraData[$hora])) {
                $kpisPorHora[$horaKey]['agentes'] = $agentesPorHoraData[$hora]->pluck('agent')->sort()->values()->toArray();
            }
        }

        // Calcular porcentajes y promedios
        foreach ($kpisPorHora as $hora => &$datos) {
            // % Abandono
            if ($datos['volumen'] > 0) {
                $pct = round(($datos['abandonadas'] / $datos['volumen']) * 100, 1);
                $datos['abandono_pct'] = $pct . '%';
                $datos['abandono_valor'] = $pct; // Para colorear
            }

            // Tiempo espera promedio (usando wait_time de queueapi)
            if ($datos['atendidas'] > 0) {
                $promedioEspera = round($datos['tiempo_espera_total'] / $datos['atendidas']);
                $datos['tiempo_espera_promedio'] = $promedioEspera . 's';
                $datos['tiempo_espera_valor'] = $promedioEspera;
            }

            // ASA (Average Speed Answer) - Promedio talk_time de las atendidas
            if ($datos['atendidas'] > 0) {
                $asa = round($datos['billsec_total'] / $datos['atendidas']);
                $datos['asa'] = $asa . 's';
                $datos['asa_valor'] = $asa;
            }

            // Ordenar agentes
            sort($datos['agentes']);
        }

        return $kpisPorHora;
    }

    /**
     * Extraer número de agente del dst_channel, channel, dstanswer o source
     * Formatos posibles: PJSIP/2000-xxx, SIP/2000-xxx, Local/2000@...
     */
    private function extraerAgente(?string $dstChannel, ?string $dstanswer, ?string $channel = null, ?string $source = null): ?string
    {
        // Intentar primero con dstanswer (más limpio) - pero ignorar si es un número de cola (6500, etc.)
        if ($dstanswer && preg_match('/^\d{3,4}$/', $dstanswer) && !str_starts_with($dstanswer, '6')) {
            return $dstanswer;
        }

        // Extraer de dst_channel
        if ($dstChannel && trim($dstChannel) !== '') {
            // Formato PJSIP/2000-xxx o SIP/2000-xxx
            if (preg_match('/(?:PJSIP|SIP)\/(\d{3,4})-/', $dstChannel, $matches)) {
                return $matches[1];
            }
            // Formato Local/2000@...
            if (preg_match('/Local\/(\d{3,4})@/', $dstChannel, $matches)) {
                return $matches[1];
            }
        }

        // Extraer de channel (cuando dst_channel está vacío)
        if ($channel && trim($channel) !== '') {
            // Formato PJSIP/2000-xxx o SIP/2000-xxx
            if (preg_match('/(?:PJSIP|SIP)\/(\d{3,4})-/', $channel, $matches)) {
                return $matches[1];
            }
        }

        // Usar source si es un anexo (3-4 dígitos) y no es número de cola
        if ($source && preg_match('/^\d{3,4}$/', $source) && !str_starts_with($source, '6')) {
            return $source;
        }

        return null;
    }

    /**
     * Calcular totales globales
     */
    private function calcularTotales(array $kpisPorHora): array
    {
        $totales = [
            'volumen' => 0,
            'atendidas' => 0,
            'abandonadas' => 0,
            'abandono_pct' => '0%',
            'tiempo_espera_total' => 0,
            'tiempo_espera_promedio' => '0s',
            'asa' => '0s',
            'agentes' => [],
        ];

        foreach ($kpisPorHora as $datos) {
            $totales['volumen'] += $datos['volumen'];
            $totales['atendidas'] += $datos['atendidas'];
            $totales['abandonadas'] += $datos['abandonadas'];
            $totales['tiempo_espera_total'] += $datos['tiempo_espera_total'];
            
            foreach ($datos['agentes'] as $agente) {
                if (!in_array($agente, $totales['agentes'])) {
                    $totales['agentes'][] = $agente;
                }
            }
        }

        // Calcular porcentajes globales
        if ($totales['volumen'] > 0) {
            $pct = round(($totales['abandonadas'] / $totales['volumen']) * 100, 1);
            $totales['abandono_pct'] = $pct . '%';
            $totales['abandono_valor'] = $pct;
        }

        if ($totales['atendidas'] > 0) {
            $promedioEspera = round($totales['tiempo_espera_total'] / $totales['atendidas']);
            $totales['tiempo_espera_promedio'] = $promedioEspera . 's';
            $totales['tiempo_espera_valor'] = $promedioEspera;
        }

        sort($totales['agentes']);

        return $totales;
    }

    /**
     * Calcular KPIs agrupados por cola
     * 
     * FUENTE DE DATOS: queue_call_details (sincronizado desde queueapi)
     */
    private function calcularKpisPorCola(string $fechaInicio, string $fechaFin): array
    {
        // Agregar en SQL en lugar de cargar todos los registros en memoria
        $datosAgrupados = QueueCallDetail::betweenDates($fechaInicio, $fechaFin)
            ->whereNotNull('queue')
            ->select([
                'queue',
                DB::raw('COUNT(*) as volumen'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN 1 ELSE 0 END) as atendidas'),
                DB::raw('SUM(CASE WHEN connected = 0 THEN 1 ELSE 0 END) as abandonadas'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN talk_time ELSE 0 END) as billsec_total'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN wait_time ELSE 0 END) as tiempo_espera_total'),
            ])
            ->groupBy('queue')
            ->get();

        // Obtener agentes únicos por cola
        $agentesPorColaData = QueueCallDetail::betweenDates($fechaInicio, $fechaFin)
            ->where('connected', true)
            ->whereNotNull('agent')
            ->where('agent', '!=', 'NONE')
            ->whereNotNull('queue')
            ->select('queue', 'agent')
            ->distinct()
            ->get()
            ->groupBy('queue');

        $kpisPorCola = [];

        foreach ($datosAgrupados as $datos) {
            $cola = $datos->queue;
            $volumen = (int) $datos->volumen;
            $atendidas = (int) $datos->atendidas;
            $abandonadas = (int) $datos->abandonadas;

            $kpisPorCola[$cola] = [
                'cola' => $cola,
                'volumen' => $volumen,
                'atendidas' => $atendidas,
                'abandonadas' => $abandonadas,
                'abandono_pct' => '0%',
                'billsec_total' => (int) $datos->billsec_total,
                'tiempo_espera_total' => (int) $datos->tiempo_espera_total,
                'agentes' => isset($agentesPorColaData[$cola])
                    ? $agentesPorColaData[$cola]->pluck('agent')->sort()->values()->toArray()
                    : [],
                'asa' => '0s',
            ];

            if ($volumen > 0) {
                $pct = round(($abandonadas / $volumen) * 100, 1);
                $kpisPorCola[$cola]['abandono_pct'] = $pct . '%';
                $kpisPorCola[$cola]['abandono_valor'] = $pct;
            }
            if ($atendidas > 0) {
                $asa = round((int) $datos->billsec_total / $atendidas);
                $kpisPorCola[$cola]['asa'] = $asa . 's';
            }
        }

        return $kpisPorCola;
    }

    /**
     * Extraer número de cola de action_type
     * Ejemplo: QUEUE[6500] -> 6500
     */
    private function extraerCola(?string $actionType): ?string
    {
        if (!$actionType) return null;
        
        if (preg_match('/QUEUE\[(\d+)\]/', $actionType, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * API para obtener datos en formato JSON (útil para gráficos)
     * EXCLUSIVO PARA COLAS (QUEUE)
     */
    public function apiKpis(Request $request)
    {
        // Verificar permiso para ver gráficos/estadísticas
        if (!auth()->user()->canViewCharts()) {
            return response()->json(['error' => 'No tienes permiso para ver estadísticas.'], 403);
        }
        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->subMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', Carbon::now()->format('Y-m-d'));
        $colaFiltro = $request->input('cola');

        $kpisPorHora = $this->calcularKpisPorHora($fechaInicio, $fechaFin, $colaFiltro);
        $totales = $this->calcularTotales($kpisPorHora);

        return response()->json([
            'success' => true,
            'data' => [
                'por_hora' => array_values($kpisPorHora),
                'totales' => $totales,
            ],
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'cola' => $colaFiltro,
            ]
        ]);
    }

    /**
     * Obtener agentes por cola con estadísticas detalladas
     * Usa queue_call_details para datos precisos
     */
    private function obtenerAgentesPorCola(string $fechaInicio, string $fechaFin): array
    {
        $datos = QueueCallDetail::betweenDates($fechaInicio, $fechaFin)
            ->whereNotNull('agent')
            ->where('agent', '!=', 'NONE')
            ->select([
                'queue',
                'agent',
                DB::raw('COUNT(*) as intentos'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN 1 ELSE 0 END) as contestadas'),
                DB::raw('SUM(CASE WHEN connected = 1 THEN talk_time ELSE 0 END) as tiempo_total'),
                DB::raw('AVG(wait_time) as espera_promedio'),
            ])
            ->groupBy('queue', 'agent')
            ->orderBy('queue')
            ->orderByDesc('contestadas')
            ->get();

        $resultado = [];
        foreach ($datos as $d) {
            if (!isset($resultado[$d->queue])) {
                $resultado[$d->queue] = [];
            }
            $resultado[$d->queue][] = [
                'agente' => $d->agent,
                'intentos' => (int) $d->intentos,
                'contestadas' => (int) $d->contestadas,
                'tiempo_total' => (int) $d->tiempo_total,
                'tiempo_formato' => gmdate('H:i:s', (int) $d->tiempo_total),
                'espera_promedio' => round($d->espera_promedio ?? 0),
                'efectividad' => $d->intentos > 0 ? round(($d->contestadas / $d->intentos) * 100, 1) : 0,
            ];
        }

        return $resultado;
    }

    /**
     * Sincronizar datos de colas (solo administradores)
     */
    public function sincronizarColas(Request $request)
    {
        // Verificar permiso para sincronizar colas
        if (!Auth::user() || !Auth::user()->canSyncQueues()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para sincronizar colas.'
            ], 403);
        }

        $days = $request->input('days', 7);
        $pbxId = session('active_pbx_id');

        try {
            // Ejecutar comando de sincronización
            $exitCode = Artisan::call('sync:queue-stats', [
                '--days' => $days,
                '--pbx' => $pbxId,
            ]);

            $output = Artisan::output();

            // Extraer números del output
            preg_match('/Registros insertados: (\d+)/', $output, $insertados);
            preg_match('/Registros omitidos.*: (\d+)/', $output, $omitidos);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 
                    ? 'Sincronización completada correctamente' 
                    : 'Error durante la sincronización',
                'insertados' => $insertados[1] ?? 0,
                'omitidos' => $omitidos[1] ?? 0,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
