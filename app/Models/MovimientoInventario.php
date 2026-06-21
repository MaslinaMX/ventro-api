<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'variante_id',
        'sucursal_id',
        'user_id',
        'type',
        'reason',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'notas',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'stock_anterior' => 'decimal:2',
        'stock_nuevo' => 'decimal:2',
    ];

    public const TYPES = ['in', 'out'];

    public const REASONS = [
        'ajuste',
        'compra',
        'venta',
        'merma',
        'devolucion',
        'transferencia',
    ];

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function producto()
    {
        return $this->hasOneThrough(
            Producto::class,
            ProductoVariante::class,
            'id',
            'id',
            'variante_id',
            'producto_id'
        );
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Registra un movimiento de inventario y actualiza el stock atómicamente.
     *
     * @param  array{
     *     variante_id: int,
     *     sucursal_id: int,
     *     type: string,
     *     reason: string,
     *     cantidad: float,
     *     user_id?: int|null,
     *     notas?: string|null,
     *     reference_id?: int|null,
     *     reference_type?: string|null,
     * } $data
     */
    public static function registrar(array $data): self
    {
        if (! in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException("Tipo de movimiento inválido: {$data['type']}");
        }

        if (! in_array($data['reason'], self::REASONS, true)) {
            throw new InvalidArgumentException("Motivo de movimiento inválido: {$data['reason']}");
        }

        if ($data['cantidad'] <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($data) {
            $stockRow = ProductoVarianteStock::lockForUpdate()
                ->firstOrCreate(
                    [
                        'variante_id' => $data['variante_id'],
                        'sucursal_id' => $data['sucursal_id'],
                    ],
                    [
                        'cantidad' => 0,
                        'cantidad_minima' => 0,
                    ]
                );

            $stockAnterior = (float) $stockRow->cantidad;

            if ($data['type'] === 'in') {
                $stockNuevo = $stockAnterior + $data['cantidad'];
            } else {
                $stockNuevo = $stockAnterior - $data['cantidad'];

                $variante = ProductoVariante::findOrFail($data['variante_id']);
                if ($stockNuevo < 0 && ! $variante->allow_out_of_stock) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente. Disponible: {$stockAnterior}, solicitado: {$data['cantidad']}"
                    );
                }
            }

            $stockRow->update(['cantidad' => $stockNuevo]);

            return self::create([
                'variante_id' => $data['variante_id'],
                'sucursal_id' => $data['sucursal_id'],
                'user_id' => $data['user_id'] ?? null,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'cantidad' => $data['cantidad'],
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'notas' => $data['notas'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
            ]);
        });
    }
}
