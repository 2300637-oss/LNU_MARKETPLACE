<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('student_verifications')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropCheckIfExists('student_verifications', 'chk_student_verifications_token_or_otp');
        DB::statement('ALTER TABLE student_verifications MODIFY sent_to_email VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('student_verifications')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE student_verifications SET token_hash = SHA2(CONCAT('rollback:', id), 256) WHERE token_hash IS NULL AND otp_hash IS NULL");
        DB::statement("UPDATE student_verifications SET sent_to_email = CONCAT('student:', user_id) WHERE sent_to_email IS NULL");
        DB::statement('ALTER TABLE student_verifications MODIFY sent_to_email VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE student_verifications ADD CONSTRAINT chk_student_verifications_token_or_otp CHECK (token_hash IS NOT NULL OR otp_hash IS NOT NULL)');
    }

    private function dropCheckIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
            return;
        } catch (\Throwable) {
            // Try MariaDB fallback syntax below.
        }

        try {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
        } catch (\Throwable) {
            // Ignore when the constraint does not exist.
        }
    }
};
