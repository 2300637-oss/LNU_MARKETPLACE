<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('verification_type', ['email_otp', 'email_link'])->default('email_otp');
            $table->char('token_hash', 64)->nullable()->unique();
            $table->char('otp_hash', 64)->nullable();
            $table->string('sent_to_email');
            $table->enum('status', ['pending', 'verified', 'expired', 'failed', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
        });

        DB::statement("ALTER TABLE student_verifications ADD CONSTRAINT chk_student_verifications_token_or_otp CHECK (token_hash IS NOT NULL OR otp_hash IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_verifications');
    }
};

