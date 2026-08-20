<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('use_case')->nullable();
            $table->string('preview_image')->nullable();
            $table->json('theme');       // theme defaults applied to the store
            $table->json('blueprint');   // ordered block definitions
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->unique();   // mirrors the public /{username} route
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->json('socials')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('active'); // active | suspended
            $table->text('suspension_reason')->nullable();
            $table->boolean('show_platform_branding')->default(true);
            $table->json('pixels')->nullable();          // meta / tiktok / ga4 / gtm ids
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'status']);
        });

        Schema::create('store_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('primary_color')->default('#7C3AED');
            $table->string('accent_color')->default('#FB7185');
            $table->string('background_type')->default('solid'); // solid | gradient | image
            $table->string('background_value')->default('#FFFFFF');
            $table->string('background_image_path')->nullable();
            $table->string('font_family')->default('jakarta');
            $table->string('button_style')->default('rounded');  // rounded | pill | square
            $table->string('card_style')->default('soft');       // soft | outline | flat
            $table->string('product_layout')->default('grid');   // grid | list
            $table->string('color_scheme')->default('light');    // light | dark | auto
            $table->json('extras')->nullable();
            $table->timestamps();

            $table->unique('store_id');
        });

        Schema::create('store_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('verification_token');
            $table->string('status')->default('pending'); // pending | verified | failed
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title')->nullable();
            $table->json('content')->nullable();  // published payload
            $table->json('draft_content')->nullable();
            $table->json('style')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('visible_desktop')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('animation')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'position']);
        });

        Schema::create('block_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['block_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_versions');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('store_domains');
        Schema::dropIfExists('store_themes');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('storefront_templates');
    }
};
