<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete(); // null = store default
            $table->string('commission_type')->default('percentage'); // percentage | fixed
            $table->decimal('commission_value', 14, 2)->default(10);
            $table->unsignedInteger('cookie_days')->default(30);
            $table->boolean('auto_approve')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('terms')->nullable();
            $table->json('promo_materials')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
        });

        Schema::create('affiliate_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING | APPROVED | REJECTED | SUSPENDED
            $table->text('message')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_program_id', 'user_id']);
        });

        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('campaign')->nullable();
            $table->string('sub_id')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_link_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_hash', 64)->nullable();  // privacy-conscious, salted
            $table->string('referrer')->nullable();
            $table->json('utm')->nullable();
            $table->string('device')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('expires_at');
            $table->boolean('converted')->default(false);
            $table->timestamps();

            $table->index(['affiliate_link_id', 'created_at']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // the affiliate
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();      // the seller
            $table->decimal('base_amount', 14, 2);
            $table->decimal('rate', 8, 4)->default(0);
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('PENDING');
            $table->timestamp('available_at')->nullable();  // after the refund window
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['order_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('affiliate_applications');
        Schema::dropIfExists('affiliate_programs');
    }
};
