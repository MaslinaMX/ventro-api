<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variante_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->integer('cantidad')->default(0);
            $table->integer('cantidad_minima')->default(0);
            $table->timestamps();

            $table->unique(['variante_id', 'sucursal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variante_stock');
    }
};
