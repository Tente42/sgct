<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class QueueCallDetail extends Model
{
    protected $fillable = [
        'pbx_connection_id',
        'queue',
        'caller',
        'agent',
        'call_time',
        'wait_time',
        'talk_time',
        'connected',
    ];

    protected $casts = [
        'call_time' => 'datetime',
        'wait_time' => 'integer',
        'talk_time' => 'integer',
        'connected' => 'boolean',
    ];

    /**
     * Relación con la conexión PBX
     */
    public function pbxConnection(): BelongsTo
    {
        return $this->belongsTo(PbxConnection::class);
    }

    /**
     * Scope global para filtrar por PBX activo
     */
    protected static function booted(): void
    {
        static::addGlobalScope('pbx', function (Builder $builder) {
            if (session()->has('active_pbx_id')) {
                $builder->where('pbx_connection_id', session('active_pbx_id'));
            }
        });
    }

    /**
     * Scope para filtrar por cola
     */
    public function scopeForQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope para filtrar solo llamadas conectadas
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('connected', true);
    }

    /**
     * Scope para filtrar por agente
     */
    public function scopeForAgent(Builder $query, string $agent): Builder
    {
        return $query->where('agent', $agent);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeBetweenDates(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('call_time', [$start . ' 00:00:00', $end . ' 23:59:59']);
    }
}
