<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;

class Call extends Model
{
    use HasFactory;

    // --- LISTA DE PERMISOS ---
    protected $fillable = [
        'unique_id',      // ID único de la central
        'start_time',     // Hora inicio
        'end_time',       // Hora fin
        'source',         // Origen (Anexo)
        'destination',    // Destino (Número marcado)
        'caller_name',    // Nombre del que llama
        'duration',       // Duración total
        'billsec',        // Segundos cobrados
        'disposition',    // Estado (ANSWERED, BUSY, etc.)
        'recording_file', // Archivo de grabación (si existe)
    ];

    // --- ACCESSORS ---
    // Cache estático para tarifas (evita múltiples consultas a BD)
    protected static ?array $cachedPrices = null;

    /**
     * Obtener tarifas cacheadas
     */
    protected static function getPrices(): array
    {
        if (self::$cachedPrices === null) {
            self::$cachedPrices = Setting::pluck('value', 'key')->toArray();
        }
        return self::$cachedPrices;
    }

    /**
     * Limpiar cache de tarifas (útil si se actualizan las tarifas)
     */
    public static function clearPricesCache(): void
    {
        self::$cachedPrices = null;
    }
    
    /**
     * Calcular el costo de la llamada basado en el destino y la duración
     * Fórmula: (Duración en segundos / 60) * Tarifa del destino
     * Redondea al minuto superior solo si dura más de 3 segundos
     * 
     * Formatos Chile:
     * - Celular: 9XXXXXXXX o +569XXXXXXXX
     * - Fijo RM: 2XXXXXXXX o +562XXXXXXXX (2 + 8 dígitos)
     * - Fijo regiones: YYXXXXXXX o +56YYXXXXXXX (YY=código área + 7 dígitos)
     * - 800XXXXXX = Gratis (toll-free)
     * - 600XXXXXX = Tarifa nacional (costo compartido)
     */
    public function getCostAttribute(): int
    {
        // Si no hay tiempo facturado o dura 3 segundos o menos, no hay costo
        if ($this->billsec <= 3) {
            return 0;
        }

        // Clasificar el destino
        $destination = $this->destination;

        // Llamada interna (3-4 dígitos) = SIN COSTO
        if (preg_match('/^\d{3,4}$/', $destination)) {
            return 0;
        }

        // Línea 800 en Chile = SIN COSTO (toll-free)
        if (preg_match('/^800\d+$/', $destination)) {
            return 0;
        }

        // Obtener tarifas cacheadas (con valores por defecto)
        $prices = self::getPrices();
        $priceMobile = $prices['price_mobile'] ?? 80;
        $priceNational = $prices['price_national'] ?? 40;
        $priceInternational = $prices['price_international'] ?? 500;

        $pricePerMinute = $priceNational; // Por defecto: fijo nacional

        // Línea 600 en Chile = Tarifa nacional (costo compartido, NO es gratis)
        if (preg_match('/^600\d+$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Celular Chile: 9XXXXXXXX (9 dígitos empezando con 9)
        elseif (preg_match('/^9\d{8}$/', $destination)) {
            $pricePerMinute = $priceMobile;
        }
        // Celular Chile con código país: +569XXXXXXXX o 569XXXXXXXX
        elseif (preg_match('/^(\+?56)9\d{8}$/', $destination)) {
            $pricePerMinute = $priceMobile;
        }
        // Fijo RM con código país: +562XXXXXXXX (2 + 8 dígitos)
        elseif (preg_match('/^(\+?56)2\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo otras regiones con código país: +56YYXXXXXXX (YY + 7 dígitos, YY != 2 ni 9)
        elseif (preg_match('/^(\+?56)[3-8]\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo RM sin código país: 2XXXXXXXX (2 + 8 dígitos = 9 dígitos)
        elseif (preg_match('/^2\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo otras regiones sin código país: YYXXXXXXX (código área + 7 dígitos)
        elseif (preg_match('/^[3-8]\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Internacional: empieza con + o 00 (pero no es Chile +56)
        elseif (preg_match('/^(\+|00)/', $destination) && !preg_match('/^(\+?56)/', $destination)) {
            $pricePerMinute = $priceInternational;
        }

        // Calcular: minutos (redondeando hacia arriba) * precio
        $minutes = ceil($this->billsec / 60);
        return (int) ($minutes * $pricePerMinute);
    }

    /**
     * Obtener el tipo de llamada basado en el destino
     */
    public function getCallTypeAttribute(): string
    {
        $destination = $this->destination;

        // Llamada interna (3-4 dígitos típicamente)
        if (preg_match('/^\d{3,4}$/', $destination)) {
            return 'Interna';
        }
        // Línea 800 = Local (sin cobro)
        if (preg_match('/^800\d+$/', $destination)) {
            return 'Local';
        }
        // Línea 600 = Nacional (con cobro)
        if (preg_match('/^600\d+$/', $destination)) {
            return 'Nacional';
        }
        // Celular Chile: 9XXXXXXXX
        if (preg_match('/^9\d{8}$/', $destination)) {
            return 'Celular';
        }
        // Celular Chile con código país: +569XXXXXXXX o 569XXXXXXXX
        if (preg_match('/^(\+?56)9\d{8}$/', $destination)) {
            return 'Celular';
        }
        // Fijo RM: 2XXXXXXXX o +562XXXXXXXX
        if (preg_match('/^(\+?56)?2\d{8}$/', $destination)) {
            return 'Nacional';
        }
        // Fijo otras regiones: YYXXXXXXX o +56YYXXXXXXX
        if (preg_match('/^(\+?56)?[3-8]\d{8}$/', $destination)) {
            return 'Nacional';
        }
        // Internacional: empieza con + o 00 (pero no es Chile +56)
        if (preg_match('/^(\+|00)/', $destination) && !preg_match('/^(\+?56)/', $destination)) {
            return 'Internacional';
        }
        // Por defecto: Fijo Nacional
        return 'Nacional';
    }
}