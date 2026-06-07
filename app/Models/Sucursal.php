<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'activa',
    ];

    public function cajas()
    {
        return $this->hasMany(Caja::class);
    }

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }
}
