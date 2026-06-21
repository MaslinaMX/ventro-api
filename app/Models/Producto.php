<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria_id',
        'nombre',
        'descripcion',
        'tiene_variantes',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiene_variantes' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function variantes()
    {
        return $this->hasMany(ProductoVariante::class);
    }

    public function stocks()
    {
        return $this->hasManyThrough(
            ProductoVarianteStock::class,
            ProductoVariante::class,
            'producto_id',
            'variante_id'
        );
    }

    public function precios()
    {
        return $this->hasManyThrough(
            ProductoVariantePrecio::class,
            ProductoVariante::class,
            'producto_id',
            'variante_id'
        );
    }

    public function imagenes()
    {
        return $this->hasManyThrough(
            ProductoVarianteImagen::class,
            ProductoVariante::class,
            'producto_id',
            'variante_id'
        );
    }

    public function getStockTotalAttribute()
    {
        return $this->stocks()->sum('cantidad');
    }

    public function getTieneMultiplesVariantesAttribute()
    {
        return $this->variantes()->count() > 1;
    }
}
