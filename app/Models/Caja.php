<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'nombre',
        'sucursal_id',
        'activa',
        'is_deletable',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'is_deletable' => 'boolean',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function sesionActiva()
    {
        return $this->hasOne(SesionCaja::class)->where('estado', 'abierta');
    }
}
