<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoVarianteImagen extends Model
{
    protected $table = 'producto_variante_imagenes';

    protected $fillable = ['variante_id', 'path', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }
}
