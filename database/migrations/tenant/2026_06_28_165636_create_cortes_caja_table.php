<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortes_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_caja_id')->constrained('sesiones_caja')->onDelete('cascade');
            $table->enum('tipo', ['X', 'Z']);
            $table->json('snapshot');
            $table->foreignId('generado_por_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('generado_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes_caja');
    }
};
