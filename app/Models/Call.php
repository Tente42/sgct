<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    // --- AGREGA ESTA LISTA DE PERMISOS ---
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
}