<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // 'in' o 'out' - controla la matemática (suma o resta del stock)
            $table->enum('type', ['in', 'out']);

            // Motivo de negocio - controla la semántica, independiente del 'type'
            $table->enum('reason', [
                'ajuste',
                'compra',
                'venta',
                'merma',
                'devolucion',
                'transferencia',
            ]);

            $table->decimal('cantidad', 12, 2);
            $table->decimal('stock_anterior', 12, 2);
            $table->decimal('stock_nuevo', 12, 2);

            $table->text('notas')->nullable();

            // Referencia polimórfica opcional (venta_id, compra_id, transferencia_id, etc.)
            $table->nullableMorphs('reference');

            $table->timestamps();

            $table->index(['variante_id', 'sucursal_id']);
            $table->index(['sucursal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
