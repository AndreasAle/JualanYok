<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('icon');
            $table->text('description')->nullable()->after('name');
            $table->string('seo_title')->nullable()->after('is_active');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_marketplace_listed')->default(false)->after('visibility');
            $table->string('marketplace_status')->default('DRAFT')->after('is_marketplace_listed');
            $table->foreignId('marketplace_category_id')->nullable()->after('marketplace_status')
                ->constrained('product_categories')->nullOnDelete();
            $table->timestamp('featured_at')->nullable()->after('marketplace_category_id');
            $table->timestamp('featured_until')->nullable()->after('featured_at');
            $table->text('rejection_reason')->nullable()->after('featured_until');
            $table->timestamp('moderated_at')->nullable()->after('rejection_reason');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')
                ->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('marketplace_quality_score')->default(0)->after('moderated_by');

            $table->index(
                ['marketplace_status', 'is_marketplace_listed', 'featured_at'],
                'products_marketplace_visibility_index',
            );
            $table->index(['marketplace_category_id', 'marketplace_status'], 'products_marketplace_category_index');
            $table->index(['price', 'marketplace_status'], 'products_marketplace_price_index');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->string('creator_category')->nullable()->after('featured_at');
            $table->boolean('is_verified')->default(false)->after('creator_category');
            $table->index(['is_published', 'status', 'is_featured'], 'stores_marketplace_index');
        });

        Schema::create('campaign_banners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('desktop_image_path')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('tone')->default('violet');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('strategy')->default('latest');
            $table->unsignedSmallInteger('item_limit')->default(8);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('marketplace_search_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->unsignedBigInteger('search_count')->default(0);
            $table->unsignedBigInteger('result_clicks')->default(0);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();
            $table->index(['search_count', 'last_searched_at']);
        });

        Schema::create('marketplace_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_hash', 64)->nullable();
            $table->string('session_hash', 64)->nullable();
            $table->text('referrer')->nullable();
            $table->string('device', 20)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('affiliate_click_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['name', 'created_at']);
            $table->index(['product_id', 'name', 'created_at']);
            $table->index(['store_id', 'name', 'created_at']);
            $table->index(['session_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_events');
        Schema::dropIfExists('marketplace_search_terms');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('campaign_banners');

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_marketplace_index');
            $table->dropColumn(['is_featured', 'featured_at', 'creator_category', 'is_verified']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_marketplace_visibility_index');
            $table->dropIndex('products_marketplace_category_index');
            $table->dropIndex('products_marketplace_price_index');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropConstrainedForeignId('marketplace_category_id');
            $table->dropColumn([
                'is_marketplace_listed', 'marketplace_status', 'featured_at', 'featured_until',
                'rejection_reason', 'moderated_at', 'marketplace_quality_score',
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'description', 'seo_title', 'seo_description']);
        });
    }
};
