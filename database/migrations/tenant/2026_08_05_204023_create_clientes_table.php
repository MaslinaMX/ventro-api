<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // Datos generales
            $table->string('nombre');
            $table->enum('tipo', ['persona_fisica', 'persona_moral'])->default('persona_fisica');

            // Contacto
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            // Dirección
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('codigo_postal', 10)->nullable();

            // Facturación
            $table->string('rfc', 13)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('regimen_fiscal', 3)->nullable(); // clave SAT c_RegimenFiscal
            $table->string('uso_cfdi', 4)->nullable(); // clave SAT c_UsoCFDI

            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre');
            $table->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
