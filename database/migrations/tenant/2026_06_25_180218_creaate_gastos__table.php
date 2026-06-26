<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('categoria_id')->constrained('categorias_gasto');
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->foreignId('user_id')->constrained('users');
            $table->string('concepto');
            $table->decimal('monto', 10, 2);
            $table->date('fecha');
            $table->string('proveedor')->nullable();
            $table->string('comprobante_url')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
