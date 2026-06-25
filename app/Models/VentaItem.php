<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaItem extends Model
{
    protected $table = 'venta_items';

    protected $fillable = [
        'venta_id',
        'producto_variante_id',
        'nombre_snapshot',
        'cantidad',
        'precio_unitario',
        'costo_unitario',
        'subtotal',
        'precio_lista',
        'descuento_linea',
        'iva_monto',
        'ieps_monto',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
        'precio_lista' => 'decimal:2',
        'descuento_linea' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'ieps_monto' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function productoVariante()
    {
        return $this->belongsTo(ProductoVariante::class);
    }
}
