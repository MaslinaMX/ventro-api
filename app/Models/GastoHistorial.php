<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoHistorial extends Model
{
    protected $table = 'gasto_historial';

    protected $fillable = [
        'gasto_id',
        'editado_por',
        'snapshot_anterior',
        'motivo',
    ];

    protected $casts = [
        'snapshot_anterior' => 'array',
    ];

    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }

    public function editadoPor()
    {
        return $this->belongsTo(User::class, 'editado_por');
    }
}
