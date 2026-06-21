<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('direccion_2')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('direccion_2');
            $table->string('estado')->nullable()->after('ciudad');
            $table->string('codigo_postal')->nullable()->after('estado');
            $table->string('pais')->default('México')->after('codigo_postal');
            $table->string('telefono_alternativo')->nullable()->after('telefono');
            $table->string('sitio_web')->nullable()->after('email');
            $table->string('rfc')->nullable()->after('sitio_web');
            $table->boolean('is_main')->default(false)->after('activa');
            $table->boolean('is_deletable')->default(true)->after('is_main');
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn([
                'direccion_2', 'ciudad', 'estado', 'codigo_postal', 'pais',
                'telefono_alternativo', 'sitio_web', 'rfc', 'is_main', 'is_deletable',
            ]);
        });
    }
};
