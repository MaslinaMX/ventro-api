<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('last_name');
            $table->string('employee_number')->nullable()->unique()->after('phone');
            $table->string('security_pin', 6)->nullable()->after('employee_number');
            $table->timestamp('pin_updated_at')->nullable()->after('security_pin');
            $table->boolean('is_seller')->default(false)->after('pin_updated_at');
            $table->boolean('is_deletable')->default(true)->after('is_seller');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'phone', 'employee_number',
                'security_pin', 'pin_updated_at', 'is_seller', 'is_deletable',
            ]);
        });
    }
};
