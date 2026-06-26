<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoVariante extends Model
{
    protected $table = 'producto_variantes';

    protected $fillable = [
        'producto_id', 'nombre', 'sku', 'codigo_barras',
        'precio', 'precio_comparacion', 'cost_net',
        'iva', 'ieps', 'impuestos_incluidos',
        'is_default', 'allow_online', 'allow_out_of_stock',
        'sat_key', 'imagen', 'activo',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_comparacion' => 'decimal:2',
        'cost_net' => 'decimal:2',
        'iva' => 'decimal:2',
        'ieps' => 'decimal:2',
        'impuestos_incluidos' => 'boolean',
        'is_default' => 'boolean',
        'allow_online' => 'boolean',
        'allow_out_of_stock' => 'boolean',
        'activo' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function stock()
    {
        return $this->hasMany(ProductoVarianteStock::class, 'variante_id');
    }

    public function stockEnSucursal($sucursalId)
    {
        return $this->stock()->where('sucursal_id', $sucursalId)->first();
    }

    public function precios()
    {
        return $this->hasMany(ProductoVariantePrecio::class, 'variante_id');
    }

    public function imagenes()
    {
        return $this->hasMany(ProductoVarianteImagen::class, 'variante_id');
    }

    public function atributos()
    {
        return $this->belongsToMany(AtributoValor::class, 'producto_variante_atributos', 'variante_id', 'atributo_valor_id');
    }

    public function calcularPrecio(): array
    {

        return $this->calcularDesgloseFiscal((float) $this->precio);
    }

    /**
     * Mismo desglose fiscal que calcularPrecio(), pero sobre un monto
     * específico en vez del precio de catálogo — útil cuando se vendió
     * con descuento y el IVA debe calcularse sobre lo realmente cobrado.
     */
    public function calcularDesgloseFiscal(float $base): array
    {

        $iva = (float) $this->iva / 100;
        $ieps = (float) $this->ieps / 100;

        if ($this->impuestos_incluidos) {
            $divisor = 1 + $iva + $ieps;
            $baseNeta = round($base / $divisor, 2);
            $montoIva = round($baseNeta * $iva, 2);
            $montoIeps = round($baseNeta * $ieps, 2);
            $total = $base;
        } else {
            $baseNeta = $base;
            $montoIva = round($base * $iva, 2);
            $montoIeps = round($base * $ieps, 2);
            $total = round($base + $montoIva + $montoIeps, 2);
        }

        return [
            'precio_base' => $baseNeta,
            'iva_porcentaje' => $this->iva,
            'iva_monto' => $montoIva,
            'ieps_porcentaje' => $this->ieps,
            'ieps_monto' => $montoIeps,
            'total' => $total,
        ];
    }
}
