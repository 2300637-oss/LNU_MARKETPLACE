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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('student_id', 7)->unique();
            $table->char('student_id_prefix', 3);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->enum('account_status', ['pending_verification', 'active', 'suspended', 'deactivated'])->default('pending_verification');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_status', 'created_at']);
            $table->foreign('student_id_prefix')->references('prefix')->on('student_id_prefixes')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_student_id_format CHECK (student_id REGEXP '^[0-9]{2}0[0-9]{4}$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_student_id_prefix_match CHECK (student_id_prefix = SUBSTRING(student_id, 1, 3))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_email_match CHECK (email = CONCAT(student_id, '@lnu.edu.ph'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_active_requires_verified CHECK (account_status <> 'active' OR email_verified_at IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
