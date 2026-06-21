<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('security_pin');
            $table->timestamp('invited_at')->nullable()->after('invite_token');
            $table->timestamp('activated_at')->nullable()->after('invited_at');
            $table->string('password')->nullable()->change(); // ya no requerido al crear
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invite_token', 'invited_at', 'activated_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
