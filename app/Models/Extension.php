<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Extension extends Model
{
    use HasFactory;

    /**
     * Boot del modelo - Registrar Global Scope para Multi-Tenant
     */
    protected static function booted(): void
    {
        // Global Scope: Filtrar extensiones por la central activa
        static::addGlobalScope('current_pbx', function (Builder $builder) {
            // Solo aplicar si hay usuario logueado Y central seleccionada
            if (auth()->check() && session()->has('active_pbx_id')) {
                $builder->where('pbx_connection_id', session('active_pbx_id'));
            }
        });

        // Auto-asignar pbx_connection_id al crear nuevas extensiones
        static::creating(function ($extension) {
            if (empty($extension->pbx_connection_id) && session()->has('active_pbx_id')) {
                $extension->pbx_connection_id = session('active_pbx_id');
            }
        });
    }

    // Campos permitidos para asignacion masiva
    protected $fillable = [
        'pbx_connection_id', // ID de la central PBX
        'extension',
        'first_name',
        'last_name',
        'fullname',
        'email',
        'phone',
        'ip',
        'permission',
        'do_not_disturb',
        'max_contacts',
        'secret',
    ];

    /**
     * Relación: Una extensión pertenece a una conexión PBX
     */
    public function pbxConnection(): BelongsTo
    {
        return $this->belongsTo(PbxConnection::class);
    }

    // Casting de tipos para campos especificos
    protected $casts = [
        'do_not_disturb' => 'boolean',
        'max_contacts' => 'integer',
    ];
}