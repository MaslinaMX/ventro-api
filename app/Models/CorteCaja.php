<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteCaja extends Model
{
    protected $table = 'cortes_caja';

    protected $fillable = [
        'sesion_caja_id',
        'tipo',
        'snapshot',
        'efectivo_contado',
        'diferencia',
        'status',
        'generado_por_id',
        'generado_en',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'generado_en' => 'datetime',
        'efectivo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }
}
