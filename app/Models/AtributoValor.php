<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtributoValor extends Model
{
    protected $table = 'atributo_valores';

    protected $fillable = ['atributo_id', 'valor'];

    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }

    public function variantes()
    {
        return $this->belongsToMany(ProductoVariante::class, 'producto_variante_atributos', 'atributo_valor_id', 'variante_id');
    }
}
