<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'venta_id',
        'cancelada_por_id',
        'metodo_devolucion_id',
        'monto_devuelto',
        'motivo',
        'items_devueltos',
        'devuelta_en',
    ];

    protected $casts = [
        'items_devueltos' => 'array',
        'devuelta_en' => 'datetime',
        'monto_devuelto' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function canceladaPor()
    {
        return $this->belongsTo(User::class, 'cancelada_por_id');
    }

    public function metodoDevolucion()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_devolucion_id');
    }
}
