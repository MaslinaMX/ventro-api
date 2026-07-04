<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('cortes_caja', function (Blueprint $table) {
            $table->decimal('efectivo_contado', 10, 2)->nullable()->after('snapshot');
            $table->decimal('diferencia', 10, 2)->nullable()->after('efectivo_contado');
            $table->string('status')->nullable()->after('diferencia'); // 'faltante' | 'sobrante' | 'exacto'
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('cortes_caja', function (Blueprint $table) {
            $table->dropColumn(['efectivo_contado', 'diferencia', 'status']);
        });
    }
};
