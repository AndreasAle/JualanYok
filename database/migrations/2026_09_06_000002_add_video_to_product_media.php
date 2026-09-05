<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product video.
 *
 * A gallery of stills cannot show how a fabric falls or how big a thing
 * actually is, which is exactly what buyers ask about in chat. The column
 * defaults to `image` so every row already stored keeps meaning what it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->string('kind', 8)->default('image')->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
