<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'label', 'value'];

    /**
     * Obtener un setting por su key
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Obtener todos los settings como array key => value
     */
    public static function allAsArray(): array
    {
        return self::pluck('value', 'key')->toArray();
    }
}
