<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PbxConnection extends Model
{
    use HasFactory;

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
    ];

    /**
     * Casting de tipos para campos específicos
     * El campo password se encripta automáticamente
     */
    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'verify_ssl' => 'boolean',
    ];

    /**
     * Campos ocultos en serialización (JSON/Array)
     */
    protected $hidden = [
        'password',
    ];

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
