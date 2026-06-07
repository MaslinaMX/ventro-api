<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoVariantePrecio extends Model
{
    protected $table = 'producto_variante_precios';

    protected $fillable = ['variante_id', 'lista_id', 'precio'];

    protected $casts = ['precio' => 'decimal:2'];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function lista()
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_id');
    }
}
