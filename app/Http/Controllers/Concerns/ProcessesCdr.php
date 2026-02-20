<?php

namespace App\Http\Controllers\Concerns;

/**
 * Trait para procesar registros CDR de Grandstream
 */
trait ProcessesCdr
{
    /**
     * Recolectar todos los segmentos de un paquete CDR recursivamente.
     * Incluye cualquier nodo que tenga un campo 'start' (fecha de inicio)
     * sin importar si tiene disposition o no — la consolidación se encarga
     * de determinar el estado final de la llamada.
     */
    protected function collectCdrSegments(array $node): array
    {
        $collected = [];

        // Un nodo con 'start' es un segmento válido de llamada
        if (isset($node['start']) && !empty($node['start'])) {
            $collected[] = $node;
        }

        foreach ($node as $key => $value) {
            if (is_array($value) && (str_starts_with($key, 'sub_cdr') || $key === 'main_cdr')) {
                $collected = array_merge($collected, $this->collectCdrSegments($value));
            }
        }

        // Si no encontramos segmentos con 'start' pero el nodo raíz tiene datos
        // útiles (acctid/uniqueid), tratarlo como un segmento válido
        if (empty($collected) && (isset($node['acctid']) || isset($node['uniqueid']))) {
            $collected[] = $node;
        }

        return $collected;
    }

    /**
     * Consolidar segmentos en un solo registro de llamada.
     * Guarda TODAS las llamadas (internas, entrantes, salientes).
     * La tarificación se calcula después por el accessor getCostAttribute.
     */
    protected function consolidateCdrSegments(array $segments): array
    {
        if (empty($segments)) return [];

        $segments = array_values($segments);
        $first = $segments[0];
        $firstSrc = $first['src'] ?? '';
        $firstDst = $first['dst'] ?? '';

        $esEntrante = ($firstSrc !== '' && $firstDst !== '')
            ? ($this->isExternalNumber($firstSrc) && $this->isExtension($firstDst))
            : false;

        $data = [
            'unique_id' => null,
            'start_time' => null,
            'answer_time' => null,
            'source' => null,
            'destination' => null,
            'dstanswer' => null,
            'duration' => 0,
            'billsec' => 0,
            'disposition' => 'NO ANSWER',
            'action_type' => null,
            'lastapp' => null,
            'channel' => null,
            'dst_channel' => null,
            'src_trunk_name' => null,
            'caller_name' => null,
            'recording_file' => null,
            'call_type' => $esEntrante ? 'inbound' : 'outbound',
            'userfield' => null,
        ];

        foreach ($segments as $seg) {
            $src = $seg['src'] ?? '';
            $dst = $seg['dst'] ?? '';

            // Capturar datos más tempranos
            if (!$data['start_time'] || ($seg['start'] ?? '') < $data['start_time']) {
                $data['start_time'] = $seg['start'] ?? null;
            }
            $data['unique_id'] ??= $seg['acctid'] ?? $seg['uniqueid'] ?? null;
            $data['caller_name'] ??= $seg['caller_name'] ?? null;
            $data['recording_file'] ??= $seg['recordfiles'] ?? null;

            // Nuevos campos detallados
            $data['action_type'] ??= $seg['action_type'] ?? null;
            $data['lastapp'] ??= $seg['lastapp'] ?? null;
            $data['channel'] ??= $seg['channel'] ?? null;
            $data['dst_channel'] ??= $seg['dstchannel'] ?? null;
            $data['src_trunk_name'] ??= $seg['src_trunk_name'] ?? null;
            
            // Capturar userfield (clasificación UCM: Inbound, Outbound, Internal)
            $data['userfield'] ??= $seg['userfield'] ?? null;

            // Capturar answer_time si existe
            if (!empty($seg['answer']) && $seg['answer'] !== '0000-00-00 00:00:00') {
                $data['answer_time'] ??= $seg['answer'];
            }

            // Capturar dstanswer (quien contestó)
            if (!empty($seg['dstanswer'])) {
                $data['dstanswer'] ??= $seg['dstanswer'];
            }

            // Sumar tiempos
            $data['duration'] += (int)($seg['duration'] ?? 0);
            $data['billsec'] += (int)($seg['billsec'] ?? 0);

            // Determinar origen/destino
            if ($esEntrante) {
                $data['source'] ??= $this->isExtension($dst) ? $dst : null;
                $data['destination'] ??= $this->isExternalNumber($src) ? $src : null;
            } else {
                $data['source'] ??= $this->isExtension($src) ? $src : null;
                $data['destination'] ??= $dst ?: null;
            }

            if ((int)($seg['billsec'] ?? 0) > 0) {
                $data['disposition'] = 'ANSWERED';
            }
        }

        // Valores por defecto
        $data['source'] ??= $firstSrc ?: 'Desconocido';
        $data['destination'] ??= $firstDst ?: 'Desconocido';
        $data['unique_id'] ??= md5($data['start_time'] . $data['source'] . $data['destination']);

        // Determinar disposition final
        if ($data['disposition'] !== 'ANSWERED') {
            foreach ($segments as $seg) {
                $disp = strtoupper($seg['disposition'] ?? '');
                if (str_contains($disp, 'BUSY')) {
                    $data['disposition'] = 'BUSY';
                    break;
                } elseif (str_contains($disp, 'FAILED')) {
                    $data['disposition'] = 'FAILED';
                }
            }
        }

        return $data;
    }

    protected function isExtension(string $num): bool
    {
        return preg_match('/^\d{3,4}$/', $num) === 1;
    }

    protected function isExternalNumber(string $num): bool
    {
        return preg_match('/^(\+|\d{5,})/', $num) === 1;
    }
}
