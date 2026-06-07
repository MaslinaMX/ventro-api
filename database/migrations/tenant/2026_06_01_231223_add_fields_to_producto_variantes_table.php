<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->decimal('cost_net', 10, 2)->nullable()->after('precio_comparacion');
            $table->decimal('iva', 5, 2)->default(0)->after('cost_net');
            $table->decimal('ieps', 5, 2)->default(0)->after('iva');
            $table->boolean('impuestos_incluidos')->default(false)->after('ieps');
            $table->boolean('is_default')->default(false)->after('impuestos_incluidos');
            $table->boolean('allow_online')->default(false)->after('is_default');
            $table->boolean('allow_out_of_stock')->default(false)->after('allow_online');
            $table->string('sat_key')->nullable()->after('allow_out_of_stock');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropColumn([
                'cost_net', 'iva', 'ieps', 'impuestos_incluidos',
                'is_default', 'allow_online', 'allow_out_of_stock', 'sat_key',
            ]);
        });
    }
};
