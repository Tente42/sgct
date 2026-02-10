<?php

namespace App\Services;

/**
 * CallBillingAnalyzer - Analizador de Facturación de Llamadas
 * 
 * Esta clase analiza registros CDR de Grandstream para determinar
 * qué llamadas deben ser facturadas.
 * 
 * LÓGICA DE FACTURACIÓN:
 * - Solo se cobran llamadas SALIENTES (Outbound) de la empresa hacia números externos
 * - Las llamadas entrantes (Inbound) desde afuera NO se cobran
 * - Las llamadas internas (Internal) entre extensiones NO se cobran
 * 
 * CAMPOS CLAVE DE LA API CDR:
 * - channel: Canal de origen (ej: "PJSIP/1760-00006d6f" o "PJSIP/trunk_2-00006d19")
 * - channel_ext: Extensión del canal (más confiable que src)
 * - src: Número origen (puede estar enmascarado por la PBX)
 * - dst: Número destino
 * - userfield: Clasificación UCM ("Inbound", "Outbound", "Internal")
 * - src_trunk_name: Nombre del trunk de origen (si viene de afuera)
 * - dst_trunk_name: Nombre del trunk de destino (si va hacia afuera)
 * - disposition: Estado de la llamada (ANSWERED, NO ANSWER, BUSY, FAILED)
 * - billsec: Segundos facturables
 */
class CallBillingAnalyzer
{
    /**
     * Extensiones consideradas como internas (típicamente 4 dígitos o menos)
     */
    private int $maxInternalExtensionLength = 4;

    /**
     * Longitud mínima para considerar un número como externo
     */
    private int $minExternalNumberLength = 7;

    /**
     * Patrones de trunk que identifican llamadas que salen/entran por línea externa
     */
    private array $trunkPatterns = ['trunk', 'movistar', 'claro', 'entel', 'sip'];

    /**
     * Extrae el número/identificador real del canal de origen
     * 
     * Formato típico del channel: "PJSIP/4444-000000a1" o "SIP/1001-0000b2" o "PJSIP/trunk_2-00006d19"
     * 
     * @param string $channel String del canal de origen
     * @return string|null El identificador extraído (número de extensión o nombre del trunk) o null si no se puede extraer
     */
    public function getRealExtension(string $channel): ?string
    {
        if (empty($channel)) {
            return null;
        }

        // Regex para extraer el identificador entre "/" y "-"
        // Soporta: PJSIP, SIP, IAX2
        // Captura tanto números (1760) como identificadores alfanuméricos (trunk_2)
        if (preg_match('/(?:PJSIP|SIP|IAX2)\/([a-zA-Z0-9_]+)-/', $channel, $matches)) {
            return $matches[1];
        }

        // Fallback: intentar extraer cualquier cosa después de / y antes de -
        if (preg_match('/\/([^-]+)-/', $channel, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Determina si el identificador extraído corresponde a un anexo interno
     * 
     * Un anexo interno típicamente:
     * - Es puramente numérico
     * - Tiene 4 dígitos o menos (configurable)
     * 
     * @param string|null $extension El identificador a evaluar
     * @return bool True si es un anexo interno
     */
    public function isInternalExtension(?string $extension): bool
    {
        if ($extension === null || $extension === '') {
            return false;
        }

        // Si contiene letras (como "trunk_2"), no es un anexo interno
        if (!ctype_digit($extension)) {
            return false;
        }

        // Si tiene más de N dígitos, es probablemente un número externo
        return strlen($extension) <= $this->maxInternalExtensionLength;
    }

    /**
     * Determina si un número destino es externo
     * 
     * Un número externo típicamente:
     * - Tiene más de 6 dígitos (número telefónico real)
     * - NO es un código de servicio corto (ej: *70, #123)
     * 
     * @param string $destination Número de destino
     * @return bool True si es un destino externo
     */
    public function isExternalDestination(string $destination): bool
    {
        if (empty($destination)) {
            return false;
        }

        // Limpiar caracteres especiales comunes en números telefónicos
        $cleaned = preg_replace('/[^0-9]/', '', $destination);

        // Si tiene suficientes dígitos, es externo
        return strlen($cleaned) >= $this->minExternalNumberLength;
    }

    /**
     * Determina si una llamada viene desde un trunk externo (es Inbound)
     * 
     * @param array $callData Datos de la llamada
     * @return bool True si la llamada viene desde afuera
     */
    public function isInboundFromTrunk(array $callData): bool
    {
        // Método 1: Usar userfield (más confiable)
        $userfield = strtolower($callData['userfield'] ?? '');
        if ($userfield === 'inbound') {
            return true;
        }

        // Método 2: Verificar src_trunk_name
        $srcTrunk = $callData['src_trunk_name'] ?? '';
        if (!empty($srcTrunk)) {
            return true;
        }

        // Método 3: Verificar si el channel indica un trunk
        $realExt = $this->getRealExtension($callData['channel'] ?? '');
        if ($realExt !== null && $this->isTrunkIdentifier($realExt)) {
            return true;
        }

        return false;
    }

    /**
     * Determina si una llamada sale hacia un trunk externo (es Outbound)
     * 
     * @param array $callData Datos de la llamada
     * @return bool True si la llamada va hacia afuera
     */
    public function isOutboundToTrunk(array $callData): bool
    {
        // Método 1: Usar userfield (más confiable)
        $userfield = strtolower($callData['userfield'] ?? '');
        if ($userfield === 'outbound') {
            return true;
        }

        // Método 2: Verificar dst_trunk_name
        $dstTrunk = $callData['dst_trunk_name'] ?? '';
        if (!empty($dstTrunk)) {
            return true;
        }

        // Método 3: Verificar si el dstchannel indica un trunk
        $dstChannelExt = $this->getRealExtension($callData['dstchannel'] ?? '');
        if ($dstChannelExt !== null && $this->isTrunkIdentifier($dstChannelExt)) {
            return true;
        }

        return false;
    }

    /**
     * Verifica si un identificador corresponde a un trunk
     * 
     * @param string $identifier El identificador a verificar (ej: "trunk_2")
     * @return bool True si parece ser un trunk
     */
    public function isTrunkIdentifier(string $identifier): bool
    {
        $lower = strtolower($identifier);
        foreach ($this->trunkPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determina si una llamada es interna (entre extensiones)
     * 
     * @param array $callData Datos de la llamada
     * @return bool True si es una llamada interna
     */
    public function isInternalCall(array $callData): bool
    {
        // Método 1: Usar userfield (más confiable)
        $userfield = strtolower($callData['userfield'] ?? '');
        if ($userfield === 'internal') {
            return true;
        }

        // Método 2: Verificar que ambos extremos sean extensiones internas
        $srcExt = $this->getRealExtension($callData['channel'] ?? '');
        $dstExt = $callData['dst'] ?? '';

        $srcIsInternal = $this->isInternalExtension($srcExt);
        $dstIsInternal = $this->isInternalExtension($dstExt) || 
                         $this->isInternalExtension($callData['dstanswer'] ?? '');

        // Si no hay trunks involucrados y ambos extremos son internos
        $noTrunks = empty($callData['src_trunk_name']) && empty($callData['dst_trunk_name']);

        return $srcIsInternal && $dstIsInternal && $noTrunks;
    }

    /**
     * MÉTODO PRINCIPAL: Determina si una llamada debe ser facturada
     * 
     * Criterios para facturar:
     * 1. La llamada debe originarse desde un anexo INTERNO de la empresa
     * 2. El destino debe ser un número EXTERNO (fuera de la empresa)
     * 3. La llamada debe haber sido CONTESTADA (disposition = ANSWERED)
     * 
     * En otras palabras: Solo cobramos cuando NOSOTROS llamamos AFUERA y contestan.
     * 
     * @param array|object $callData Datos de la llamada (array o objeto stdClass)
     * @return bool True si la llamada debe ser cobrada
     */
    public function isBillable($callData): bool
    {
        // Convertir a array si es objeto
        if (is_object($callData)) {
            $callData = (array) $callData;
        }

        // Criterio 1: La llamada debe estar CONTESTADA
        $disposition = strtoupper($callData['disposition'] ?? '');
        if ($disposition !== 'ANSWERED') {
            return false;
        }

        // Criterio 2: NO debe ser una llamada entrante (Inbound)
        if ($this->isInboundFromTrunk($callData)) {
            return false;
        }

        // Criterio 3: NO debe ser una llamada interna
        if ($this->isInternalCall($callData)) {
            return false;
        }

        // Criterio 4: Debe ser una llamada saliente hacia el exterior
        if (!$this->isOutboundToTrunk($callData)) {
            return false;
        }

        // Criterio 5: El origen debe ser un anexo interno (no un trunk)
        $realExt = $this->getRealExtension($callData['channel'] ?? '');
        if ($realExt === null || !$this->isInternalExtension($realExt)) {
            // Fallback: usar channel_ext si está disponible
            $channelExt = $callData['channel_ext'] ?? '';
            if (!$this->isInternalExtension($channelExt)) {
                return false;
            }
        }

        // Si llegamos aquí, la llamada es cobrable
        return true;
    }

    /**
     * Analiza una llamada y retorna información detallada de facturación
     * 
     * @param array $callData Datos de la llamada
     * @return array Información de análisis
     */
    public function analyze(array $callData): array
    {
        $channel = $callData['channel'] ?? '';
        $realExt = $this->getRealExtension($channel);
        $channelExt = $callData['channel_ext'] ?? '';
        $src = $callData['src'] ?? '';
        $dst = $callData['dst'] ?? '';
        $userfield = $callData['userfield'] ?? '';
        $disposition = $callData['disposition'] ?? '';
        $billsec = (int) ($callData['billsec'] ?? 0);

        $isInbound = $this->isInboundFromTrunk($callData);
        $isOutbound = $this->isOutboundToTrunk($callData);
        $isInternal = $this->isInternalCall($callData);
        $isBillable = $this->isBillable($callData);

        // Determinar tipo de llamada
        $callType = 'unknown';
        if ($isInternal) {
            $callType = 'internal';
        } elseif ($isInbound) {
            $callType = 'inbound';
        } elseif ($isOutbound) {
            $callType = 'outbound';
        }

        // Determinar por qué no es cobrable
        $reason = '';
        if (!$isBillable) {
            if ($disposition !== 'ANSWERED') {
                $reason = 'No contestada';
            } elseif ($isInbound) {
                $reason = 'Llamada entrante (no cobramos a quien nos llama)';
            } elseif ($isInternal) {
                $reason = 'Llamada interna (entre extensiones)';
            } elseif (!$isOutbound) {
                $reason = 'No es llamada saliente';
            } else {
                $reason = 'Origen no es anexo interno';
            }
        }

        return [
            'isBillable' => $isBillable,
            'callType' => $callType,
            'reason' => $reason,
            'realExtension' => $realExt,
            'channelExt' => $channelExt,
            'reportedSrc' => $src,
            'destination' => $dst,
            'userfield' => $userfield,
            'disposition' => $disposition,
            'billableSeconds' => $isBillable ? $billsec : 0,
            'analysis' => [
                'isInbound' => $isInbound,
                'isOutbound' => $isOutbound,
                'isInternal' => $isInternal,
                'srcIsInternal' => $this->isInternalExtension($realExt ?? $channelExt),
                'dstIsExternal' => $this->isExternalDestination($dst),
                'srcTrunk' => $callData['src_trunk_name'] ?? '',
                'dstTrunk' => $callData['dst_trunk_name'] ?? '',
            ]
        ];
    }

    /**
     * Analiza múltiples llamadas y retorna estadísticas
     * 
     * @param array $calls Array de llamadas
     * @return array Estadísticas de análisis
     */
    public function analyzeMultiple(array $calls): array
    {
        $stats = [
            'total' => count($calls),
            'billable' => 0,
            'notBillable' => 0,
            'byType' => [
                'inbound' => 0,
                'outbound' => 0,
                'internal' => 0,
                'unknown' => 0,
            ],
            'byDisposition' => [
                'ANSWERED' => 0,
                'NO ANSWER' => 0,
                'BUSY' => 0,
                'FAILED' => 0,
                'OTHER' => 0,
            ],
            'totalBillableSeconds' => 0,
            'billableCalls' => [],
            'sampleNonBillable' => [],
        ];

        foreach ($calls as $call) {
            $analysis = $this->analyze($call);

            if ($analysis['isBillable']) {
                $stats['billable']++;
                $stats['totalBillableSeconds'] += $analysis['billableSeconds'];
                $stats['billableCalls'][] = $analysis;
            } else {
                $stats['notBillable']++;
                if (count($stats['sampleNonBillable']) < 10) {
                    $stats['sampleNonBillable'][] = $analysis;
                }
            }

            $stats['byType'][$analysis['callType']]++;

            $disposition = strtoupper($call['disposition'] ?? 'OTHER');
            if (isset($stats['byDisposition'][$disposition])) {
                $stats['byDisposition'][$disposition]++;
            } else {
                $stats['byDisposition']['OTHER']++;
            }
        }

        return $stats;
    }

    // ============ SETTERS PARA CONFIGURACIÓN ============

    public function setMaxInternalExtensionLength(int $length): self
    {
        $this->maxInternalExtensionLength = $length;
        return $this;
    }

    public function setMinExternalNumberLength(int $length): self
    {
        $this->minExternalNumberLength = $length;
        return $this;
    }

    public function addTrunkPattern(string $pattern): self
    {
        $this->trunkPatterns[] = strtolower($pattern);
        return $this;
    }
}
