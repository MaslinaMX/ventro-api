<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaPrecio extends Model
{
    protected $table = 'listas_precios';

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function precios()
    {
        return $this->hasMany(ProductoVariantePrecio::class, 'lista_id');
    }
}
