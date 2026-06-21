<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // ─── Plan ─────────────────────────────────────────────────
            $table->enum('plan', ['free_trial', 'basic', 'pro'])->default('free_trial');
            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled'])->default('trial');
            $table->decimal('base_price', 8, 2)->default(349.90);
            $table->string('currency', 3)->default('MXN');
            $table->enum('period', ['monthly', 'annual'])->default('monthly');
            $table->unsignedInteger('included_branches')->default(1);
            $table->decimal('extra_branch_cost', 8, 2)->default(199.90);

            // ─── Fechas ───────────────────────────────────────────────
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();

            // ─── Método de pago ───────────────────────────────────────
            $table->string('payment_method')->nullable(); // spei, card, etc
            $table->string('spei_clabe')->nullable();
            $table->string('spei_bank')->nullable();
            $table->string('spei_beneficiary')->nullable();
            $table->string('spei_reference')->nullable();

            // ─── Bloqueo ──────────────────────────────────────────────
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();

            // ─── Cancelación ──────────────────────────────────────────
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
