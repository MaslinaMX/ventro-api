<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variante_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('lista_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->decimal('precio', 10, 2);
            $table->timestamps();

            $table->unique(['variante_id', 'lista_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variante_precios');
    }
};
