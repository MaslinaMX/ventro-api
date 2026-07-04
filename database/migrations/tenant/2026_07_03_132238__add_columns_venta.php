<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('base_gravable', 10, 2)->default(0)->after('descuento');
            $table->decimal('iva_total', 10, 2)->default(0)->after('base_gravable');
            $table->decimal('ieps_total', 10, 2)->default(0)->after('iva_total');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['base_gravable', 'iva_total', 'ieps_total']);
        });
    }
};
