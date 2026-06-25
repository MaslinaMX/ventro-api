<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'nombre',
        'activo',
        'is_deletable',
        'requiere_referencia',
        'icono',
        'color',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'is_deletable' => 'boolean',
        'requiere_referencia' => 'boolean',
    ];
}
