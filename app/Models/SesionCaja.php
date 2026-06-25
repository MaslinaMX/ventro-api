<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesionCaja extends Model
{
    protected $table = 'sesiones_caja';

    protected $fillable = [
        'caja_id',
        'usuario_id',
        'monto_inicial',
        'monto_final_esperado',
        'monto_final_contado',
        'diferencia',
        'estado',
        'cerrada_por_id',
        'abierta_en',
        'cerrada_en',
    ];

    protected $casts = [
        'monto_inicial' => 'decimal:2',
        'monto_final_esperado' => 'decimal:2',
        'monto_final_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'abierta_en' => 'datetime',
        'cerrada_en' => 'datetime',
    ];

    public function caja()
    {
        return $this->belongsTo(Caja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cerradaPor()
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    public function isAbierta(): bool
    {
        return $this->estado === 'abierta';
    }
}
