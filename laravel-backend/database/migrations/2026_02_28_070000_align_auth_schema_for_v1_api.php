<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANT:
     * This is a breaking alignment migration for legacy auth schema.
     * - Intended for fresh databases, OR
     * - Existing databases that explicitly migrate legacy 3-digit prefixes
     *   (e.g. 230 -> 23) for both student_id_prefixes.prefix and
     *   users.student_id_prefix.
     * Existing-row prep for legacy DBs (run before migration if needed):
     *   UPDATE users SET student_id_prefix = LEFT(student_id_prefix, 2) WHERE CHAR_LENGTH(student_id_prefix) > 2;
     *   UPDATE student_id_prefixes SET prefix = LEFT(prefix, 2) WHERE CHAR_LENGTH(prefix) > 2;
     *
     * Existing-data safety checks are included below.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('student_id_prefixes')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $duplicateFuturePrefixes = DB::table('student_id_prefixes')
            ->selectRaw('LEFT(prefix, 2) as target_prefix, COUNT(*) as aggregate_count')
            ->groupBy('target_prefix')
            ->having('aggregate_count', '>', 1)
            ->exists();

        if ($duplicateFuturePrefixes) {
            throw new \RuntimeException(
                'align_auth_schema_for_v1_api cannot trim 3-digit prefixes to 2 digits safely because duplicates would be created. '
                .'Resolve prefix data manually before running this migration.'
            );
        }

        $this->dropCheckIfExists('users', 'chk_users_student_id_format');
        $this->dropCheckIfExists('users', 'chk_users_student_id_prefix_match');
        $this->dropCheckIfExists('users', 'chk_users_email_match');
        $this->dropCheckIfExists('users', 'chk_users_active_requires_verified');
        $this->dropCheckIfExists('student_id_prefixes', 'chk_student_id_prefixes_format');

        $this->dropForeignIfExists('users', 'users_student_id_prefix_foreign');

        DB::statement('UPDATE users SET student_id_prefix = LEFT(student_id_prefix, 2) WHERE CHAR_LENGTH(student_id_prefix) > 2');
        DB::statement('UPDATE student_id_prefixes SET prefix = LEFT(prefix, 2) WHERE CHAR_LENGTH(prefix) > 2');

        DB::statement('ALTER TABLE student_id_prefixes MODIFY prefix CHAR(2) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY student_id VARCHAR(12) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY student_id_prefix CHAR(2) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_student_id_prefix_foreign FOREIGN KEY (student_id_prefix) REFERENCES student_id_prefixes(prefix) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE student_id_prefixes ADD CONSTRAINT chk_student_id_prefixes_format CHECK (prefix REGEXP '^[0-9]{2}$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_student_id_format CHECK (student_id REGEXP '^[0-9]{6,12}$')");
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_student_id_prefix_match CHECK (student_id_prefix = SUBSTRING(student_id, 1, 2))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible because legacy schema restoration can truncate production data.
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        } catch (\Throwable) {
            // Ignore when missing.
        }
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
            // Ignore when missing.
        }
    }
};
