<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------------------------------------------------------
         | Courses
         --------------------------------------------------------------- */
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('beginner');
            $table->text('outcome')->nullable();
            $table->unsignedInteger('access_days')->nullable(); // null = lifetime
            $table->boolean('certificate_enabled')->default(false);
            $table->timestamps();

            $table->unique('product_id');
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('video'); // video | text | attachment | quiz
            $table->longText('body')->nullable();
            $table->string('video_url')->nullable();
            $table->string('attachment_path')->nullable();
            $table->json('quiz')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->unsignedInteger('drip_days')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('certificate_code')->nullable()->unique();
            $table->timestamps();

            $table->index(['course_id', 'customer_id']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('seconds_watched')->default(0);
            $table->timestamps();

            $table->unique(['enrollment_id', 'lesson_id']);
        });

        /* ---------------------------------------------------------------
         | Events
         --------------------------------------------------------------- */
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('online'); // online | offline | hybrid
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('location')->nullable();
            $table->text('location_detail')->nullable();
            $table->timestamps();

            $table->unique('product_id');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('sold')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('customer_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('code')->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
        });

        /* ---------------------------------------------------------------
         | Services & bookings
         --------------------------------------------------------------- */
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->unsignedInteger('buffer_minutes')->default(0);
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('meeting_url')->nullable();
            $table->unsignedInteger('lead_time_hours')->default(12);
            $table->unsignedInteger('max_days_ahead')->default(30);
            $table->timestamps();

            $table->unique('product_id');
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('customer_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('status')->default('confirmed'); // confirmed | cancelled | completed | no_show
            $table->text('customer_note')->nullable();
            $table->string('meeting_url')->nullable();
            $table->timestamps();

            // Guards against double booking the same slot.
            $table->unique(['service_id', 'starts_at'], 'bookings_slot_unique');
        });

        /* ---------------------------------------------------------------
         | Memberships
         --------------------------------------------------------------- */
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2)->default(0);
            $table->string('interval')->default('monthly'); // monthly | quarterly | yearly
            $table->unsignedInteger('interval_count')->default(1);
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE | PAST_DUE | CANCELLED | EXPIRED
            $table->timestamp('started_at');
            $table->timestamp('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('services');
        Schema::dropIfExists('attendees');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('events');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
    }
};
