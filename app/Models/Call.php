<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Setting;

class Call extends Model
{
    use HasFactory;

    /**
     * Boot del modelo - Registrar Global Scope para Multi-Tenant
     */
    protected static function booted(): void
    {
        // Global Scope: Filtrar llamadas por la central activa
        static::addGlobalScope('current_pbx', function (Builder $builder) {
            // Solo aplicar si hay usuario logueado Y central seleccionada
            if (auth()->check() && session()->has('active_pbx_id')) {
                $builder->where('pbx_connection_id', session('active_pbx_id'));
            }
        });

        // Auto-asignar pbx_connection_id al crear nuevas llamadas
        static::creating(function ($call) {
            if (empty($call->pbx_connection_id) && session()->has('active_pbx_id')) {
                $call->pbx_connection_id = session('active_pbx_id');
            }
        });
    }

    // --- LISTA DE PERMISOS ---
    protected $fillable = [
        'pbx_connection_id', // ID de la central PBX
        'unique_id',      // ID unico de la central
        'start_time',     // Hora inicio
        'answer_time',    // Hora en que se contestó
        'end_time',       // Hora fin
        'source',         // Origen (Anexo)
        'destination',    // Destino (Número marcado)
        'dstanswer',      // Extensión/agente que contestó
        'caller_name',    // Nombre del que llama
        'duration',       // Duracion total
        'billsec',        // Segundos cobrados
        'disposition',    // Estado (ANSWERED, BUSY, etc.)
        'action_type',    // Tipo de acción (DIAL, QUEUE, etc.)
        'lastapp',        // Última aplicación ejecutada
        'channel',        // Canal de origen
        'dst_channel',    // Canal de destino
        'src_trunk_name', // Nombre del trunk de origen
        'recording_file', // Archivo de grabacion (si existe)
    ];

    /**
     * Relación: Una llamada pertenece a una conexión PBX
     */
    public function pbxConnection(): BelongsTo
    {
        return $this->belongsTo(PbxConnection::class);
    }

    // --- ACCESSORS ---
    // Cache estatico para tarifas (evita multiples consultas a BD)
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
     * Limpiar cache de tarifas (util si se actualizan las tarifas)
     */
    public static function clearPricesCache(): void
    {
        self::$cachedPrices = null;
    }
    
    /**
     * Calcular el costo de la llamada basado en el destino y la duracion
     * Formula: (Duracion en segundos / 60) * Tarifa del destino
     * Redondea al minuto superior solo si dura mas de 3 segundos
     * 
     * Formatos Chile:
     * - Celular: 9XXXXXXXX o +569XXXXXXXX
     * - Fijo RM: 2XXXXXXXX o +562XXXXXXXX (2 + 8 digitos)
     * - Fijo regiones: YYXXXXXXX o +56YYXXXXXXX (YY=codigo area + 7 digitos)
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
        // Celular Chile con codigo pais: +569XXXXXXXX o 569XXXXXXXX
        elseif (preg_match('/^(\+?56)9\d{8}$/', $destination)) {
            $pricePerMinute = $priceMobile;
        }
        // Fijo RM con codigo pais: +562XXXXXXXX (2 + 8 digitos)
        elseif (preg_match('/^(\+?56)2\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo otras regiones con codigo pais: +56YYXXXXXXX (YY + 7 digitos, YY != 2 ni 9)
        elseif (preg_match('/^(\+?56)[3-8]\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo RM sin codigo pais: 2XXXXXXXX (2 + 8 digitos = 9 digitos)
        elseif (preg_match('/^2\d{8}$/', $destination)) {
            $pricePerMinute = $priceNational;
        }
        // Fijo otras regiones sin codigo pais: YYXXXXXXX (codigo area + 7 digitos)
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

        // Llamada interna (3-4 digitos tipicamente)
        if (preg_match('/^\d{3,4}$/', $destination)) {
            return 'Interna';
        }
        // LLinea 800 = Local (sin cobro)
        if (preg_match('/^800\d+$/', $destination)) {
            return 'Local';
        }
        // Linea 600 = Nacional (con cobro)
        if (preg_match('/^600\d+$/', $destination)) {
            return 'Nacional';
        }
        // Celular Chile: 9XXXXXXXX
        if (preg_match('/^9\d{8}$/', $destination)) {
            return 'Celular';
        }
        // Celular Chile con codigo pais: +569XXXXXXXX o 569XXXXXXXX
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