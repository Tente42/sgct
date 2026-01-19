<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Gestión Telefónica</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .small { font-size: 10px; }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0056b3; /* Línea azul */
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #0056b3;
        }
        .info-box {
            width: 100%;
            margin-bottom: 20px;
        }
        .resumen-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #f8f9fa;
        }
        .resumen-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .highlight {
            font-weight: bold;
            color: #0056b3;
            font-size: 1.1em;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .data-table th {
            background-color: #0056b3; /* Cabecera azul */
            color: white;
            text-transform: uppercase;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f2f2f2; /* Filas rayadas para leer mejor */
        }
        .text-right {
            text-align: right;
        }
        .badge {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 10px;
            color: white;
            background-color: #666;
        }
        /* Colores para estados si quieres ponerle detalle */
        .estado-answered { background-color: #28a745; } /* Verde */
        .estado-no-answer { background-color: #dc3545; } /* Rojo */
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="text-align: left; vertical-align: top; border: none;">
                    <span class="small">Central IP: {{ $ip_central }}</span>
                </td>
            </tr>
            <tr>
                <td style="text-align: center; border: none;">
                    <h1>{{ $titulo }}</h1>
                    <p>Generado el: {{ now()->format('d/m/Y H:i') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="resumen-table">
        <tr>
            <td>
                <strong>PERIODO:</strong><br>
                {{ $fechaInicio }} al {{ $fechaFin }}
            </td>
            <td>
                <strong>TOTAL LLAMADAS:</strong><br>
                {{ $totalLlamadas }}
            </td>
            <td>
                <strong>MINUTOS FACTURABLES:</strong><br>
                {{ $minutosFacturables }} min
            </td>
            <td style="background-color: #e9ecef;">
                <strong>TOTAL A PAGAR:</strong><br>
                <span class="highlight">${{ number_format($totalPagar, 0, ',', '.') }}</span>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="text-right">Duración</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($llamadas as $call)
            <tr>
                <td>{{ \Carbon\Carbon::parse($call->start_time)->format('d/m/Y H:i:s') }}</td>
                <td>{{ $call->source }}</td>
                <td>{{ $call->destination }}</td>
                <td class="text-right">{{ $call->duration }} seg</td>
                <td>
                    @php
                        $color = 'gray';
                        if(str_contains(strtoupper($call->disposition), 'ANSWERED') || str_contains(strtoupper($call->disposition), 'CONTESTADA')) $color = '#28a745';
                        if(str_contains(strtoupper($call->disposition), 'NO ANSWER') || str_contains(strtoupper($call->disposition), 'NO CONTESTAN')) $color = '#dc3545';
                        if(str_contains(strtoupper($call->disposition), 'BUSY')) $color = '#ffc107';
                    @endphp
                    <span style="color: {{ $color }}; font-weight: bold;">
                        {{ $call->disposition }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>