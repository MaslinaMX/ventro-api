<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    protected $table = 'gastos';

    protected $fillable = [
        'sucursal_id',
        'categoria_id',
        'metodo_pago_id',
        'user_id',
        'concepto',
        'monto',
        'fecha',
        'proveedor',
        'comprobante_url',
        'notas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historial()
    {
        return $this->hasMany(GastoHistorial::class)->latest();
    }
}
