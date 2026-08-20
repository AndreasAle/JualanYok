<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();       // free | creator | pro | business
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->decimal('price_monthly', 14, 2)->default(0);
            $table->decimal('price_yearly', 14, 2)->default(0);
            $table->decimal('transaction_fee_percent', 8, 4)->default(5);
            $table->decimal('transaction_fee_fixed', 14, 2)->default(0);
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('highlights')->nullable();
            $table->timestamps();
        });

        // One row per capability. Nothing about a plan is hard-coded in code —
        // features and limits are always resolved through this table.
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('key');                 // products.limit, domain.custom, branding.remove
            $table->string('value_type')->default('boolean'); // boolean | limit
            $table->boolean('enabled')->default(true);
            $table->integer('limit')->nullable();  // null = unlimited
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('ACTIVE');
            $table->string('billing_interval')->default('monthly'); // monthly | yearly
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('provider')->default('mock');
            $table->string('provider_reference')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('PENDING'); // PENDING | PAID | FAILED | VOID
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('used')->default(0);
            $table->string('period')->nullable();  // e.g. 2026-08 for monthly counters
            $table->timestamps();

            $table->unique(['user_id', 'key', 'period']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('enabled')->default(false);
            $table->json('audience')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('plan_usages');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
