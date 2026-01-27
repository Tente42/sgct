<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use HasFactory;

    // Campos permitidos para asignacion masiva
    protected $fillable = [
        'extension',
        'first_name',
        'last_name',
        'fullname',
        'email',
        'phone',
        'permission',
        'do_not_disturb',
        'max_contacts',
        'secret',
    ];

    // Casting de tipos para campos especificos
    protected $casts = [
        'do_not_disturb' => 'boolean',
        'max_contacts' => 'integer',
    ];
}