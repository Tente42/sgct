<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use HasFactory;

    // ESTA LÍNEA ES LA LLAVE MAESTRA
    // Permite guardar cualquier campo en la base de datos sin errores de seguridad
    protected $guarded = [];
}