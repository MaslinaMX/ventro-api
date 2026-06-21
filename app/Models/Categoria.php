<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['nombre', 'slug', 'descripcion', 'imagen', 'icono', 'color', 'parent_id', 'activo'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function subcategorias()
    {
        return $this->hasMany(Categoria::class, 'parent_id');
    }

    public function padre()
    {
        return $this->belongsTo(Categoria::class, 'parent_id');
    }
}
