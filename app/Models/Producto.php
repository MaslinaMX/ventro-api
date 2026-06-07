<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = ['categoria_id', 'nombre', 'descripcion', 'activo'];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class);
    }

    public function stockTotal($sucursalId)
    {
        return $this->variantes()
            ->join('producto_variante_stock', 'producto_variantes.id', '=', 'producto_variante_stock.variante_id')
            ->where('producto_variante_stock.sucursal_id', $sucursalId)
            ->sum('producto_variante_stock.cantidad');
    }
}
