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
     * 
     * NOTA: La API Grandstream puede devolver main_cdr con campos vacíos ("")
     * y los valores reales en sub_cdr_*. Usamos empty() en vez de ??= para
     * evitar que strings vacíos bloqueen los valores reales de sub-segmentos.
     */
    protected function consolidateCdrSegments(array $segments): array
    {
        if (empty($segments)) return [];

        $segments = array_values($segments);

        // Determinar src/dst desde el primer segmento que tenga valores reales
        $firstSrc = '';
        $firstDst = '';
        foreach ($segments as $seg) {
            if (empty($firstSrc) && !empty($seg['src'])) $firstSrc = $seg['src'];
            if (empty($firstDst) && !empty($seg['dst'])) $firstDst = $seg['dst'];
            if ($firstSrc !== '' && $firstDst !== '') break;
        }

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

        // Tracking para duración: si hay main_cdr con totales, usarlos en vez de sumar
        $hasMainCdrTotals = false;
        $mainCdrDuration = 0;
        $mainCdrBillsec = 0;

        foreach ($segments as $seg) {
            $src = $seg['src'] ?? '';
            $dst = $seg['dst'] ?? '';

            // Capturar datos más tempranos
            $segStart = $seg['start'] ?? '';
            if (!empty($segStart) && (!$data['start_time'] || $segStart < $data['start_time'])) {
                $data['start_time'] = $segStart;
            }

            // unique_id: priorizar uniqueid (ID Asterisk de la llamada) sobre acctid/AcctId (ID por segmento)
            // Manejar ambas variantes de capitalización (acctid y AcctId)
            if (empty($data['unique_id'])) {
                $uniqueid = $seg['uniqueid'] ?? '';
                $acctid = $seg['acctid'] ?? $seg['AcctId'] ?? '';
                if (!empty($uniqueid)) {
                    $data['unique_id'] = $uniqueid;
                } elseif (!empty($acctid)) {
                    $data['unique_id'] = $acctid;
                }
            }

            // Campos de texto: usar empty() para que strings vacíos no bloqueen valores reales
            if (empty($data['caller_name'])) {
                $data['caller_name'] = !empty($seg['caller_name']) ? $seg['caller_name'] : null;
            }
            if (empty($data['recording_file'])) {
                $data['recording_file'] = !empty($seg['recordfiles']) ? $seg['recordfiles'] : null;
            }

            // Nuevos campos detallados
            if (empty($data['action_type'])) {
                $data['action_type'] = !empty($seg['action_type']) ? $seg['action_type'] : null;
            }
            if (empty($data['lastapp'])) {
                $data['lastapp'] = !empty($seg['lastapp']) ? $seg['lastapp'] : null;
            }
            if (empty($data['channel'])) {
                $data['channel'] = !empty($seg['channel']) ? $seg['channel'] : null;
            }
            if (empty($data['dst_channel'])) {
                $data['dst_channel'] = !empty($seg['dstchannel']) ? $seg['dstchannel'] : null;
            }
            if (empty($data['src_trunk_name'])) {
                $data['src_trunk_name'] = !empty($seg['src_trunk_name']) ? $seg['src_trunk_name'] : null;
            }
            
            // Capturar userfield (clasificación UCM: Inbound, Outbound, Internal)
            if (empty($data['userfield'])) {
                $data['userfield'] = !empty($seg['userfield']) ? $seg['userfield'] : null;
            }

            // Capturar answer_time si existe
            if (empty($data['answer_time']) && !empty($seg['answer']) && $seg['answer'] !== '0000-00-00 00:00:00') {
                $data['answer_time'] = $seg['answer'];
            }

            // Capturar dstanswer (quien contestó)
            if (empty($data['dstanswer']) && !empty($seg['dstanswer'])) {
                $data['dstanswer'] = $seg['dstanswer'];
            }

            // Detectar si este segmento es un main_cdr (tiene campos vacíos de identificación pero totales)
            $esMainCdr = empty($seg['uniqueid'] ?? '') && empty($seg['acctid'] ?? '') && empty($seg['AcctId'] ?? '');
            if ($esMainCdr && (int)($seg['duration'] ?? 0) > 0) {
                $hasMainCdrTotals = true;
                $mainCdrDuration = (int)($seg['duration'] ?? 0);
                $mainCdrBillsec = (int)($seg['billsec'] ?? 0);
            } else {
                // Solo sumar duración de sub-segmentos (no de main_cdr para evitar duplicados)
                $data['duration'] += (int)($seg['duration'] ?? 0);
                $data['billsec'] += (int)($seg['billsec'] ?? 0);
            }

            // Determinar origen/destino
            if ($esEntrante) {
                if (empty($data['source']) && $this->isExtension($dst)) {
                    $data['source'] = $dst;
                }
                if (empty($data['destination']) && $this->isExternalNumber($src)) {
                    $data['destination'] = $src;
                }
            } else {
                if (empty($data['source']) && $this->isExtension($src)) {
                    $data['source'] = $src;
                }
                if (empty($data['destination']) && !empty($dst)) {
                    $data['destination'] = $dst;
                }
            }

            if ((int)($seg['billsec'] ?? 0) > 0) {
                $data['disposition'] = 'ANSWERED';
            }
        }

        // Si hay totales de main_cdr, usarlos (son los correctos para toda la llamada)
        if ($hasMainCdrTotals) {
            $data['duration'] = $mainCdrDuration;
            $data['billsec'] = $mainCdrBillsec;
        }

        // Valores por defecto
        if (empty($data['source'])) {
            $data['source'] = $firstSrc ?: 'Desconocido';
        }
        if (empty($data['destination'])) {
            $data['destination'] = $firstDst ?: 'Desconocido';
        }
        if (empty($data['unique_id'])) {
            $data['unique_id'] = md5($data['start_time'] . $data['source'] . $data['destination']);
        }

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

        // Determinar call_type basado en userfield si está disponible
        if (!empty($data['userfield'])) {
            $uf = strtolower($data['userfield']);
            if ($uf === 'inbound') {
                $data['call_type'] = 'inbound';
            } elseif ($uf === 'outbound') {
                $data['call_type'] = 'outbound';
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
