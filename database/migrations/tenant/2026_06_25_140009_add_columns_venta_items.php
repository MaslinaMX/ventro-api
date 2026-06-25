<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->decimal('precio_lista', 10, 2)->after('precio_unitario'); // precio de catálogo, sin descuento
            $table->decimal('descuento_linea', 10, 2)->default(0)->after('precio_lista');
            $table->decimal('iva_monto', 10, 2)->default(0)->after('descuento_linea');
            $table->decimal('ieps_monto', 10, 2)->default(0)->after('iva_monto');
        });
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropColumn(['precio_lista', 'descuento_linea', 'iva_monto', 'ieps_monto']);
        });
    }
};
