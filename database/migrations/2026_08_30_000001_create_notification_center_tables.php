<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each change is guarded so a partially completed MySQL DDL migration
        // remains safe to run again during a production recovery.
        if (! Schema::hasColumn('notifications', 'resolved_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->timestamp('resolved_at')->nullable()->after('read_at');
            });
        }

        if (! Schema::hasColumn('notifications', 'archived_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('resolved_at')->index();
            });
        }

        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('category', 32);
                $table->string('email_frequency', 16)->default('daily');
                $table->timestamps();
                $table->unique(['user_id', 'category']);
            });
        }

        if (! Schema::hasTable('notification_digest_states')) {
            Schema::create('notification_digest_states', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_login_devices')) {
            Schema::create('user_login_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->char('fingerprint', 64);
                $table->char('ip_hash', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('last_used_at');
                $table->timestamps();
                $table->unique(['user_id', 'fingerprint']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_devices');
        Schema::dropIfExists('notification_digest_states');
        Schema::dropIfExists('notification_preferences');

        if (Schema::hasColumn('notifications', 'archived_at')) {
            Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('archived_at'));
        }

        if (Schema::hasColumn('notifications', 'resolved_at')) {
            Schema::table('notifications', fn (Blueprint $table) => $table->dropColumn('resolved_at'));
        }
    }
};
