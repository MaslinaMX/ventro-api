<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'sesion_caja_id',
        'usuario_id',
        'cliente_id',
        'subtotal',
        'descuento',
        'numero_ticket',
        'total',
        'estado',
        'cancelada_en',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'cancelada_en' => 'datetime',
    ];

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function isCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    public function items()
    {
        return $this->hasMany(VentaItem::class);
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class);
    }
}
