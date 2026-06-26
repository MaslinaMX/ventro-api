<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionTicket extends Model
{
    protected $table = 'configuracion_tickets';

    protected $fillable = [
        'mostrar_logo',
        'mensaje_personalizado',
    ];

    protected $casts = [
        'mostrar_logo' => 'boolean',
    ];

    /**
     * Siempre regresa la única fila de configuración, creándola con
     * defaults si todavía no existe (igual al patrón de stock mínimo global).
     */
    public static function obtener(): self
    {
        return self::firstOrCreate([], [
            'mostrar_logo' => true,
            'mensaje_personalizado' => null,
        ]);
    }
}
