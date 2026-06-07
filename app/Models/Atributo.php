<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atributo extends Model
{
    protected $fillable = ['nombre'];

    public function valores()
    {
        return $this->hasMany(AtributoValor::class);
    }
}
