<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gasto_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_id')->constrained('gastos')->cascadeOnDelete();
            $table->foreignId('editado_por')->constrained('users');
            $table->json('snapshot_anterior');
            $table->text('motivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_historial');
    }
};
