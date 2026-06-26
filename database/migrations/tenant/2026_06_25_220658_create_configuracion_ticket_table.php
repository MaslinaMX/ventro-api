<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_tickets', function (Blueprint $table) {
            $table->id();
            $table->boolean('mostrar_logo')->default(true);
            $table->text('mensaje_personalizado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_tickets');
    }
};
