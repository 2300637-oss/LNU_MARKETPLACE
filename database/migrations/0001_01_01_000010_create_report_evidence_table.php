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
        Schema::create('report_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderation_report_id')->constrained('moderation_reports')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size_bytes');
            $table->char('sha256_hash', 64)->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE report_evidence ADD CONSTRAINT chk_report_evidence_file_size_positive CHECK (file_size_bytes > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_evidence');
    }
};

