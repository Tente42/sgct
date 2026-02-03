<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PbxConnection extends Model
{
    use HasFactory;

    /**
     * Estados posibles de la central
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SYNCING = 'syncing';
    const STATUS_READY = 'ready';
    const STATUS_ERROR = 'error';

    /**
     * Campos permitidos para asignación masiva
     */
    protected $fillable = [
        'name',
        'ip',
        'port',
        'username',
        'password',
        'verify_ssl',
        'status',
        'sync_message',
        'last_sync_at',
    ];

    /**
     * Casting de tipos para campos específicos
     * El campo password se encripta automáticamente
     */
    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'verify_ssl' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Campos ocultos en serialización (JSON/Array)
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Verifica si la central está lista para usar
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Verifica si la central está sincronizando
     */
    public function isSyncing(): bool
    {
        return $this->status === self::STATUS_SYNCING;
    }

    /**
     * Verifica si la central está pendiente de sincronización
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Obtiene el nombre para mostrar del estado
     */
    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SYNCING => 'Sincronizando...',
            self::STATUS_READY => 'Lista',
            self::STATUS_ERROR => 'Error',
            default => 'Desconocido',
        };
    }

    /**
     * Relación: Una conexión PBX tiene muchas llamadas
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /**
     * Relación: Una conexión PBX tiene muchas extensiones
     */
    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }
}
