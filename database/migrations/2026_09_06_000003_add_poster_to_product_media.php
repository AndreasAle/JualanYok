<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A still frame for every product video.
 *
 * Without one, showing a video in a gallery means the browser must download
 * part of the file just to draw something — for every visitor, including the
 * overwhelming majority who never press play. A poster is a few kilobytes and
 * turns that into a decision the buyer makes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->string('poster_path')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('poster_path');
        });
    }
};
