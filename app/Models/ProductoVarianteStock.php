<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoVarianteStock extends Model
{
    protected $table = 'producto_variante_stock';

    protected $fillable = ['variante_id', 'sucursal_id', 'cantidad', 'cantidad_minima'];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
}
