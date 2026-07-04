<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('cancelada_por_id')->constrained('users');
            $table->foreignId('metodo_devolucion_id')->constrained('metodos_pago');
            $table->decimal('monto_devuelto', 10, 2);
            $table->text('motivo')->nullable();
            $table->json('items_devueltos'); // snapshot de qué ítems y si se devolvió inventario
            $table->timestamp('devuelta_en')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('devoluciones');
    }
};
