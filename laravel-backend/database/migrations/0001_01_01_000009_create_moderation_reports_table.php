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
        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('target_type', ['listing', 'user']);
            $table->foreignId('target_listing_id')->nullable()->constrained('listings')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('report_category', ['spam', 'fraud', 'prohibited_item', 'harassment', 'fake_listing', 'other']);
            $table->text('description');
            $table->enum('status', ['pending', 'under_review', 'resolved', 'rejected'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('assigned_admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('resolution_action', ['none', 'warning', 'listing_removed', 'account_suspended', 'account_banned'])->default('none');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['target_type', 'target_listing_id']);
            $table->index(['target_type', 'target_user_id']);
            $table->index(['assigned_admin_user_id', 'status']);
        });

        DB::statement("
            ALTER TABLE moderation_reports
            ADD CONSTRAINT chk_moderation_reports_target_xor CHECK (
                (target_type = 'listing' AND target_listing_id IS NOT NULL AND target_user_id IS NULL)
                OR
                (target_type = 'user' AND target_user_id IS NOT NULL AND target_listing_id IS NULL)
            )
        ");

        DB::statement("
            ALTER TABLE moderation_reports
            ADD CONSTRAINT chk_moderation_reports_no_self_user_target CHECK (
                target_type <> 'user' OR reporter_user_id <> target_user_id
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_reports');
    }
};

