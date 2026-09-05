<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity checks, before money leaves the platform.
 *
 * This table holds the most sensitive data in the application: a national ID
 * number, a photograph of the card, and a selfie. Three decisions follow from
 * that and are enforced elsewhere in code:
 *
 * 1. The NIK is encrypted at rest, so a database dump is not a list of
 *    identity numbers.
 * 2. The images live on a private disk and are only ever reachable through a
 *    signed, short-lived link that also checks who is asking.
 * 3. Consent is recorded with a timestamp. Someone handing over their ID is
 *    entitled to have agreed to it first, and to be able to point at when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('status')->default('PENDING'); // PENDING | APPROVED | REJECTED

            $table->string('full_name');
            // Encrypted by the model, so this is ciphertext and cannot be
            // indexed or searched — which is the point.
            $table->text('nik');
            // The last four, in the clear, purely so support can confirm a
            // number over the phone without decrypting the whole record.
            $table->string('nik_last4', 4)->nullable();
            $table->string('birth_place');
            $table->date('birth_date');
            $table->text('address');

            $table->string('id_card_path');
            $table->string('selfie_path');

            $table->timestamp('consented_at');
            $table->string('consent_ip', 45)->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
