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

        DB::statement("
            UPDATE student_verifications
            SET verification_type = 'email_link'
            WHERE verification_type = 'email_otp'
              AND (
                sent_to_email IS NULL
                OR (otp_hash IS NULL AND token_hash IS NULL)
              )
        ");

        $this->dropCheckIfExists('student_verifications', 'chk_student_verifications_email_otp_requirements');

        DB::statement("
            ALTER TABLE student_verifications
            ADD CONSTRAINT chk_student_verifications_email_otp_requirements CHECK (
                verification_type <> 'email_otp'
                OR (
                    sent_to_email IS NOT NULL
                    AND (otp_hash IS NOT NULL OR token_hash IS NOT NULL)
                    AND expires_at IS NOT NULL
                )
            )
        ");
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

        $this->dropCheckIfExists('student_verifications', 'chk_student_verifications_email_otp_requirements');
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
