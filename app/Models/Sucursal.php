<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'direccion_2',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'telefono',
        'telefono_alternativo',
        'email',
        'sitio_web',
        'rfc',
        'activa',
        'is_main',
        'is_deletable',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'is_main' => 'boolean',
        'is_deletable' => 'boolean',
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
