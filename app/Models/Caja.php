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
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
}
