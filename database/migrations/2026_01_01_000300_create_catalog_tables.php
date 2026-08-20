<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('compare_at_price', 14, 2)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_pay_what_you_want')->default(false);
            $table->decimal('minimum_price', 14, 2)->nullable();
            $table->string('status')->default('DRAFT');
            $table->string('visibility')->default('public'); // public | unlisted | private
            $table->json('tags')->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('sales_limit')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->text('checkout_message')->nullable();
            $table->text('post_purchase_message')->nullable();
            $table->text('terms')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('affiliate_enabled')->default(false);
            $table->string('external_url')->nullable();      // EXTERNAL products
            $table->json('settings')->nullable();            // type specific config
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();       // never exposed to the client
            $table->string('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('version')->default('1.0');
            $table->boolean('watermark_pdf')->default(false);
            $table->unsignedInteger('download_limit')->nullable();
            $table->unsignedInteger('access_days')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->json('options')->nullable();      // {"Ukuran":"L","Warna":"Hitam"}
            $table->decimal('price', 14, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('weight_gram')->default(0);
            $table->json('dimensions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved')->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id'], 'inventories_product_variant_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->integer('change');
            $table->integer('balance_after');
            $table->string('reason');   // sale | refund | manual | restock
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_files');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
