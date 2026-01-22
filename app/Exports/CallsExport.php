<?php

namespace App\Exports;

use App\Models\Call;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CallsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filtros;

    // Recibimos los filtros desde el Controlador
    public function __construct($filtros)
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
        $query = Call::query();

        // 1. Filtro por Fecha Inicio
        if (!empty($this->filtros['fecha_inicio'])) {
            $query->whereDate('start_time', '>=', $this->filtros['fecha_inicio']);
        }

        // 2. Filtro por Fecha Fin
        if (!empty($this->filtros['fecha_fin'])) {
            $query->whereDate('start_time', '<=', $this->filtros['fecha_fin']);
        }

        // 3. Filtro por Anexo (SOLO llamadas hechas por ese anexo - comparación exacta)
        if (!empty($this->filtros['anexo'])) {
            $query->where('source', $this->filtros['anexo']);
        }

        // Ordenamos: Las más recientes primero
        return $query->orderBy('start_time', 'desc');
    }

    // Títulos de las columnas (La primera fila del Excel)
    public function headings(): array
    {
        return [
            'Fecha y Hora',
            'Origen',
            'Destino',
            'Tipo',
            'Duración (seg)',
            'Costo ($)',
            'Estado',
        ];
    }

    // Qué datos poner en cada fila
    public function map($call): array
    {
        return [
            $call->start_time,
            $call->source,
            $call->destination,
            $call->call_type,
            $call->billsec, // Tiempo hablado (facturado), no duration
            $call->cost, // Costo calculado dinámicamente
            $call->disposition,
        ];
    }

    // Poner negrita a los títulos
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}