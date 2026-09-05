<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product reviews.
 *
 * A review is tied to the order line it came from, not merely to a product and
 * a person. That one decision is what makes the star rating mean anything: you
 * can only write one about something you actually bought, exactly once per
 * purchase, and the shop cannot manufacture praise for itself.
 *
 * The averages are denormalised onto the product because every catalogue tile,
 * search result and marketplace card shows them — recomputing across a growing
 * review table on each of those is the difference between a fast shop and a
 * slow one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // The purchase this review is for. Unique, so buying twice earns
            // two reviews and buying once can never earn two.
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();

            // What was bought — "Hitam, L" — because a complaint about sizing
            // is only useful next to the size it was about.
            $table->string('variant_label')->nullable();
            $table->boolean('is_anonymous')->default(false);

            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();

            // Hidden rather than deleted when moderated, so a seller can never
            // quietly erase criticism and the platform keeps an audit trail.
            $table->string('status')->default('PUBLISHED');
            $table->string('hidden_reason')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'status', 'id']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('review_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 8);           // image | video
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['review_id', 'position']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable()->after('sales_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });

        Schema::dropIfExists('review_media');
        Schema::dropIfExists('reviews');
    }
};
