<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mapear valores viejos a los nuevos (por si hay datos de prueba)
        DB::table('users')->where('role', 'admin')->update(['role' => 'admin_empresa']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'admin_sucursal']);
        DB::table('users')->where('role', 'cashier')->update(['role' => 'vendedor']);
        DB::table('users')->where('role', 'personalizado')->update(['role' => 'vendedor']);

        // 2. Cambiar el default de la columna
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('vendedor')->change();
        });

        // 3. Eliminar columna permissions (ya no se usa, era solo para 'personalizado')
        if (Schema::hasColumn('users', 'permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('cashier')->change();
            $table->json('permissions')->nullable();
        });
    }
};
